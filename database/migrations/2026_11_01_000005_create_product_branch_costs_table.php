<?php

use App\Support\Database\RollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_branch_costs')) {
            Schema::create('product_branch_costs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->nullable()->constrained('entities')->restrictOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->decimal('qty_on_hand', 18, 4)->default(0);
                $table->decimal('avg_unit_cost', 18, 4)->default(0);
                $table->decimal('inventory_value', 18, 2)->default(0);
                $table->dateTime('last_movement_at')->nullable();
                $table->unsignedBigInteger('version')->default(0);
                $table->timestamps();

                $table->unique(['branch_id', 'product_id'], 'uq_product_branch_costs');
            });
        }

        if (! Schema::hasTable('inventory_cost_movements')) {
            Schema::create('inventory_cost_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->nullable()->constrained('entities')->restrictOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->enum('movement_type', [
                    'receipt',
                    'sale',
                    'sales_return',
                    'purchase_return',
                    'transfer_in',
                    'transfer_out',
                    'adjustment',
                    'opening',
                ]);
                $table->string('source_type', 50);
                $table->unsignedBigInteger('source_id');
                $table->decimal('qty', 18, 4);
                $table->decimal('unit_cost', 18, 4);
                $table->decimal('value', 18, 2);
                $table->decimal('qty_after', 18, 4);
                $table->decimal('avg_cost_after', 18, 4);
                $table->decimal('value_after', 18, 2);
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['branch_id', 'product_id', 'id'], 'idx_icm_branch_product');
                $table->index(['source_type', 'source_id'], 'idx_icm_source');
            });
        }
    }

    public function down(): void
    {
        RollbackGuard::assertTableEmpty('inventory_cost_movements');
        RollbackGuard::assertTableEmpty('product_branch_costs');

        Schema::dropIfExists('inventory_cost_movements');
        Schema::dropIfExists('product_branch_costs');
    }
};
