<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Dipakai di down() migrasi ledger v2: rollback ditolak bila tabel atau kolom
 * transaksional sudah berisi data, sehingga migrate:rollback tidak pernah
 * membuang data secara diam-diam.
 */
final class RollbackGuard
{
    public static function assertTableEmpty(string $table): void
    {
        if (Schema::hasTable($table) && DB::table($table)->exists()) {
            throw new RuntimeException(
                "Rollback dibatalkan: tabel {$table} sudah berisi data. Backup dan tangani datanya secara manual sebelum rollback."
            );
        }
    }

    /**
     * @param  list<string>  $columns
     */
    public static function assertColumnsEmpty(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column) && DB::table($table)->whereNotNull($column)->exists()) {
                throw new RuntimeException(
                    "Rollback dibatalkan: kolom {$table}.{$column} sudah berisi data. Backup dan tangani datanya secara manual sebelum rollback."
                );
            }
        }
    }
}
