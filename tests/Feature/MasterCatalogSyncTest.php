<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Sale;
use App\Models\User;
use Tests\TestCase;

class MasterCatalogSyncTest extends TestCase
{
    private Branch $office;

    private Branch $warehouse;

    private Branch $store;

    private Branch $otherGroup;

    private User $master;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Branch::create(['code' => 'OFFICE-PUPUK', 'name' => 'Kantor Pupuk']);
        $this->warehouse = Branch::create(['code' => 'GUDANG-PUPUK', 'name' => 'Gudang Pupuk', 'parent_id' => $this->office->id]);
        $this->store = Branch::create(['code' => 'CBG-ABI', 'name' => 'Toko ABI', 'parent_id' => $this->office->id]);
        $this->otherGroup = Branch::create(['code' => 'PUSAT', 'name' => 'Pusat Lama']);

        $this->master = User::factory()->create(['branch_id' => $this->warehouse->id, 'role' => 'master']);
        $this->category = Category::create(['name' => 'Pupuk', 'catalog' => Category::CATALOG_PUPUK]);
    }

    public function test_master_created_product_is_registered_in_every_branch_of_the_group_only(): void
    {
        $petani = CustomerGroup::firstOrCreate(['name' => 'Petani']);

        $this->actingAs($this->master)->post(route('backoffice.products.store'), [
            'name' => 'Urea 50kg',
            'sku' => 'UREA-50',
            'category_id' => $this->category->id,
            'branch_id' => $this->warehouse->id,
            'purchase_price' => 250000,
            'selling_price' => 275000,
            'stock' => 40,
            'customer_group_prices' => [$petani->id => 265000],
        ])->assertRedirect(route('backoffice.products.index'));

        foreach ([$this->office, $this->warehouse, $this->store] as $branch) {
            $product = $this->productIn($branch, 'UREA-50');
            $this->assertSame('Urea 50kg', $product->name);
            $this->assertEquals(250000, (float) $product->purchase_price);
            $this->assertEquals(275000, (float) $product->selling_price);
            $this->assertEquals(265000, (float) $product->getPriceForGroup($petani->id));
            $this->assertDatabaseHas('inventories', ['branch_id' => $branch->id, 'product_id' => $product->id]);
        }

        $this->assertSame(40, $this->productIn($this->warehouse, 'UREA-50')->stock);
        $this->assertSame(0, $this->productIn($this->store, 'UREA-50')->stock);
        $this->assertNull(Product::withoutGlobalScopes()->where('branch_id', $this->otherGroup->id)->where('sku', 'UREA-50')->first());
    }

    public function test_master_edit_is_copied_to_every_branch_of_the_group(): void
    {
        $petani = CustomerGroup::firstOrCreate(['name' => 'Petani']);
        $warehouseProduct = $this->makeProduct($this->warehouse, 'NPK-1', 'NPK Lama', 10000, 12000);
        $storeProduct = $this->makeProduct($this->store, 'NPK-1', 'NPK Toko', 9000, 13000);
        $otherProduct = $this->makeProduct($this->otherGroup, 'NPK-1', 'NPK Grup Lain', 8000, 11000);
        ProductPrice::create(['product_id' => $storeProduct->id, 'customer_group_id' => $petani->id, 'price' => 1]);

        $this->actingAs($this->master)->put(route('backoffice.products.update', $warehouseProduct->id), [
            'name' => 'NPK Mutiara 1kg',
            'sku' => 'NPK-1',
            'category_id' => $this->category->id,
            'unit' => 'kg',
            'purchase_price' => 15000,
            'selling_price' => 18000,
            'customer_group_prices' => [$petani->id => 17000],
        ])->assertRedirect(route('backoffice.products.index'));

        foreach ([$warehouseProduct, $storeProduct, $this->productIn($this->office, 'NPK-1')] as $product) {
            $product = $product->fresh();
            $this->assertSame('NPK Mutiara 1kg', $product->name);
            $this->assertEquals(15000, (float) $product->purchase_price);
            $this->assertEquals(18000, (float) $product->selling_price);
            $this->assertEquals(17000, (float) $product->getPriceForGroup($petani->id));
        }

        $this->assertSame('NPK Grup Lain', $otherProduct->fresh()->name);
        $this->assertEquals(11000, (float) $otherProduct->fresh()->selling_price);
    }

    public function test_master_sku_change_follows_to_siblings_and_rejects_taken_sku(): void
    {
        $warehouseProduct = $this->makeProduct($this->warehouse, 'OLD-SKU', 'Produk', 1000, 2000);
        $storeProduct = $this->makeProduct($this->store, 'OLD-SKU', 'Produk', 1000, 2000);
        $this->makeProduct($this->store, 'TAKEN', 'Produk Lain', 1000, 2000);

        $payload = [
            'name' => 'Produk',
            'category_id' => $this->category->id,
            'purchase_price' => 1000,
            'selling_price' => 2000,
        ];

        $this->actingAs($this->master)
            ->put(route('backoffice.products.update', $warehouseProduct->id), [...$payload, 'sku' => 'TAKEN'])
            ->assertSessionHasErrors('sku');
        $this->assertSame('OLD-SKU', $warehouseProduct->fresh()->sku);

        $this->actingAs($this->master)
            ->put(route('backoffice.products.update', $warehouseProduct->id), [...$payload, 'sku' => 'NEW-SKU'])
            ->assertRedirect(route('backoffice.products.index'));

        $this->assertSame('NEW-SKU', $warehouseProduct->fresh()->sku);
        $this->assertSame('NEW-SKU', $storeProduct->fresh()->sku);
    }

    public function test_branch_admin_only_changes_category_unit_and_tax_in_own_branch(): void
    {
        $admin = User::factory()->create(['branch_id' => $this->store->id, 'role' => 'admin']);
        $otherCategory = Category::create(['name' => 'Pestisida', 'catalog' => Category::CATALOG_PUPUK]);
        $warehouseProduct = $this->makeProduct($this->warehouse, 'KCL-1', 'KCL', 5000, 7000);
        $storeProduct = $this->makeProduct($this->store, 'KCL-1', 'KCL', 5000, 7000);

        $this->actingAs($admin)->get(route('backoffice.products.edit', $storeProduct->id))
            ->assertOk()
            ->assertSee('mengikuti cabang acuan Gudang Pupuk (GUDANG-PUPUK)', false)
            ->assertDontSee('Ubah di cabang acuan');

        $this->actingAs($admin)->put(route('backoffice.products.update', $storeProduct->id), [
            'name' => 'Diubah Admin',
            'sku' => 'HACK',
            'category_id' => $otherCategory->id,
            'unit' => 'sak',
            'purchase_price' => 1,
            'selling_price' => 1,
            'is_tax_exempt' => '1',
        ])->assertRedirect(route('backoffice.products.index'));

        $storeProduct->refresh();
        $this->assertSame('KCL', $storeProduct->name);
        $this->assertSame('KCL-1', $storeProduct->sku);
        $this->assertEquals(5000, (float) $storeProduct->purchase_price);
        $this->assertEquals(7000, (float) $storeProduct->selling_price);
        $this->assertSame($otherCategory->id, $storeProduct->category_id);
        $this->assertSame('sak', $storeProduct->unit);
        $this->assertFalse($storeProduct->is_taxable);

        $this->assertSame($this->category->id, $warehouseProduct->fresh()->category_id);
        $this->assertTrue($warehouseProduct->fresh()->is_taxable);
    }

    public function test_store_rows_follow_the_warehouse_even_when_master_edits_them(): void
    {
        $warehouseProduct = $this->makeProduct($this->warehouse, 'TSP-1', 'TSP 50kg', 5000, 7000);
        $storeProduct = $this->makeProduct($this->store, 'TSP-1', 'TSP 50kg', 5000, 7000);

        $this->actingAs($this->master)->get(route('backoffice.products.edit', $storeProduct->id))
            ->assertOk()
            ->assertSee('mengikuti cabang acuan', false)
            ->assertSee(route('backoffice.products.edit', $warehouseProduct->id), false);

        $this->actingAs($this->master)->put(route('backoffice.products.update', $storeProduct->id), [
            'name' => 'Diubah Dari Toko',
            'sku' => 'TSP-1',
            'category_id' => $this->category->id,
            'unit' => 'sak',
            'purchase_price' => 1,
            'selling_price' => 1,
        ])->assertRedirect(route('backoffice.products.index'));

        $this->assertSame('TSP 50kg', $storeProduct->fresh()->name);
        $this->assertEquals(7000, (float) $storeProduct->fresh()->selling_price);
        $this->assertSame('sak', $storeProduct->fresh()->unit);
        $this->assertSame('TSP 50kg', $warehouseProduct->fresh()->name);
        $this->assertEquals(7000, (float) $warehouseProduct->fresh()->selling_price);
    }

    public function test_warehouse_admin_creates_and_edits_products_for_every_branch_of_the_group(): void
    {
        $warehouseAdmin = User::factory()->create(['branch_id' => $this->warehouse->id, 'role' => 'branch_admin']);

        $this->actingAs($warehouseAdmin)->get(route('backoffice.products.index'))
            ->assertOk()
            ->assertSee('+ Tambah Produk');

        $this->actingAs($warehouseAdmin)->post(route('backoffice.products.store'), [
            'name' => 'Phonska 50kg',
            'sku' => 'PHONSKA-50',
            'category_id' => $this->category->id,
            'purchase_price' => 150000,
            'selling_price' => 170000,
            'stock' => 25,
        ])->assertRedirect(route('backoffice.products.index'));

        $warehouseProduct = $this->productIn($this->warehouse, 'PHONSKA-50');
        $this->assertSame(25, $warehouseProduct->stock);
        $this->assertSame(0, $this->productIn($this->store, 'PHONSKA-50')->stock);

        $this->actingAs($warehouseAdmin)->put(route('backoffice.products.update', $warehouseProduct->id), [
            'name' => 'Phonska Plus 50kg',
            'sku' => 'PHONSKA-50',
            'category_id' => $this->category->id,
            'purchase_price' => 155000,
            'selling_price' => 175000,
        ])->assertRedirect(route('backoffice.products.index'));

        foreach ([$this->office, $this->warehouse, $this->store] as $branch) {
            $product = $this->productIn($branch, 'PHONSKA-50');
            $this->assertSame('Phonska Plus 50kg', $product->name);
            $this->assertEquals(155000, (float) $product->purchase_price);
            $this->assertEquals(175000, (float) $product->selling_price);
        }
        $this->assertSame(25, $this->productIn($this->warehouse, 'PHONSKA-50')->stock);
        $this->assertSame(0, $this->productIn($this->store, 'PHONSKA-50')->stock);
    }

    public function test_products_cannot_be_created_from_a_store_of_the_warehouse_group(): void
    {
        $storeAdmin = User::factory()->create(['branch_id' => $this->store->id, 'role' => 'branch_admin']);
        $payload = [
            'name' => 'Produk Toko',
            'sku' => 'TOKO-1',
            'category_id' => $this->category->id,
            'purchase_price' => 1000,
            'selling_price' => 2000,
        ];

        $this->actingAs($storeAdmin)->get(route('backoffice.products.create'))->assertForbidden();
        $this->actingAs($storeAdmin)->post(route('backoffice.products.store'), $payload)->assertForbidden();

        $this->actingAs($this->master)->get(route('backoffice.products.create'))
            ->assertOk()
            ->assertSee('GUDANG-PUPUK')
            ->assertDontSee('CBG-ABI');

        $this->actingAs($this->master)
            ->post(route('backoffice.products.store'), [...$payload, 'branch_id' => $this->store->id])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('products', ['sku' => 'TOKO-1']);
    }

    public function test_warehouse_delete_hides_the_product_in_every_branch_and_keeps_stock(): void
    {
        $warehouseAdmin = User::factory()->create(['branch_id' => $this->warehouse->id, 'role' => 'branch_admin']);
        $storeAdmin = User::factory()->create(['branch_id' => $this->store->id, 'role' => 'branch_admin']);
        $warehouseProduct = $this->makeProduct($this->warehouse, 'DOL-1', 'Dolomit', 1000, 1500);
        $storeProduct = $this->makeProduct($this->store, 'DOL-1', 'Dolomit', 1000, 1500);
        $storeProduct->update(['stock' => 6]);
        $otherProduct = $this->makeProduct($this->otherGroup, 'DOL-1', 'Dolomit', 1000, 1500);

        $this->actingAs($storeAdmin)->delete(route('backoffice.products.destroy', $storeProduct->id))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted('products', ['id' => $storeProduct->id]);

        $this->actingAs($warehouseAdmin)->delete(route('backoffice.products.destroy', $warehouseProduct->id))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('products', ['id' => $warehouseProduct->id]);
        $this->assertSoftDeleted('products', ['id' => $storeProduct->id, 'stock' => 6, 'catalog_hold_reason' => null]);
        $this->assertNotSoftDeleted('products', ['id' => $otherProduct->id]);
    }

    public function test_warehouse_admin_archives_product_with_history_in_every_branch(): void
    {
        $warehouseAdmin = User::factory()->create(['branch_id' => $this->warehouse->id, 'role' => 'branch_admin']);
        $warehouseProduct = $this->makeProduct($this->warehouse, 'HIST-1', 'Produk Berriwayat', 1000, 1500);
        $storeProduct = $this->makeProduct($this->store, 'HIST-1', 'Produk Berriwayat', 1000, 1500);
        Sale::create([
            'branch_id' => $this->warehouse->id,
            'receipt_number' => 'INV-HIST-01',
            'total_amount' => 1500,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $warehouseAdmin->id,
        ])->items()->create(['product_id' => $warehouseProduct->id, 'quantity' => 1, 'price' => 1500, 'subtotal' => 1500]);

        $this->actingAs($warehouseAdmin)->get(route('backoffice.products.index'))
            ->assertOk()
            ->assertSee(route('backoffice.products.destroy', $warehouseProduct->id), false);

        $this->actingAs($warehouseAdmin)->delete(route('backoffice.products.destroy', $warehouseProduct->id))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('products', ['id' => $warehouseProduct->id]);
        $this->assertSoftDeleted('products', ['id' => $storeProduct->id, 'stock' => 10]);
        $this->assertDatabaseHas('sale_items', ['product_id' => $warehouseProduct->id]);
    }

    public function test_new_warehouse_product_restores_archived_store_row_with_its_stock(): void
    {
        $archived = $this->makeProduct($this->store, 'KAPUR-1', 'Kapur Lama', 100, 200);
        $archived->update(['stock' => 4]);
        $archived->forceFill(['deleted_at' => now(), 'catalog_hold_reason' => 'Tidak ada di acuan'])->save();

        $this->actingAs($this->master)->post(route('backoffice.products.store'), [
            'name' => 'Kapur Pertanian',
            'sku' => 'KAPUR-1',
            'category_id' => $this->category->id,
            'branch_id' => $this->warehouse->id,
            'purchase_price' => 900,
            'selling_price' => 1200,
        ])->assertRedirect(route('backoffice.products.index'));

        $archived->refresh();
        $this->assertNull($archived->deleted_at);
        $this->assertNull($archived->catalog_hold_reason);
        $this->assertSame('Kapur Pertanian', $archived->name);
        $this->assertEquals(1200, (float) $archived->selling_price);
        $this->assertSame(4, $archived->stock);
    }

    public function test_new_branch_in_the_warehouse_group_gets_the_warehouse_catalog_with_zero_stock(): void
    {
        $petani = CustomerGroup::firstOrCreate(['name' => 'Petani']);
        $urea = $this->makeProduct($this->warehouse, 'UREA-1', 'Urea 50kg', 250000, 275000);
        ProductPrice::create(['product_id' => $urea->id, 'customer_group_id' => $petani->id, 'price' => 265000]);
        $this->makeProduct($this->warehouse, 'ZA-1', 'ZA 50kg', 120000, 140000);
        $this->makeProduct($this->warehouse, 'OLD-1', 'Produk Lama', 1000, 2000)->delete();
        $this->makeProduct($this->otherGroup, 'PAKAN-1', 'Pakan Ayam', 5000, 6000);

        $this->actingAs($this->master)->post(route('backoffice.branches.store'), [
            'code' => 'cbg-baru',
            'name' => 'Toko Baru',
            'parent_id' => $this->office->id,
        ])->assertRedirect(route('backoffice.branches.index'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '2 produk dari cabang acuan'));

        $newBranch = Branch::where('code', 'CBG-BARU')->firstOrFail();
        $products = Product::withoutGlobalScopes()->where('branch_id', $newBranch->id)->orderBy('sku')->get();

        $this->assertSame(['UREA-1', 'ZA-1'], $products->pluck('sku')->all());
        $newUrea = $products->firstWhere('sku', 'UREA-1');
        $this->assertSame('Urea 50kg', $newUrea->name);
        $this->assertEquals(250000, (float) $newUrea->purchase_price);
        $this->assertEquals(275000, (float) $newUrea->selling_price);
        $this->assertEquals(265000, (float) $newUrea->getPriceForGroup($petani->id));
        $this->assertSame(0, $newUrea->stock);
        $this->assertDatabaseHas('inventories', ['branch_id' => $newBranch->id, 'product_id' => $newUrea->id, 'quantity' => 0]);

        $admin = User::factory()->create(['branch_id' => $newBranch->id, 'role' => 'branch_admin']);
        $this->actingAs($admin)->get(route('inventory.opening-stock.create'))
            ->assertOk()
            ->assertSee('Urea 50kg')
            ->assertSee('ZA 50kg')
            ->assertDontSee('Pakan Ayam');

        $this->actingAs($this->master)->post(route('backoffice.branches.store'), [
            'code' => 'PAKAN-BARU',
            'name' => 'Pakan Baru',
            'parent_id' => $this->otherGroup->id,
        ])->assertRedirect(route('backoffice.branches.index'));

        $this->assertSame(0, Product::withoutGlobalScopes()->where('branch_id', Branch::where('code', 'PAKAN-BARU')->value('id'))->count());
    }

    public function test_cashier_sees_master_selling_price_without_purchase_price(): void
    {
        $cashier = User::factory()->create(['branch_id' => $this->store->id, 'role' => 'cashier']);
        $this->makeProduct($this->store, 'ZA-1', 'Pupuk ZA', 4000, 5500);

        $this->actingAs($cashier)->get('/pos')->assertOk()->assertSee('5500.00');

        $this->actingAs($cashier)->get(route('backoffice.products.index'))
            ->assertOk()
            ->assertSee('Pupuk ZA')
            ->assertDontSee('Harga Beli (HPP)');
    }

    public function test_sync_command_simulates_by_default_and_applies_from_source_branch(): void
    {
        $this->makeProduct($this->warehouse, 'SP36', 'SP-36', 3000, 4000);
        $this->makeProduct($this->store, 'SP36', 'SP-36 Toko', 3000, 4500);
        $this->makeProduct($this->store, 'ONLY-STORE', 'Produk Toko Saja', 100, 200);

        $this->artisan('catalog:sync-master', ['--source' => ['GUDANG-PUPUK']])
            ->expectsOutputToContain('acuan GUDANG-PUPUK')
            ->expectsOutputToContain('Tidak ada di acuan: [CBG-ABI] ONLY-STORE')
            ->assertSuccessful();

        $this->assertEquals(4500, (float) $this->productIn($this->store, 'SP36')->selling_price);
        $this->assertNull(Product::withoutGlobalScopes()->where('branch_id', $this->office->id)->where('sku', 'SP36')->first());

        $this->artisan('catalog:sync-master', ['--source' => ['GUDANG-PUPUK'], '--apply' => true])
            ->expectsConfirmation('1 produk acuan perlu disamakan, 0 produk cabang dihubungkan lewat nama, 0 produk cabang disembunyikan. Lanjutkan?', 'yes')
            ->assertSuccessful();

        $this->assertSame('SP-36', $this->productIn($this->store, 'SP36')->name);
        $this->assertEquals(4000, (float) $this->productIn($this->store, 'SP36')->selling_price);
        $this->assertSame(10, $this->productIn($this->store, 'SP36')->stock);
        $this->assertEquals(4000, (float) $this->productIn($this->office, 'SP36')->selling_price);
        $this->assertSame(0, $this->productIn($this->office, 'SP36')->stock);
        $this->assertSame('Produk Toko Saja', $this->productIn($this->store, 'ONLY-STORE')->name);
    }

    public function test_sync_command_relinks_same_name_products_without_duplicates_or_stock_changes(): void
    {
        $this->makeProduct($this->warehouse, 'AJQ-1', 'AJI QUAT 1L*20', 50000, 60000);
        $this->makeProduct($this->warehouse, 'OMNI-1', 'OMNI NET 200GR*50', 7000, 9000);
        $storeAji = $this->makeProduct($this->store, 'BR2-ZAYYIM', 'aji quat  1l*20', 48000, 65000);
        $storeAji->update(['stock' => 7]);
        $this->makeProduct($this->store, 'OMNI-1', 'OMNI NET 200GR*50', 7000, 9000);
        $storeOmniDuplicate = $this->makeProduct($this->store, 'BR2-OM5OHZ', 'OMNI NET 200GR*50', 7000, 9500);

        $this->artisan('catalog:sync-master', ['--source' => ['GUDANG-PUPUK'], '--match-name' => true])
            ->expectsOutputToContain('Dihubungkan ke AJQ-1: [CBG-ABI] BR2-ZAYYIM')
            ->expectsOutputToContain('Cabang sudah punya SKU OMNI-1, perlu digabung manual: [CBG-ABI] BR2-OM5OHZ')
            ->assertSuccessful();

        $this->assertSame('BR2-ZAYYIM', $storeAji->fresh()->sku);

        $this->artisan('catalog:sync-master', ['--source' => ['GUDANG-PUPUK'], '--match-name' => true, '--apply' => true])
            ->expectsConfirmation('2 produk acuan perlu disamakan, 1 produk cabang dihubungkan lewat nama, 0 produk cabang disembunyikan. Lanjutkan?', 'yes')
            ->assertSuccessful();

        $storeAji->refresh();
        $this->assertSame('AJQ-1', $storeAji->sku);
        $this->assertSame('AJI QUAT 1L*20', $storeAji->name);
        $this->assertEquals(50000, (float) $storeAji->purchase_price);
        $this->assertEquals(60000, (float) $storeAji->selling_price);
        $this->assertSame(7, $storeAji->stock);
        $this->assertSame(1, Product::withoutGlobalScopes()->where('branch_id', $this->store->id)->where('sku', 'AJQ-1')->count());

        $this->assertSame('BR2-OM5OHZ', $storeOmniDuplicate->fresh()->sku);
        $this->assertEquals(9500, (float) $storeOmniDuplicate->fresh()->selling_price);
    }

    public function test_hold_cases_hides_unmatched_products_and_purge_keeps_those_with_history(): void
    {
        $cashier = User::factory()->create(['branch_id' => $this->store->id, 'role' => 'cashier']);
        $this->makeProduct($this->warehouse, 'OMNI-1', 'OMNI NET 200GR*50', 7000, 9000);
        $this->makeProduct($this->store, 'OMNI-1', 'OMNI NET 200GR*50', 7000, 9000);
        $duplicate = $this->makeProduct($this->store, 'BR2-OM5OHZ', 'OMNI NET 200GR*50', 7000, 9500);
        $storeOnly = $this->makeProduct($this->store, 'BR2-PRIMA', 'PRIMA BOMB 1L*20', 30000, 40000);
        $storeOnly->update(['stock' => 3]);
        Sale::create([
            'branch_id' => $this->store->id,
            'receipt_number' => 'INV-HOLD-01',
            'total_amount' => 40000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $cashier->id,
        ])->items()->create(['product_id' => $storeOnly->id, 'quantity' => 1, 'price' => 40000, 'subtotal' => 40000]);

        $this->artisan('catalog:sync-master', ['--source' => ['GUDANG-PUPUK'], '--match-name' => true, '--hold-cases' => true, '--apply' => true])
            ->expectsOutputToContain('Cabang sudah punya SKU OMNI-1, perlu digabung manual: [CBG-ABI] BR2-OM5OHZ')
            ->expectsOutputToContain('Tidak ada di acuan: [CBG-ABI] BR2-PRIMA PRIMA BOMB 1L*20 (stok 3)')
            ->expectsConfirmation('1 produk acuan perlu disamakan, 0 produk cabang dihubungkan lewat nama, 2 produk cabang disembunyikan. Lanjutkan?', 'yes')
            ->expectsOutputToContain('Daftar produk yang disembunyikan:')
            ->assertSuccessful();

        $this->assertSoftDeleted('products', ['id' => $duplicate->id]);
        $this->assertSoftDeleted('products', ['id' => $storeOnly->id, 'stock' => 3, 'catalog_hold_reason' => 'Tidak ada di acuan']);
        $this->actingAs($cashier)->get('/pos')->assertOk()->assertDontSee('PRIMA BOMB');
        $master = User::factory()->create(['branch_id' => $this->warehouse->id, 'role' => 'master']);
        $this->actingAs($master)->get(route('backoffice.products.index', ['branch_id' => $this->store->id]))
            ->assertOk()->assertSee('OMNI-1')->assertDontSee('BR2-PRIMA');

        $this->artisan('catalog:held-products')
            ->expectsOutputToContain('BR2-OM5OHZ')
            ->assertSuccessful();

        $this->artisan('catalog:held-products', ['--purge' => true])
            ->expectsConfirmation('1 produk akan dihapus permanen, 1 produk tetap diarsipkan karena punya riwayat. Lanjutkan?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('products', ['id' => $duplicate->id]);
        $this->assertSoftDeleted('products', ['id' => $storeOnly->id]);
    }

    private function makeProduct(Branch $branch, string $sku, string $name, int $cost, int $price): Product
    {
        return Product::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'category_id' => $this->category->id,
            'sku' => $sku,
            'name' => $name,
            'unit' => 'pcs',
            'purchase_price' => $cost,
            'selling_price' => $price,
            'stock' => 10,
            'is_taxable' => true,
        ]);
    }

    private function productIn(Branch $branch, string $sku): Product
    {
        return Product::withoutGlobalScopes()->where('branch_id', $branch->id)->where('sku', $sku)->firstOrFail();
    }
}
