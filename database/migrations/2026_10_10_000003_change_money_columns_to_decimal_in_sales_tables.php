<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POS money uses the same DECIMAL(15,2) scale as journal_lines, so a receipt
     * and its journal always hold the same rupiah and sen.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->change();
            $table->decimal('discount_amount', 15, 2)->default(0)->change();
            $table->decimal('paid_amount', 15, 2)->default(0)->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->change();
            $table->decimal('subtotal', 15, 2)->change();
        });
    }

    /**
     * Rolling back truncates sen on total_amount, price, and subtotal.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->integer('price')->change();
            $table->integer('subtotal')->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->integer('total_amount')->change();
            $table->decimal('discount_amount', 15, 4)->default(0)->change();
            $table->decimal('paid_amount', 15, 4)->default(0)->change();
        });
    }
};
