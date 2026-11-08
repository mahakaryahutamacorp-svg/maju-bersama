<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Menghapus transaksi kasir uji coba dan menolkan stok seluruh gudang/cabang.
 *
 * Transaksi kasir dikenali dari baris barangnya (sale_items). Piutang hasil pencatatan manual
 * (import:piutang-awal) disimpan di tabel sales tanpa baris barang, sehingga tidak ikut terhapus.
 * Produk, harga modal, harga jual, karyawan, pelanggan, pembelian, dan biaya tidak disentuh.
 */
class ResetTrialTransactions extends Command
{
    protected $signature = 'data:reset-uji-coba
                            {--execute : Simpan perubahan. Tanpa opsi ini perintah hanya simulasi dan semua perubahan dibatalkan}
                            {--until= : Hanya transaksi kasir yang dibuat sampai tanggal ini (YYYY-MM-DD)}
                            {--keep-unpaid : Pertahankan transaksi kasir tempo yang masih punya sisa tagihan}
                            {--keep-shifts : Jangan hapus riwayat shift kasir}
                            {--force : Lewati konfirmasi saat --execute}';

    protected $description = 'Hapus transaksi kasir uji coba dan nolkan stok semua gudang & cabang (produk, harga, karyawan, dan piutang manual tetap utuh)';

    private const CHUNK = 500;

    public function handle(): int
    {
        $until = $this->option('until');
        if ($until !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $until)) {
            $this->error('Format --until harus YYYY-MM-DD.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $this->alert($execute ? 'RESET DATA UJI COBA (EKSEKUSI)' : 'RESET DATA UJI COBA (SIMULASI)');

        if ($execute && ! $this->option('force')
            && ! $this->confirm('Transaksi kasir uji coba akan dihapus permanen dan stok semua cabang menjadi 0. Lanjutkan?')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $protectedBefore = $this->protectedSnapshot();

        DB::beginTransaction();

        try {
            $report = $this->purge($until, (bool) $this->option('keep-unpaid'), (bool) $this->option('keep-shifts'));

            $protectedAfter = $this->protectedSnapshot();
            if ($protectedAfter !== $protectedBefore) {
                throw new RuntimeException('Data yang dilindungi ikut berubah. Semua perubahan dibatalkan.');
            }

            $report['inventory_balance'] = $this->inventoryAccountBalance();

            $execute ? DB::commit() : DB::rollBack();
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->renderReport($report, $protectedAfter);

        if ($execute) {
            $this->info('Selesai. Stok semua cabang sekarang 0 dan siap diinput ulang lewat menu Input Stok Awal.');
        } else {
            $this->warn('SIMULASI: tidak ada data yang berubah. Jalankan ulang dengan --execute untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string,mixed>
     */
    private function purge(?string $until, bool $keepUnpaid, bool $keepShifts): array
    {
        $sales = $this->targetSales($until, $keepUnpaid);
        $saleIds = $sales->pluck('id')->map(fn ($id) => (int) $id)->all();
        $receipts = $sales->pluck('receipt_number')->filter()->map(fn ($receipt) => (string) $receipt)->all();

        $this->assertNotLinkedToLedgerV2($saleIds);

        $returns = $this->targetReturns($saleIds, $until);
        $returnIds = $returns->pluck('id')->map(fn ($id) => (int) $id)->all();
        $journalIds = $returns->pluck('journal_header_id')->filter()->map(fn ($id) => (int) $id)->all();

        [$paymentIds, $mixedPaymentCount] = $this->paymentsOwnedBySales($saleIds);
        $journalIds = array_merge(
            $journalIds,
            $this->pluckIn('payments', 'id', $paymentIds, 'journal_header_id'),
            $this->pluckIn('journal_headers', 'reference_number', $receipts, 'id'),
        );
        $journalIds = array_values(array_unique(array_map('intval', array_filter($journalIds))));

        $this->assertJournalsNotMigratedToLedgerV2($journalIds);

        $report = [
            'sales' => count($saleIds),
            'sales_value' => (float) $sales->sum('total_amount'),
            'sales_unpaid' => $sales->filter(fn ($sale) => round((float) $sale->total_amount - (float) $sale->paid_amount, 2) > 0)->count(),
            'sales_first' => $sales->min('created_at'),
            'sales_last' => $sales->max('created_at'),
            'returns' => count($returnIds),
            'payments' => count($paymentIds),
            'mixed_payments' => $mixedPaymentCount,
            'journals' => count($journalIds),
        ];

        $this->deleteIn('sales_return_items', 'sales_return_id', $returnIds);
        $this->deleteIn('sales_returns', 'id', $returnIds);
        $this->deleteIn('payment_allocations', 'sale_id', $saleIds);
        $this->deleteIn('payments', 'id', $paymentIds);
        $this->deleteIn('sale_items', 'sale_id', $saleIds);
        $this->deleteIn('sales', 'id', $saleIds);
        $this->deleteIn('journal_lines', 'journal_header_id', $journalIds);
        $this->deleteIn('journal_headers', 'id', $journalIds);

        $report['shifts'] = $keepShifts ? 0 : $this->deleteUnusedShifts($until);

        return $report + $this->zeroStock();
    }

    private function targetSales(?string $until, bool $keepUnpaid): Collection
    {
        return DB::table('sales')
            ->whereExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('sale_items')
                ->whereColumn('sale_items.sale_id', 'sales.id'))
            ->when($until, fn (Builder $query) => $query->whereDate('sales.created_at', '<=', $until))
            ->when($keepUnpaid, fn (Builder $query) => $query
                ->whereRaw('ROUND(COALESCE(total_amount, 0) - COALESCE(paid_amount, 0), 2) <= 0'))
            ->get(['id', 'receipt_number', 'total_amount', 'paid_amount', 'created_at']);
    }

    /**
     * Retur dari struk yang dihapus, ditambah retur kasir yang tidak tertaut struk mana pun.
     *
     * @param  array<int,int>  $saleIds
     */
    private function targetReturns(array $saleIds, ?string $until): Collection
    {
        if (! Schema::hasTable('sales_returns')) {
            return collect();
        }

        $linked = collect();
        foreach (array_chunk($saleIds, self::CHUNK) as $chunk) {
            $linked = $linked->merge(DB::table('sales_returns')->whereIn('sale_id', $chunk)->get(['id', 'journal_header_id']));
        }

        $unlinked = DB::table('sales_returns')
            ->whereNull('sale_id')
            ->when($until, fn (Builder $query) => $query->whereDate('return_date', '<=', $until))
            ->get(['id', 'journal_header_id']);

        return $linked->merge($unlinked)->unique('id')->values();
    }

    /**
     * Pembayaran yang seluruh alokasinya hanya ke struk yang dihapus ikut dihapus. Pembayaran yang
     * juga melunasi piutang lain dipertahankan; hanya alokasinya ke struk uji coba yang dilepas.
     *
     * @param  array<int,int>  $saleIds
     * @return array{0:array<int,int>,1:int}
     */
    private function paymentsOwnedBySales(array $saleIds): array
    {
        if (! Schema::hasTable('payment_allocations') || $saleIds === []) {
            return [[], 0];
        }

        $touched = array_values(array_unique($this->pluckIn('payment_allocations', 'sale_id', $saleIds, 'payment_id')));
        $targetLookup = array_fill_keys($saleIds, true);
        $owned = [];
        $mixed = 0;

        foreach (array_chunk($touched, self::CHUNK) as $chunk) {
            $allocations = DB::table('payment_allocations')->whereIn('payment_id', $chunk)->get(['payment_id', 'sale_id'])->groupBy('payment_id');

            foreach ($allocations as $paymentId => $rows) {
                $onlyTrial = $rows->every(fn ($row) => $row->sale_id !== null && isset($targetLookup[(int) $row->sale_id]));
                if ($onlyTrial) {
                    $owned[] = (int) $paymentId;
                } else {
                    $mixed++;
                }
            }
        }

        return [$owned, $mixed];
    }

    private function deleteUnusedShifts(?string $until): int
    {
        if (! Schema::hasTable('cash_register_shifts')) {
            return 0;
        }

        $query = DB::table('cash_register_shifts')
            ->when($until, fn (Builder $query) => $query->whereDate('opened_at', '<=', $until))
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('sales')
                ->whereColumn('sales.cash_register_shift_id', 'cash_register_shifts.id'));

        if (Schema::hasTable('sales_returns')) {
            $query->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('sales_returns')
                ->whereColumn('sales_returns.cash_register_shift_id', 'cash_register_shifts.id'));
        }

        if (Schema::hasTable('pos_sessions') && Schema::hasColumn('pos_sessions', 'legacy_shift_id')) {
            $query->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('pos_sessions')
                ->whereColumn('pos_sessions.legacy_shift_id', 'cash_register_shifts.id'));
        }

        return $query->delete();
    }

    /**
     * @return array{inventory_rows:int,product_rows:int,cost_rows:int}
     */
    private function zeroStock(): array
    {
        $now = now();

        $inventoryRows = Schema::hasTable('inventories')
            ? DB::table('inventories')->where('quantity', '!=', 0)->update(['quantity' => 0, 'updated_at' => $now])
            : 0;

        $productRows = DB::table('products')->where('stock', '!=', 0)->update(['stock' => 0, 'updated_at' => $now]);

        $costRows = Schema::hasTable('product_branch_costs')
            ? DB::table('product_branch_costs')
                ->where(fn (Builder $query) => $query->where('qty_on_hand', '!=', 0)->orWhere('inventory_value', '!=', 0))
                ->update(['qty_on_hand' => 0, 'inventory_value' => 0, 'updated_at' => $now])
            : 0;

        return ['inventory_rows' => $inventoryRows, 'product_rows' => $productRows, 'cost_rows' => $costRows];
    }

    /**
     * @param  array<int,int>  $saleIds
     */
    private function assertNotLinkedToLedgerV2(array $saleIds): void
    {
        if (! Schema::hasColumn('sales', 'journal_entry_id')) {
            return;
        }

        foreach (array_chunk($saleIds, self::CHUNK) as $chunk) {
            $linked = DB::table('sales')
                ->whereIn('id', $chunk)
                ->where(fn (Builder $query) => $query->whereNotNull('journal_entry_id')->orWhereNotNull('pos_session_id'))
                ->exists();

            if ($linked) {
                throw new RuntimeException('Ada transaksi kasir yang sudah tercatat di ledger v2 (journal_entries). Jurnal posted tidak bisa dihapus; reset dibatalkan.');
            }
        }
    }

    /**
     * @param  array<int,int>  $journalIds
     */
    private function assertJournalsNotMigratedToLedgerV2(array $journalIds): void
    {
        if (! Schema::hasTable('journal_entries') || ! Schema::hasColumn('journal_entries', 'legacy_journal_header_id')) {
            return;
        }

        if ($this->pluckIn('journal_entries', 'legacy_journal_header_id', $journalIds, 'id') !== []) {
            throw new RuntimeException('Sebagian jurnal kasir sudah disalin ke ledger v2 (journal_entries.legacy_journal_header_id). Reset dibatalkan.');
        }
    }

    /**
     * Ringkasan data yang wajib tetap utuh. Bila nilainya berubah setelah penghapusan, transaksi dibatalkan.
     *
     * @return array<string,string>
     */
    private function protectedSnapshot(): array
    {
        $manualReceivables = DB::table('sales')
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('sale_items')
                ->whereColumn('sale_items.sale_id', 'sales.id'))
            ->selectRaw('COUNT(*) as total_rows, COALESCE(SUM(total_amount), 0) as total_amount, COALESCE(SUM(paid_amount), 0) as paid_amount')
            ->first();

        $products = DB::table('products')
            ->selectRaw('COUNT(*) as total_rows, COALESCE(SUM(purchase_price), 0) as purchase_total, COALESCE(SUM(selling_price), 0) as selling_total')
            ->first();

        $snapshot = [
            'Produk' => (string) $products->total_rows,
            'Total harga modal' => number_format((float) $products->purchase_total, 2, '.', ''),
            'Total harga jual' => number_format((float) $products->selling_total, 2, '.', ''),
            'Karyawan (user)' => (string) DB::table('users')->count(),
            'Pelanggan' => Schema::hasTable('customers') ? (string) DB::table('customers')->count() : '0',
            'Piutang manual (nota)' => (string) $manualReceivables->total_rows,
            'Piutang manual (nilai)' => number_format((float) $manualReceivables->total_amount, 2, '.', ''),
            'Piutang manual (sudah dibayar)' => number_format((float) $manualReceivables->paid_amount, 2, '.', ''),
        ];

        foreach (['product_prices' => 'Harga per grup', 'product_branch_prices' => 'Harga per cabang'] as $table => $label) {
            if (Schema::hasTable($table)) {
                $snapshot[$label] = (string) DB::table($table)->count();
            }
        }

        return $snapshot;
    }

    private function inventoryAccountBalance(): float
    {
        return (float) DB::table('journal_lines')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.chart_of_account_id')
            ->where('chart_of_accounts.code', '1210')
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) - COALESCE(SUM(journal_lines.credit), 0) as balance')
            ->value('balance');
    }

    /**
     * @param  array<int,int|string>  $values
     * @return array<int,mixed>
     */
    private function pluckIn(string $table, string $column, array $values, string $pluck): array
    {
        if ($values === [] || ! Schema::hasTable($table)) {
            return [];
        }

        $result = [];
        foreach (array_chunk($values, self::CHUNK) as $chunk) {
            array_push($result, ...DB::table($table)->whereIn($column, $chunk)->pluck($pluck)->all());
        }

        return $result;
    }

    /**
     * @param  array<int,int>  $ids
     */
    private function deleteIn(string $table, string $column, array $ids): void
    {
        if ($ids === [] || ! Schema::hasTable($table)) {
            return;
        }

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            DB::table($table)->whereIn($column, $chunk)->delete();
        }
    }

    /**
     * @param  array<string,mixed>  $report
     * @param  array<string,string>  $protected
     */
    private function renderReport(array $report, array $protected): void
    {
        $rupiah = fn (float $value) => 'Rp '.number_format($value, 0, ',', '.');

        $this->table(['Dihapus / direset', 'Jumlah'], [
            ['Struk kasir', $report['sales']],
            ['Nilai struk kasir', $rupiah($report['sales_value'])],
            ['  di antaranya tempo yang belum lunas', $report['sales_unpaid']],
            ['  rentang tanggal', $report['sales'] > 0 ? "{$report['sales_first']} s/d {$report['sales_last']}" : '-'],
            ['Retur penjualan', $report['returns']],
            ['Pembayaran piutang atas struk kasir', $report['payments']],
            ['Jurnal (penjualan, retur, pembayaran)', $report['journals']],
            ['Shift kasir', $report['shifts']],
            ['Baris stok gudang/cabang diset 0', $report['inventory_rows']],
            ['Stok produk (cache) diset 0', $report['product_rows']],
            ['Baris biaya rata-rata (ledger v2) diset 0', $report['cost_rows']],
        ]);

        if ($report['mixed_payments'] > 0) {
            $this->warn("{$report['mixed_payments']} pembayaran juga melunasi piutang lain, jadi tetap disimpan; hanya alokasinya ke struk uji coba yang dilepas.");
        }

        $this->table(['Tetap utuh', 'Nilai'], collect($protected)->map(fn ($value, $label) => [$label, $value])->values()->all());

        $this->line('Saldo akun 1210 Persediaan setelah reset: '.$rupiah($report['inventory_balance']));
        if (abs($report['inventory_balance']) >= 0.01) {
            $this->warn('Saldo Persediaan di buku besar belum 0 (berasal dari pembelian/opname/saldo awal yang tidak dihapus). Periksa bersama bagian akuntansi sebelum input stok awal dengan jurnal.');
        }
    }
}
