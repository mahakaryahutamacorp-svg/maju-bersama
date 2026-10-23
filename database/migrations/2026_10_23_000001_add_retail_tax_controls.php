<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('tax_enabled')->default(false)->after('is_active');
            $table->decimal('tax_rate', 5, 2)->default(11.00)->after('tax_enabled');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_taxable')->default(false)->after('selling_price');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('subtotal_non_tax', 15, 2)->default(0)->after('total_amount');
            $table->decimal('dpp_taxable', 15, 2)->default(0)->after('subtotal_non_tax');
            $table->decimal('tax_total', 15, 2)->default(0)->after('dpp_taxable');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->boolean('is_taxable')->default(false)->after('subtotal');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('is_taxable');
            $table->decimal('dpp_amount', 15, 2)->default(0)->after('tax_rate');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('dpp_amount');
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->decimal('dpp_amount', 15, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('dpp_amount');
        });

        DB::table('sales')->update([
            'subtotal_non_tax' => DB::raw('total_amount'),
        ]);

        DB::table('branches')->where('code', 'CBG-MAMA')->update([
            'tax_enabled' => true,
            'tax_rate' => 11.00,
        ]);
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropColumn(['dpp_amount', 'tax_amount']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['is_taxable', 'tax_rate', 'dpp_amount', 'tax_amount']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['subtotal_non_tax', 'dpp_taxable', 'tax_total']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_taxable');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['tax_enabled', 'tax_rate']);
        });
    }
};
