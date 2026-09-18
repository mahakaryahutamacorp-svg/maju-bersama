<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_levels_are_seeded_with_expected_defaults(): void
    {
        $this->assertDatabaseCount('price_levels', 3);

        $eceran = PriceLevel::where('name', 'Harga Eceran')->first();
        $this->assertNotNull($eceran);
        $this->assertTrue($eceran->is_default);
        $this->assertTrue($eceran->is_active);

        $grosir = PriceLevel::where('name', 'Harga Grosir')->first();
        $this->assertNotNull($grosir);
        $this->assertFalse($grosir->is_default);
        $this->assertTrue($grosir->is_active);

        $member = PriceLevel::where('name', 'Harga Member')->first();
        $this->assertNotNull($member);
        $this->assertFalse($member->is_default);
        $this->assertTrue($member->is_active);
    }

    public function test_product_prices_relation_and_get_price_fallback(): void
    {
        $branch = Branch::create(['name' => 'Branch Test', 'code' => 'BT1']);
        $category = Category::create(['name' => 'General']);

        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'TEST-001',
            'name' => 'Item Testing',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'stock' => 10,
        ]);

        $grosirLevel = PriceLevel::where('name', 'Harga Grosir')->first();
        $memberLevel = PriceLevel::where('name', 'Harga Member')->first();

        // Initially no tiered prices entered
        $this->assertEquals('15000.00', $product->getPrice());
        $this->assertEquals('15000.00', $product->getPrice(null));
        $this->assertEquals('15000.00', $product->getPrice($grosirLevel->id)); // falls back to selling_price

        // Add a price for Grosir
        ProductPrice::create([
            'product_id' => $product->id,
            'price_level_id' => $grosirLevel->id,
            'price' => 13500,
        ]);

        // Fresh instance without eager loading
        $freshProduct = Product::withoutGlobalScopes()->find($product->id);
        $this->assertEquals('13500.00', $freshProduct->getPrice($grosirLevel->id));
        $this->assertEquals('15000.00', $freshProduct->getPrice($memberLevel->id)); // still falls back

        // Instance with eager loaded productPrices
        $loadedProduct = Product::withoutGlobalScopes()->with('productPrices')->find($product->id);
        $this->assertTrue($loadedProduct->relationLoaded('productPrices'));
        $this->assertEquals('13500.00', $loadedProduct->getPrice($grosirLevel->id));
        $this->assertEquals('15000.00', $loadedProduct->getPrice($memberLevel->id));
    }

    public function test_product_price_unique_constraint(): void
    {
        $branch = Branch::create(['name' => 'Branch Test', 'code' => 'BT2']);
        $category = Category::create(['name' => 'General']);

        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'TEST-002',
            'name' => 'Item Unique',
            'purchase_price' => 10000,
            'selling_price' => 20000,
            'stock' => 5,
        ]);

        $level = PriceLevel::first();

        ProductPrice::create([
            'product_id' => $product->id,
            'price_level_id' => $level->id,
            'price' => 20000,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ProductPrice::create([
            'product_id' => $product->id,
            'price_level_id' => $level->id,
            'price' => 19000,
        ]);
    }

    public function test_cascade_delete_on_product(): void
    {
        $branch = Branch::create(['name' => 'Branch Test', 'code' => 'BT3']);
        $category = Category::create(['name' => 'General']);

        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'TEST-003',
            'name' => 'Item Delete Cascade',
            'purchase_price' => 10000,
            'selling_price' => 20000,
            'stock' => 5,
        ]);

        $level = PriceLevel::first();

        ProductPrice::create([
            'product_id' => $product->id,
            'price_level_id' => $level->id,
            'price' => 20000,
        ]);

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'price_level_id' => $level->id,
        ]);

        $product->delete();

        $this->assertDatabaseMissing('product_prices', [
            'product_id' => $product->id,
        ]);
    }

    public function test_product_create_and_edit_page_renders_price_levels(): void
    {
        $branch = Branch::create(['name' => 'Branch Test', 'code' => 'BT4']);
        $category = Category::create(['name' => 'General']);
        $admin = \App\Models\User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'admin',
        ]);

        $createResponse = $this->actingAs($admin)->get(route('backoffice.products.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee('Penetapan Tingkat Harga');
        $createResponse->assertSee('Harga Eceran');
        $createResponse->assertSee('Harga Grosir');
        $createResponse->assertSee('Harga Member');

        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'TEST-EDIT-01',
            'name' => 'Item To Edit',
            'purchase_price' => 10000,
            'selling_price' => 25000,
            'stock' => 10,
        ]);

        $editResponse = $this->actingAs($admin)->get(route('backoffice.products.edit', $product->id));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('Penetapan Tingkat Harga');
        $editResponse->assertSee('Harga Eceran');
        $editResponse->assertSee('25000');
    }

    public function test_product_store_with_tiered_prices_syncs_product_prices_and_selling_price(): void
    {
        $branch = Branch::create(['name' => 'Branch Test', 'code' => 'BT5']);
        $category = Category::create(['name' => 'General']);
        $admin = \App\Models\User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'admin',
        ]);

        $eceran = PriceLevel::where('name', 'Harga Eceran')->first();
        $grosir = PriceLevel::where('name', 'Harga Grosir')->first();
        $member = PriceLevel::where('name', 'Harga Member')->first();

        $response = $this->actingAs($admin)->post(route('backoffice.products.store'), [
            'name' => 'Produk Multi Tier',
            'sku' => 'SKU-TIER-01',
            'category_id' => $category->id,
            'purchase_price' => 20000,
            'prices' => [
                $eceran->id => 30000,
                $grosir->id => 27000,
                $member->id => 28500,
            ],
            'stock' => 15,
        ]);

        $response->assertRedirect(route('backoffice.products.index'));

        $product = Product::withoutGlobalScopes()->where('sku', 'SKU-TIER-01')->first();
        $this->assertNotNull($product);
        // selling_price fallback populated with Harga Eceran
        $this->assertEquals('30000.00', $product->selling_price);

        // Product prices synced
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'price_level_id' => $eceran->id,
            'price' => 30000,
        ]);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'price_level_id' => $grosir->id,
            'price' => 27000,
        ]);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'price_level_id' => $member->id,
            'price' => 28500,
        ]);

        // getPrice() helper
        $this->assertEquals('30000.00', $product->getPrice());
        $this->assertEquals('27000.00', $product->getPrice($grosir->id));
        $this->assertEquals('28500.00', $product->getPrice($member->id));
    }

    public function test_product_update_with_tiered_prices_updates_and_deletes_cleared_tiers(): void
    {
        $branch = Branch::create(['name' => 'Branch Test', 'code' => 'BT6']);
        $category = Category::create(['name' => 'General']);
        $admin = \App\Models\User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'admin',
        ]);

        $eceran = PriceLevel::where('name', 'Harga Eceran')->first();
        $grosir = PriceLevel::where('name', 'Harga Grosir')->first();

        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'SKU-UPDATE-01',
            'name' => 'Produk Awal',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'stock' => 10,
        ]);

        ProductPrice::create(['product_id' => $product->id, 'price_level_id' => $eceran->id, 'price' => 15000]);
        ProductPrice::create(['product_id' => $product->id, 'price_level_id' => $grosir->id, 'price' => 13000]);

        // Update: increase Eceran to 16000, clear Grosir (empty string)
        $response = $this->actingAs($admin)->put(route('backoffice.products.update', $product->id), [
            'name' => 'Produk Terupdate',
            'sku' => 'SKU-UPDATE-01',
            'category_id' => $category->id,
            'purchase_price' => 11000,
            'prices' => [
                $eceran->id => 16000,
                $grosir->id => '',
            ],
        ]);

        $response->assertRedirect(route('backoffice.products.index'));

        $freshProduct = Product::withoutGlobalScopes()->find($product->id);
        $this->assertEquals('16000.00', $freshProduct->selling_price);

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'price_level_id' => $eceran->id,
            'price' => 16000,
        ]);

        // Cleared tier deleted
        $this->assertDatabaseMissing('product_prices', [
            'product_id' => $product->id,
            'price_level_id' => $grosir->id,
        ]);
    }

    public function test_product_index_displays_multi_price_badge(): void
    {
        $branch = Branch::create(['name' => 'Branch Test', 'code' => 'BT7']);
        $category = Category::create(['name' => 'General']);
        $admin = \App\Models\User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'admin',
        ]);

        $eceran = PriceLevel::where('name', 'Harga Eceran')->first();
        $grosir = PriceLevel::where('name', 'Harga Grosir')->first();

        // Product 1: Single price
        $singlePriceProduct = Product::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'SINGLE-01',
            'name' => 'Produk Single Price',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'stock' => 10,
        ]);
        ProductPrice::create(['product_id' => $singlePriceProduct->id, 'price_level_id' => $eceran->id, 'price' => 15000]);

        // Product 2: Multi price
        $multiPriceProduct = Product::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'MULTI-01',
            'name' => 'Produk Multi Price Tiered',
            'purchase_price' => 10000,
            'selling_price' => 20000,
            'stock' => 10,
        ]);
        ProductPrice::create(['product_id' => $multiPriceProduct->id, 'price_level_id' => $eceran->id, 'price' => 20000]);
        ProductPrice::create(['product_id' => $multiPriceProduct->id, 'price_level_id' => $grosir->id, 'price' => 18000]);

        $response = $this->actingAs($admin)->get(route('backoffice.products.index'));
        $response->assertStatus(200);
        $response->assertSee('Produk Single Price');
        $response->assertSee('Produk Multi Price Tiered');
        $response->assertSee('(+ Multi Harga)');
    }
}
