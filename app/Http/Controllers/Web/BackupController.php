<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        try {
            $exitCode = Artisan::call('backup:run', [
                '--only-db' => true,
            ]);

            if ($exitCode !== 0) {
                $output = Artisan::output();
                Log::error('Backup failed: ' . $output);
                return redirect()->back()->with('error', 'Gagal membuat backup database. Silakan periksa log sistem.');
            }

            return redirect()->back()->with('success', 'Backup database berhasil dibuat!');
        } catch (\Throwable $e) {
            Log::error('Exception during backup generation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Terjadi kesalahan saat mem-backup database: ' . $e->getMessage());
        }
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
            Log::warning('Could not list files via storage disk: ' . $e->getMessage());
        }

        // Fallback checks directly in storage directories
        if (! $latestFilePath || ! file_exists($latestFilePath)) {
            $candidateDirs = [
                storage_path('app/' . $backupName),
                storage_path('app/private/' . $backupName),
                storage_path('app'),
                storage_path('app/private'),
            ];

            foreach ($candidateDirs as $dir) {
                if (File::isDirectory($dir)) {
                    $zipFiles = File::glob($dir . '/*.zip');
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
