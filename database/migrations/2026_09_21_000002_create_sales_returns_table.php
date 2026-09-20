<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cash_register_shift_id')->nullable()->constrained('cash_register_shifts')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->date('return_date');
            $table->string('reference_number')->unique();
            $table->string('refund_method')->default('cash'); // cash, transfer
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->enum('status', ['pending', 'completed'])->default('completed');
            $table->text('reason')->nullable();
            $table->foreignId('journal_header_id')->nullable()->constrained('journal_headers')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'return_date']);
            $table->index('sale_id');
            $table->index('cash_register_shift_id');
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2); // Harga refund satuan
            $table->decimal('unit_cost', 15, 2)->default(0); // HPP satuan saat retur
            $table->decimal('subtotal', 15, 2);
            $table->decimal('subtotal_cost', 15, 2)->default(0);
            $table->timestamps();

            $table->index('sales_return_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
