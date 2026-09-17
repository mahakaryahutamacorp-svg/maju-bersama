<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Warehouses represent physical storage locations within a branch.
     * One branch can have many warehouses (e.g., "Etalase Depan", "Gudang Belakang").
     * Stock inventory will eventually bind to warehouse_id in the next migration phase.
     */
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'code'], 'warehouses_branch_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
