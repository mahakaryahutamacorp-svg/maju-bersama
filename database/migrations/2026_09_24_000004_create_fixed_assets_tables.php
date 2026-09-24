<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->date('purchase_date');
            $table->decimal('purchase_price', 15, 4);
            $table->decimal('salvage_value', 15, 4)->default(0);
            $table->integer('useful_life_months');
            $table->foreignId('asset_account_id')->constrained('chart_of_accounts');
            $table->foreignId('depreciation_account_id')->constrained('chart_of_accounts');
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
            $table->enum('status', ['ACTIVE', 'DISPOSED'])->default('ACTIVE');
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('purchase_date');
        });

        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('depreciation_date');
            $table->decimal('amount', 15, 4);
            $table->foreignId('journal_header_id')->nullable()->constrained('journal_headers')->nullOnDelete();
            $table->timestamps();

            $table->index(['fixed_asset_id', 'depreciation_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('fixed_assets');
    }
};
