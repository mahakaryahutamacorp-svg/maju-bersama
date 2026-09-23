<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;

class TrialBalanceService
{
    /**
     * Generate Trial Balance (Neraca Saldo) report data.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @param int|null $branchId Optional branch ID filter
     * @return array
     */
    public function generate(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $branch = $branchId ? Branch::query()->find($branchId) : null;
        $branchName = $branch ? $branch->name : 'Konsolidasi Seluruh Cabang';

        $accounts = ChartOfAccount::query()
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $totalEndingDebit = 0.0;
        $totalEndingCredit = 0.0;

        foreach ($accounts as $account) {
            $code = (string) $account->code;
            $type = (string) $account->type;

            // Normal Balance determination
            // Asset (1xxx), COGS (5xxx), Expense (6xxx) -> Debit
            // Liability (2xxx), Equity (3xxx), Revenue (4xxx) -> Credit
            $normalBalance = match ($type) {
                'asset', 'expense' => 'debit',
                'liability', 'equity', 'revenue' => 'credit',
                default => (str_starts_with($code, '1') || str_starts_with($code, '5') || str_starts_with($code, '6')) ? 'debit' : 'credit',
            };

            // Calculate Period Mutations
            $movementSum = JournalLine::query()
                ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
                ->where('journal_lines.chart_of_account_id', $account->id)
                ->whereNull('journal_headers.deleted_at')
                ->whereDate('journal_headers.transaction_date', '>=', $startDate)
                ->whereDate('journal_headers.transaction_date', '<=', $endDate)
                ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
                ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
                ->first();

            $debit = (float) ($movementSum->total_debit ?? 0);
            $credit = (float) ($movementSum->total_credit ?? 0);

            // Calculate Cumulative Balance up to $endDate
            $cumSum = JournalLine::query()
                ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
                ->where('journal_lines.chart_of_account_id', $account->id)
                ->whereNull('journal_headers.deleted_at')
                ->whereDate('journal_headers.transaction_date', '<=', $endDate)
                ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
                ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
                ->first();

            $cumDebit = (float) ($cumSum->total_debit ?? 0);
            $cumCredit = (float) ($cumSum->total_credit ?? 0);

            // Ending balance calculation based on normal balance
            if ($normalBalance === 'debit') {
                $endingBalance = round($cumDebit - $cumCredit, 2);
                $endingDebit = $endingBalance >= 0 ? $endingBalance : 0.0;
                $endingCredit = $endingBalance < 0 ? abs($endingBalance) : 0.0;
            } else {
                $endingBalance = round($cumCredit - $cumDebit, 2);
                $endingCredit = $endingBalance >= 0 ? $endingBalance : 0.0;
                $endingDebit = $endingBalance < 0 ? abs($endingBalance) : 0.0;
            }

            // Skip account if no activity at all
            if (abs($debit) < 0.0001 && abs($credit) < 0.0001 && abs($cumDebit) < 0.0001 && abs($cumCredit) < 0.0001) {
                continue;
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
            $totalEndingDebit += $endingDebit;
            $totalEndingCredit += $endingCredit;

            $rows[] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $normalBalance,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'ending_balance' => $endingBalance,
                'ending_debit' => round($endingDebit, 2),
                'ending_credit' => round($endingCredit, 2),
            ];
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        $totalEndingDebit = round($totalEndingDebit, 2);
        $totalEndingCredit = round($totalEndingCredit, 2);

        $difference = round(abs($totalDebit - $totalCredit), 2);
        $isBalanced = $difference < 0.01;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'branch_id' => $branchId,
            'branch' => $branch,
            'branch_name' => $branchName,
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'total_ending_debit' => $totalEndingDebit,
            'total_ending_credit' => $totalEndingCredit,
            'is_balanced' => $isBalanced,
            'difference' => $difference,
        ];
    }
}
