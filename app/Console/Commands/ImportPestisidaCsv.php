<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;
use App\Models\Branch;

class ImportPestisidaCsv extends Command
{
    protected $signature = 'import:pestisida {filepath}';
    protected $description = 'Import katalog pestisida dari CSV beserta pemisahan kemasan dan Branch-Level Pricing';

    public function handle()
    {
        $filepath = $this->argument('filepath');

        if (!file_exists($filepath)) {
            $this->error("File tidak ditemukan di path: {$filepath}");
            return 1;
        }

        $this->info("Memulai proses ETL dari file: {$filepath}...");

        $category = Category::firstOrCreate(
            ['name' => 'Pestisida']
        );

        $branchPusat = Branch::where('code', 'MBP')->orWhere('name', 'MB PUSAT')->first()
            ?? Branch::create(['code' => 'MBP', 'name' => 'MB PUSAT', 'is_active' => true]);

        $branchArofah = Branch::where('code', 'ARF')->orWhere('name', 'AROFAH')->first()
            ?? Branch::create(['code' => 'ARF', 'name' => 'AROFAH', 'is_active' => true]);

        if (($handle = fopen($filepath, "r")) !== false) {
            fgetcsv($handle, 1000, ",");
            $rowCount = 0;
            $successCount = 0;

            DB::beginTransaction();
            try {
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $rowCount++;
                    $rawName = trim($data[0] ?? '');
                    if (empty($rawName)) continue;

                    $modal = (float) str_replace(',', '', trim($data[1] ?? '0'));
                    $hargaPusat = (float) str_replace(',', '', trim($data[2] ?? '0'));
                    $hargaArofah = (float) str_replace(',', '', trim($data[3] ?? '0'));

                    if ($modal <= 0) {
                        $this->warn("Baris {$rowCount}: '{$rawName}' dilewati karena belum ada harga modal.");
                        continue;
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
                            'selling_price' => $hargaPusat,
                        ]);
                    } else {
                        $product = Product::withoutGlobalScopes()->create([
                            'branch_id' => $branchPusat->id,
                            'sku' => $sku,
                            'name' => $productName,
                            'category_id' => $category->id,
                            'unit' => $unit,
                            'purchase_price' => $modal,
                            'selling_price' => $hargaPusat,
                            'stock' => 0,
                        ]);
                    }

                    $product->branchPrices()->updateOrCreate(
                        ['branch_id' => $branchPusat->id],
                        ['price' => $hargaPusat]
                    );

                    $product->branchPrices()->updateOrCreate(
                        ['branch_id' => $branchArofah->id],
                        ['price' => $hargaArofah]
                    );

                    $successCount++;
                }
                
                DB::commit();
                $this->info("ETL Selesai! {$successCount} produk pestisida berhasil diimpor.");
                
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