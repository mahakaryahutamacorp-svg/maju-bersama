<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
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

        \App\Models\ChartOfAccount::updateOrCreate(['code' => '1110'], ['name' => 'Kas', 'type' => 'asset']);
        \App\Models\ChartOfAccount::updateOrCreate(['code' => '1210'], ['name' => 'Persediaan', 'type' => 'asset']);
        \App\Models\ChartOfAccount::updateOrCreate(['code' => '4110'], ['name' => 'Pendapatan', 'type' => 'revenue']);
        \App\Models\ChartOfAccount::updateOrCreate(['code' => '4130'], ['name' => 'Potongan Penjualan', 'type' => 'revenue']);
        \App\Models\ChartOfAccount::updateOrCreate(['code' => '5100'], ['name' => 'Harga Pokok Penjualan', 'type' => 'expense']);
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
            'total_amount' => 3000000, // 30.000 (in cents)
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 1500000,
            'subtotal' => 3000000,
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
        \App\Models\ChartOfAccount::updateOrCreate(['code' => '1110'], ['name' => 'Kas', 'type' => 'asset']);
        \App\Models\ChartOfAccount::updateOrCreate(['code' => '1210'], ['name' => 'Persediaan', 'type' => 'asset']);
        \App\Models\ChartOfAccount::updateOrCreate(['code' => '4110'], ['name' => 'Pendapatan', 'type' => 'revenue']);
        \App\Models\ChartOfAccount::updateOrCreate(['code' => '4130'], ['name' => 'Potongan Penjualan', 'type' => 'revenue']);
        \App\Models\ChartOfAccount::updateOrCreate(['code' => '5100'], ['name' => 'Harga Pokok Penjualan', 'type' => 'expense']);

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

        // 1. Verifikasi tabel sales: Grand Total 25.000 (2.500.000 sen), diskon 5.000
        $sale = Sale::where('receipt_number', $receiptNumber)->firstOrFail();
        $this->assertEquals(2500000, $sale->total_amount);
        $this->assertEquals(5000, (float) $sale->discount_amount);

        // 2. Verifikasi Jurnal Akuntansi seimbang (Balanced Journal)
        $journal = \App\Models\JournalHeader::where('reference_number', $receiptNumber)->firstOrFail();
        $lines = $journal->journalLines;

        // Debit: Kas (1110) senilai Grand Total = 25.000
        $cashLine = $lines->firstWhere('chart_of_account_id', \App\Models\ChartOfAccount::where('code', '1110')->value('id'));
        $this->assertNotNull($cashLine);
        $this->assertEquals(25000, (float) $cashLine->debit);
        $this->assertEquals(0, (float) $cashLine->credit);

        // Debit: Potongan Penjualan (4130) senilai discount_amount = 5.000
        $discountLine = $lines->firstWhere('chart_of_account_id', \App\Models\ChartOfAccount::where('code', '4130')->value('id'));
        $this->assertNotNull($discountLine);
        $this->assertEquals(5000, (float) $discountLine->debit);
        $this->assertEquals(0, (float) $discountLine->credit);

        // Kredit: Pendapatan Penjualan (4110) senilai Subtotal = 30.000
        $revenueLine = $lines->firstWhere('chart_of_account_id', \App\Models\ChartOfAccount::where('code', '4110')->value('id'));
        $this->assertNotNull($revenueLine);
        $this->assertEquals(0, (float) $revenueLine->debit);
        $this->assertEquals(30000, (float) $revenueLine->credit);

        // Sisi HPP & Persediaan: 2 x 10.000 = 20.000
        $cogsLine = $lines->firstWhere('chart_of_account_id', \App\Models\ChartOfAccount::where('code', '5100')->value('id'));
        $this->assertNotNull($cogsLine);
        $this->assertEquals(20000, (float) $cogsLine->debit);

        $inventoryLine = $lines->firstWhere('chart_of_account_id', \App\Models\ChartOfAccount::where('code', '1210')->value('id'));
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
            'total_amount' => 2500000, // 25.000 (in cents)
            'discount_amount' => 5000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 1500000,
            'subtotal' => 3000000,
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
}
