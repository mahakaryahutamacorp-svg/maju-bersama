<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiPricingWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Cabang Utama',
            'code' => 'C01',
        ]);

        $this->admin = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'admin',
        ]);

        $this->category = Category::create([
            'name' => 'Pertanian & Pupuk',
        ]);
    }

    public function test_get_price_for_group_returns_group_price_or_fallback_default(): void
    {
        // 1. Buat grup pelanggan 'Petani'
        $groupPetani = CustomerGroup::create([
            'name' => 'Petani',
            'description' => 'Komunitas petani kemitraan (Margin 10%)',
        ]);

        // 2. Buat produk dengan harga default 10.000
        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'NPK-10K',
            'name' => 'Pupuk NPK 1kg',
            'unit' => 'kg',
            'purchase_price' => 8000,
            'selling_price' => 10000,
            'stock' => 50,
        ]);

        // 3. Buat harga khusus 'Petani' 9.000
        ProductPrice::create([
            'product_id' => $product->id,
            'customer_group_id' => $groupPetani->id,
            'price' => 9000,
        ]);

        // 4. Verifikasi relasi prices
        $this->assertTrue($product->prices()->where('customer_group_id', $groupPetani->id)->exists());
        $this->assertCount(1, $product->fresh()->prices);

        // 5. Verifikasi method getPriceForGroup
        // Jika groupId null -> harga default (10.000)
        $this->assertEquals(10000, (float) $product->getPriceForGroup(null));

        // Jika groupId adalah 'Petani' -> harga khusus (9.000)
        $this->assertEquals(9000, (float) $product->getPriceForGroup($groupPetani->id));

        // Jika groupId tidak memiliki override harga -> fallback ke harga default (10.000)
        $otherGroup = CustomerGroup::create(['name' => 'Retailer']);
        $this->assertEquals(10000, (float) $product->getPriceForGroup($otherGroup->id));
        $this->assertEquals(10000, (float) $product->getPriceForGroup(9999));
    }

    public function test_customer_belongs_to_customer_group(): void
    {
        $group = CustomerGroup::create([
            'name' => 'Petani Binaan',
        ]);

        $customer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Pak Tani Joko',
            'phone' => '081234567890',
            'customer_group_id' => $group->id,
        ]);

        $this->assertNotNull($customer->customerGroup);
        $this->assertEquals('Petani Binaan', $customer->customerGroup->name);
        $this->assertTrue($group->customers->contains($customer));
    }

    public function test_backoffice_product_create_and_edit_page_renders_customer_groups(): void
    {
        $groupPetani = CustomerGroup::firstOrCreate(['name' => 'Petani']);
        $groupRetailer = CustomerGroup::firstOrCreate(['name' => 'Retailer']);

        $response = $this->actingAs($this->admin)->get(route('backoffice.products.create'));
        $response->assertOk();
        $response->assertSee('Petani');
        $response->assertSee('Retailer');
        $response->assertSee('Harga Khusus Grup Pelanggan');

        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'TEST-EDIT',
            'name' => 'Benih Jagung',
            'purchase_price' => 5000,
            'selling_price' => 7000,
            'stock' => 10,
        ]);

        $editResponse = $this->actingAs($this->admin)->get(route('backoffice.products.edit', $product->id));
        $editResponse->assertOk();
        $editResponse->assertSee('Petani');
        $editResponse->assertSee('Retailer');
    }

    public function test_backoffice_store_and_update_syncs_customer_group_prices(): void
    {
        $groupPetani = CustomerGroup::firstOrCreate(['name' => 'Petani']);
        $groupRetailer = CustomerGroup::firstOrCreate(['name' => 'Retailer']);

        // 1. Simpan produk baru dengan harga khusus grup
        $storeResponse = $this->actingAs($this->admin)->post(route('backoffice.products.store'), [
            'name' => 'Herbisida Gramoxone 1L',
            'sku' => 'HRB-001',
            'category_id' => $this->category->id,
            'purchase_price' => 60000,
            'selling_price' => 75000,
            'stock' => 20,
            'customer_group_prices' => [
                $groupPetani->id => 68000,
                $groupRetailer->id => 71000,
            ],
        ]);

        $storeResponse->assertRedirect(route('backoffice.products.index'));

        $product = Product::withoutGlobalScopes()->where('sku', 'HRB-001')->first();
        $this->assertNotNull($product);

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'customer_group_id' => $groupPetani->id,
            'price' => 68000,
        ]);

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'customer_group_id' => $groupRetailer->id,
            'price' => 71000,
        ]);

        // Verifikasi getPriceForGroup
        $this->assertEquals(68000, (float) $product->getPriceForGroup($groupPetani->id));
        $this->assertEquals(71000, (float) $product->getPriceForGroup($groupRetailer->id));
        $this->assertEquals(75000, (float) $product->getPriceForGroup(null));

        // 2. Update harga khusus grup
        $updateResponse = $this->actingAs($this->admin)->put(route('backoffice.products.update', $product->id), [
            'name' => 'Herbisida Gramoxone 1L Updated',
            'sku' => 'HRB-001',
            'category_id' => $this->category->id,
            'purchase_price' => 60000,
            'selling_price' => 80000,
            'customer_group_prices' => [
                $groupPetani->id => 70000,
                $groupRetailer->id => '', // Kosongkan harga retailer
            ],
        ]);

        $updateResponse->assertRedirect(route('backoffice.products.index'));

        // Harga Petani terupdate
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'customer_group_id' => $groupPetani->id,
            'price' => 70000,
        ]);

        // Harga Retailer terhapus karena dikosongkan
        $this->assertDatabaseMissing('product_prices', [
            'product_id' => $product->id,
            'customer_group_id' => $groupRetailer->id,
        ]);

        $product->refresh();
        $this->assertEquals(70000, (float) $product->getPriceForGroup($groupPetani->id));
        // Fallback ke selling_price baru (80.000)
        $this->assertEquals(80000, (float) $product->getPriceForGroup($groupRetailer->id));
    }

    public function test_pos_screen_renders_customer_group_dropdown(): void
    {
        CustomerGroup::firstOrCreate(['name' => 'Petani']);
        CustomerGroup::firstOrCreate(['name' => 'Retailer']);

        $response = $this->actingAs($this->admin)->get('/pos');
        $response->assertOk();
        $response->assertSee('Tipe Pelanggan (Tingkat Harga)');
        $response->assertSee('Petani');
        $response->assertSee('Retailer');
    }
}
