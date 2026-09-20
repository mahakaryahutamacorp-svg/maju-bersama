<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('reference_number')->unique();
            $table->text('notes')->nullable();
            $table->foreignId('journal_header_id')->nullable()->constrained('journal_headers')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'expense_date']);
            $table->index('expense_category_id');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
