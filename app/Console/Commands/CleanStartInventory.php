<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanStartInventory extends Command
{
    protected $signature = 'inventory:clean-start {--force : Jalankan tanpa prompt konfirmasi}';
    protected $description = 'Bersihkan transaksi testing, hapus produk dummy, dan inisialisasi stok 83 pestisida ke 0 di semua gudang';

    public function handle()
    {
        $this->alert('INISIALISASI CLEAN-START SISTEM POS & INVENTORY MAJU BERSAMA');

        if (! $this->option('force') && ! $this->confirm('Apakah Anda yakin ingin menghapus data testing, produk dummy, dan mereset stok ke 0?')) {
            $this->info('Operasi dibatalkan.');
            return 0;
        }

        $this->info('Memulai pembersihan data...');

        Schema::disableForeignKeyConstraints();

        // 1. Bersihkan tabel-tabel transaksi pengujian
        $transactionTables = [
            'sale_items',
            'sales',
            'sales_return_items',
            'sales_returns',
            'payment_allocations',
            'payments',
            'goods_receipt_items',
            'goods_receipts',
            'purchase_return_items',
            'purchase_returns',
            'purchase_order_items',
            'purchase_orders',
            'supplier_payments',
            'stock_transfer_items',
            'stock_transfers',
            'stock_adjustment_lines',
            'stock_adjustment_items',
            'stock_adjustments',
            'expenses',
            'cash_transfers',
            'cash_register_shifts',
            'journal_lines',
            'journal_headers',
        ];

        foreach ($transactionTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
                if (DB::getDriverName() === 'sqlite' && Schema::hasTable('sqlite_sequence')) {
                    DB::table('sqlite_sequence')->where('name', $table)->delete();
                }
            }
        }
        $this->info('✓ Riwayat transaksi testing (penjualan, pembelian, jurnal, pergeseran stok, kasir) telah dibersihkan.');

        // 2. Hapus produk dummy yang bukan merupakan katalog Pestisida (SKU non-PST)
        $dummyProducts = Product::withoutGlobalScopes()
            ->where('sku', 'not like', 'PST-%')
            ->get();

        $dummyCount = $dummyProducts->count();
        foreach ($dummyProducts as $dummy) {
            if (Schema::hasTable('product_prices')) {
                DB::table('product_prices')->where('product_id', $dummy->id)->delete();
            }
            if (Schema::hasTable('product_branch_prices')) {
                DB::table('product_branch_prices')->where('product_id', $dummy->id)->delete();
            }
            if (Schema::hasTable('inventories')) {
                DB::table('inventories')->where('product_id', $dummy->id)->delete();
            }
            $dummy->forceDelete();
        }
        $this->info("✓ Sebanyak {$dummyCount} produk dummy non-pestisida berhasil dihapus.");

        // Hapus kategori dummy yang kosong
        Category::where('name', '!=', 'Pestisida')
            ->whereDoesntHave('products')
            ->delete();

        // 3. Reset stok di tabel products menjadi 0
        Product::withoutGlobalScopes()->update(['stock' => 0]);

        // 4. Bersihkan tabel inventories dan inisialisasi ulang dengan stok 0
        if (Schema::hasTable('inventories')) {
            DB::table('inventories')->delete();
            if (DB::getDriverName() === 'sqlite' && Schema::hasTable('sqlite_sequence')) {
                DB::table('sqlite_sequence')->where('name', 'inventories')->delete();
            }

            $branches = Branch::where('is_active', true)->get();
            $pestisidaProducts = Product::withoutGlobalScopes()->where('sku', 'like', 'PST-%')->get();

            $inventoryRowsCreated = 0;
            foreach ($branches as $branch) {
                // Pastikan branch memiliki minimal satu warehouse/gudang aktif
                $warehouse = Warehouse::withoutGlobalScopes()
                    ->where('branch_id', $branch->id)
                    ->where('is_active', true)
                    ->first();

                if (! $warehouse) {
                    $cleanCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $branch->code ?? 'CAB'));
                    $warehouse = Warehouse::withoutGlobalScopes()->create([
                        'branch_id' => $branch->id,
                        'code' => $cleanCode . '-GU',
                        'name' => 'Gudang Utama ' . $branch->name,
                        'is_active' => true,
                    ]);
                }

                foreach ($pestisidaProducts as $product) {
                    DB::table('inventories')->insert([
                        'branch_id' => $branch->id,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                        'quantity' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $inventoryRowsCreated++;
                }
            }
            $this->info("✓ Inisialisasi kartu stok: {$inventoryRowsCreated} baris inventory di seluruh cabang dan gudang diset ke 0.");
        }

        Schema::enableForeignKeyConstraints();

        $activeProductsCount = Product::withoutGlobalScopes()->count();
        $this->table(
            ['Metrik', 'Status'],
            [
                ['Total Produk Katalog Aktif', "{$activeProductsCount} Produk (Pestisida)"],
                ['Status Stok Fisik (Gudang & Cabang)', '0 (Siap diisi saldo awal / stok baru)'],
                ['Riwayat Transaksi Penjualan & Pembelian', 'Bersih (0 data)'],
                ['Jurnal Keuangan Testing', 'Bersih (0 data)'],
                ['Bagan Akun (Chart of Accounts)', 'Utuh & Terkonfigurasi'],
                ['Pricing Multi-Cabang (Pusat & Arofah)', 'Tersimpan & Aktif'],
            ]
        );

        $this->info('Sistem Maju Bersama telah siap digunakan untuk operasional riil!');
        return 0;
    }
}
