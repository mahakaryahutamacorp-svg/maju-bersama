<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchPricingWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $centralBranch;
    private Branch $branchA;
    private User $centralAdmin;
    private User $cashierA;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Cabang Pusat dan Cabang A
        $this->centralBranch = Branch::create([
            'name' => 'Kantor Pusat',
            'code' => 'HQ',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $this->branchA = Branch::create([
            'name' => 'Cabang Arofah',
            'code' => 'CAB-ARF',
            'parent_id' => $this->centralBranch->id,
            'is_active' => true,
        ]);

        // 2. Setup Pengguna
        $this->centralAdmin = User::factory()->create([
            'branch_id' => $this->centralBranch->id,
            'role' => 'admin',
        ]);

        $this->cashierA = User::create([
            'name' => 'Kasir Arofah',
            'email' => 'kasir.arofah@example.com',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'branch_id' => $this->branchA->id,
        ]);

        $this->category = Category::create([
            'branch_id' => $this->centralBranch->id,
            'name' => 'Pupuk & Kimia Pertanian',
        ]);
    }

    /**
     * Uji helper getPriceForBranch() dan relasi branchPrices()
     */
    public function test_get_price_for_branch_returns_branch_price_or_falls_back_to_standard(): void
    {
        // Produk X: Harga Pusat = 10.000
        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->centralBranch->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-X',
            'name' => 'Produk X',
            'unit' => 'pcs',
            'purchase_price' => 8000,
            'selling_price' => 10000,
            'stock' => 100,
        ]);

        // Override harga di Cabang A = 12.000
        ProductBranchPrice::create([
            'product_id' => $product->id,
            'branch_id' => $this->branchA->id,
            'price' => 12000,
        ]);

        // Verifikasi relasi
        $this->assertTrue($product->branchPrices()->where('branch_id', $this->branchA->id)->exists());
        $this->assertCount(1, $product->fresh()->branchPrices);

        // Kasir / Cabang A harus mendapatkan 12.000
        $this->assertEquals(12000, (float) $product->getPriceForBranch($this->branchA->id));

        // Cabang Pusat atau cabang lain tanpa override harus fallback ke 10.000
        $this->assertEquals(10000, (float) $product->getPriceForBranch($this->centralBranch->id));
        $this->assertEquals(10000, (float) $product->getPriceForBranch(null));

        $otherBranch = Branch::create(['name' => 'Cabang B', 'code' => 'CAB-B', 'is_active' => true]);
        $this->assertEquals(10000, (float) $product->getPriceForBranch($otherBranch->id));
    }

    /**
     * Skenario Utama: Produk X harga pusat 10.000, harga Cabang A 12.000.
     * Saat kasir Cabang A memuat produk di POS (/pos), harganya harus 12.000.
     */
    public function test_cashier_in_branch_a_loads_pos_with_branch_specific_price(): void
    {
        // Buat Produk X dengan harga standar 10.000
        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->centralBranch->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-X-POS',
            'name' => 'Produk X Multi-Pricing',
            'unit' => 'pcs',
            'purchase_price' => 8000,
            'selling_price' => 10000,
            'stock' => 50,
        ]);

        // Set harga override untuk Cabang A = 12.000
        ProductBranchPrice::create([
            'product_id' => $product->id,
            'branch_id' => $this->branchA->id,
            'price' => 12000,
        ]);

        // 1. Akses POS sebagai Kasir Cabang A
        $responseA = $this->actingAs($this->cashierA)->get('/pos');
        $responseA->assertOk();
        $responseA->assertSee('Produk X Multi-Pricing');
        // Pastikan harga data yang dimuat ke state POS adalah 12000.00
        $responseA->assertSee('12000.00');

        // 2. Akses POS sebagai Kasir Pusat
        $cashierCentral = User::create([
            'name' => 'Kasir Pusat',
            'email' => 'kasir.pusat@example.com',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'branch_id' => $this->centralBranch->id,
        ]);

        $responseCentral = $this->actingAs($cashierCentral)->get('/pos');
        $responseCentral->assertOk();
        $responseCentral->assertSee('Produk X Multi-Pricing');
        $responseCentral->assertSee('10000.00');
    }

    /**
     * Uji API ProductController mengembalikan harga Cabang A bagi user Cabang A
     */
    public function test_api_product_endpoints_return_branch_level_pricing(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-API-01',
            'barcode' => '8991234567890',
            'name' => 'Produk API Test',
            'selling_price' => 10000,
            'purchase_price' => 7000,
            'stock' => 30,
        ]);

        ProductBranchPrice::create([
            'product_id' => $product->id,
            'branch_id' => $this->branchA->id,
            'price' => 12000,
        ]);

        Sanctum::actingAs($this->cashierA);

        // API index
        $indexResponse = $this->getJson('/api/products');
        $indexResponse->assertOk();
        $this->assertEquals(12000, (float) $indexResponse->json('data.0.base_price'));
        $this->assertEquals(12000, (float) $indexResponse->json('data.0.price'));

        // API show
        $showResponse = $this->getJson("/api/products/{$product->id}");
        $showResponse->assertOk();
        $this->assertEquals(12000, (float) $showResponse->json('data.price'));
        $this->assertEquals(12000, (float) $showResponse->json('data.base_price'));

        // API barcode (via sku)
        $barcodeResponse = $this->getJson("/api/products/barcode/{$product->sku}");
        $barcodeResponse->assertOk();
        $this->assertEquals(12000, (float) $barcodeResponse->json('data.price'));
        $this->assertEquals(12000, (float) $barcodeResponse->json('data.base_price'));
    }

    /**
     * Uji Master Produk Backoffice: Form create & edit me-render dynamic input per cabang
     * dan dapat menyimpan/mengupdate product_branch_prices.
     */
    public function test_backoffice_product_form_renders_and_syncs_branch_prices(): void
    {
        // 1. Halaman Create menampilkan input Cabang Arofah
        $createPage = $this->actingAs($this->centralAdmin)->get(route('backoffice.products.create'));
        $createPage->assertOk();
        $createPage->assertSee('Harga Jual Per Cabang (Branch-Level Pricing)');
        $createPage->assertSee('Cabang Arofah');

        // 2. Simpan produk baru dengan harga cabang
        $storeResponse = $this->actingAs($this->centralAdmin)->post(route('backoffice.products.store'), [
            'name' => 'Produk Master Cabang Baru',
            'sku' => 'MST-CBG-01',
            'category_id' => $this->category->id,
            'purchase_price' => 7000,
            'selling_price' => 10000,
            'stock' => 15,
            'branch_prices' => [
                $this->branchA->id => 12000,
            ],
        ]);

        $storeResponse->assertRedirect(route('backoffice.products.index'));

        $product = Product::withoutGlobalScopes()->where('sku', 'MST-CBG-01')->firstOrFail();
        $this->assertDatabaseHas('product_branch_prices', [
            'product_id' => $product->id,
            'branch_id' => $this->branchA->id,
            'price' => 12000,
        ]);

        // 3. Halaman Edit menampilkan harga yang tersimpan
        $editPage = $this->actingAs($this->centralAdmin)->get(route('backoffice.products.edit', $product->id));
        $editPage->assertOk();
        $editPage->assertSee('value="12000"', false);

        // 4. Update harga cabang menjadi 13.500
        $updateResponse = $this->actingAs($this->centralAdmin)->put(route('backoffice.products.update', $product->id), [
            'name' => 'Produk Master Cabang Baru Edited',
            'sku' => 'MST-CBG-01',
            'category_id' => $this->category->id,
            'purchase_price' => 7000,
            'selling_price' => 10000,
            'stock' => 15,
            'branch_prices' => [
                $this->branchA->id => 13500,
            ],
        ]);

        $updateResponse->assertRedirect(route('backoffice.products.index'));
        $this->assertDatabaseHas('product_branch_prices', [
            'product_id' => $product->id,
            'branch_id' => $this->branchA->id,
            'price' => 13500,
        ]);
    }
}
