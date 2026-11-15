<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BackofficeMonitoringService
{
    public const PERIODS = ['day', 'week', 'month', 'year'];

    private const TOTAL_LABELS = [
        'day' => 'Total hari ini',
        'week' => 'Total minggu ini',
        'month' => 'Total bulan ini',
        'year' => 'Total tahun ini',
    ];

    /**
     * Total penjualan kasir per lokasi. Nota belum selesai tidak dihitung, dan retur tidak mengurangi total.
     *
     * @return array{
     *     period: string,
     *     total_label: string,
     *     total_amount: string,
     *     total_formatted: string,
     *     receipt_count: int,
     *     locations: list<array{id: int, name: string, total_amount: string, total_formatted: string, receipt_count: int}>
     * }
     */
    public function snapshot(User $user, ?string $period = null): array
    {
        $period = $this->period($period);
        [$start, $end] = $this->range($period, Carbon::now('Asia/Jakarta'));

        $branches = $this->locationsFor($user);
        $totals = $this->completedSalesByBranch($user, $start, $end);

        $locations = $branches->map(function (Branch $branch) use ($totals) {
            $summary = $totals->get($branch->id);
            $amount = Money::of($summary->sales_total ?? 0);

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'total_amount' => $amount,
                'total_formatted' => $this->formatRupiah($amount),
                'receipt_count' => (int) ($summary->receipt_count ?? 0),
            ];
        })->sort(function (array $left, array $right): int {
            $byAmount = bccomp($right['total_amount'], $left['total_amount'], 2);
            if ($byAmount !== 0) {
                return $byAmount;
            }

            return strcasecmp($left['name'], $right['name']);
        })->values();

        $totalAmount = $locations->reduce(
            fn (string $carry, array $location) => bcadd($carry, $location['total_amount'], 2),
            '0.00'
        );

        return [
            'period' => $period,
            'total_label' => self::TOTAL_LABELS[$period],
            'total_amount' => $totalAmount,
            'total_formatted' => $this->formatRupiah($totalAmount),
            'receipt_count' => (int) $locations->sum('receipt_count'),
            'locations' => $locations->all(),
        ];
    }

    public function period(?string $period): string
    {
        return in_array($period, self::PERIODS, true) ? $period : 'day';
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(string $period, Carbon $now): array
    {
        $end = $now->copy()->timezone('Asia/Jakarta');
        $start = match ($this->period($period)) {
            'week' => $end->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $end->copy()->startOfMonth(),
            'year' => $end->copy()->startOfYear(),
            default => $end->copy()->startOfDay(),
        };

        return [$start, $end];
    }

    /**
     * @return Collection<int, Branch>
     */
    private function locationsFor(User $user): Collection
    {
        return Branch::query()
            ->when(! $user->isMaster(), fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Sale>
     */
    private function completedSalesByBranch(User $user, Carbon $start, Carbon $end): Collection
    {
        return Sale::query()
            ->select('branch_id')
            ->selectRaw('SUM(total_amount) as sales_total')
            ->selectRaw('COUNT(*) as receipt_count')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->when(! $user->isMaster(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->groupBy('branch_id')
            ->get()
            ->keyBy(fn (Sale $sale) => (int) $sale->branch_id);
    }

    private function formatRupiah(mixed $amount): string
    {
        return 'Rp '.number_format((float) Money::of($amount), 0, ',', '.');
    }
}
