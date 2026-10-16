<?php

namespace App\Services\Reports;

use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PurchaseReportService
{
    /**
     * Generate purchase order summary and detailed records.
     *
     * @return array{
     *     purchases: Collection,
     *     total_purchases: float,
     *     total_orders: int,
     *     completed_orders: int,
     *     pending_orders: int,
     *     total_items: int
     * }
     */
    public function getPurchases(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $query = PurchaseOrder::query()
            ->with(['supplier', 'branch', 'items.product'])
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($dateQ) use ($start, $end) {
                    $dateQ->whereDate('order_date', '>=', $start->toDateString())
                        ->whereDate('order_date', '<=', $end->toDateString());
                })->orWhere(function ($sub) use ($start, $end) {
                    $sub->whereNull('order_date')
                        ->whereBetween('created_at', [$start, $end]);
                });
            })
            ->latest('order_date')
            ->latest('id');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $purchases = $query->get();

        $activePurchases = $purchases->reject(
            fn ($purchase) => strtolower((string) ($purchase->status ?? '')) === 'cancelled'
        );
        $totalPurchases = (float) $activePurchases->sum('total_amount');
        $totalOrders = $activePurchases->count();
        $completedOrders = $activePurchases->filter(
            fn ($purchase) => in_array(strtolower((string) ($purchase->status ?? '')), ['completed', 'received', 'closed'], true)
        )->count();
        $pendingOrders = $totalOrders - $completedOrders;
        $totalItems = (int) $activePurchases->sum(fn ($purchase) => $purchase->items->sum('quantity'));

        return [
            'purchases' => $purchases,
            'total_purchases' => $totalPurchases,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'pending_orders' => $pendingOrders,
            'total_items' => $totalItems,
        ];
    }
}
