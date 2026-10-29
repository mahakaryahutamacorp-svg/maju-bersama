<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entities')) {
            return;
        }

        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('legal_name', 200);
            $table->string('npwp', 30)->nullable();
            $table->char('base_currency', 3)->default('IDR');
            $table->decimal('cash_shortage_auto_threshold', 18, 2)->default(10000);
            $table->decimal('cash_overage_auto_threshold', 18, 2)->default(10000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE entities ADD CONSTRAINT chk_entities_threshold CHECK (cash_shortage_auto_threshold >= 0 AND cash_overage_auto_threshold >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
