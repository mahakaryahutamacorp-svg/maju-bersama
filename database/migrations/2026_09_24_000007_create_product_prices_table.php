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
        if (! Schema::hasTable('product_prices')) {
            Schema::create('product_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('customer_group_id')->constrained('customer_groups')->cascadeOnDelete();
                $table->decimal('price', 15, 4);
                $table->timestamps();

                $table->unique(['product_id', 'customer_group_id']);
            });
        } else {
            Schema::table('product_prices', function (Blueprint $table) {
                if (! Schema::hasColumn('product_prices', 'customer_group_id')) {
                    $table->foreignId('customer_group_id')->nullable()->after('product_id')->constrained('customer_groups')->cascadeOnDelete();
                    $table->unique(['product_id', 'customer_group_id']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_prices') && Schema::hasColumn('product_prices', 'customer_group_id')) {
            Schema::table('product_prices', function (Blueprint $table) {
                $table->dropUnique(['product_id', 'customer_group_id']);
                $table->dropForeign(['customer_group_id']);
                $table->dropColumn('customer_group_id');
            });
        }
    }
};
