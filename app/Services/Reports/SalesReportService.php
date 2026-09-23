<?php

namespace App\Services\Reports;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesReportService
{
    /**
     * Generate sales summary and detailed transactions.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $branchId
     * @return array{
     *     sales: Collection,
     *     total_sales: float,
     *     total_transactions: int,
     *     total_items_sold: int,
     *     average_per_transaction: float,
     *     cash_sales: float,
     *     non_cash_sales: float
     * }
     */
    public function getSales(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $query = Sale::query()
            ->with(['branch', 'user', 'items.product'])
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $sales = $query->get();

        $totalSales = (float) $sales->sum('total_amount');
        $totalTransactions = $sales->count();
        $totalItemsSold = (int) $sales->sum(function ($sale) {
            return $sale->items->sum('quantity');
        });

        $averagePerTransaction = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0.0;

        $cashSales = (float) $sales->filter(fn ($s) => strtolower($s->payment_method ?? '') === 'cash')->sum('total_amount');
        $nonCashSales = $totalSales - $cashSales;

        return [
            'sales' => $sales,
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'total_items_sold' => $totalItemsSold,
            'average_per_transaction' => $averagePerTransaction,
            'cash_sales' => $cashSales,
            'non_cash_sales' => $nonCashSales,
        ];
    }
}
