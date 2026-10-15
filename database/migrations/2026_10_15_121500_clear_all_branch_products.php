<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        // Katalog yang dikosongkan tidak dikembalikan.
    }
};
