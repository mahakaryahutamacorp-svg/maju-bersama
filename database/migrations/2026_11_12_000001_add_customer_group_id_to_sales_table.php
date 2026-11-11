<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipe harga yang dipakai saat transaksi, agar pembeli tanpa nama tetap tercatat tipenya di struk.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'customer_group_id')) {
                $table->foreignId('customer_group_id')->nullable()->after('customer_id')->constrained('customer_groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'customer_group_id')) {
                $table->dropForeign(['customer_group_id']);
                $table->dropColumn('customer_group_id');
            }
        });
    }
};
