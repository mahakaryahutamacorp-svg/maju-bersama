<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->enum('type', ['AR', 'AP']);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->date('payment_date');
            $table->string('reference_number')->unique();
            $table->decimal('amount', 15, 4);
            $table->text('notes')->nullable();
            $table->foreignId('journal_header_id')->nullable()->constrained('journal_headers')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'type', 'payment_date']);
            $table->index('reference_number');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->decimal('allocated_amount', 15, 4);
            $table->timestamps();

            $table->index('payment_id');
            $table->index('sale_id');
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
