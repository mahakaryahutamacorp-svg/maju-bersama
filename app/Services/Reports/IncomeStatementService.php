<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\JournalLine;

class IncomeStatementService
{
    /**
     * Generate Income Statement (Laba Rugi Standar) report data.
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

        $query = JournalLine::query()
            ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->whereDate('journal_headers.transaction_date', '>=', $startDate)
            ->whereDate('journal_headers.transaction_date', '<=', $endDate)
            ->whereNull('journal_headers.deleted_at')
            ->where(function ($q) {
                $q->where('chart_of_accounts.code', 'LIKE', '4%')
                    ->orWhere('chart_of_accounts.code', 'LIKE', '5%')
                    ->orWhere('chart_of_accounts.code', 'LIKE', '6%')
                    ->orWhereIn('chart_of_accounts.type', ['revenue', 'expense']);
            });

        if ($branchId) {
            $query->where('journal_headers.branch_id', $branchId);
        }

        $lines = $query
            ->selectRaw('
                chart_of_accounts.id as account_id,
                chart_of_accounts.code as account_code,
                chart_of_accounts.name as account_name,
                chart_of_accounts.type as account_type,
                COALESCE(SUM(journal_lines.debit), 0) as total_debit,
                COALESCE(SUM(journal_lines.credit), 0) as total_credit
            ')
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type'
            )
            ->orderBy('chart_of_accounts.code')
            ->get();

        $revenueAccounts = [];
        $totalRevenue = 0.0;

        $cogsAccounts = [];
        $totalCogs = 0.0;

        $expenseAccounts = [];
        $totalExpenses = 0.0;

        foreach ($lines as $line) {
            $debit = (float) $line->total_debit;
            $credit = (float) $line->total_credit;
            $code = (string) $line->account_code;
            $name = (string) $line->account_name;
            $type = (string) $line->account_type;

            // 1. Pendapatan (Revenue) - Akun Kepala 4 (Saldo Normal: Kredit)
            if (str_starts_with($code, '4') || $type === 'revenue') {
                $amount = round($credit - $debit, 2);
                if (abs($amount) > 0.0001) {
                    $revenueAccounts[] = [
                        'id' => $line->account_id,
                        'code' => $code,
                        'name' => $name,
                        'amount' => $amount,
                    ];
                    $totalRevenue += $amount;
                }
            }
            // 3. Beban Operasional (Expenses) - Akun Kepala 6 atau Akun Beban Non-HPP (Saldo Normal: Debit)
            elseif (
                str_starts_with($code, '6')
                || str_starts_with($code, '511')
                || $code === '5110'
                || str_contains(strtolower($name), 'operasional')
                || str_contains(strtolower($name), 'biaya')
                || str_contains(strtolower($name), 'beban')
            ) {
                $amount = round($debit - $credit, 2);
                if (abs($amount) > 0.0001) {
                    $expenseAccounts[] = [
                        'id' => $line->account_id,
                        'code' => $code,
                        'name' => $name,
                        'amount' => $amount,
                    ];
                    $totalExpenses += $amount;
                }
            }
            // 2. Harga Pokok Penjualan (COGS) - Akun Kepala 5 / HPP (Saldo Normal: Debit)
            elseif (str_starts_with($code, '5') || $type === 'expense') {
                $amount = round($debit - $credit, 2);
                if (abs($amount) > 0.0001) {
                    $cogsAccounts[] = [
                        'id' => $line->account_id,
                        'code' => $code,
                        'name' => $name,
                        'amount' => $amount,
                    ];
                    $totalCogs += $amount;
                }
            }
        }

        // Urutkan akun berdasarkan kode
        usort($revenueAccounts, fn ($a, $b) => strcmp($a['code'], $b['code']));
        usort($cogsAccounts, fn ($a, $b) => strcmp($a['code'], $b['code']));
        usort($expenseAccounts, fn ($a, $b) => strcmp($a['code'], $b['code']));

        // Perhitungan Laba Kotor & Laba Bersih
        $grossProfit = round($totalRevenue - $totalCogs, 2);
        $netProfit = round($grossProfit - $totalExpenses, 2);

        $grossMargin = $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0.0;
        $netMargin = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0.0;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'branch_id' => $branchId,
            'branch' => $branch,
            'branch_name' => $branchName,
            'revenue' => [
                'accounts' => $revenueAccounts,
                'total' => round($totalRevenue, 2),
            ],
            'cogs' => [
                'accounts' => $cogsAccounts,
                'total' => round($totalCogs, 2),
            ],
            'gross_profit' => $grossProfit,
            'gross_margin' => $grossMargin,
            'operating_expenses' => [
                'accounts' => $expenseAccounts,
                'total' => round($totalExpenses, 2),
            ],
            'net_profit' => $netProfit,
            'net_margin' => $netMargin,
            'is_profitable' => $netProfit >= 0,
        ];
    }
}
