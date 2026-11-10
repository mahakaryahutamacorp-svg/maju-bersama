<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melebarkan kolom kuantitas dari integer ke DECIMAL(15,3).
 *
 * Aman di SQLite (pengembangan/tes) dan MySQL/MariaDB (produksi): ALTER hanya
 * mengubah tipe, data lama tetap ada. Bilangan bulat yang sudah tersimpan
 * menjadi x.000. Pecahan yang dulu terpotong saat input tidak bisa dipulihkan
 * karena tidak pernah tersimpan.
 *
 * down() memotong kembali ke integer dan membuang desimal baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->widen('products', 'stock', true);
        $this->widen('inventories', 'quantity', true);
        $this->widen('sale_items', 'quantity');
        $this->widen('goods_receipt_items', 'quantity');
        $this->widen('purchase_order_items', 'quantity');
        $this->widen('purchase_order_items', 'received_quantity', true);
        $this->widen('stock_transfer_items', 'quantity');
        $this->widen('stock_adjustment_items', 'expected_qty');
        $this->widen('stock_adjustment_items', 'actual_qty');
        $this->widen('stock_adjustment_items', 'difference_qty');
        $this->widen('stock_adjustment_lines', 'system_qty');
        $this->widen('stock_adjustment_lines', 'actual_qty');
        $this->widen('stock_adjustment_lines', 'difference_qty');
        $this->widen('purchase_return_items', 'quantity');
        $this->widen('sales_return_items', 'quantity');
    }

    public function down(): void
    {
        $this->narrow('sales_return_items', 'quantity');
        $this->narrow('purchase_return_items', 'quantity');
        $this->narrow('stock_adjustment_lines', 'difference_qty');
        $this->narrow('stock_adjustment_lines', 'actual_qty');
        $this->narrow('stock_adjustment_lines', 'system_qty');
        $this->narrow('stock_adjustment_items', 'difference_qty');
        $this->narrow('stock_adjustment_items', 'actual_qty');
        $this->narrow('stock_adjustment_items', 'expected_qty');
        $this->narrow('stock_transfer_items', 'quantity');
        $this->narrow('purchase_order_items', 'received_quantity', true);
        $this->narrow('purchase_order_items', 'quantity');
        $this->narrow('goods_receipt_items', 'quantity');
        $this->narrow('sale_items', 'quantity');
        $this->narrow('inventories', 'quantity', true);
        $this->narrow('products', 'stock', true);
    }

    private function widen(string $table, string $column, bool $defaultZero = false): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $defaultZero) {
            $definition = $blueprint->decimal($column, 15, 3);
            if ($defaultZero) {
                $definition->default(0);
            }
            $definition->change();
        });
    }

    private function narrow(string $table, string $column, bool $defaultZero = false): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $defaultZero) {
            $definition = $blueprint->integer($column);
            if ($defaultZero) {
                $definition->default(0);
            }
            $definition->change();
        });
    }
};
