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
        if (! Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('entry_number', 40)->unique('uq_je_number');
                $table->foreignId('entity_id')->nullable()->constrained('entities')->restrictOnDelete();
                $table->char('intercompany_group_id', 26)->nullable()->index('idx_je_intercompany');
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->date('entry_date');
                $table->dateTime('posted_at')->nullable();
                $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
                $table->string('source_type', 50);
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('source_event', 30)->default('post');
                $table->string('reference_number', 100)->nullable();
                $table->string('description', 500);
                $table->unsignedBigInteger('pos_session_id')->nullable()->index('idx_je_session');
                $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->decimal('total_debit', 18, 2)->default(0);
                $table->decimal('total_credit', 18, 2)->default(0);
                $table->char('currency_code', 3)->default('IDR');
                $table->decimal('exchange_rate', 15, 6)->default(1);
                $table->foreignId('legacy_journal_header_id')->nullable()->unique('uq_je_legacy')->constrained('journal_headers')->restrictOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['source_type', 'source_id', 'source_event'], 'uq_je_source');
                $table->index(['branch_id', 'entry_date', 'status'], 'idx_je_branch_date');
                $table->index(['entity_id', 'entry_date', 'status'], 'idx_je_entity_date');
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT chk_je_totals CHECK (total_debit >= 0 AND total_credit >= 0)');
            }
        }

        if (Schema::hasTable('inventory_cost_movements') && ! $this->hasJournalEntryForeignKey()) {
            Schema::table('inventory_cost_movements', function (Blueprint $table) {
                $table->foreign('journal_entry_id', 'fk_icm_journal_entry')->references('id')->on('journal_entries')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        RollbackGuard::assertTableEmpty('journal_entries');

        if (Schema::hasTable('inventory_cost_movements') && $this->hasJournalEntryForeignKey()) {
            Schema::table('inventory_cost_movements', function (Blueprint $table) {
                $table->dropForeign('fk_icm_journal_entry');
            });
        }

        Schema::dropIfExists('journal_entries');
    }

    private function hasJournalEntryForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('inventory_cost_movements'))
            ->contains(fn (array $foreignKey) => $foreignKey['columns'] === ['journal_entry_id']);
    }
};
