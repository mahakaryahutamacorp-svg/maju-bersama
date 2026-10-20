<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saldo awal fisik TOKO AROFAH (CBG-AROFAH).
 *
 * Barang secara operasional berasal dari GUDANG-PUPUK, tetapi angka stok gudang
 * saat ini sudah merupakan sisa nyata setelah pengiriman pra-go-live. Seeder ini
 * menuliskan histori transfer tanpa mengurangi stok atau inventory GUDANG-PUPUK.
 *
 * Idempotent lewat nomor referensi TRF-INIT-AROFAH-001: menjalankan ulang tidak
 * menambah stok cabang dan tidak membuat dokumen transfer kedua.
 *
 * php artisan db:seed --class=ArofahInitialStockSeeder
 */
class ArofahInitialStockSeeder extends Seeder
{
    public const REFERENCE = 'TRF-INIT-AROFAH-001';

    public const SOURCE_CODE = 'GUDANG-PUPUK';

    public const DESTINATION_CODE = 'CBG-AROFAH';

    public const NOTES = 'Initial Stock Setup Arofah - Migrasi Saldo Awal Fisik (Tanpa Potong Stok Gudang Aktual)';

    public function run(): void
    {
        $rows = $this->readCsv();

        DB::transaction(function () use ($rows): void {
            $alreadySeeded = StockTransfer::query()
                ->where('reference_number', self::REFERENCE)
                ->lockForUpdate()
                ->exists();

            if ($alreadySeeded) {
                $this->command?->info(self::REFERENCE.' sudah ada. Seeder dilewati agar stok tidak digandakan.');

                return;
            }

            $source = $this->requireBranch(self::SOURCE_CODE);
            $destination = $this->requireBranch(self::DESTINATION_CODE);
            $actor = $this->requireActor();
            $category = $this->defaultCategory();
            $sourceStock = $this->snapshotSourceStock($source->id);
            $sourceByName = $this->indexProductsByName($source->id);

            $transfer = StockTransfer::query()->create([
                'reference_number' => self::REFERENCE,
                'source_branch_id' => $source->id,
                'destination_branch_id' => $destination->id,
                'created_by' => $actor->id,
                'transfer_date' => now()->toDateString(),
                'status' => 'completed',
                'notes' => self::NOTES,
            ]);

            foreach ($rows as $row) {
                $sourceProduct = $sourceByName[$row['key']] ?? null;

                if ($sourceProduct === null) {
                    $sourceProduct = $this->createSourceProduct($source, $category, $row);
                    $sourceByName[$row['key']] = $sourceProduct;
                }

                $destinationProduct = $this->upsertDestinationProduct($destination, $sourceProduct, $row['quantity']);

                $transfer->items()->create([
                    'source_product_id' => $sourceProduct->id,
                    'destination_product_id' => $destinationProduct->id,
                    'quantity' => $row['quantity'],
                    'unit_cost' => $sourceProduct->purchase_price,
                ]);
            }

            $this->assertSourceStockUnchanged($sourceStock);
        });
    }

    /**
     * @return list<array{key: string, name: string, quantity: int, unit: string}>
     */
    private function readCsv(): array
    {
        $path = database_path('seeders/data/stok_awal_arofah.csv');

        if (! is_file($path)) {
            throw new RuntimeException("File CSV saldo awal Arofah tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("File CSV saldo awal Arofah tidak dapat dibaca: {$path}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('File CSV saldo awal Arofah kosong.');
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? '')) ?? '';
            $normalizedHeader = array_map(
                fn ($column) => strtolower(trim((string) $column)),
                array_slice($header, 0, 3),
            );

            if ($normalizedHeader !== ['nama_barang', 'stok', 'satuan']) {
                throw new RuntimeException('Header CSV tidak sesuai. Diharapkan: nama_barang,stok,satuan');
            }

            $rows = [];
            $line = 1;

            while (($record = fgetcsv($handle)) !== false) {
                $line++;
                $name = trim((string) ($record[0] ?? ''));

                if ($name === '' && trim((string) ($record[1] ?? '')) === '') {
                    continue;
                }

                if ($name === '') {
                    throw new RuntimeException("Nama barang kosong pada baris {$line}.");
                }

                $quantity = trim((string) ($record[1] ?? ''));

                if (preg_match('/^\d+$/', $quantity) !== 1) {
                    throw new RuntimeException("Kuantitas tidak valid pada baris {$line}: {$quantity}");
                }

                $key = $this->normalizeName($name);

                if ($key === '') {
                    throw new RuntimeException("Nama barang tidak valid pada baris {$line}.");
                }

                if (isset($rows[$key])) {
                    throw new RuntimeException("Nama barang ganda setelah normalisasi pada baris {$line}: {$name}");
                }

                $unit = trim((string) ($record[2] ?? ''));

                $rows[$key] = [
                    'key' => $key,
                    'name' => $name,
                    'quantity' => (int) $quantity,
                    'unit' => $unit !== '' ? mb_substr($unit, 0, 50) : 'pcs',
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw new RuntimeException('File CSV saldo awal Arofah tidak berisi data.');
        }

        return array_values($rows);
    }

    private function requireBranch(string $code): Branch
    {
        $branch = Branch::query()->where('code', $code)->first();

        if ($branch === null) {
            throw new RuntimeException("Cabang {$code} tidak ditemukan. Jalankan BusinessStructureSeeder terlebih dahulu.");
        }

        return $branch;
    }

    private function requireActor(): User
    {
        $sourceBranchId = Branch::query()->where('code', self::SOURCE_CODE)->value('id');

        $actor = User::query()
            ->when($sourceBranchId, fn ($query) => $query->where('branch_id', $sourceBranchId))
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();

        if ($actor === null) {
            throw new RuntimeException('ArofahInitialStockSeeder membutuhkan minimal satu user untuk kolom created_by.');
        }

        return $actor;
    }

    private function defaultCategory(): Category
    {
        $category = Category::query()
            ->where('catalog', Category::CATALOG_PUPUK)
            ->orderBy('id')
            ->first();

        if ($category !== null) {
            return $category;
        }

        return Category::query()->firstOrCreate(
            ['name' => 'Pertanian'],
            ['catalog' => Category::CATALOG_PUPUK],
        );
    }

    /**
     * @return array<int, array{stock: int, inventory: int|null}>
     */
    private function snapshotSourceStock(int $branchId): array
    {
        $products = Product::query()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->get(['id', 'stock']);

        $snapshot = [];

        foreach ($products as $product) {
            $inventory = Inventory::query()
                ->withoutGlobalScope('branch')
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->value('quantity');

            $snapshot[$product->id] = [
                'stock' => (int) $product->stock,
                'inventory' => $inventory === null ? null : (int) $inventory,
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array<int, array{stock: int, inventory: int|null}>  $snapshot
     */
    private function assertSourceStockUnchanged(array $snapshot): void
    {
        foreach ($snapshot as $productId => $expected) {
            $stock = Product::query()
                ->withoutGlobalScope('branch')
                ->whereKey($productId)
                ->value('stock');

            if ((int) $stock !== $expected['stock']) {
                throw new RuntimeException("Stok GUDANG-PUPUK berubah pada produk {$productId}. Seeder dibatalkan.");
            }

            $inventory = Inventory::query()
                ->withoutGlobalScope('branch')
                ->where('product_id', $productId)
                ->value('quantity');

            $actualInventory = $inventory === null ? null : (int) $inventory;

            if ($actualInventory !== $expected['inventory']) {
                throw new RuntimeException("Inventory GUDANG-PUPUK berubah pada produk {$productId}. Seeder dibatalkan.");
            }
        }
    }

    /**
     * @return array<string, Product>
     */
    private function indexProductsByName(int $branchId): array
    {
        $products = Product::query()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->orderBy('id')
            ->get();

        $indexed = [];

        foreach ($products as $product) {
            $key = $this->normalizeName($product->name);

            if ($key !== '' && ! isset($indexed[$key])) {
                $indexed[$key] = $product;
            }
        }

        return $indexed;
    }

    /**
     * @param  array{key: string, name: string, quantity: int, unit: string}  $row
     */
    private function createSourceProduct(Branch $source, Category $category, array $row): Product
    {
        $product = Product::query()->withoutGlobalScope('branch')->create([
            'branch_id' => $source->id,
            'category_id' => $category->id,
            'sku' => $this->standardizedSku($source->id, $row['key']),
            'name' => $row['name'],
            'unit' => $row['unit'],
            'purchase_price' => 0,
            'selling_price' => 0,
            'stock' => 0,
        ]);

        $this->writeQuantity($product, 0);

        return $product;
    }

    private function upsertDestinationProduct(Branch $destination, Product $sourceProduct, int $quantity): Product
    {
        $product = Product::query()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $destination->id)
            ->where('sku', $sourceProduct->sku)
            ->first();

        if ($product === null) {
            $product = $this->findDestinationByName($destination->id, $sourceProduct->name);
        }

        if ($product === null) {
            $product = Product::query()->withoutGlobalScope('branch')->create([
                'branch_id' => $destination->id,
                'category_id' => $sourceProduct->category_id,
                'sku' => $sourceProduct->sku,
                'name' => $sourceProduct->name,
                'unit' => $sourceProduct->unit,
                'purchase_price' => $sourceProduct->purchase_price,
                'selling_price' => $sourceProduct->selling_price,
                'stock' => $quantity,
            ]);

            $this->writeQuantity($product, $quantity);

            return $product;
        }

        if ($product->sku !== $sourceProduct->sku && ! $this->skuTaken($destination->id, $sourceProduct->sku, $product->id)) {
            $product->sku = $sourceProduct->sku;
        }

        $product->stock = $quantity;
        $product->save();
        $this->writeQuantity($product, $quantity);

        return $product;
    }

    private function findDestinationByName(int $branchId, string $name): ?Product
    {
        $key = $this->normalizeName($name);

        return Product::query()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->orderBy('id')
            ->get()
            ->first(fn (Product $product) => $this->normalizeName($product->name) === $key);
    }

    private function skuTaken(int $branchId, string $sku, int $ignoreProductId): bool
    {
        return Product::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('sku', $sku)
            ->where('id', '!=', $ignoreProductId)
            ->exists();
    }

    private function standardizedSku(int $branchId, string $normalizedName): string
    {
        $digest = strtoupper(substr(hash('sha256', $normalizedName), 0, 8));
        $sku = 'ARF-'.$digest;
        $suffix = 2;

        while ($this->skuTaken($branchId, $sku, 0)) {
            $sku = 'ARF-'.$digest.'-'.$suffix;
            $suffix++;
        }

        return $sku;
    }

    /**
     * Menulis kuantitas absolut pada produk cabang tujuan dan baris inventory-nya.
     * Tidak pernah dipakai untuk produk GUDANG-PUPUK yang sudah ada.
     */
    private function writeQuantity(Product $product, int $quantity): void
    {
        if ((int) $product->stock !== $quantity) {
            $product->stock = $quantity;
            $product->save();
        }

        $inventory = Inventory::query()
            ->withoutGlobalScope('branch')
            ->firstOrNew([
                'branch_id' => $product->branch_id,
                'product_id' => $product->id,
            ]);

        if (! $inventory->exists) {
            $inventory->warehouse_id = Warehouse::query()
                ->withoutGlobalScope('branch')
                ->where('branch_id', $product->branch_id)
                ->where('name', 'Gudang Utama')
                ->value('id')
                ?? Warehouse::query()
                    ->withoutGlobalScope('branch')
                    ->where('branch_id', $product->branch_id)
                    ->orderBy('id')
                    ->value('id');
        }

        $inventory->quantity = $quantity;
        $inventory->save();
    }

    private function normalizeName(string $name): string
    {
        $normalized = mb_strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9]+/u', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return trim($normalized);
    }
}
