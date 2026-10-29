<?php

use App\Support\Database\RollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RESOLUTIONS = ['expense_6810', 'employee_receivable_1135', 'other_income_4150'];

    public function up(): void
    {
        if (Schema::hasTable('cash_difference_approvals')) {
            return;
        }

        Schema::create('cash_difference_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('pos_session_id')->constrained('pos_sessions')->restrictOnDelete();
            $table->enum('direction', ['shortage', 'overage']);
            $table->decimal('amount', 18, 2);
            $table->decimal('threshold_applied', 18, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'resolved'])->default('pending');
            $table->enum('requested_resolution', self::RESOLUTIONS)->nullable();
            $table->enum('approved_resolution', self::RESOLUTIONS)->nullable();
            $table->foreignId('employee_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('requested_at');
            $table->text('reason');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('suspense_journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('resolution_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('resubmission_of_id')->nullable()->constrained('cash_difference_approvals')->restrictOnDelete();
            $table->timestamps();

            $table->index(['entity_id', 'branch_id', 'status'], 'idx_cda_status');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cash_difference_approvals ADD CONSTRAINT chk_cda_amount CHECK (amount > 0)');
            DB::statement("ALTER TABLE cash_difference_approvals ADD CONSTRAINT chk_cda_employee CHECK (approved_resolution IS NULL OR approved_resolution <> 'employee_receivable_1135' OR employee_user_id IS NOT NULL)");
            DB::statement("ALTER TABLE cash_difference_approvals ADD CONSTRAINT chk_cda_shortage CHECK (direction = 'overage' OR approved_resolution IS NULL OR approved_resolution IN ('expense_6810', 'employee_receivable_1135'))");
            DB::statement("ALTER TABLE cash_difference_approvals ADD CONSTRAINT chk_cda_overage CHECK (direction = 'shortage' OR approved_resolution IS NULL OR approved_resolution = 'other_income_4150')");
            DB::statement("ALTER TABLE cash_difference_approvals ADD CONSTRAINT chk_cda_resolved CHECK (status <> 'resolved' OR (decided_by IS NOT NULL AND resolution_journal_entry_id IS NOT NULL))");
        }
    }

    public function down(): void
    {
        RollbackGuard::assertTableEmpty('cash_difference_approvals');

        Schema::dropIfExists('cash_difference_approvals');
    }
};
