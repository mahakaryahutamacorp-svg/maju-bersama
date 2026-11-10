<?php

namespace App\Services\Reports;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Support\DecimalQuantity;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PurchaseReportService
{
    /**
     * Pembelian periode berjalan: pesanan pembelian (PO) dan penerimaan barang langsung tanpa PO.
     * Penerimaan yang berasal dari PO tidak dihitung lagi supaya nilai pembelian tidak dobel.
     *
     * @return array{
     *     purchases: Collection,
     *     total_purchases: float,
     *     total_orders: int,
     *     direct_receipts: int,
     *     completed_orders: int,
     *     pending_orders: int,
     *     total_items: int
     * }
     */
    public function getPurchases(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $orders = PurchaseOrder::query()
            ->with(['supplier', 'branch', 'items'])
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($dateQ) use ($start, $end) {
                    $dateQ->whereDate('order_date', '>=', $start->toDateString())
                        ->whereDate('order_date', '<=', $end->toDateString());
                })->orWhere(function ($sub) use ($start, $end) {
                    $sub->whereNull('order_date')
                        ->whereBetween('created_at', [$start, $end]);
                });
            })
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->map(fn (PurchaseOrder $order) => $this->orderRow($order));

        $receipts = GoodsReceipt::query()
            ->with(['supplier', 'branch', 'items'])
            ->whereNull('purchase_order_id')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->map(fn (GoodsReceipt $receipt) => $this->receiptRow($receipt));

        $purchases = $orders->concat($receipts)
            ->sortByDesc(fn (object $row) => $row->date.'-'.str_pad((string) $row->id, 10, '0', STR_PAD_LEFT))
            ->values();

        $active = $purchases->reject(fn (object $row) => $row->status === 'cancelled');
        $completed = $active->filter(fn (object $row) => $row->is_received)->count();

        return [
            'purchases' => $purchases,
            'total_purchases' => round((float) $active->sum('total_amount'), 2),
            'total_orders' => $active->where('source', 'order')->count(),
            'direct_receipts' => $active->where('source', 'receipt')->count(),
            'total_documents' => $active->count(),
            'completed_orders' => $completed,
            'pending_orders' => $active->count() - $completed,
            'total_items' => DecimalQuantity::present(DecimalQuantity::sum($active->pluck('quantity'))),
        ];
    }

    private function orderRow(PurchaseOrder $order): object
    {
        $status = strtolower((string) ($order->status ?? 'pending'));

        return (object) [
            'id' => $order->id,
            'source' => 'order',
            'source_label' => 'Pesanan (PO)',
            'date' => ($order->order_date ?? $order->created_at)?->toDateString() ?? '',
            'reference_number' => $order->reference_number,
            'supplier_name' => $order->supplier?->name ?? 'Supplier Umum',
            'branch_name' => $order->branch?->name ?? '-',
            'item_count' => $order->items->count(),
            'quantity' => DecimalQuantity::present(DecimalQuantity::sum($order->items->pluck('quantity'))),
            'total_amount' => (float) $order->total_amount,
            'status' => $status,
            'status_label' => $order->status ?? 'pending',
            'is_received' => in_array($status, ['completed', 'received', 'closed'], true),
        ];
    }

    private function receiptRow(GoodsReceipt $receipt): object
    {
        $total = (float) $receipt->total_amount;
        $isCash = strtolower((string) $receipt->payment_type) !== 'credit';
        $paid = $isCash ? $total : (float) $receipt->paid_amount;
        $status = match (true) {
            $paid >= $total => 'paid',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };

        return (object) [
            'id' => $receipt->id,
            'source' => 'receipt',
            'source_label' => $isCash ? 'Terima langsung (tunai)' : 'Terima langsung (tempo)',
            'date' => $receipt->date?->toDateString() ?? '',
            'reference_number' => $receipt->reference_number,
            'supplier_name' => $receipt->supplier?->name ?? ($receipt->supplier_name ?: 'Supplier Umum'),
            'branch_name' => $receipt->branch?->name ?? '-',
            'item_count' => $receipt->items->count(),
            'quantity' => DecimalQuantity::present(DecimalQuantity::sum($receipt->items->pluck('quantity'))),
            'total_amount' => $total,
            'status' => $status,
            'status_label' => ['paid' => 'Lunas', 'partial' => 'Dibayar sebagian', 'unpaid' => 'Belum dibayar'][$status],
            'is_received' => true,
        ];
    }
}
