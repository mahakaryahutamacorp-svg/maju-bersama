<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\CustomerGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiPriceWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private Category $category;
    private CustomerGroup $groupUmum;
    private CustomerGroup $groupPetani;
    private CustomerGroup $groupGrosir;
    private Customer $customerUmum;
    private Customer $customerPetani;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountSeeder::class);
        $this->seed(CustomerGroupSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Cabang Pertanian Sleman',
            'code' => 'SLM-01',
            'address' => 'Jl. Magelang KM 10',
            'phone' => '08123456789',
        ]);

        $this->user = User::create([
            'name' => 'Kasir Toko',
            'email' => 'kasir@majubersama.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'branch_id' => $this->branch->id,
        ]);

        $register = CashRegister::create([
            'branch_id' => $this->branch->id,
            'name' => 'Kasir 1',
            'is_active' => true,
        ]);

        CashRegisterShift::create([
            'branch_id' => $this->branch->id,
            'cash_register_id' => $register->id,
            'user_id' => $this->user->id,
            'opened_at' => now(),
            'opening_balance' => 500000,
            'status' => 'open',
        ]);

        $this->category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Pupuk & Nutrisi',
        ]);

        $this->groupUmum = CustomerGroup::where('name', 'Umum/Retail')->firstOrFail();
        $this->groupPetani = CustomerGroup::where('name', 'Petani')->firstOrFail();
        $this->groupGrosir = CustomerGroup::where('name', 'Grosir')->firstOrFail();

        $this->customerUmum = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Budi Retail',
            'phone' => '0811111111',
            'customer_group_id' => $this->groupUmum->id,
        ]);

        $this->customerPetani = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Pak Joko Kelompok Tani',
            'phone' => '0822222222',
            'customer_group_id' => $this->groupPetani->id,
        ]);

        // Produk A dengan Harga Dasar = 100.000, HPP = 70.000, Stok = 50
        $this->product = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'PUPUK-NPK-01',
            'name' => 'Pupuk NPK Mutiara 1kg',
            'purchase_price' => 70000,
            'selling_price' => 100000,
            'stock' => 50,
        ]);

        // Tetapkan Harga Khusus: Petani = 85.000, Grosir = 80.000
        ProductPrice::create([
            'product_id' => $this->product->id,
            'customer_group_id' => $this->groupPetani->id,
            'price' => 85000,
        ]);

        ProductPrice::create([
            'product_id' => $this->product->id,
            'customer_group_id' => $this->groupGrosir->id,
            'price' => 80000,
        ]);
    }

    public function test_get_price_for_group_helper_logic(): void
    {
        // 1. Grup Petani -> Harga Khusus Petani (85.000)
        $this->assertEquals(85000, (float) $this->product->getPriceForGroup($this->groupPetani->id));

        // 2. Grup Grosir -> Harga Khusus Grosir (80.000)
        $this->assertEquals(80000, (float) $this->product->getPriceForGroup($this->groupGrosir->id));

        // 3. Grup Umum/Retail (tidak ada tier khusus) -> Fallback ke Harga Dasar (100.000)
        $this->assertEquals(100000, (float) $this->product->getPriceForGroup($this->groupUmum->id));

        // 4. Tanpa grup (null) -> Fallback ke Harga Dasar (100.000)
        $this->assertEquals(100000, (float) $this->product->getPriceForGroup(null));

        // 5. Grup non-existent -> Fallback ke Harga Dasar (100.000)
        $this->assertEquals(100000, (float) $this->product->getPriceForGroup(99999));
    }

    public function test_scenario_1_customer_umum_buys_product_at_base_price(): void
    {
        Sanctum::actingAs($this->user);

        // Pelanggan Umum membeli 2 unit Produk A
        $response = $this->postJson('/api/checkout', [
            'customer_id' => $this->customerUmum->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');

        // Total harus 2 x 100.000 = 200.000 (Harga Dasar) -> 20.000.000 cents
        $sale = $response->json('sale');
        $this->assertEquals(20000000, $sale['total_amount']);

        $saleItem = $sale['items'][0];
        $this->assertEquals(10000000, $saleItem['price']);
        $this->assertEquals(20000000, $saleItem['subtotal']);
    }

    public function test_scenario_2_customer_petani_buys_product_at_petani_tiered_price(): void
    {
        Sanctum::actingAs($this->user);

        // Pelanggan Petani membeli 2 unit Produk A
        $response = $this->postJson('/api/checkout', [
            'customer_id' => $this->customerPetani->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');

        // Total harus 2 x 85.000 = 170.000 (Harga Khusus Petani) -> 17.000.000 cents
        $sale = $response->json('sale');
        $this->assertEquals(17000000, $sale['total_amount']);

        $saleItem = $sale['items'][0];
        $this->assertEquals(8500000, $saleItem['price']);
        $this->assertEquals(17000000, $saleItem['subtotal']);
    }

    public function test_pos_products_api_returns_group_adjusted_prices(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Panggilan API untuk Pelanggan Petani
        $responsePetani = $this->getJson("/api/products?customer_id={$this->customerPetani->id}");
        $responsePetani->assertStatus(200);

        $productsPetani = collect($responsePetani->json('data'));
        $targetProductPetani = $productsPetani->firstWhere('id', $this->product->id);
        $this->assertNotNull($targetProductPetani);
        $this->assertEquals(85000, (float) $targetProductPetani['selling_price']);

        // 2. Panggilan API untuk Pelanggan Umum
        $responseUmum = $this->getJson("/api/products?customer_id={$this->customerUmum->id}");
        $responseUmum->assertStatus(200);

        $productsUmum = collect($responseUmum->json('data'));
        $targetProductUmum = $productsUmum->firstWhere('id', $this->product->id);
        $this->assertNotNull($targetProductUmum);
        $this->assertEquals(100000, (float) $targetProductUmum['selling_price']);

        // 3. Scan barcode dengan parameter customer_id Petani
        $responseBarcode = $this->getJson("/api/products/barcode/{$this->product->sku}?customer_id={$this->customerPetani->id}");
        $responseBarcode->assertStatus(200);
        $this->assertEquals(85000, (float) $responseBarcode->json('data.selling_price'));
    }

    public function test_backoffice_product_store_and_update_with_customer_group_prices(): void
    {
        $admin = $this->user;

        // 1. Simpan produk baru dengan harga khusus CustomerGroup
        $response = $this->actingAs($admin)->post(route('backoffice.products.store'), [
            'name' => 'Bibit Jagung Manis Hibrida',
            'sku' => 'BBT-JAGUNG-01',
            'category_id' => $this->category->id,
            'purchase_price' => 50000,
            'selling_price' => 75000,
            'customer_group_prices' => [
                $this->groupPetani->id => 65000,
                $this->groupGrosir->id => 60000,
            ],
            'stock' => 20,
        ]);

        $response->assertRedirect(route('backoffice.products.index'));

        $newProduct = Product::withoutGlobalScopes()->where('sku', 'BBT-JAGUNG-01')->firstOrFail();
        $this->assertEquals('75000.00', $newProduct->selling_price);

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $newProduct->id,
            'customer_group_id' => $this->groupPetani->id,
            'price' => 65000,
        ]);

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $newProduct->id,
            'customer_group_id' => $this->groupGrosir->id,
            'price' => 60000,
        ]);

        // 2. Update harga khusus Petani dan hapus Grosir (kirim string kosong)
        $updateResponse = $this->actingAs($admin)->put(route('backoffice.products.update', $newProduct->id), [
            'name' => 'Bibit Jagung Manis Hibrida Unggul',
            'sku' => 'BBT-JAGUNG-01',
            'category_id' => $this->category->id,
            'purchase_price' => 50000,
            'selling_price' => 75000,
            'customer_group_prices' => [
                $this->groupPetani->id => 63000,
                $this->groupGrosir->id => '',
            ],
        ]);

        $updateResponse->assertRedirect(route('backoffice.products.index'));

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $newProduct->id,
            'customer_group_id' => $this->groupPetani->id,
            'price' => 63000,
        ]);

        $this->assertDatabaseMissing('product_prices', [
            'product_id' => $newProduct->id,
            'customer_group_id' => $this->groupGrosir->id,
        ]);
    }

    public function test_customer_crud_with_customer_group_selection(): void
    {
        $admin = $this->user;

        // View create & edit page
        $createPage = $this->actingAs($admin)->get(route('backoffice.customers.create'));
        $createPage->assertStatus(200);
        $createPage->assertSee('Grup Pelanggan');
        $createPage->assertSee('Petani');

        // Store new customer
        $storeResponse = $this->actingAs($admin)->post(route('backoffice.customers.store'), [
            'name' => 'Haji Mansur',
            'phone' => '0877889900',
            'email' => 'mansur@petani.id',
            'address' => 'Desa Pakem',
            'customer_group_id' => $this->groupPetani->id,
        ]);

        $storeResponse->assertRedirect(route('backoffice.customers.index'));

        $this->assertDatabaseHas('customers', [
            'name' => 'Haji Mansur',
            'customer_group_id' => $this->groupPetani->id,
        ]);

        $customer = Customer::where('name', 'Haji Mansur')->firstOrFail();
        $this->assertEquals($this->groupPetani->id, $customer->customerGroup->id);
    }
}
