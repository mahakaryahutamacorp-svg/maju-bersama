<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\StockAdjustment;
use App\Services\OpeningBalanceService;
use App\Services\StockAdjustmentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah dokumen stok opname yang seharusnya input stok awal. Stok tidak berubah; hanya
 * catatan dokumen dan jurnalnya yang disamakan dengan hasil menu Input Stok Awal.
 */
class ConvertOpnameToOpeningStock extends Command
{
    private const JOURNAL_MODES = ['auto', 'modal', 'none'];

    protected $signature = 'stock:opname-to-opening
        {reference?* : Nomor dokumen stok opname (SA-...). Tanpa nomor, tampilkan daftar dokumen stok opname}
        {--branch= : Kode cabang untuk menyaring daftar}
        {--journal=auto : auto (ikuti menu Input Stok Awal), modal (Cr 3110 Modal Awal), atau none (tanpa jurnal)}
        {--apply : Tulis perubahan. Tanpa opsi ini hanya simulasi}';

    protected $description = 'Ubah dokumen stok opname yang salah input menjadi input stok awal tanpa mengubah stok';

    public function handle(): int
    {
        $journalMode = (string) $this->option('journal');
        if (! in_array($journalMode, self::JOURNAL_MODES, true)) {
            $this->error('Opsi --journal harus auto, modal, atau none.');

            return self::INVALID;
        }

        $references = array_map('strtoupper', (array) $this->argument('reference'));
        if ($references === []) {
            $this->listOpnameDocuments();

            return self::SUCCESS;
        }

        $adjustments = StockAdjustment::withoutGlobalScopes()
            ->with(['branch:id,code,name', 'journal.journalLines.chartOfAccount'])
            ->withCount('lines')
            ->whereIn('reference_number', $references)
            ->get()
            ->keyBy('reference_number');

        $missing = array_diff($references, $adjustments->keys()->all());
        if ($missing !== []) {
            $this->error('Dokumen tidak ditemukan: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $alreadyOpening = $adjustments->filter(fn (StockAdjustment $adjustment) => $this->isOpeningStock($adjustment));
        if ($alreadyOpening->isNotEmpty()) {
            $this->error('Sudah tercatat sebagai input stok awal: '.$alreadyOpening->keys()->implode(', '));

            return self::FAILURE;
        }

        $plans = $adjustments->map(fn (StockAdjustment $adjustment) => [
            'adjustment' => $adjustment,
            'journal' => $this->resolveJournalMode($adjustment, $journalMode),
        ]);

        $this->info($this->option('apply') ? 'Mode: TULIS PERUBAHAN' : 'Mode: SIMULASI (tambahkan --apply untuk menulis perubahan)');
        $this->table(
            ['Dokumen', 'Cabang', 'Tanggal', 'Produk', 'Nilai lebih', 'Nilai kurang', 'Jurnal sekarang', 'Jurnal sesudah'],
            $plans->map(fn (array $plan) => [
                $plan['adjustment']->reference_number,
                $plan['adjustment']->branch?->code,
                $plan['adjustment']->date?->format('Y-m-d'),
                $plan['adjustment']->lines_count,
                number_format((float) $plan['adjustment']->total_gain_value, 0, ',', '.'),
                number_format((float) $plan['adjustment']->total_loss_value, 0, ',', '.'),
                $this->describeCurrentJournal($plan['adjustment']),
                $this->describeTargetJournal($plan['adjustment'], $plan['journal']),
            ])->values()->all(),
        );
        $this->line('Jumlah stok tidak berubah.');

        if (! $this->option('apply')) {
            return self::SUCCESS;
        }

        if (! $this->confirm("{$plans->count()} dokumen stok opname akan diubah menjadi input stok awal. Lanjutkan?", true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plans): void {
            foreach ($plans as $plan) {
                $this->convert($plan['adjustment'], $plan['journal']);
            }
        });

        $this->info("Selesai: {$plans->count()} dokumen sekarang tercatat sebagai input stok awal.");

        return self::SUCCESS;
    }

    private function listOpnameDocuments(): void
    {
        $branchCode = $this->option('branch') ? strtoupper((string) $this->option('branch')) : null;

        $documents = StockAdjustment::withoutGlobalScopes()
            ->with('branch:id,code')
            ->withCount('lines')
            ->where(fn (Builder $query) => $query->whereNull('notes')
                ->orWhere('notes', 'not like', StockAdjustmentService::OPENING_STOCK_NOTE.'%'))
            ->when($branchCode, fn (Builder $query) => $query->whereIn(
                'branch_id',
                Branch::query()->where('code', $branchCode)->select('id'),
            ))
            ->latest('id')
            ->limit(50)
            ->get();

        if ($documents->isEmpty()) {
            $this->info('Tidak ada dokumen stok opname.');

            return;
        }

        $this->table(
            ['Dokumen', 'Cabang', 'Tanggal', 'Dibuat', 'Produk', 'Nilai lebih', 'Nilai kurang', 'Catatan'],
            $documents->map(fn (StockAdjustment $adjustment) => [
                $adjustment->reference_number,
                $adjustment->branch?->code,
                $adjustment->date?->format('Y-m-d'),
                $adjustment->created_at?->format('Y-m-d H:i'),
                $adjustment->lines_count,
                number_format((float) $adjustment->total_gain_value, 0, ',', '.'),
                number_format((float) $adjustment->total_loss_value, 0, ',', '.'),
                mb_strimwidth((string) $adjustment->notes, 0, 40, '...'),
            ])->all(),
        );
        $this->line('Jalankan ulang dengan nomor dokumen, misalnya: php artisan stock:opname-to-opening SA-20261108-ABCDE');
    }

    private function convert(StockAdjustment $adjustment, string $journalMode): void
    {
        $notes = trim(StockAdjustmentService::OPENING_STOCK_NOTE.' '.(string) $adjustment->notes);
        $adjustment->lines()->update(['reason' => StockAdjustmentService::OPENING_STOCK_NOTE]);

        $journal = $adjustment->journal;
        if ($journal !== null && $journalMode === 'none') {
            $journal->delete();
            $adjustment->journal_header_id = null;
        } elseif ($journal !== null) {
            $equity = ChartOfAccount::firstOrCreate(
                ['code' => StockAdjustmentService::ACCOUNT_OPENING_EQUITY],
                ['name' => 'Modal Awal', 'type' => 'equity'],
            );

            foreach ($journal->journalLines as $line) {
                if ($this->isAdjustmentRevenueLine($line)) {
                    $line->update(['chart_of_account_id' => $equity->id, 'memo' => 'Modal awal berupa persediaan barang']);
                } elseif ($line->chartOfAccount?->code === StockAdjustmentService::ACCOUNT_INVENTORY && (float) $line->debit > 0) {
                    $line->update(['memo' => 'Persediaan awal hasil hitung fisik']);
                }
            }

            $suffix = $notes !== StockAdjustmentService::OPENING_STOCK_NOTE ? " - {$notes}" : '';
            $journal->update(['description' => StockAdjustmentService::OPENING_STOCK_NOTE." #{$adjustment->reference_number}{$suffix}"]);
        }

        $adjustment->notes = $notes;
        $adjustment->save();
    }

    private function resolveJournalMode(StockAdjustment $adjustment, string $journalMode): string
    {
        if ($journalMode !== 'auto') {
            return $journalMode;
        }

        return OpeningBalanceService::hasInventoryInOpeningBalance((int) $adjustment->branch_id) ? 'none' : 'modal';
    }

    private function describeCurrentJournal(StockAdjustment $adjustment): string
    {
        $lines = $adjustment->journal?->journalLines;
        if ($lines === null) {
            return 'Tidak ada';
        }

        return $this->describeAccounts($lines);
    }

    private function describeTargetJournal(StockAdjustment $adjustment, string $journalMode): string
    {
        if ($adjustment->journal === null) {
            return 'Tidak ada';
        }

        if ($journalMode === 'none') {
            return 'Dihapus (persediaan sudah di Saldo Awal)';
        }

        return str_replace(StockAdjustmentService::ACCOUNT_REVENUE_ADJUSTMENT, StockAdjustmentService::ACCOUNT_OPENING_EQUITY, $this->describeAccounts($adjustment->journal->journalLines));
    }

    /**
     * @param  Collection<int, JournalLine>  $lines
     */
    private function describeAccounts(Collection $lines): string
    {
        $debits = $lines->filter(fn (JournalLine $line) => (float) $line->debit > 0)->map(fn (JournalLine $line) => $line->chartOfAccount?->code)->unique()->implode('+');
        $credits = $lines->filter(fn (JournalLine $line) => (float) $line->credit > 0)->map(fn (JournalLine $line) => $line->chartOfAccount?->code)->unique()->implode('+');

        return "Dr {$debits} / Cr {$credits}";
    }

    private function isAdjustmentRevenueLine(JournalLine $line): bool
    {
        return $line->chartOfAccount?->code === StockAdjustmentService::ACCOUNT_REVENUE_ADJUSTMENT && (float) $line->credit > 0;
    }

    private function isOpeningStock(StockAdjustment $adjustment): bool
    {
        return str_starts_with((string) $adjustment->notes, StockAdjustmentService::OPENING_STOCK_NOTE);
    }
}
