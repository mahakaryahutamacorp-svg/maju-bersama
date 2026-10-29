<?php

use App\Support\Database\RollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journal_items')) {
            return;
        }

        Schema::create('journal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->restrictOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->restrictOnDelete();
            $table->string('memo', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['journal_entry_id', 'line_no'], 'uq_ji_line');
            $table->index(['account_id', 'branch_id', 'journal_entry_id'], 'idx_ji_account_branch');
            $table->index(['entity_id', 'account_id'], 'idx_ji_entity_account');
            $table->index(['customer_id', 'account_id'], 'idx_ji_customer');
            $table->index(['supplier_id', 'account_id'], 'idx_ji_supplier');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE journal_items ADD CONSTRAINT chk_ji_one_side CHECK (debit >= 0 AND credit >= 0 AND ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0)))');
        }
    }

    public function down(): void
    {
        RollbackGuard::assertTableEmpty('journal_items');

        Schema::dropIfExists('journal_items');
    }
};
