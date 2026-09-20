<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_register_shift_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('cash_register_shifts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['cash_register_shift_id']);
            $table->dropColumn('cash_register_shift_id');
        });
    }
};
