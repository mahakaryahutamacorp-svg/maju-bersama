<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('expected_closing_balance', 15, 2)->default(0);
            $table->decimal('actual_closing_balance', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['cash_register_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_shifts');
    }
};
