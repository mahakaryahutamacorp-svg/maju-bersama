<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HeldCatalogProducts extends Command
{
    /**
     * Tables whose rows are stock or ledger history; deleting the product would cascade or fail.
     */
    private const HISTORY_TABLES = ['stock_adjustment_items', 'stock_adjustment_lines', 'product_branch_costs', 'journal_items'];

    protected $signature = 'catalog:held-products
        {--purge : Hapus permanen produk yang disembunyikan dan tidak punya riwayat transaksi maupun stok opname}';

    protected $description = 'Tampilkan atau bersihkan produk cabang yang disembunyikan sinkronisasi katalog master';

    public function handle(): int
    {
        $held = Product::withoutGlobalScopes()
            ->whereNotNull('catalog_hold_reason')
            ->with('branch:id,code')
            ->withExists(Product::TRANSACTION_RELATIONS)
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get();

        if ($held->isEmpty()) {
            $this->info('Tidak ada produk yang disembunyikan.');

            return self::SUCCESS;
        }

        $held->each(fn (Product $product) => $product->setAttribute('has_history', $this->hasHistory($product)));

        $this->table(
            ['Cabang', 'SKU', 'Nama', 'Stok', 'Alasan', 'Punya riwayat'],
            $held->map(fn (Product $product) => [
                $product->branch?->code,
                $product->sku,
                $product->name,
                (int) $product->stock,
                $product->catalog_hold_reason,
                $product->getAttribute('has_history') ? 'Ya' : 'Tidak',
            ])->all(),
        );

        if (! $this->option('purge')) {
            return self::SUCCESS;
        }

        $purgeable = $held->reject(fn (Product $product) => $product->getAttribute('has_history'));
        $kept = $held->count() - $purgeable->count();

        if ($purgeable->isEmpty()) {
            $this->info("Tidak ada yang bisa dihapus permanen. {$kept} produk tetap diarsipkan karena punya riwayat.");

            return self::SUCCESS;
        }

        if (! $this->confirm("{$purgeable->count()} produk akan dihapus permanen, {$kept} produk tetap diarsipkan karena punya riwayat. Lanjutkan?", false)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($purgeable): void {
            foreach ($purgeable as $product) {
                Inventory::withoutGlobalScopes()->where('product_id', $product->id)->delete();
                ProductPrice::where('product_id', $product->id)->delete();
                $product->forceDelete();
            }
        });

        $this->info("{$purgeable->count()} produk dihapus permanen, {$kept} produk tetap diarsipkan.");

        return self::SUCCESS;
    }

    private function hasHistory(Product $product): bool
    {
        if ($product->hasTransactionHistory()) {
            return true;
        }

        foreach (self::HISTORY_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'product_id')
                && DB::table($table)->where('product_id', $product->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
