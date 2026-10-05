<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class BackupController extends Controller
{
    /**
     * Generate a new database backup.
     */
    public function generate(Request $request): RedirectResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $spatieError = null;

        // 1. Try Spatie backup:run first (if not in-memory SQLite)
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            try {
                $exitCode = Artisan::call('backup:run', [
                    '--only-db' => true,
                ]);

                if ($exitCode === 0) {
                    return redirect()->back()->with('success', 'Backup database berhasil dibuat!');
                }

                $spatieError = trim(Artisan::output());
                Log::warning('Spatie backup:run exited with non-zero code ('.$exitCode.'): '.$spatieError.'. Attempting native database backup fallback.');
            } catch (\Throwable $e) {
                $spatieError = $e->getMessage();
                Log::warning('Spatie backup:run threw exception: '.$spatieError.'. Attempting native database backup fallback.');
            }
        }

        // 2. Automatic Fallback: Native PHP Database Dumper (Works without mysqldump binary or proc_open restrictions)
        try {
            $this->performNativeBackup();

            return redirect()->back()->with('success', 'Backup database berhasil dibuat!');
        } catch (\Throwable $fallbackException) {
            Log::error('Native backup fallback failed: '.$fallbackException->getMessage(), [
                'spatie_error' => $spatieError,
                'trace' => $fallbackException->getTraceAsString(),
            ]);

            $detail = $spatieError ? " (Error sistem: {$spatieError})" : '';

            return redirect()->back()->with('error', 'Gagal membuat backup database: '.$fallbackException->getMessage().$detail);
        }
    }

    /**
     * Perform native database dump via PDO/DB and archive into ZIP.
     */
    protected function performNativeBackup(): void
    {
        $backupName = config('backup.backup.name', config('app.name', 'Laravel'));
        $diskName = config('backup.backup.destination.disks.0', 'local');
        $disk = Storage::disk($diskName);

        $tempDir = storage_path('framework/cache/db_backup_'.uniqid('', true));
        File::ensureDirectoryExists($tempDir);

        $sqlPath = $tempDir.'/database.sql';
        $handle = fopen($sqlPath, 'w');
        if (! $handle) {
            throw new \RuntimeException('Tidak dapat membuat file SQL sementara.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $dbPath = $connection->getDatabaseName();
            if ($dbPath !== ':memory:' && file_exists($dbPath)) {
                fclose($handle);
                copy($dbPath, $sqlPath);
            } else {
                $pdo = $connection->getPdo();
                fwrite($handle, "-- Maju Bersama SQLite Dump\n");
                $tables = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($tables as $t) {
                    if (! empty($t['sql'])) {
                        fwrite($handle, $t['sql'].";\n");
                        $stmt = $pdo->query("SELECT * FROM \"{$t['name']}\"");
                        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                            $cols = array_map(fn ($col) => "\"{$col}\"", array_keys($row));
                            $vals = array_map(fn ($val) => $val === null ? 'NULL' : $pdo->quote($val), array_values($row));
                            fwrite($handle, "INSERT INTO \"{$t['name']}\" (".implode(', ', $cols).') VALUES ('.implode(', ', $vals).");\n");
                        }
                        fwrite($handle, "\n");
                    }
                }
                fclose($handle);
            }
        } else {
            $pdo = $connection->getPdo();

            fwrite($handle, "-- Maju Bersama Database Backup\n");
            fwrite($handle, '-- Generated: '.date('Y-m-d H:i:s')."\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
            fwrite($handle, "SET NAMES utf8mb4;\n\n");

            $tables = [];
            if ($driver === 'mysql' || $driver === 'mariadb') {
                $rawTables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_NUM);
                foreach ($rawTables as $row) {
                    $tables[] = $row[0];
                }
            } else {
                $tables = $connection->getDoctrineSchemaManager()->listTableNames();
            }

            foreach ($tables as $table) {
                // Table structure
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
                if (! empty($createRow[1])) {
                    fwrite($handle, $createRow[1].";\n\n");
                }

                // Table data
                $statement = $pdo->query("SELECT * FROM `{$table}`");
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $cols = array_map(fn ($col) => "`{$col}`", array_keys($row));
                    $vals = array_map(function ($val) use ($pdo) {
                        return $val === null ? 'NULL' : $pdo->quote($val);
                    }, array_values($row));

                    fwrite($handle, "INSERT INTO `{$table}` (".implode(', ', $cols).') VALUES ('.implode(', ', $vals).");\n");
                }
                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
        }

        // Create ZIP archive
        $timestamp = date('Y-m-d-H-i-s');
        $zipFileName = "{$backupName}-{$timestamp}.zip";
        $zipFilePath = $tempDir.'/'.$zipFileName;

        if (! class_exists('ZipArchive')) {
            throw new \RuntimeException('Ekstensi PHP ZipArchive tidak aktif pada server.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat arsip ZIP.');
        }

        $zip->addFile($sqlPath, 'db-dumps/'.basename($sqlPath));
        $zip->close();

        // Store to backup disk
        $stream = fopen($zipFilePath, 'r');
        if (! $stream) {
            throw new \RuntimeException('Gagal membaca arsip ZIP hasil backup.');
        }
        $disk->put("{$backupName}/{$zipFileName}", $stream);
        fclose($stream);

        // Cleanup temporary directory
        File::deleteDirectory($tempDir);
    }

    /**
     * Download the latest database backup file.
     */
    public function download(): BinaryFileResponse|RedirectResponse
    {
        $backupName = config('backup.backup.name', config('app.name', 'Laravel'));
        $diskName = config('backup.backup.destination.disks.0', 'local');
        $disk = Storage::disk($diskName);

        $latestFilePath = null;
        $latestTime = 0;

        // Check via Storage disk
        try {
            $files = $disk->allFiles($backupName);
            foreach ($files as $file) {
                if (str_ends_with(strtolower($file), '.zip')) {
                    $mtime = $disk->lastModified($file);
                    if ($mtime > $latestTime) {
                        $latestTime = $mtime;
                        $latestFilePath = $disk->path($file);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not list files via storage disk: '.$e->getMessage());
        }

        // Fallback checks directly in storage directories
        if (! $latestFilePath || ! file_exists($latestFilePath)) {
            $candidateDirs = [
                storage_path('app/'.$backupName),
                storage_path('app/private/'.$backupName),
                storage_path('app'),
                storage_path('app/private'),
            ];

            foreach ($candidateDirs as $dir) {
                if (File::isDirectory($dir)) {
                    $zipFiles = File::glob($dir.'/*.zip');
                    foreach ($zipFiles as $file) {
                        $mtime = File::lastModified($file);
                        if ($mtime > $latestTime) {
                            $latestTime = $mtime;
                            $latestFilePath = $file;
                        }
                    }
                }
            }
        }

        if (! $latestFilePath || ! file_exists($latestFilePath)) {
            return redirect()->back()->with('error', 'Belum ada file backup database yang tersedia untuk diunduh.');
        }

        return response()->download($latestFilePath, basename($latestFilePath));
    }
}
