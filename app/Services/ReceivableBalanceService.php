<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Support\Money;

/**
 * Sisa piutang satu nota = total nota − uang yang sudah diterima − retur yang dikreditkan ke Piutang Usaha.
 */
class ReceivableBalanceService
{
    /**
     * @param  iterable<int>  $saleIds
     * @return array<int, string>
     */
    public function returnedBySale(iterable $saleIds): array
    {
        $ids = collect($saleIds)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $receivableAccountIds = ChartOfAccount::withTrashed()
            ->where('code', JournalPostingService::ACCOUNT_AR)
            ->pluck('id');

        return SalesReturn::withoutGlobalScopes()
            ->whereIn('sale_id', $ids)
            ->whereIn('chart_of_account_id', $receivableAccountIds)
            ->where('status', 'completed')
            ->groupBy('sale_id')
            ->selectRaw('sale_id, SUM(total_amount) as returned')
            ->pluck('returned', 'sale_id')
            ->map(fn ($amount) => Money::of($amount))
            ->all();
    }

    public function outstanding(Sale $sale, ?string $returned = null): string
    {
        $returned ??= $this->returnedBySale([$sale->id])[$sale->id] ?? '0.00';

        $balance = bcsub(
            bcsub(Money::of($sale->total_amount), Money::of($sale->paid_amount ?? 0), 2),
            Money::of($returned),
            2,
        );

        return bccomp($balance, '0.00', 2) === -1 ? '0.00' : $balance;
    }

    /**
     * Status bayar mengikuti sisa piutang; nota yang habis diretur tidak lagi ditagih.
     */
    public function refreshPaymentStatus(Sale $sale): void
    {
        $outstanding = $this->outstanding($sale);

        if (bccomp($outstanding, '0.00', 2) === 0) {
            $sale->payment_status = 'PAID';
        } elseif (bccomp(Money::of($sale->paid_amount ?? 0), '0.00', 2) === 1) {
            $sale->payment_status = 'PARTIAL';
        } else {
            $sale->payment_status = 'UNPAID';
        }

        $sale->save();
    }
}
