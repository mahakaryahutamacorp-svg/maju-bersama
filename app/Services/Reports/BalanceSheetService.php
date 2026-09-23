<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;

class BalanceSheetService
{
    /**
     * Generate Balance Sheet (Neraca Standar) report data.
     *
     * @param string $endDate YYYY-MM-DD (as of date)
     * @param int|null $branchId Optional branch ID filter
     * @param string|null $startDate Optional start date for label or filtering
     * @return array
     */
    public function generate(string $endDate, ?int $branchId = null, ?string $startDate = null): array
    {
        $branch = $branchId ? Branch::query()->find($branchId) : null;
        $branchName = $branch ? $branch->name : 'Konsolidasi Seluruh Cabang';

        // Query cumulative journal lines up to $endDate
        $lines = JournalLine::query()
            ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->whereDate('journal_headers.transaction_date', '<=', $endDate)
            ->whereNull('journal_headers.deleted_at')
            ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
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

        $assetAccounts = [];
        $totalAssets = 0.0;

        $liabilityAccounts = [];
        $totalLiabilities = 0.0;

        $equityAccounts = [];
        $totalEquityBase = 0.0;

        // Temporary profit/loss metrics for Net Income calculation
        $totalRevenue = 0.0;
        $totalCogs = 0.0;
        $totalExpenses = 0.0;

        foreach ($lines as $line) {
            $debit = (float) $line->total_debit;
            $credit = (float) $line->total_credit;
            $code = (string) $line->account_code;
            $name = (string) $line->account_name;
            $type = (string) $line->account_type;

            // 1. AKTIVA / ASET - Kepala 1 atau tipe asset (Normal: Debit)
            if (str_starts_with($code, '1') || $type === 'asset') {
                $amount = round($debit - $credit, 2);
                if (abs($amount) > 0.0001) {
                    $assetAccounts[] = [
                        'id' => $line->account_id,
                        'code' => $code,
                        'name' => $name,
                        'amount' => $amount,
                    ];
                    $totalAssets += $amount;
                }
            }
            // 2. KEWAJIBAN / HUTANG - Kepala 2 atau tipe liability (Normal: Kredit)
            elseif (str_starts_with($code, '2') || $type === 'liability') {
                $amount = round($credit - $debit, 2);
                if (abs($amount) > 0.0001) {
                    $liabilityAccounts[] = [
                        'id' => $line->account_id,
                        'code' => $code,
                        'name' => $name,
                        'amount' => $amount,
                    ];
                    $totalLiabilities += $amount;
                }
            }
            // 3. MODAL / EKUITAS - Kepala 3 atau tipe equity (Normal: Kredit)
            elseif (str_starts_with($code, '3') || $type === 'equity') {
                $amount = round($credit - $debit, 2);
                if (abs($amount) > 0.0001) {
                    $equityAccounts[] = [
                        'id' => $line->account_id,
                        'code' => $code,
                        'name' => $name,
                        'amount' => $amount,
                    ];
                    $totalEquityBase += $amount;
                }
            }
            // 4. PENDAPATAN (Akun Kepala 4)
            elseif (str_starts_with($code, '4') || $type === 'revenue') {
                $totalRevenue += round($credit - $debit, 2);
            }
            // 5. BEBAN OPERASIONAL (Akun Kepala 6 atau beban non-HPP)
            elseif (
                str_starts_with($code, '6')
                || str_starts_with($code, '511')
                || $code === '5110'
                || str_contains(strtolower($name), 'operasional')
                || str_contains(strtolower($name), 'biaya')
                || str_contains(strtolower($name), 'beban')
            ) {
                $totalExpenses += round($debit - $credit, 2);
            }
            // 6. HARGA POKOK PENJUALAN (Akun Kepala 5)
            elseif (str_starts_with($code, '5') || $type === 'expense') {
                $totalCogs += round($debit - $credit, 2);
            }
        }

        // CRITICAL RULE: Hitung Laba Bersih Tahun Berjalan (Net Income) dari akun 4, 5, 6
        $grossProfit = round($totalRevenue - $totalCogs, 2);
        $netIncome = round($grossProfit - $totalExpenses, 2);

        // Tambahkan Laba Bersih ke kelompok Modal agar Aktiva seimbang dengan Kewajiban + Modal
        $equityAccounts[] = [
            'id' => null,
            'code' => '3-9999',
            'name' => 'Laba Bersih Periode Berjalan',
            'amount' => $netIncome,
            'is_calculated' => true,
        ];

        $totalEquity = round($totalEquityBase + $netIncome, 2);
        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);
        $totalAssets = round($totalAssets, 2);

        $difference = round(abs($totalAssets - $totalLiabilitiesAndEquity), 2);
        $isBalanced = $difference < 0.01;

        return [
            'as_of_date' => $endDate,
            'start_date' => $startDate ?? date('Y-01-01', strtotime($endDate)),
            'end_date' => $endDate,
            'branch_id' => $branchId,
            'branch' => $branch,
            'branch_name' => $branchName,
            'assets' => [
                'accounts' => $assetAccounts,
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'accounts' => $liabilityAccounts,
                'total' => $totalLiabilities,
            ],
            'equity' => [
                'accounts' => $equityAccounts,
                'base_total' => round($totalEquityBase, 2),
                'net_income' => $netIncome,
                'total' => $totalEquity,
            ],
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => $isBalanced,
            'difference' => $difference,
        ];
    }
}
