<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;
use App\Models\Branch;
use App\Models\PriceLevel;
use App\Models\CustomerGroup;
use App\Models\Warehouse;

class ImportPestisidaCsv extends Command
{
    protected $signature = 'import:pestisida {filepath}';
    protected $description = 'Import katalog pestisida dari CSV beserta pemisahan kemasan, harga ecer, harga grosir, dan Branch-Level Pricing';

    public function handle()
    {
        $filepath = $this->argument('filepath');

        if (!file_exists($filepath)) {
            $this->error("File tidak ditemukan di path: {$filepath}");
            return 1;
        }

        $this->info("Memulai proses ETL dari file: {$filepath}...");

        $category = Category::firstOrCreate(['name' => 'Pestisida']);

        $branchPusat = Branch::where('code', 'MBP')->orWhere('name', 'MB PUSAT')->first()
            ?? Branch::create(['code' => 'MBP', 'name' => 'MB PUSAT', 'is_active' => true]);

        $branchArofah = Branch::where('code', 'ARF')->orWhere('name', 'AROFAH')->first()
            ?? Branch::create(['code' => 'ARF', 'name' => 'AROFAH', 'is_active' => true]);

        // Pastikan Price Level Eceran dan Grosir tersedia
        $levelEceran = PriceLevel::firstOrCreate(
            ['name' => 'Harga Eceran'],
            ['is_default' => true, 'is_active' => true]
        );
        $levelGrosir = PriceLevel::firstOrCreate(
            ['name' => 'Harga Grosir'],
            ['is_default' => false, 'is_active' => true]
        );

        // Pastikan Customer Group Umum dan Grosir tersedia
        $groupRetail = CustomerGroup::firstOrCreate(
            ['name' => 'Umum/Retail'],
            ['notes' => 'Pelanggan retail umum dengan harga standar.']
        );
        $groupGrosir = CustomerGroup::firstOrCreate(
            ['name' => 'Grosir'],
            ['notes' => 'Pelanggan partai besar / toko mitra dengan harga grosir.']
        );

        $activeBranches = Branch::where('is_active', true)->get();

        $parseNumber = function ($raw) {
            if (!$raw) return 0.0;
            $trimmed = trim($raw);
            if ($trimmed === '-' || $trimmed === '' || $trimmed === '0') return 0.0;
            $cleaned = preg_replace('/[^\d]/', '', $trimmed);
            return (float) $cleaned;
        };

        if (($handle = fopen($filepath, "r")) !== false) {
            $header = fgetcsv($handle, 1000, ",");
            if ($header === false) {
                $this->error("File CSV kosong.");
                fclose($handle);
                return 1;
            }

            // Deteksi format header:
            // Format 1: Nama Produk, Harga Modal, Harga MB Pusat, Harga Arofah
            // Format 2: Nama Pestisida, Harga Modal, Harga Jual (7%), Harga Jual (10%)
            $isFormatWithMargin = false;
            foreach ($header as $col) {
                if (stripos($col, '7%') !== false || stripos($col, '10%') !== false || stripos($col, 'Grosir') !== false) {
                    $isFormatWithMargin = true;
                    break;
                }
            }

            $rowCount = 0;
            $successCount = 0;
            $emptyPriceCount = 0;

            DB::beginTransaction();
            try {
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $rowCount++;
                    $rawName = trim($data[0] ?? '');
                    if (empty($rawName)) continue;

                    $modal = $parseNumber($data[1] ?? '0');

                    if ($isFormatWithMargin) {
                        $hargaGrosir = $parseNumber($data[2] ?? '0'); // 7%
                        $hargaEcer = $parseNumber($data[3] ?? '0');   // 10%
                        $hargaPusat = $hargaEcer > 0 ? $hargaEcer : ($hargaGrosir > 0 ? $hargaGrosir : $modal);
                        $hargaArofah = $hargaPusat;
                    } else {
                        $hargaPusat = $parseNumber($data[2] ?? '0');
                        $hargaArofah = $parseNumber($data[3] ?? '0');
                        $hargaEcer = $hargaPusat;
                        $hargaGrosir = round($modal > 0 ? $modal * 1.07 : $hargaPusat * 0.95);
                    }

                    if ($modal <= 0 && $hargaEcer <= 0) {
                        $emptyPriceCount++;
                    }

                    $parts = explode('@', $rawName);
                    $productName = trim($parts[0]);
                    $unit = isset($parts[1]) ? trim(strtoupper($parts[1])) : 'PCS';

                    $sku = 'PST-' . strtoupper(substr(md5($productName . $unit), 0, 6));

                    $product = Product::withoutGlobalScopes()
                        ->where('branch_id', $branchPusat->id)
                        ->where('sku', $sku)
                        ->first();

                    if ($product) {
                        $product->update([
                            'name' => $productName,
                            'category_id' => $category->id,
                            'unit' => $unit,
                            'purchase_price' => $modal,
                            'selling_price' => $hargaEcer,
                        ]);
                    } else {
                        $product = Product::withoutGlobalScopes()->create([
                            'branch_id' => $branchPusat->id,
                            'sku' => $sku,
                            'name' => $productName,
                            'category_id' => $category->id,
                            'unit' => $unit,
                            'purchase_price' => $modal,
                            'selling_price' => $hargaEcer,
                            'stock' => 0,
                        ]);
                    }

                    // 1. Sync Price Levels (Eceran & Grosir)
                    DB::table('product_prices')->updateOrInsert(
                        ['product_id' => $product->id, 'price_level_id' => $levelEceran->id],
                        ['price' => $hargaEcer, 'customer_group_id' => $groupRetail->id, 'updated_at' => now()]
                    );

                    DB::table('product_prices')->updateOrInsert(
                        ['product_id' => $product->id, 'price_level_id' => $levelGrosir->id],
                        ['price' => $hargaGrosir, 'customer_group_id' => $groupGrosir->id, 'updated_at' => now()]
                    );

                    // 2. Sync Branch Prices
                    $product->branchPrices()->updateOrCreate(
                        ['branch_id' => $branchPusat->id],
                        ['price' => $hargaPusat]
                    );

                    $product->branchPrices()->updateOrCreate(
                        ['branch_id' => $branchArofah->id],
                        ['price' => $hargaArofah]
                    );

                    // 3. Ensure inventory rows exist with quantity = 0
                    foreach ($activeBranches as $b) {
                        $wh = Warehouse::withoutGlobalScopes()->where('branch_id', $b->id)->where('is_active', true)->first();
                        if ($wh) {
                            $invExists = DB::table('inventories')->where([
                                'branch_id' => $b->id,
                                'warehouse_id' => $wh->id,
                                'product_id' => $product->id,
                            ])->exists();

                            if (! $invExists) {
                                DB::table('inventories')->insert([
                                    'branch_id' => $b->id,
                                    'warehouse_id' => $wh->id,
                                    'product_id' => $product->id,
                                    'quantity' => 0,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }

                    $successCount++;
                }

                DB::commit();
                $this->info("ETL Selesai! {$successCount} produk pestisida berhasil diproses.");
                if ($emptyPriceCount > 0) {
                    $this->info("Catatan: Ada {$emptyPriceCount} produk yang harga awalnya kosong/0 (siap diisi dari frontend).");
                }

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Terjadi kesalahan sistem pada baris {$rowCount}: " . $e->getMessage());
                return 1;
            }
            fclose($handle);
        }
        return 0;
    }
}