<?php

namespace App\Services\Reports;

use App\Enums\CashFlowTag;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use Illuminate\Support\Collection;

class CashFlowService
{
    public const OPERATING = 'operating';

    public const INVESTING = 'investing';

    public const FINANCING = 'financing';

    public const ACTIVITY_LABELS = [
        self::OPERATING => 'Aktivitas Operasional',
        self::INVESTING => 'Aktivitas Investasi',
        self::FINANCING => 'Aktivitas Pendanaan',
    ];

    /**
     * Laporan Arus Kas metode langsung dari baris jurnal akun kas & bank.
     * Pindah dana antar-akun kas tidak menggembungkan kas masuk maupun keluar.
     *
     * @param  string  $startDate  YYYY-MM-DD
     * @param  string  $endDate  YYYY-MM-DD
     * @param  int|null  $branchId  Optional branch ID filter
     */
    public function generate(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $branch = $branchId ? Branch::query()->find($branchId) : null;
        $branchName = $branch ? $branch->name : 'Konsolidasi Seluruh Cabang';

        $cashAccountIds = ChartOfAccount::cashAccountIds();

        $openingQuery = JournalLine::query()
            ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
            ->whereIn('journal_lines.chart_of_account_id', $cashAccountIds)
            ->whereNull('journal_headers.deleted_at')
            ->whereDate('journal_headers.transaction_date', '<', $startDate)
            ->when($branchId, fn ($q) => $q->where('journal_headers.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as prior_debit, COALESCE(SUM(journal_lines.credit), 0) as prior_credit')
            ->first();

        $openingBalance = round((float) ($openingQuery->prior_debit ?? 0) - (float) ($openingQuery->prior_credit ?? 0), 2);

        $journals = JournalHeader::query()
            ->with(['branch', 'journalLines.chartOfAccount' => fn ($query) => $query->withTrashed()])
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('journalLines', fn ($q) => $q->whereIn('chart_of_account_id', $cashAccountIds))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $cashInItems = [];
        $cashOutItems = [];
        $activities = collect(array_keys(self::ACTIVITY_LABELS))
            ->mapWithKeys(fn (string $key) => [$key => ['label' => self::ACTIVITY_LABELS[$key], 'cash_in' => 0.0, 'cash_out' => 0.0]])
            ->all();

        foreach ($journals as $journal) {
            [$cashLines, $counterLines] = $journal->journalLines
                ->partition(fn (JournalLine $line) => in_array((int) $line->chart_of_account_id, $cashAccountIds, true));

            // Jurnal yang hanya berisi akun kas & bank adalah pindah dana internal, bukan kas masuk/keluar.
            if ($counterLines->isEmpty()) {
                continue;
            }

            $activity = $this->classify($counterLines);

            foreach ($cashLines as $line) {
                $item = [
                    'date' => optional($journal->transaction_date)->format('Y-m-d') ?? '',
                    'reference' => (string) ($journal->reference_number ?? '-'),
                    'description' => (string) ($line->memo ?: ($journal->description ?? 'Mutasi Kas')),
                    'account' => (string) ($line->chartOfAccount?->name ?? 'Kas'),
                    'branch' => (string) ($journal->branch?->name ?? 'Pusat'),
                    'activity' => $activity,
                    'activity_label' => self::ACTIVITY_LABELS[$activity],
                ];

                if ((float) $line->debit > 0) {
                    $cashInItems[] = $item + ['amount' => round((float) $line->debit, 2)];
                    $activities[$activity]['cash_in'] += (float) $line->debit;
                }

                if ((float) $line->credit > 0) {
                    $cashOutItems[] = $item + ['amount' => round((float) $line->credit, 2)];
                    $activities[$activity]['cash_out'] += (float) $line->credit;
                }
            }
        }

        foreach ($activities as $key => $activity) {
            $activities[$key]['cash_in'] = round($activity['cash_in'], 2);
            $activities[$key]['cash_out'] = round($activity['cash_out'], 2);
            $activities[$key]['net'] = round($activity['cash_in'] - $activity['cash_out'], 2);
        }

        $totalCashIn = round(array_sum(array_column($cashInItems, 'amount')), 2);
        $totalCashOut = round(array_sum(array_column($cashOutItems, 'amount')), 2);
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
            'activities' => $activities,
            'net_cash_flow' => $netCashFlow,
            'ending_balance' => $endingBalance,
            'is_surplus' => $netCashFlow >= 0,
        ];
    }

    /**
     * Aktivitas ditentukan dari akun lawan kas: tanda arus kas pada akun bila diisi,
     * selain itu aset tetap (13xx) = investasi, hutang jangka panjang (22xx) dan modal (3xxx) = pendanaan.
     *
     * @param  Collection<int, JournalLine>  $counterLines
     */
    private function classify(Collection $counterLines): string
    {
        $activities = $counterLines->map(function (JournalLine $line): string {
            $account = $line->chartOfAccount;
            $tag = $account?->cash_flow_tag;

            if ($tag === CashFlowTag::Investing) {
                return self::INVESTING;
            }
            if ($tag === CashFlowTag::Financing) {
                return self::FINANCING;
            }
            if ($tag === CashFlowTag::Operating) {
                return self::OPERATING;
            }

            $code = (string) ($account?->code ?? '');

            return match (true) {
                str_starts_with($code, '13') => self::INVESTING,
                str_starts_with($code, '22'), str_starts_with($code, '3') => self::FINANCING,
                default => self::OPERATING,
            };
        });

        foreach ([self::INVESTING, self::FINANCING] as $activity) {
            if ($activities->contains($activity)) {
                return $activity;
            }
        }

        return self::OPERATING;
    }
}
