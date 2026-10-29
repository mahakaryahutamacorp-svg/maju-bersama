<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('chart_of_accounts', 'entity_id')) {
            return;
        }

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->foreignId('entity_id')->nullable()->after('id')->constrained('entities')->restrictOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->enum('normal_balance', ['D', 'K'])->default('D');
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_contra')->default(false);
            $table->boolean('is_system')->default(false);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->restrictOnDelete();
            $table->enum('cash_flow_tag', ['cash', 'operating', 'investing', 'financing', 'none'])->default('none');
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropUnique('chart_of_accounts_code_unique');
            $table->unique(['entity_id', 'code'], 'uq_coa_entity_code');
            $table->unique('cash_register_id', 'uq_coa_cash_register');
            $table->index('code', 'idx_coa_code');
            $table->index(['type', 'is_active'], 'idx_coa_type_active');
            $table->index('cash_flow_tag', 'idx_coa_cash_flow_tag');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('chart_of_accounts', 'entity_id')) {
            return;
        }

        $this->removeDuplicateCodesOrFail();

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['entity_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['cash_register_id']);
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropUnique('uq_coa_entity_code');
            $table->dropUnique('uq_coa_cash_register');
            $table->dropIndex('idx_coa_code');
            $table->dropIndex('idx_coa_type_active');
            $table->dropIndex('idx_coa_cash_flow_tag');
            $table->unique('code');
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'entity_id',
                'level',
                'normal_balance',
                'is_postable',
                'is_contra',
                'is_system',
                'branch_id',
                'cash_register_id',
                'cash_flow_tag',
            ]);
        });
    }

    /**
     * Unique global `code` hanya bisa dipulihkan bila tidak ada kode ganda (salinan COA per entitas).
     * Penghapusan salinan wajib diminta eksplisit; baris yang masih direferensikan dilindungi FK.
     */
    private function removeDuplicateCodesOrFail(): void
    {
        $duplicateCodes = DB::table('chart_of_accounts')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code');

        if ($duplicateCodes->isEmpty()) {
            return;
        }

        if (! filter_var(env('LEDGER_V2_ROLLBACK_REMOVE_DUPLICATE_ACCOUNTS', false), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException(sprintf(
                'Rollback dibatalkan: %d kode akun ganda (salinan COA per entitas) mencegah pemulihan unique chart_of_accounts.code. '
                .'Setelah backup, set LEDGER_V2_ROLLBACK_REMOVE_DUPLICATE_ACCOUNTS=1 untuk menghapus salinan yang tidak direferensikan.',
                $duplicateCodes->count(),
            ));
        }

        foreach ($duplicateCodes as $code) {
            $keepId = DB::table('chart_of_accounts')->where('code', $code)->min('id');

            DB::table('chart_of_accounts')
                ->where('code', $code)
                ->where('id', '<>', $keepId)
                ->delete();
        }
    }
};
