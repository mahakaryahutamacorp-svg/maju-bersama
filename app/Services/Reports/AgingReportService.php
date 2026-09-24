<?php

namespace App\Services\Reports;

use App\Models\PurchaseOrder;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgingReportService
{
    /**
     * Menghitung Laporan Umur Piutang (AR Aging) per Pelanggan.
     *
     * @param int|null $branchId
     * @return array{
     *     rows: Collection,
     *     items: Collection,
     *     totals: array{
     *         total_amount: float,
     *         bucket_0_30: float,
     *         bucket_31_60: float,
     *         bucket_61_90: float,
     *         bucket_over_90: float,
     *         total_customers: int,
     *         total_invoices: int
     *     }
     * }
     */
    public function getArAging(?int $branchId = null): array
    {
        $query = Sale::withoutGlobalScopes()
            ->with(['customer', 'branch'])
            ->where('payment_status', '!=', 'PAID')
            ->orderBy('id', 'desc');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $sales = $query->get();
        $grouped = [];

        foreach ($sales as $sale) {
            $unpaid = (float) $sale->total_amount - (float) ($sale->paid_amount ?? 0);
            if ($unpaid <= 0) {
                continue;
            }

            // Gunakan due_date jika tersedia, atau created_at sebagai dasar
            $baseDate = $sale->due_date ? Carbon::parse($sale->due_date) : Carbon::parse($sale->created_at);
            $diffDays = (int) $baseDate->startOfDay()->diffInDays(now()->startOfDay(), false);
            if ($diffDays < 0) {
                $diffDays = 0;
            }

            $customerId = $sale->customer_id ?? 0;
            $customerName = $sale->customer ? $sale->customer->name : 'Pelanggan Umum / Non-Member';

            if (! isset($grouped[$customerId])) {
                $grouped[$customerId] = [
                    'customer_id'    => $customerId,
                    'customer_name'  => $customerName,
                    'customer'       => $sale->customer,
                    'total_amount'   => 0.0,
                    'bucket_0_30'    => 0.0,
                    'bucket_31_60'   => 0.0,
                    'bucket_61_90'   => 0.0,
                    'bucket_over_90' => 0.0,
                    'invoices_count' => 0,
                ];
            }

            $grouped[$customerId]['total_amount'] += $unpaid;
            $grouped[$customerId]['invoices_count'] += 1;

            if ($diffDays <= 30) {
                $grouped[$customerId]['bucket_0_30'] += $unpaid;
            } elseif ($diffDays <= 60) {
                $grouped[$customerId]['bucket_31_60'] += $unpaid;
            } elseif ($diffDays <= 90) {
                $grouped[$customerId]['bucket_61_90'] += $unpaid;
            } else {
                $grouped[$customerId]['bucket_over_90'] += $unpaid;
            }
        }

        $rows = collect(array_values($grouped))->sortByDesc('total_amount')->values();

        $totals = [
            'total_amount'    => (float) $rows->sum('total_amount'),
            'bucket_0_30'     => (float) $rows->sum('bucket_0_30'),
            'bucket_31_60'    => (float) $rows->sum('bucket_31_60'),
            'bucket_61_90'    => (float) $rows->sum('bucket_61_90'),
            'bucket_over_90'  => (float) $rows->sum('bucket_over_90'),
            'total_customers' => $rows->count(),
            'total_invoices'  => (int) $rows->sum('invoices_count'),
        ];

        return [
            'rows'   => $rows,
            'items'  => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Menghitung Laporan Umur Hutang (AP Aging) per Supplier.
     *
     * @param int|null $branchId
     * @return array{
     *     rows: Collection,
     *     items: Collection,
     *     totals: array{
     *         total_amount: float,
     *         bucket_0_30: float,
     *         bucket_31_60: float,
     *         bucket_61_90: float,
     *         bucket_over_90: float,
     *         total_suppliers: int,
     *         total_invoices: int
     *     }
     * }
     */
    public function getApAging(?int $branchId = null): array
    {
        $query = PurchaseOrder::withoutGlobalScopes()
            ->with(['supplier', 'branch'])
            ->where('payment_status', '!=', 'PAID')
            ->orderBy('id', 'desc');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $pos = $query->get();
        $grouped = [];

        foreach ($pos as $po) {
            $unpaid = (float) $po->total_amount - (float) ($po->paid_amount ?? 0);
            if ($unpaid <= 0) {
                continue;
            }

            // Gunakan due_date jika tersedia, atau order_date / created_at sebagai dasar
            $baseDate = $po->due_date ? Carbon::parse($po->due_date) : ($po->order_date ? Carbon::parse($po->order_date) : Carbon::parse($po->created_at));
            $diffDays = (int) $baseDate->startOfDay()->diffInDays(now()->startOfDay(), false);
            if ($diffDays < 0) {
                $diffDays = 0;
            }

            $supplierId = $po->supplier_id ?? 0;
            $supplierName = $po->supplier ? $po->supplier->name : 'Supplier Lainnya';

            if (! isset($grouped[$supplierId])) {
                $grouped[$supplierId] = [
                    'supplier_id'    => $supplierId,
                    'supplier_name'  => $supplierName,
                    'supplier'       => $po->supplier,
                    'total_amount'   => 0.0,
                    'bucket_0_30'    => 0.0,
                    'bucket_31_60'   => 0.0,
                    'bucket_61_90'   => 0.0,
                    'bucket_over_90' => 0.0,
                    'invoices_count' => 0,
                ];
            }

            $grouped[$supplierId]['total_amount'] += $unpaid;
            $grouped[$supplierId]['invoices_count'] += 1;

            if ($diffDays <= 30) {
                $grouped[$supplierId]['bucket_0_30'] += $unpaid;
            } elseif ($diffDays <= 60) {
                $grouped[$supplierId]['bucket_31_60'] += $unpaid;
            } elseif ($diffDays <= 90) {
                $grouped[$supplierId]['bucket_61_90'] += $unpaid;
            } else {
                $grouped[$supplierId]['bucket_over_90'] += $unpaid;
            }
        }

        $rows = collect(array_values($grouped))->sortByDesc('total_amount')->values();

        $totals = [
            'total_amount'    => (float) $rows->sum('total_amount'),
            'bucket_0_30'     => (float) $rows->sum('bucket_0_30'),
            'bucket_31_60'    => (float) $rows->sum('bucket_31_60'),
            'bucket_61_90'    => (float) $rows->sum('bucket_61_90'),
            'bucket_over_90'  => (float) $rows->sum('bucket_over_90'),
            'total_suppliers' => $rows->count(),
            'total_invoices'  => (int) $rows->sum('invoices_count'),
        ];

        return [
            'rows'   => $rows,
            'items'  => $rows,
            'totals' => $totals,
        ];
    }
}
