<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_mappings')) {
            return;
        }

        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->restrictOnDelete();
            $table->string('key', 60);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['entity_id', 'key', 'branch_id', 'cash_register_id'], 'uq_account_mappings_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};
