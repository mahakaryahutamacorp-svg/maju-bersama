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
        if (Schema::hasTable('accounting_periods')) {
            return;
        }

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['open', 'soft_closed', 'closed'])->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'branch_id', 'period_start'], 'uq_accounting_periods');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE accounting_periods ADD CONSTRAINT chk_accounting_periods_range CHECK (period_end >= period_start)');
        }
    }

    public function down(): void
    {
        RollbackGuard::assertTableEmpty('accounting_periods');

        Schema::dropIfExists('accounting_periods');
    }
};
