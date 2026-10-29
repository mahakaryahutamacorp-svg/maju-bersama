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
        if (! Schema::hasTable('pos_sessions')) {
            Schema::create('pos_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_number', 40)->unique('uq_pos_session_number');
                $table->foreignId('entity_id')->nullable()->constrained('entities')->restrictOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('cash_register_id')->constrained('cash_registers')->restrictOnDelete();
                $table->foreignId('cash_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
                $table->dateTime('opened_at');
                $table->dateTime('closed_at')->nullable();
                $table->enum('status', ['open', 'closing', 'closed', 'pending_approval', 'reconciled'])->default('open');
                $table->decimal('opening_float', 18, 2)->default(0);
                $table->decimal('expected_cash', 18, 2)->nullable();
                $table->decimal('counted_cash', 18, 2)->nullable();
                $table->decimal('cash_difference', 18, 2)->nullable()->storedAs('counted_cash - expected_cash');
                $table->json('denomination_json')->nullable();
                $table->decimal('cash_sales_total', 18, 2)->default(0);
                $table->decimal('cash_refunds_total', 18, 2)->default(0);
                $table->decimal('ar_collections_total', 18, 2)->default(0);
                $table->decimal('cash_out_total', 18, 2)->default(0);
                $table->decimal('non_cash_total', 18, 2)->default(0);
                $table->foreignId('difference_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->enum('difference_resolution', [
                    'none',
                    'auto_expense',
                    'auto_other_income',
                    'suspense_pending',
                    'resolved_expense',
                    'resolved_employee_receivable',
                    'resolved_other_income',
                ])->default('none');
                $table->decimal('threshold_applied', 18, 2)->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('legacy_shift_id')->nullable()->unique('uq_pos_session_legacy_shift')->constrained('cash_register_shifts')->restrictOnDelete();
                $table->timestamps();
                $table->unsignedBigInteger('open_register_guard')->nullable()
                    ->storedAs("CASE WHEN status IN ('open', 'closing') THEN cash_register_id END");
                $table->unsignedBigInteger('open_cashier_guard')->nullable()
                    ->storedAs("CASE WHEN status IN ('open', 'closing') THEN cashier_id END");

                $table->unique('open_register_guard', 'uq_pos_one_open_per_register');
                $table->unique('open_cashier_guard', 'uq_pos_one_open_per_cashier');
                $table->index(['branch_id', 'status', 'opened_at'], 'idx_pos_session_branch');
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE pos_sessions ADD CONSTRAINT chk_pos_session_closed CHECK (status NOT IN ('closed', 'pending_approval', 'reconciled') OR (closed_at IS NOT NULL AND counted_cash IS NOT NULL AND expected_cash IS NOT NULL))");
            }
        }

        if (! $this->hasPosSessionForeignKey()) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->foreign('pos_session_id', 'fk_je_pos_session')->references('id')->on('pos_sessions')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        RollbackGuard::assertTableEmpty('pos_sessions');

        if ($this->hasPosSessionForeignKey()) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropForeign('fk_je_pos_session');
            });
        }

        Schema::dropIfExists('pos_sessions');
    }

    private function hasPosSessionForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('journal_entries'))
            ->contains(fn (array $foreignKey) => $foreignKey['columns'] === ['pos_session_id']);
    }
};
