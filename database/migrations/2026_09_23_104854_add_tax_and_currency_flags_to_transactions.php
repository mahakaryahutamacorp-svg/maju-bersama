<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('tax_category', ['PPN', 'NON_PPN', 'NON_BKP'])->default('NON_PPN');
            $table->string('currency_code', 3)->default('IDR');
            $table->decimal('exchange_rate', 15, 4)->default(1.0000);
        });

        $purchaseTables = array_filter(['purchases', 'purchase_orders'], fn ($t) => Schema::hasTable($t));
        foreach ($purchaseTables as $purchaseTable) {
            Schema::table($purchaseTable, function (Blueprint $table) {
                $table->enum('tax_category', ['PPN', 'NON_PPN', 'NON_BKP'])->default('NON_PPN');
                $table->string('currency_code', 3)->default('IDR');
                $table->decimal('exchange_rate', 15, 4)->default(1.0000);
            });
        }

        Schema::table('journal_headers', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('IDR');
            $table->decimal('exchange_rate', 15, 4)->default(1.0000);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['tax_category', 'currency_code', 'exchange_rate']);
        });

        $purchaseTables = array_filter(['purchases', 'purchase_orders'], fn ($t) => Schema::hasTable($t));
        foreach ($purchaseTables as $purchaseTable) {
            Schema::table($purchaseTable, function (Blueprint $table) {
                $table->dropColumn(['tax_category', 'currency_code', 'exchange_rate']);
            });
        }

        Schema::table('journal_headers', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'exchange_rate']);
        });
    }
};
