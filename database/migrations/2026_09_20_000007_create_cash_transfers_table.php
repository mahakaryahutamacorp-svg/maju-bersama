<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('from_account_id')->constrained('chart_of_accounts');
            $table->foreignId('to_account_id')->constrained('chart_of_accounts');
            $table->decimal('amount', 15, 2);
            $table->date('transfer_date');
            $table->string('reference_number')->unique();
            $table->text('notes')->nullable();
            $table->foreignId('journal_header_id')->nullable()->constrained('journal_headers')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'transfer_date']);
            $table->index('from_account_id');
            $table->index('to_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfers');
    }
};
