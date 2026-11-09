<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Memindahkan kekurangan input stok awal dari beban laba rugi ke modal awal.
 * Jumlah barang dan nilai akun persediaan tidak berubah.
 */
class ReclassifyOpeningStockLoss extends Command
{
    protected $signature = 'stock:opening-loss-to-modal
        {--apply : Tulis perubahan. Tanpa opsi ini hanya simulasi}';

    protected $description = 'Pindahkan kekurangan input stok awal dari akun 5120 ke modal awal 3110 tanpa mengubah stok';

    public function handle(): int
    {
        $equity = ChartOfAccount::firstOrCreate(
            ['code' => StockAdjustmentService::ACCOUNT_OPENING_EQUITY],
            ['name' => 'Modal Awal', 'type' => 'equity'],
        );

        $plans = StockAdjustment::withoutGlobalScopes()
            ->with(['branch:id,code,name', 'journal.journalLines.chartOfAccount'])
            ->where('notes', 'like', StockAdjustmentService::OPENING_STOCK_NOTE.'%')
            ->whereNotNull('journal_header_id')
            ->orderBy('id')
            ->get()
            ->map(function (StockAdjustment $adjustment) {
                $lines = $adjustment->journal?->journalLines
                    ->filter(fn (JournalLine $line) => $this->isExpenseLossLine($line))
                    ->values() ?? collect();

                return ['adjustment' => $adjustment, 'lines' => $lines];
            })
            ->filter(fn (array $plan) => $plan['lines']->isNotEmpty())
            ->values();

        if ($plans->isEmpty()) {
            $this->info('Tidak ada kekurangan input stok awal yang masih masuk ke beban.');

            return self::SUCCESS;
        }

        $total = $plans->sum(fn (array $plan) => $plan['lines']->sum(fn (JournalLine $line) => (float) $line->debit));

        $this->info($this->option('apply') ? 'Mode: TULIS PERUBAHAN' : 'Mode: SIMULASI (tambahkan --apply untuk menulis perubahan)');
        $this->table(
            ['Dokumen', 'Cabang', 'Tanggal', 'Kekurangan', 'Jurnal sekarang', 'Jurnal sesudah'],
            $plans->map(fn (array $plan) => [
                $plan['adjustment']->reference_number,
                $plan['adjustment']->branch?->code,
                $plan['adjustment']->date?->format('Y-m-d'),
                number_format($plan['lines']->sum(fn (JournalLine $line) => (float) $line->debit), 0, ',', '.'),
                'Dr 5120 / Cr 1210',
                'Dr 3110 / Cr 1210',
            ])->all(),
        );
        $this->line('Total yang pindah ke modal awal: Rp '.number_format($total, 0, ',', '.'));
        $this->line('Jumlah stok dan saldo persediaan 1210 tidak berubah. Koreksi opname biasa tetap di beban 5120.');

        if (! $this->option('apply')) {
            return self::SUCCESS;
        }

        if (! $this->confirm("{$plans->count()} jurnal input stok awal akan dipindahkan dari beban ke modal awal. Lanjutkan?", true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plans, $equity): void {
            foreach ($plans as $plan) {
                foreach ($plan['lines'] as $line) {
                    $line->update([
                        'chart_of_account_id' => $equity->id,
                        'memo' => 'Pengurangan modal awal sesuai stok fisik',
                    ]);
                }
            }
        });

        $this->info("Selesai: {$plans->count()} jurnal kekurangan input stok awal sekarang mengurangi modal awal.");

        return self::SUCCESS;
    }

    private function isExpenseLossLine(JournalLine $line): bool
    {
        return $line->chartOfAccount?->code === StockAdjustmentService::ACCOUNT_EXPENSE_ADJUSTMENT
            && (float) $line->debit > 0;
    }
}
