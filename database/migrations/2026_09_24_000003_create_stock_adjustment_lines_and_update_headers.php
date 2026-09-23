<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update stock_adjustments header table
        Schema::table('stock_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_adjustments', 'adjustment_date')) {
                $table->date('adjustment_date')->nullable()->after('date');
            }
            if (! Schema::hasColumn('stock_adjustments', 'journal_header_id')) {
                $table->foreignId('journal_header_id')->nullable()->constrained('journal_headers')->nullOnDelete()->after('notes');
            }
        });

        // 2. Create stock_adjustment_lines detail table
        if (! Schema::hasTable('stock_adjustment_lines')) {
            Schema::create('stock_adjustment_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->integer('system_qty');
                $table->integer('actual_qty');
                $table->integer('difference_qty');
                $table->decimal('unit_cost', 15, 4);
                $table->decimal('subtotal_cost', 15, 4);
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->index('stock_adjustment_id');
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');

        Schema::table('stock_adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('stock_adjustments', 'journal_header_id')) {
                $table->dropForeign(['journal_header_id']);
                $table->dropColumn('journal_header_id');
            }
            if (Schema::hasColumn('stock_adjustments', 'adjustment_date')) {
                $table->dropColumn('adjustment_date');
            }
        });
    }
};
