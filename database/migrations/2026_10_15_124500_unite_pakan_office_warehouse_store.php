<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->clearProducts();
        $this->unitePakan();
    }

    public function down(): void
    {
        // Penggabungan cabang pakan dan pengosongan katalog tidak dikembalikan.
    }

    private function clearProducts(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $productIds = DB::table('products')->pluck('id')->all();
        if ($productIds === []) {
            return;
        }

        foreach ([
            'sale_items',
            'goods_receipt_items',
            'purchase_order_items',
            'purchase_return_items',
            'sales_return_items',
            'stock_adjustment_items',
            'stock_adjustment_lines',
            'product_prices',
            'product_branch_prices',
            'inventories',
        ] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'product_id')) {
                DB::table($table)->whereIn('product_id', $productIds)->delete();
            }
        }

        if (Schema::hasTable('stock_transfer_items')) {
            DB::table('stock_transfer_items')
                ->where(function ($query) use ($productIds) {
                    $query->whereIn('source_product_id', $productIds)
                        ->orWhereIn('destination_product_id', $productIds);
                })
                ->delete();
        }

        DB::table('products')->delete();
    }

    private function unitePakan(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        $sources = DB::table('branches')
            ->whereIn('code', ['OFFICE-PAKAN', 'GUDANG-PAKAN', 'CBG-PKN', 'PAKAN'])
            ->orderByRaw("CASE code WHEN 'PAKAN' THEN 0 WHEN 'CBG-PKN' THEN 1 WHEN 'GUDANG-PAKAN' THEN 2 ELSE 3 END")
            ->get();

        if ($sources->isEmpty()) {
            return;
        }

        $survivor = $sources->first();
        DB::table('branches')->where('id', $survivor->id)->update([
            'code' => 'PAKAN',
            'name' => 'CV.DEKA AGRO MANDIRI',
            'parent_id' => null,
            'is_active' => 1,
            'updated_at' => now(),
        ]);

        $absorbedIds = $sources->skip(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($absorbedIds as $fromId) {
            $this->moveBranch($fromId, (int) $survivor->id);
        }

        $this->keepOneWarehouse((int) $survivor->id);

        if ($absorbedIds !== []) {
            DB::table('branches')->whereIn('parent_id', $absorbedIds)->update(['parent_id' => null]);
            DB::table('branches')->whereIn('id', $absorbedIds)->delete();
        }
    }

    private function moveBranch(int $fromId, int $toId): void
    {
        if (Schema::hasTable('warehouses')) {
            foreach (DB::table('warehouses')->where('branch_id', $fromId)->get() as $warehouse) {
                $code = (string) $warehouse->code;
                if (DB::table('warehouses')->where('branch_id', $toId)->where('code', $code)->exists()) {
                    $code = substr($code, 0, 17).'-2';
                }
                DB::table('warehouses')->where('id', $warehouse->id)->update([
                    'branch_id' => $toId,
                    'code' => $code,
                    'updated_at' => now(),
                ]);
            }
        }

        foreach ([
            'users' => ['branch_id'],
            'products' => ['branch_id'],
            'inventories' => ['branch_id'],
            'sales' => ['branch_id'],
            'customers' => ['branch_id'],
            'suppliers' => ['branch_id'],
            'journal_headers' => ['branch_id'],
            'purchase_orders' => ['branch_id'],
            'goods_receipts' => ['branch_id'],
            'payments' => ['branch_id'],
            'purchases' => ['branch_id'],
            'purchase_payments' => ['branch_id'],
            'stock_adjustments' => ['branch_id'],
            'expenses' => ['branch_id'],
            'sales_returns' => ['branch_id'],
            'purchase_returns' => ['branch_id'],
            'supplier_payments' => ['branch_id'],
            'cash_transfers' => ['branch_id'],
            'cash_register_shifts' => ['branch_id'],
            'cash_registers' => ['branch_id'],
            'product_branch_prices' => ['branch_id'],
            'assistant_inquiries' => ['branch_id'],
            'stock_transfers' => ['source_branch_id', 'destination_branch_id'],
            'internal_messages' => ['sender_branch_id', 'receiver_branch_id'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::table($table)->where($column, $fromId)->update([$column => $toId]);
                }
            }
        }

        if (Schema::hasColumn('branches', 'parent_id')) {
            DB::table('branches')->where('parent_id', $fromId)->update(['parent_id' => $toId]);
            DB::table('branches')->where('id', $toId)->update(['parent_id' => null]);
        }
    }

    private function keepOneWarehouse(int $branchId): void
    {
        if (! Schema::hasTable('warehouses')) {
            return;
        }

        $warehouses = DB::table('warehouses')->where('branch_id', $branchId)->orderBy('id')->get();
        if ($warehouses->isEmpty()) {
            DB::table('warehouses')->insert([
                'branch_id' => $branchId,
                'code' => 'PAKAN-GU',
                'name' => 'Gudang Utama',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $keep = $warehouses->first();
        DB::table('warehouses')->where('id', $keep->id)->update([
            'name' => 'Gudang Utama',
            'is_active' => 1,
            'updated_at' => now(),
        ]);

        $extraIds = $warehouses->skip(1)->pluck('id')->all();
        if ($extraIds === []) {
            return;
        }

        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'warehouse_id')) {
            DB::table('inventories')->whereIn('warehouse_id', $extraIds)->update([
                'warehouse_id' => $keep->id,
                'branch_id' => $branchId,
            ]);
        }

        DB::table('warehouses')->whereIn('id', $extraIds)->delete();
    }
};
