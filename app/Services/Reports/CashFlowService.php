<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;

class CashFlowService
{
    /**
     * Generate Cash Flow (Laporan Arus Kas - Metode Langsung/Sederhana).
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

        // Identifikasi Akun Kas & Bank (Kepala 1110 / 1120 / type asset dengan nama kas/bank)
        $cashAccounts = ChartOfAccount::query()
            ->where(function ($q) {
                $q->where('code', 'LIKE', '111%')
                    ->orWhere('code', 'LIKE', '112%')
                    ->orWhere('code', '1110')
                    ->orWhere('name', 'LIKE', '%kas%')
                    ->orWhere('name', 'LIKE', '%bank%');
            })
            ->where('type', 'asset')
            ->get();

        $cashAccountIds = $cashAccounts->pluck('id')->toArray();

        // 1. Hitung Saldo Awal Kas (Sebelum $startDate)
        $openingQuery = JournalLine::query()
            ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
            ->whereIn('journal_lines.chart_of_account_id', $cashAccountIds)
            ->whereNull('journal_headers.deleted_at')
            ->whereDate('journal_headers.transaction_date', '<', $startDate)
            ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as prior_debit, COALESCE(SUM(journal_lines.credit), 0) as prior_credit')
            ->first();

        $openingDebit = (float) ($openingQuery->prior_debit ?? 0);
        $openingCredit = (float) ($openingQuery->prior_credit ?? 0);
        $openingBalance = round($openingDebit - $openingCredit, 2);

        // 2. Tarik Mutasi Kas Periode Berjalan ($startDate s/d $endDate)
        $lines = JournalLine::query()
            ->with(['journalHeader.branch', 'chartOfAccount'])
            ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
            ->whereIn('journal_lines.chart_of_account_id', $cashAccountIds)
            ->whereNull('journal_headers.deleted_at')
            ->whereDate('journal_headers.transaction_date', '>=', $startDate)
            ->whereDate('journal_headers.transaction_date', '<=', $endDate)
            ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
            ->orderBy('journal_headers.transaction_date', 'asc')
            ->orderBy('journal_headers.id', 'asc')
            ->orderBy('journal_lines.id', 'asc')
            ->select('journal_lines.*')
            ->get();

        $cashInItems = [];
        $totalCashIn = 0.0;

        $cashOutItems = [];
        $totalCashOut = 0.0;

        foreach ($lines as $line) {
            $header = $line->journalHeader;
            $account = $line->chartOfAccount;

            $date = optional($header->transaction_date)->format('Y-m-d') ?? '';
            $ref = (string) ($header->reference_number ?? '-');
            $desc = (string) ($line->memo ?: ($header->description ?? 'Mutasi Kas'));
            $branchLabel = (string) (optional($header->branch)->name ?? 'Pusat');
            $accountLabel = (string) ($account->name ?? 'Kas');

            // Kas Masuk (Debit pada akun Kas/Bank)
            if ($line->debit > 0) {
                $amount = (float) $line->debit;
                $cashInItems[] = [
                    'date' => $date,
                    'reference' => $ref,
                    'description' => $desc,
                    'account' => $accountLabel,
                    'branch' => $branchLabel,
                    'amount' => round($amount, 2),
                ];
                $totalCashIn += $amount;
            }

            // Kas Keluar (Kredit pada akun Kas/Bank)
            if ($line->credit > 0) {
                $amount = (float) $line->credit;
                $cashOutItems[] = [
                    'date' => $date,
                    'reference' => $ref,
                    'description' => $desc,
                    'account' => $accountLabel,
                    'branch' => $branchLabel,
                    'amount' => round($amount, 2),
                ];
                $totalCashOut += $amount;
            }
        }

        $totalCashIn = round($totalCashIn, 2);
        $totalCashOut = round($totalCashOut, 2);
        $netCashFlow = round($totalCashIn - $totalCashOut, 2);
        $endingBalance = round($openingBalance + $netCashFlow, 2);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'branch_id' => $branchId,
            'branch' => $branch,
            'branch_name' => $branchName,
            'opening_balance' => $openingBalance,
            'cash_in' => [
                'items' => $cashInItems,
                'total' => $totalCashIn,
            ],
            'cash_out' => [
                'items' => $cashOutItems,
                'total' => $totalCashOut,
            ],
            'net_cash_flow' => $netCashFlow,
            'ending_balance' => $endingBalance,
            'is_surplus' => $netCashFlow >= 0,
        ];
    }
}
