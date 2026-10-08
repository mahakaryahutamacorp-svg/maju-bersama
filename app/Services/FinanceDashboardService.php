<?php

namespace App\Services;

use App\Models\CashRegisterShift;
use App\Models\CashTransfer;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Support\Collection;

class FinanceDashboardService
{
    public const ACCOUNT_TYPE_LABELS = [
        'asset' => 'Aset',
        'liability' => 'Kewajiban',
        'equity' => 'Ekuitas',
        'revenue' => 'Pendapatan',
        'expense' => 'Beban',
    ];

    /**
     * @return array{
     *     branch_id: int|null,
     *     cash_accounts: Collection,
     *     cash_total: float,
     *     shifts: Collection,
     *     transfers: Collection,
     *     journals: Collection,
     *     ledger_groups: array<string, array{label: string, rows: Collection, total_debit: float, total_credit: float}>,
     *     expenses: Collection,
     *     expense_total: float
     * }
     */
    public function build(User $actor, ?int $branchId, string $startDate, string $endDate): array
    {
        if (! $actor->isMaster()) {
            $branchId = (int) $actor->branch_id;
        }

        $cashAccounts = $this->cashAccountBalances($branchId);
        $ledgerGroups = $this->ledgerGroupedByType($branchId, $startDate, $endDate);

        $journals = JournalHeader::query()
            ->with(['journalLines.chartOfAccount', 'user', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate)
            ->latest('transaction_date')
            ->latest('id')
            ->limit(40)
            ->get();

        $expenses = Expense::query()
            ->with(['expenseCategory', 'account', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->latest('expense_date')
            ->latest('id')
            ->limit(20)
            ->get();

        $transfers = CashTransfer::query()
            ->with(['fromAccount', 'toAccount', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('transfer_date')
            ->latest('id')
            ->limit(10)
            ->get();

        $shifts = CashRegisterShift::query()
            ->with(['cashRegister', 'user', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('opened_at')
            ->limit(8)
            ->get();

        return [
            'branch_id' => $branchId,
            'cash_accounts' => $cashAccounts,
            'cash_total' => (float) $cashAccounts->sum('balance'),
            'shifts' => $shifts,
            'transfers' => $transfers,
            'journals' => $journals,
            'ledger_groups' => $ledgerGroups,
            'expenses' => $expenses,
            'expense_total' => (float) $expenses->sum('amount'),
        ];
    }

    private function cashAccountBalances(?int $branchId): Collection
    {
        $accounts = ChartOfAccount::query()->cashAndBank()->orderBy('code')->get();
        if ($accounts->isEmpty()) {
            return collect();
        }

        $sums = JournalLine::query()
            ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
            ->whereNull('journal_headers.deleted_at')
            ->whereIn('journal_lines.chart_of_account_id', $accounts->pluck('id'))
            ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
            ->selectRaw('journal_lines.chart_of_account_id, COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->groupBy('journal_lines.chart_of_account_id')
            ->get()
            ->keyBy('chart_of_account_id');

        return $accounts->map(function (ChartOfAccount $account) use ($sums) {
            $row = $sums->get($account->id);
            $debit = (float) ($row->total_debit ?? 0);
            $credit = (float) ($row->total_credit ?? 0);

            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $debit - $credit,
            ];
        });
    }

    /**
     * @return array<string, array{label: string, rows: Collection, total_debit: float, total_credit: float}>
     */
    private function ledgerGroupedByType(?int $branchId, string $startDate, string $endDate): array
    {
        $rows = JournalLine::query()
            ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->whereNull('journal_headers.deleted_at')
            ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
            ->whereDate('journal_headers.transaction_date', '>=', $startDate)
            ->whereDate('journal_headers.transaction_date', '<=', $endDate)
            ->selectRaw('chart_of_accounts.id as account_id, chart_of_accounts.code, chart_of_accounts.name, chart_of_accounts.type, COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type')
            ->orderBy('chart_of_accounts.code')
            ->get();

        $grouped = [];
        foreach (self::ACCOUNT_TYPE_LABELS as $type => $label) {
            $typeRows = $rows->where('type', $type)->values();
            $grouped[$type] = [
                'label' => $label,
                'rows' => $typeRows,
                'total_debit' => (float) $typeRows->sum('total_debit'),
                'total_credit' => (float) $typeRows->sum('total_credit'),
            ];
        }

        return $grouped;
    }
}
