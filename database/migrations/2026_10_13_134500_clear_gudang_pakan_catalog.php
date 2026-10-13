<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches') || ! Schema::hasTable('products')) {
            return;
        }

        $branch = DB::table('branches')->where('code', 'GUDANG-PAKAN')->first();
        if (! $branch) {
            return;
        }

        $productIds = DB::table('products')->where('branch_id', $branch->id)->pluck('id')->all();
        if ($productIds === []) {
            return;
        }

        foreach (['product_prices', 'product_branch_prices', 'inventories', 'stock_adjustment_items', 'stock_adjustment_lines'] as $table) {
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

        DB::table('products')->whereIn('id', $productIds)->delete();
    }

    public function down(): void
    {
        // Katalog Gudang Pakan dikosongkan karena belum ada input produk pakan.
    }
};
