<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use Database\Seeders\ArofahInitialStockSeeder;
use Tests\TestCase;

class ArofahInitialStockSeederTest extends TestCase
{
    private Branch $gudang;

    private Branch $arofah;

    protected function setUp(): void
    {
        parent::setUp();

        $office = Branch::create(['code' => 'OFFICE-PUPUK', 'name' => 'MAJU BERSAMA']);
        $this->gudang = Branch::create([
            'code' => 'GUDANG-PUPUK',
            'name' => 'Gudang Pupuk',
            'parent_id' => $office->id,
        ]);
        $this->arofah = Branch::create([
            'code' => 'CBG-AROFAH',
            'name' => 'TOKO AROFAH',
            'parent_id' => $office->id,
        ]);

        User::factory()->create([
            'branch_id' => $this->gudang->id,
            'role' => 'branch_admin',
        ]);
    }

    public function test_seeds_arofah_opening_stock_without_reducing_gudang_pupuk(): void
    {
        $category = Category::create(['name' => 'Pestisida', 'catalog' => Category::CATALOG_PUPUK]);

        $existing = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->gudang->id,
            'category_id' => $category->id,
            'sku' => 'PST-TOPSIN',
            'name' => 'Topsin-M 500ml',
            'unit' => 'BTL',
            'purchase_price' => 27000,
            'selling_price' => 36000,
            'stock' => 120,
        ]);
        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $this->gudang->id,
            'product_id' => $existing->id,
            'quantity' => 120,
        ]);

        $unrelated = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->gudang->id,
            'category_id' => $category->id,
            'sku' => 'PST-KONTROL',
            'name' => 'Pupuk Kontrol Tidak Ada di CSV',
            'unit' => 'KG',
            'purchase_price' => 10000,
            'selling_price' => 12000,
            'stock' => 55,
        ]);
        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $this->gudang->id,
            'product_id' => $unrelated->id,
            'quantity' => 55,
        ]);

        $stockBefore = $this->gudangStockSnapshot();

        $this->seed(ArofahInitialStockSeeder::class);

        $arofahProducts = Product::withoutGlobalScopes()
            ->where('branch_id', $this->arofah->id)
            ->get();

        $this->assertCount(234, $arofahProducts);
        $this->assertSame(2732, (int) $arofahProducts->sum('stock'));

        foreach ($arofahProducts as $product) {
            $quantity = Inventory::withoutGlobalScopes()
                ->where('branch_id', $this->arofah->id)
                ->where('product_id', $product->id)
                ->value('quantity');

            $this->assertSame((int) $product->stock, (int) $quantity);
        }

        $stockAfter = $this->gudangStockSnapshot();
        foreach ($stockBefore as $productId => $expected) {
            $this->assertSame($expected, $stockAfter[$productId]);
        }
        $this->assertSame(120, (int) $existing->fresh()->stock);
        $this->assertSame(120, (int) Inventory::withoutGlobalScopes()->where('product_id', $existing->id)->value('quantity'));
        $this->assertSame(55, (int) $unrelated->fresh()->stock);
        $this->assertSame('Topsin-M 500ml', $existing->fresh()->name);
        $this->assertSame('BTL', $existing->fresh()->unit);

        $arofahTopsin = Product::withoutGlobalScopes()
            ->where('branch_id', $this->arofah->id)
            ->where('sku', 'PST-TOPSIN')
            ->firstOrFail();
        $this->assertSame(3, (int) $arofahTopsin->stock);

        $sourceAki = Product::withoutGlobalScopes()
            ->where('branch_id', $this->gudang->id)
            ->where('name', 'AKI CBA')
            ->firstOrFail();
        $destinationAki = Product::withoutGlobalScopes()
            ->where('branch_id', $this->arofah->id)
            ->where('sku', $sourceAki->sku)
            ->firstOrFail();

        $this->assertSame(0, (int) $sourceAki->stock);
        $this->assertSame(0, (int) Inventory::withoutGlobalScopes()->where('product_id', $sourceAki->id)->value('quantity'));
        $this->assertSame(1, (int) $destinationAki->stock);
        $this->assertStringStartsWith('ARF-', $sourceAki->sku);

        $transfer = StockTransfer::query()->where('reference_number', ArofahInitialStockSeeder::REFERENCE)->firstOrFail();
        $this->assertSame('completed', $transfer->status);
        $this->assertSame($this->gudang->id, $transfer->source_branch_id);
        $this->assertSame($this->arofah->id, $transfer->destination_branch_id);
        $this->assertSame(ArofahInitialStockSeeder::NOTES, $transfer->notes);
        $this->assertSame(234, $transfer->items()->count());
        $this->assertSame(2732, (int) $transfer->items()->sum('quantity'));
        $this->assertDatabaseHas('stock_transfer_items', [
            'stock_transfer_id' => $transfer->id,
            'source_product_id' => $existing->id,
            'destination_product_id' => $arofahTopsin->id,
            'quantity' => 3,
        ]);
    }

    public function test_running_the_seeder_twice_does_not_duplicate_stock_or_transfers(): void
    {
        $this->seed(ArofahInitialStockSeeder::class);
        $this->seed(ArofahInitialStockSeeder::class);

        $this->assertSame(1, StockTransfer::query()->where('reference_number', ArofahInitialStockSeeder::REFERENCE)->count());
        $this->assertSame(234, Product::withoutGlobalScopes()->where('branch_id', $this->arofah->id)->count());
        $this->assertSame(2732, (int) Product::withoutGlobalScopes()->where('branch_id', $this->arofah->id)->sum('stock'));
        $this->assertSame(234, StockTransfer::query()->firstOrFail()->items()->count());
    }

    /**
     * @return array<int, array{stock: int, inventory: int|null}>
     */
    private function gudangStockSnapshot(): array
    {
        $snapshot = [];

        $products = Product::withoutGlobalScopes()
            ->where('branch_id', $this->gudang->id)
            ->orderBy('id')
            ->get(['id', 'stock']);

        foreach ($products as $product) {
            $inventory = Inventory::withoutGlobalScopes()
                ->where('branch_id', $this->gudang->id)
                ->where('product_id', $product->id)
                ->value('quantity');

            $snapshot[$product->id] = [
                'stock' => (int) $product->stock,
                'inventory' => $inventory === null ? null : (int) $inventory,
            ];
        }

        return $snapshot;
    }
}
