<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 4)->default(0)->after('total_amount');
            $table->enum('payment_status', ['UNPAID', 'PARTIAL', 'PAID'])->default('UNPAID')->after('paid_amount');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 4)->default(0)->after('total_amount');
            $table->enum('payment_status', ['UNPAID', 'PARTIAL', 'PAID'])->default('UNPAID')->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'payment_status']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'payment_status']);
        });
    }
};
