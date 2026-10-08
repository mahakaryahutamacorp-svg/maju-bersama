<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $cashier;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Toko Cabang Utama',
            'code' => 'CAB-UTAMA',
            'address' => 'Jl. Protokol No. 88',
            'phone' => '081122334455',
        ]);

        $this->cashier = User::create([
            'name' => 'Siti Kasir',
            'email' => 'siti@example.com',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);

        $this->category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Makanan Ringan',
        ]);

        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-SNACK-01',
            'name' => 'Keripik Singkong Balado',
            'selling_price' => 15000,
            'purchase_price' => 10000,
            'stock' => 25,
        ]);

        ChartOfAccount::updateOrCreate(['code' => '1110'], ['name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::updateOrCreate(['code' => '1210'], ['name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::updateOrCreate(['code' => '4110'], ['name' => 'Pendapatan', 'type' => 'revenue']);
        ChartOfAccount::updateOrCreate(['code' => '4130'], ['name' => 'Potongan Penjualan', 'type' => 'revenue']);
        ChartOfAccount::updateOrCreate(['code' => '5100'], ['name' => 'Harga Pokok Penjualan', 'type' => 'expense']);
    }

    public function test_guest_cannot_access_pos_screen(): void
    {
        $response = $this->get('/pos');
        $response->assertRedirect('/login');
    }

    public function test_cashier_can_open_pos_screen_with_categories_and_products(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');
        $response->assertStatus(200);
        $response->assertSee('Kasir POS Multi-Store');
        $response->assertSee('Toko Cabang Utama');
        $response->assertSee('Makanan Ringan');
        $response->assertSee('Keripik Singkong Balado');
        $response->assertSee('Scan Barcode');
    }

    public function test_cashier_can_view_thermal_receipt(): void
    {
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'INV-20260916-0099',
            'total_amount' => 30000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 15000,
            'subtotal' => 30000,
        ]);

        $response = $this->actingAs($this->cashier)->get("/pos/receipt/{$sale->receipt_number}?cash=50000&change=20000");

        $response->assertStatus(200);
        $response->assertSee('MAJU BERSAMA');
        $response->assertSee('Toko Cabang Utama');
        $response->assertSee('Siti Kasir');
        $response->assertSee('INV-20260916-0099');
        $response->assertSee('Keripik Singkong Balado');
        $response->assertSee('30.000');
        $response->assertSee('50.000');
        $response->assertSee('20.000');
    }

    public function test_pos_screen_renders_discount_input_and_summary(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');
        $response->assertOk();
        $response->assertSee('Diskon (Rp)');
        $response->assertSee('pos-discount-input');
        $response->assertSee('Subtotal:');
    }

    public function test_cashier_can_checkout_with_nominal_discount_and_balanced_journal(): void
    {
        // Seed akun COA yang dibutuhkan
        ChartOfAccount::updateOrCreate(['code' => '1110'], ['name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::updateOrCreate(['code' => '1210'], ['name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::updateOrCreate(['code' => '4110'], ['name' => 'Pendapatan', 'type' => 'revenue']);
        ChartOfAccount::updateOrCreate(['code' => '4130'], ['name' => 'Potongan Penjualan', 'type' => 'revenue']);
        ChartOfAccount::updateOrCreate(['code' => '5100'], ['name' => 'Harga Pokok Penjualan', 'type' => 'expense']);

        // Kasir beli 2 pcs Keripik @ 15.000 (Subtotal = 30.000)
        // Diberikan diskon nominal kekeluargaan sebesar 5.000
        // Grand Total harus menjadi 25.000
        $response = $this->actingAs($this->cashier)->postJson('/pos', [
            'payment_method' => 'cash',
            'discount_amount' => 5000,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', 'success');

        $receiptNumber = $response->json('receipt_number');
        $this->assertNotEmpty($receiptNumber);

        // 1. Verifikasi tabel sales: Grand Total 25.000, diskon 5.000
        $sale = Sale::where('receipt_number', $receiptNumber)->firstOrFail();
        $this->assertEquals(25000, $sale->total_amount);
        $this->assertEquals(5000, (float) $sale->discount_amount);

        // 2. Verifikasi Jurnal Akuntansi seimbang (Balanced Journal)
        $journal = JournalHeader::where('reference_number', $receiptNumber)->firstOrFail();
        $lines = $journal->journalLines;

        // Debit: Kas (1110) senilai Grand Total = 25.000
        $cashLine = $lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '1110')->value('id'));
        $this->assertNotNull($cashLine);
        $this->assertEquals(25000, (float) $cashLine->debit);
        $this->assertEquals(0, (float) $cashLine->credit);

        // Debit: Potongan Penjualan (4130) senilai discount_amount = 5.000
        $discountLine = $lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '4130')->value('id'));
        $this->assertNotNull($discountLine);
        $this->assertEquals(5000, (float) $discountLine->debit);
        $this->assertEquals(0, (float) $discountLine->credit);

        // Kredit: Pendapatan Penjualan (4110) senilai Subtotal = 30.000
        $revenueLine = $lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '4110')->value('id'));
        $this->assertNotNull($revenueLine);
        $this->assertEquals(0, (float) $revenueLine->debit);
        $this->assertEquals(30000, (float) $revenueLine->credit);

        // Sisi HPP & Persediaan: 2 x 10.000 = 20.000
        $cogsLine = $lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '5100')->value('id'));
        $this->assertNotNull($cogsLine);
        $this->assertEquals(20000, (float) $cogsLine->debit);

        $inventoryLine = $lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '1210')->value('id'));
        $this->assertNotNull($inventoryLine);
        $this->assertEquals(20000, (float) $inventoryLine->credit);

        // Total Debit harus SAMA dengan Total Credit (100% BALANCE)
        $totalDebit = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');
        $this->assertEquals(50000, $totalDebit);
        $this->assertEquals(50000, $totalCredit);
        $this->assertEquals($totalDebit, $totalCredit);
    }

    public function test_checkout_rejects_discount_exceeding_subtotal(): void
    {
        // Subtotal = 30.000 (2 x 15.000). Kasir input diskon 35.000 -> Harus ditolak
        $response = $this->actingAs($this->cashier)->postJson('/pos', [
            'payment_method' => 'cash',
            'discount_amount' => 35000,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('discount_amount');
    }

    public function test_thermal_receipt_displays_discount_row(): void
    {
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'INV-20260916-0888',
            'total_amount' => 25000,
            'discount_amount' => 5000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 15000,
            'subtotal' => 30000,
        ]);

        $response = $this->actingAs($this->cashier)->get("/pos/receipt/{$sale->receipt_number}");
        $response->assertStatus(200);
        $response->assertSee('SUBTOTAL');
        $response->assertSee('30.000');
        $response->assertSee('DISKON');
        $response->assertSee('- Rp 5.000');
        $response->assertSee('TOTAL');
        $response->assertSee('25.000');
    }

    public function test_cashier_can_create_customer_with_category_and_sync_to_pos(): void
    {
        $groupGrosir = CustomerGroup::firstOrCreate(
            ['name' => 'Grosir'],
            ['notes' => 'Pelanggan Grosir']
        );

        $response = $this->actingAs($this->cashier)->postJson('/pos/customers', [
            'name' => 'Toko Tani Barokah',
            'customer_group_id' => $groupGrosir->id,
            'phone' => '08123456789',
            'address' => 'Desa Makmur',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
            'customer' => [
                'name' => 'Toko Tani Barokah',
                'customer_group_id' => $groupGrosir->id,
                'phone' => '08123456789',
            ],
        ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'Toko Tani Barokah',
            'branch_id' => $this->branch->id,
            'customer_group_id' => $groupGrosir->id,
        ]);
    }

    public function test_customer_created_by_branch_cashier_is_visible_to_central_admin(): void
    {
        // 1. Cabang Pusat
        $centralBranch = Branch::create([
            'name' => 'Kantor Pusat MB',
            'code' => 'HQ-MB',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $centralAdmin = User::factory()->create([
            'branch_id' => $centralBranch->id,
            'role' => 'master',
        ]);

        $groupUmum = CustomerGroup::firstOrCreate(['name' => 'Umum/Retail']);

        // 2. Kasir di cabang mendaftarkan pelanggan
        $customer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Pak Haji Subur',
            'phone' => '0899999999',
            'customer_group_id' => $groupUmum->id,
        ]);

        // 3. Admin Pusat membuka backoffice customers
        $response = $this->actingAs($centralAdmin)->get('/backoffice/customers');
        $response->assertOk();
        $response->assertSee('Pak Haji Subur');
        $response->assertSee($this->branch->name);
    }

    public function test_cashier_can_checkout_with_tempo_and_due_date_and_debit_receivable_journal(): void
    {
        $customer = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Pak Joko Tempo',
            'phone' => '081234567000',
        ]);

        $dueDate = now()->addDays(30)->toDateString();

        $response = $this->actingAs($this->cashier)->postJson('/pos', [
            'payment_method' => 'tempo',
            'customer_id' => $customer->id,
            'due_date' => $dueDate,
            'discount_amount' => 0,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', 'success');

        $receiptNumber = $response->json('receipt_number');
        $sale = Sale::where('receipt_number', $receiptNumber)->firstOrFail();

        // Verifikasi Sale terisi status UNPAID, tempo, due_date, dan customer_id
        $this->assertEquals('tempo', $sale->payment_method);
        $this->assertEquals('UNPAID', $sale->payment_status);
        $this->assertEquals($customer->id, $sale->customer_id);
        $this->assertEquals($dueDate, $sale->due_date->toDateString());
        $this->assertEquals(30000, $sale->total_amount);

        // Verifikasi Jurnal Akuntansi: Debit Piutang Usaha (1130) senilai 30.000
        $journal = JournalHeader::where('reference_number', $receiptNumber)->firstOrFail();
        $lines = $journal->journalLines;

        $arLine = $lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '1130')->value('id'));
        $this->assertNotNull($arLine);
        $this->assertEquals(30000, (float) $arLine->debit);
        $this->assertEquals(0, (float) $arLine->credit);

        // Kredit Pendapatan (4110)
        $revenueLine = $lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '4110')->value('id'));
        $this->assertEquals(30000, (float) $revenueLine->credit);

        // Seimbang
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'));
    }

    public function test_pos_strictly_scopes_products_to_cashier_branch(): void
    {
        $otherBranch = Branch::create([
            'name' => 'Cabang Lain Seberang',
            'code' => 'CAB-LAIN-02',
            'address' => 'Jl. Lain No. 99',
            'phone' => '089988776655',
        ]);

        $otherProduct = Product::create([
            'branch_id' => $otherBranch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-OTHER-BRANCH',
            'name' => 'Barang Khusus Cabang Seberang',
            'selling_price' => 50000,
            'purchase_price' => 35000,
            'stock' => 50,
        ]);

        $response = $this->actingAs($this->cashier)->get('/pos');
        $response->assertOk();

        // Produk cabang sendiri harus ada
        $response->assertSee('Keripik Singkong Balado');

        // Produk cabang lain SAMA SEKALI tidak boleh muncul
        $response->assertDontSee('Barang Khusus Cabang Seberang');
        $response->assertDontSee('SKU-OTHER-BRANCH');

        $viewProducts = $response->viewData('products');
        $this->assertFalse($viewProducts->contains('id', $otherProduct->id));
    }

    public function test_pos_shows_zero_stock_with_cross_branch_info(): void
    {
        $branch2 = Branch::create(['name' => 'MB PUSAT', 'code' => 'MB-PST']);
        $branch3 = Branch::create(['name' => 'ECERAN', 'code' => 'MB-ECR']);

        // Produk di cabang kasir dengan stok 0
        $outOfStockProduct = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-CROSS-01',
            'name' => 'Biskuit Cross Branch',
            'selling_price' => 12000,
            'purchase_price' => 8000,
            'stock' => 0,
        ]);

        // Produk sama (SKU sama) di MB PUSAT dengan stok 5
        Product::create([
            'branch_id' => $branch2->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-CROSS-01',
            'name' => 'Biskuit Cross Branch',
            'selling_price' => 12000,
            'purchase_price' => 8000,
            'stock' => 5,
        ]);

        // Produk sama (SKU sama) di ECERAN dengan stok 2
        Product::create([
            'branch_id' => $branch3->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-CROSS-01',
            'name' => 'Biskuit Cross Branch',
            'selling_price' => 12000,
            'purchase_price' => 8000,
            'stock' => 2,
        ]);

        // Produk lain di cabang kasir yang stoknya 0 dan di cabang lain juga habis
        $completelyOutOfStock = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-EMPTY-ALL',
            'name' => 'Barang Langka Total',
            'selling_price' => 20000,
            'purchase_price' => 15000,
            'stock' => 0,
        ]);

        $response = $this->actingAs($this->cashier)->get('/pos');
        $response->assertOk();

        // 1. Produk dengan stok 0 TETAP dikirim ke frontend POS
        $response->assertSee('Biskuit Cross Branch');
        $response->assertSee('Barang Langka Total');
        $response->assertSee('Habis di seluruh cabang');

        $viewProducts = $response->viewData('products');
        $this->assertTrue($viewProducts->contains('id', $outOfStockProduct->id));
        $this->assertTrue($viewProducts->contains('id', $completelyOutOfStock->id));

        // 2. Data other_branch_stock terisi dengan akurat
        $loadedProduct = $viewProducts->firstWhere('id', $outOfStockProduct->id);
        $this->assertNotEmpty($loadedProduct->other_branch_stock);
        $this->assertEquals([
            ['branch_name' => 'MB PUSAT', 'stock' => 5],
            ['branch_name' => 'ECERAN', 'stock' => 2],
        ], $loadedProduct->other_branch_stock);

        $loadedEmpty = $viewProducts->firstWhere('id', $completelyOutOfStock->id);
        $this->assertEmpty($loadedEmpty->other_branch_stock);

        // 3. Produk berstok 0 TIDAK BISA di-checkout (validasi penambahan/checkout)
        $checkoutResponse = $this->actingAs($this->cashier)->postJson('/pos', [
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $outOfStockProduct->id, 'quantity' => 1],
            ],
        ]);
        $checkoutResponse->assertStatus(422);
    }

    public function test_pos_category_badge_counts_all_products_for_branch(): void
    {
        // Category 1 ($this->category): memiliki 1 produk stok 25 ($this->product)
        // Tambahkan produk stok 0 di category 1 -> tetap dihitung untuk cabang ini
        Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-ZERO-CAT1',
            'name' => 'Snack Kosong',
            'selling_price' => 10000,
            'purchase_price' => 6000,
            'stock' => 0,
        ]);

        // Category 2: Minuman, ada 1 stok 15 di cabang ini, dan 1 stok 100 di cabang lain
        $catBeverage = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Minuman Segar',
        ]);

        $inStockBeverage = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $catBeverage->id,
            'sku' => 'SKU-BEV-01',
            'name' => 'Teh Manis Dingin',
            'selling_price' => 5000,
            'purchase_price' => 2000,
            'stock' => 15,
        ]);

        $otherBranch = Branch::create(['name' => 'Cabang Luar', 'code' => 'CAB-LUAR-03']);
        Product::create([
            'branch_id' => $otherBranch->id,
            'category_id' => $catBeverage->id,
            'sku' => 'SKU-BEV-OTHER',
            'name' => 'Teh Luar Kota',
            'selling_price' => 5000,
            'purchase_price' => 2000,
            'stock' => 100,
        ]);

        $response = $this->actingAs($this->cashier)->get('/pos');
        $response->assertOk();

        $viewCategories = $response->viewData('categories');
        $viewProducts = $response->viewData('products');

        // Total produk yang dikirim ke pos adalah 3 milik cabang ini (Keripik + Snack Kosong + Teh Manis)
        // Produk Cabang Luar tidak boleh masuk
        $this->assertCount(3, $viewProducts);

        // Kategori Makanan Ringan menghitung 2 produk di cabang ini
        $cat1 = $viewCategories->firstWhere('id', $this->category->id);
        $this->assertEquals(2, $cat1->products_count);

        // Kategori Minuman hanya menghitung 1 (karena produk cabang lain tidak boleh dihitung)
        $cat2 = $viewCategories->firstWhere('id', $catBeverage->id);
        $this->assertEquals(1, $cat2->products_count);
    }
}
