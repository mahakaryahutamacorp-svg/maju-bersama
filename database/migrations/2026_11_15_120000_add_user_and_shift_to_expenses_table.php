<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
            $table->foreignId('cash_register_shift_id')->nullable()->after('user_id')->constrained('cash_register_shifts')->nullOnDelete();
            $table->index('cash_register_shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['cash_register_shift_id']);
            $table->dropColumn(['user_id', 'cash_register_shift_id']);
        });
    }
};