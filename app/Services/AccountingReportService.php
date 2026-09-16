<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;

/**
 * Service to generate core financial and accounting reports:
 * - General Ledger (Buku Besar)
 * - Trial Balance (Neraca Saldo)
 * - Income Statement (Laporan Laba Rugi)
 *
 * Supports multi-tenancy: can scope to an individual branch or consolidate all branches.
 */
class AccountingReportService
{
    /**
     * Get General Ledger (Buku Besar) per Chart of Account.
     *
     * Calculates opening balance prior to $startDate, lists individual journal lines
     * within the date range with running balances, and calculates ending balance.
     *
     * @param  int|null  $branchId    Filter by branch or null for consolidated
     * @param  string|null  $startDate YYYY-MM-DD
     * @param  string|null  $endDate   YYYY-MM-DD
     * @return array{
     *     branch: array{id: int|null, name: string},
     *     start_date: string|null,
     *     end_date: string|null,
     *     accounts: array<int, array{
     *         account: array{id: int, code: string, name: string, type: string, normal_balance: string},
     *         beginning_balance: float,
     *         total_debit: float,
     *         total_credit: float,
     *         net_change: float,
     *         ending_balance: float,
     *         lines: array<int, array{
     *             id: int,
     *             date: string,
     *             reference_number: string,
     *             description: string,
     *             memo: string|null,
     *             branch_id: int,
     *             branch_name: string,
     *             debit: float,
     *             credit: float,
     *             balance: float
     *         }>
     *     }>,
     *     grand_total_debit: float,
     *     grand_total_credit: float
     * }
     */
    public function getLedger(?int $branchId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $branchInfo = $this->resolveBranchInfo($branchId);
        $accounts = ChartOfAccount::query()
            ->orderBy('code')
            ->get();

        $accountLedgers = [];
        $grandTotalDebit = 0.0;
        $grandTotalCredit = 0.0;

        foreach ($accounts as $account) {
            $normalBalance = $this->getNormalBalance($account);

            // 1. Calculate Beginning Balance (prior to $startDate)
            $beginningBalance = 0.0;
            if ($startDate !== null) {
                $priorSum = JournalLine::query()
                    ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
                    ->where('journal_lines.chart_of_account_id', $account->id)
                    ->whereNull('journal_headers.deleted_at')
                    ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
                    ->where('journal_headers.transaction_date', '<', $startDate)
                    ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
                    ->first();

                $priorDebit = (float) ($priorSum->total_debit ?? 0);
                $priorCredit = (float) ($priorSum->total_credit ?? 0);

                $beginningBalance = $normalBalance === 'debit'
                    ? ($priorDebit - $priorCredit)
                    : ($priorCredit - $priorDebit);
            }

            // 2. Fetch Journal Lines for the current period
            $linesQuery = JournalLine::query()
                ->with(['journalHeader.branch'])
                ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
                ->where('journal_lines.chart_of_account_id', $account->id)
                ->whereNull('journal_headers.deleted_at')
                ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
                ->when($startDate, fn ($q) => $q->where('journal_headers.transaction_date', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->where('journal_headers.transaction_date', '<=', $endDate))
                ->orderBy('journal_headers.transaction_date', 'asc')
                ->orderBy('journal_headers.id', 'asc')
                ->orderBy('journal_lines.id', 'asc')
                ->select('journal_lines.*');

            $journalLines = $linesQuery->get();

            // Skip accounts that have neither a beginning balance nor any transactions in this period
            if (abs($beginningBalance) < 0.0001 && $journalLines->isEmpty()) {
                continue;
            }

            $totalDebit = 0.0;
            $totalCredit = 0.0;
            $runningBalance = $beginningBalance;
            $formattedLines = [];

            foreach ($journalLines as $line) {
                $debit = (float) $line->debit;
                $credit = (float) $line->credit;

                $totalDebit += $debit;
                $totalCredit += $credit;

                if ($normalBalance === 'debit') {
                    $runningBalance += ($debit - $credit);
                } else {
                    $runningBalance += ($credit - $debit);
                }

                $header = $line->journalHeader;
                $formattedLines[] = [
                    'id' => $line->id,
                    'date' => optional($header->transaction_date)->format('Y-m-d') ?? '',
                    'reference_number' => (string) ($header->reference_number ?? ''),
                    'description' => (string) ($header->description ?? ''),
                    'memo' => $line->memo,
                    'branch_id' => (int) ($header->branch_id ?? 0),
                    'branch_name' => (string) (optional($header->branch)->name ?? '-'),
                    'debit' => round($debit, 2),
                    'credit' => round($credit, 2),
                    'balance' => round($runningBalance, 2),
                ];
            }

            $netChange = $normalBalance === 'debit'
                ? ($totalDebit - $totalCredit)
                : ($totalCredit - $totalDebit);

            $endingBalance = $beginningBalance + $netChange;

            $grandTotalDebit += $totalDebit;
            $grandTotalCredit += $totalCredit;

            $accountLedgers[] = [
                'account' => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'normal_balance' => $normalBalance,
                ],
                'beginning_balance' => round($beginningBalance, 2),
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'net_change' => round($netChange, 2),
                'ending_balance' => round($endingBalance, 2),
                'lines' => $formattedLines,
            ];
        }

        return [
            'branch' => $branchInfo,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'accounts' => $accountLedgers,
            'grand_total_debit' => round($grandTotalDebit, 2),
            'grand_total_credit' => round($grandTotalCredit, 2),
        ];
    }

    /**
     * Get Trial Balance (Neraca Saldo).
     *
     * Summarizes ending Debit and Credit balances per account as of $endDate (or in period).
     * Double-entry bookkeeping guarantees that total ending debits always equal total ending credits.
     *
     * @param  int|null  $branchId    Filter by branch or null for consolidated
     * @param  string|null  $startDate YYYY-MM-DD
     * @param  string|null  $endDate   YYYY-MM-DD
     * @return array{
     *     branch: array{id: int|null, name: string},
     *     start_date: string|null,
     *     end_date: string|null,
     *     accounts: array<int, array{
     *         account_id: int,
     *         code: string,
     *         name: string,
     *         type: string,
     *         normal_balance: string,
     *         opening_debit: float,
     *         opening_credit: float,
     *         movement_debit: float,
     *         movement_credit: float,
     *         ending_debit: float,
     *         ending_credit: float
     *     }>,
     *     total_opening_debit: float,
     *     total_opening_credit: float,
     *     total_movement_debit: float,
     *     total_movement_credit: float,
     *     total_ending_debit: float,
     *     total_ending_credit: float,
     *     is_balanced: bool,
     *     difference: float
     * }
     */
    public function getTrialBalance(?int $branchId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $branchInfo = $this->resolveBranchInfo($branchId);
        $accounts = ChartOfAccount::query()
            ->orderBy('code')
            ->get();

        $rows = [];

        $totalOpeningDebit = 0.0;
        $totalOpeningCredit = 0.0;
        $totalMovementDebit = 0.0;
        $totalMovementCredit = 0.0;
        $totalEndingDebit = 0.0;
        $totalEndingCredit = 0.0;

        foreach ($accounts as $account) {
            $normalBalance = $this->getNormalBalance($account);

            // 1. Opening balance before $startDate (if $startDate provided)
            $priorDebit = 0.0;
            $priorCredit = 0.0;
            if ($startDate !== null) {
                $priorSum = JournalLine::query()
                    ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
                    ->where('journal_lines.chart_of_account_id', $account->id)
                    ->whereNull('journal_headers.deleted_at')
                    ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
                    ->where('journal_headers.transaction_date', '<', $startDate)
                    ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
                    ->first();

                $priorDebit = (float) ($priorSum->total_debit ?? 0);
                $priorCredit = (float) ($priorSum->total_credit ?? 0);
            }

            $netOpening = $priorDebit - $priorCredit;
            $openingDebit = $netOpening > 0 ? $netOpening : 0.0;
            $openingCredit = $netOpening < 0 ? abs($netOpening) : 0.0;

            // 2. Period movement between $startDate and $endDate
            $periodSum = JournalLine::query()
                ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
                ->where('journal_lines.chart_of_account_id', $account->id)
                ->whereNull('journal_headers.deleted_at')
                ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
                ->when($startDate, fn ($q) => $q->where('journal_headers.transaction_date', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->where('journal_headers.transaction_date', '<=', $endDate))
                ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
                ->first();

            $movementDebit = (float) ($periodSum->total_debit ?? 0);
            $movementCredit = (float) ($periodSum->total_credit ?? 0);

            // 3. Cumulative ending balance up to $endDate
            $cumDebit = $priorDebit + $movementDebit;
            $cumCredit = $priorCredit + $movementCredit;
            $netEnding = $cumDebit - $cumCredit;

            $endingDebit = $netEnding > 0 ? $netEnding : 0.0;
            $endingCredit = $netEnding < 0 ? abs($netEnding) : 0.0;

            // Skip accounts with zero activity across all metrics
            if (
                abs($openingDebit) < 0.0001 &&
                abs($openingCredit) < 0.0001 &&
                abs($movementDebit) < 0.0001 &&
                abs($movementCredit) < 0.0001 &&
                abs($endingDebit) < 0.0001 &&
                abs($endingCredit) < 0.0001
            ) {
                continue;
            }

            $totalOpeningDebit += $openingDebit;
            $totalOpeningCredit += $openingCredit;
            $totalMovementDebit += $movementDebit;
            $totalMovementCredit += $movementCredit;
            $totalEndingDebit += $endingDebit;
            $totalEndingCredit += $endingCredit;

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $normalBalance,
                'opening_debit' => round($openingDebit, 2),
                'opening_credit' => round($openingCredit, 2),
                'movement_debit' => round($movementDebit, 2),
                'movement_credit' => round($movementCredit, 2),
                'ending_debit' => round($endingDebit, 2),
                'ending_credit' => round($endingCredit, 2),
            ];
        }

        $difference = round(abs($totalEndingDebit - $totalEndingCredit), 2);
        $isBalanced = $difference < 0.01;

        return [
            'branch' => $branchInfo,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'accounts' => $rows,
            'total_opening_debit' => round($totalOpeningDebit, 2),
            'total_opening_credit' => round($totalOpeningCredit, 2),
            'total_movement_debit' => round($totalMovementDebit, 2),
            'total_movement_credit' => round($totalMovementCredit, 2),
            'total_ending_debit' => round($totalEndingDebit, 2),
            'total_ending_credit' => round($totalEndingCredit, 2),
            'is_balanced' => $isBalanced,
            'difference' => $difference,
        ];
    }

    /**
     * Get Income Statement (Laporan Laba Rugi).
     *
     * Calculates:
     * - Revenue (Akun 4xxx / type revenue)
     * - Cost of Goods Sold (Akun 5100 / COGS)
     * - Gross Profit = Revenue - COGS
     * - Operating Expenses (Akun 5xxx selain 5100 / type expense)
     * - Net Profit = Gross Profit - Operating Expenses
     *
     * @param  int|null  $branchId    Filter by branch or null for consolidated
     * @param  string|null  $startDate YYYY-MM-DD
     * @param  string|null  $endDate   YYYY-MM-DD
     * @return array{
     *     branch: array{id: int|null, name: string},
     *     start_date: string|null,
     *     end_date: string|null,
     *     revenue: array{
     *         accounts: array<int, array{code: string, name: string, amount: float}>,
     *         total: float
     *     },
     *     cogs: array{
     *         accounts: array<int, array{code: string, name: string, amount: float}>,
     *         total: float
     *     },
     *     gross_profit: float,
     *     operating_expenses: array{
     *         accounts: array<int, array{code: string, name: string, amount: float}>,
     *         total: float
     *     },
     *     net_profit: float,
     *     gross_profit_margin: float,
     *     net_profit_margin: float
     * }
     */
    public function getIncomeStatement(?int $branchId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $branchInfo = $this->resolveBranchInfo($branchId);

        // Fetch aggregated movements for revenue and expense accounts in the period
        $lines = JournalLine::query()
            ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->whereNull('journal_headers.deleted_at')
            ->whereNull('chart_of_accounts.deleted_at')
            ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
            ->when($startDate, fn ($q) => $q->where('journal_headers.transaction_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('journal_headers.transaction_date', '<=', $endDate))
            ->where(function ($query) {
                $query->whereIn('chart_of_accounts.type', ['revenue', 'expense'])
                    ->orWhere('chart_of_accounts.code', 'LIKE', '4%')
                    ->orWhere('chart_of_accounts.code', 'LIKE', '5%');
            })
            ->selectRaw('chart_of_accounts.id as coa_id, chart_of_accounts.code as coa_code, chart_of_accounts.name as coa_name, chart_of_accounts.type as coa_type, COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type')
            ->get();

        $revenueAccounts = [];
        $totalRevenue = 0.0;

        $cogsAccounts = [];
        $totalCogs = 0.0;

        $operatingExpenseAccounts = [];
        $totalOperatingExpenses = 0.0;

        foreach ($lines as $line) {
            $debit = (float) $line->total_debit;
            $credit = (float) $line->total_credit;

            $code = (string) $line->coa_code;
            $type = (string) $line->coa_type;
            $name = (string) $line->coa_name;

            if ($type === 'revenue' || str_starts_with($code, '4')) {
                // Revenue has normal credit balance: Net Revenue = Credit - Debit
                $amount = round($credit - $debit, 2);
                if (abs($amount) > 0.0001) {
                    $revenueAccounts[] = [
                        'code' => $code,
                        'name' => $name,
                        'amount' => $amount,
                    ];
                    $totalRevenue += $amount;
                }
            } elseif ($code === '5100' || str_starts_with($code, '510')) {
                // Cost of Goods Sold (HPP) has normal debit balance: Net COGS = Debit - Credit
                $amount = round($debit - $credit, 2);
                if (abs($amount) > 0.0001) {
                    $cogsAccounts[] = [
                        'code' => $code,
                        'name' => $name,
                        'amount' => $amount,
                    ];
                    $totalCogs += $amount;
                }
            } elseif ($type === 'expense' || str_starts_with($code, '5')) {
                // Operating expenses have normal debit balance: Net Expense = Debit - Credit
                $amount = round($debit - $credit, 2);
                if (abs($amount) > 0.0001) {
                    $operatingExpenseAccounts[] = [
                        'code' => $code,
                        'name' => $name,
                        'amount' => $amount,
                    ];
                    $totalOperatingExpenses += $amount;
                }
            }
        }

        // Sort items by code ascending
        usort($revenueAccounts, fn ($a, $b) => strcmp($a['code'], $b['code']));
        usort($cogsAccounts, fn ($a, $b) => strcmp($a['code'], $b['code']));
        usort($operatingExpenseAccounts, fn ($a, $b) => strcmp($a['code'], $b['code']));

        $grossProfit = round($totalRevenue - $totalCogs, 2);
        $netProfit = round($grossProfit - $totalOperatingExpenses, 2);

        $grossProfitMargin = $totalRevenue > 0
            ? round(($grossProfit / $totalRevenue) * 100, 2)
            : 0.0;

        $netProfitMargin = $totalRevenue > 0
            ? round(($netProfit / $totalRevenue) * 100, 2)
            : 0.0;

        return [
            'branch' => $branchInfo,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'revenue' => [
                'accounts' => $revenueAccounts,
                'total' => round($totalRevenue, 2),
            ],
            'cogs' => [
                'accounts' => $cogsAccounts,
                'total' => round($totalCogs, 2),
            ],
            'gross_profit' => $grossProfit,
            'operating_expenses' => [
                'accounts' => $operatingExpenseAccounts,
                'total' => round($totalOperatingExpenses, 2),
            ],
            'net_profit' => $netProfit,
            'gross_profit_margin' => $grossProfitMargin,
            'net_profit_margin' => $netProfitMargin,
        ];
    }

    /**
     * Resolve account normal balance direction ('debit' or 'credit').
     */
    public function getNormalBalance(ChartOfAccount $account): string
    {
        $type = strtolower($account->type);
        $code = (string) $account->code;

        // Assets (1xxx) and Expenses (5xxx) have normal Debit balances
        if (in_array($type, ['asset', 'expense'], true) || str_starts_with($code, '1') || str_starts_with($code, '5')) {
            return 'debit';
        }

        // Liabilities (2xxx), Equity (3xxx), and Revenue (4xxx) have normal Credit balances
        return 'credit';
    }

    /**
     * Resolve metadata for the selected branch.
     *
     * @return array{id: int|null, name: string}
     */
    private function resolveBranchInfo(?int $branchId): array
    {
        if ($branchId === null) {
            return [
                'id' => null,
                'name' => 'Konsolidasi Seluruh Cabang',
            ];
        }

        $branch = Branch::query()->find($branchId);

        return [
            'id' => $branchId,
            'name' => $branch ? $branch->name : "Cabang #{$branchId}",
        ];
    }
}
