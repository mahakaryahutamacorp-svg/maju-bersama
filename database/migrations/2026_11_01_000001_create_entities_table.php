<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertLedgerTriggersCanBeCreated();

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

    /**
     * Migrasi 12 membuat trigger. Dengan binary log aktif dan log_bin_trust_function_creators=0,
     * CREATE TRIGGER butuh SUPER (MySQL 1419). Gagal di sini mencegah migrasi 01–11 terpasang tanpa trigger.
     */
    private function assertLedgerTriggersCanBeCreated(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $variables = DB::selectOne('SELECT @@log_bin AS log_bin, @@log_bin_trust_function_creators AS trust_function_creators');

        if ($variables === null || (int) $variables->log_bin === 0 || (int) $variables->trust_function_creators === 1) {
            return;
        }

        $hasSuper = collect(DB::select('SHOW GRANTS'))
            ->flatMap(fn (object $row) => array_values((array) $row))
            ->contains(fn (string $grant) => preg_match('/^GRANT (ALL PRIVILEGES|[^`]*\bSUPER\b[^`]*) ON \*\.\* TO /', $grant) === 1);

        if (! $hasSuper) {
            throw new RuntimeException(
                'Migrasi ledger v2 dibatalkan sebelum mengubah apa pun: binary log aktif, log_bin_trust_function_creators=0, '
                .'dan user database tidak punya SUPER, sehingga trigger di migrasi 12 akan gagal (MySQL 1419). '
                .'Minta admin MySQL menjalankan: SET PERSIST log_bin_trust_function_creators = 1; lalu ulangi migrate.'
            );
        }
    }
};
