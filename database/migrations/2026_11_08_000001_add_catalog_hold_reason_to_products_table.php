<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produk cabang yang diarsipkan sinkronisasi katalog master karena perlu diinput ulang manual.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('catalog_hold_reason')->nullable()->after('is_taxable');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('catalog_hold_reason');
        });
    }
};
