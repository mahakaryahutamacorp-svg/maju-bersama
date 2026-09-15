<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A SKU only needs to be unique inside a branch catalogue. Keeping it globally
     * unique makes it impossible for the central entity and a branch to hold the same
     * article, which blocks stock transfers entirely.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_sku_unique');
            $table->unique(['branch_id', 'sku'], 'products_branch_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_branch_sku_unique');
            $table->unique('sku', 'products_sku_unique');
        });
    }
};
