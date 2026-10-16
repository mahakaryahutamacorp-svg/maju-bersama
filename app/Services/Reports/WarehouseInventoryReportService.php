<?php

namespace App\Services\Reports;

use App\Models\Inventory;
use Illuminate\Support\Collection;

class WarehouseInventoryReportService
{
    /**
     * @return array{rows: Collection, total_quantity: int, total_value: float, warehouse_count: int, hidden_zero_rows: int}
     */
    public function getInventory(?int $branchId = null): array
    {
        $rows = Inventory::withoutGlobalScopes()
            ->with([
                'branch',
                'warehouse',
                'product' => fn ($query) => $query->withoutGlobalScopes()->withTrashed(),
            ])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->map(function (Inventory $row) {
                $quantity = (int) $row->quantity;
                $unitCost = (float) ($row->product?->purchase_price ?? 0);

                return [
                    'branch_name' => $row->branch?->name ?? '-',
                    'warehouse_name' => $row->warehouse?->name ?? 'Gudang belum ditetapkan',
                    'warehouse_code' => $row->warehouse?->code,
                    'sku' => $row->product?->sku,
                    'product_name' => $row->product?->name ?? 'Barang dihapus',
                    'unit' => $row->product?->unit,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'value' => round($quantity * $unitCost, 2),
                ];
            });

        $visible = $rows
            ->filter(fn (array $row) => $row['quantity'] !== 0)
            ->sortBy([
                ['warehouse_name', 'asc'],
                ['product_name', 'asc'],
            ])
            ->values();

        return [
            'rows' => $visible,
            'total_quantity' => (int) $visible->sum('quantity'),
            'total_value' => round((float) $visible->sum('value'), 2),
            'warehouse_count' => $visible->pluck('warehouse_name')->unique()->count(),
            'hidden_zero_rows' => $rows->count() - $visible->count(),
        ];
    }
}
