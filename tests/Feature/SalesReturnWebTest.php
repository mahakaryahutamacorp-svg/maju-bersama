<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\CashRegisterShiftService;
use App\Services\SalesReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReturnWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $cashier;
    private Category $category;
    private Product $productA;
    private Product $productB;
    private ChartOfAccount $cashAccount;
    private ChartOfAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Cabang Rungkut',
            'code' => 'RKT01',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'cashier',
        ]);

        $this->category = Category::create(['name' => 'Aksesoris HP']);

        $this->productA = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'CASE-IP15',
            'name' => 'Case iPhone 15 Pro',
            'purchase_price' => 50000,
            'selling_price' => 120000,
            'stock' => 10,
            'is_active' => true,
        ]);

        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->productA->id,
            'quantity' => 10,
        ]);

        $this->productB = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'TG-IP15',
            'name' => 'Tempered Glass iPhone 15',
            'purchase_price' => 15000,
            'selling_price' => 45000,
            'stock' => 20,
            'is_active' => true,
        ]);

        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->productB->id,
            'quantity' => 20,
        ]);

        $this->cashAccount = ChartOfAccount::create([
            'code' => '1110',
            'name' => 'Kas Toko',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->bankAccount = ChartOfAccount::create([
            'code' => '1120',
            'name' => 'Bank BCA Operasional',
            'type' => 'asset',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '4110',
            'name' => 'Pendapatan Penjualan',
            'type' => 'revenue',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '1210',
            'name' => 'Persediaan',
            'type' => 'asset',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '5100',
            'name' => 'Harga Pokok Penjualan',
            'type' => 'expense',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_sales_returns(): void
    {
        $response = $this->get(route('backoffice.sales-returns.index'));
        $response->assertRedirect('/login');

        $response = $this->get(route('backoffice.sales-returns.create'));
        $response->assertRedirect('/login');
    }

    public function test_user_can_view_index_screen(): void
    {
        SalesReturn::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'customer_name' => 'Rina Wijaya',
            'return_date' => '2026-09-21',
            'reference_number' => 'SR-20260921-99999',
            'refund_method' => 'cash',
            'chart_of_account_id' => $this->cashAccount->id,
            'total_amount' => 120000,
            'total_cost' => 50000,
            'status' => 'completed',
            'reason' => 'Salah tipe casing',
        ]);

        $response = $this->actingAs($this->cashier)->get(route('backoffice.sales-returns.index'));

        $response->assertStatus(200);
        $response->assertSee('Riwayat Retur Penjualan');
        $response->assertSee('SR-20260921-99999');
        $response->assertSee('Rina Wijaya');
        $response->assertSee('120.000');
        $response->assertSee('loadTransaction');
    }

    public function test_user_can_view_create_screen_with_products_and_accounts(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('backoffice.sales-returns.create'));

        $response->assertStatus(200);
        $response->assertSee('Input Retur Penjualan (Refund)');
        $response->assertSee('Case iPhone 15 Pro');
        $response->assertSee('Live Journal Preview');
        $response->assertSee('salesReturnForm');
    }

    public function test_user_can_store_sales_return_with_cash_refund(): void
    {
        // Setup cash register shift for cashier
        $register = CashRegister::create([
            'branch_id' => $this->branch->id,
            'name' => 'Kasir 1',
            'code' => 'REG-01',
            'is_active' => true,
        ]);

        $shiftService = app(CashRegisterShiftService::class);
        $shift = $shiftService->openShift($register->id, $this->cashier->id, 500000, 'Buka kasir pagi');

        $payload = [
            'return_date' => '2026-09-21',
            'customer_name' => 'Budi Setiawan',
            'refund_method' => 'cash',
            'chart_of_account_id' => $this->cashAccount->id,
            'reason' => 'Warna casing tidak sesuai pesanan',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => 120000,
                    'unit_cost' => 50000,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->post(route('backoffice.sales-returns.store'), $payload);

        $response->assertRedirect(route('backoffice.sales-returns.index'));
        $response->assertSessionHas('success');

        // Check sales_returns record
        $this->assertDatabaseHas('sales_returns', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Budi Setiawan',
            'refund_method' => 'cash',
            'total_amount' => '120000.00',
            'total_cost' => '50000.00',
            'status' => 'completed',
        ]);

        // Check sales_return_items record
        $this->assertDatabaseHas('sales_return_items', [
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => '120000.00',
            'unit_cost' => '50000.00',
            'subtotal' => '120000.00',
            'subtotal_cost' => '50000.00',
        ]);

        // Check stock incremented: 10 + 1 = 11 (goods returned to shelf)
        $this->productA->refresh();
        $this->assertEquals(11, $this->productA->stock);

        $inv = Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(11, $inv->quantity);

        // Check 4-line balanced accounting journal
        $salesReturn = SalesReturn::withoutGlobalScopes()->where('customer_name', 'Budi Setiawan')->first();
        $this->assertNotNull($salesReturn->journal_header_id);

        $lines = $salesReturn->journalHeader->journalLines;
        $this->assertCount(4, $lines);

        // Dr Pendapatan Penjualan 4110 = 120,000
        $revLine = $lines->firstWhere('chartOfAccount.code', '4110');
        $this->assertEquals('120000.00', $revLine->debit);
        $this->assertEquals('0.00', $revLine->credit);

        // Cr Kas 1110 = 120,000
        $cashLine = $lines->firstWhere('chartOfAccount.code', '1110');
        $this->assertEquals('0.00', $cashLine->debit);
        $this->assertEquals('120000.00', $cashLine->credit);

        // Dr Persediaan 1210 = 50,000
        $invLine = $lines->firstWhere('chartOfAccount.code', '1210');
        $this->assertEquals('50000.00', $invLine->debit);
        $this->assertEquals('0.00', $invLine->credit);

        // Cr HPP 5100 = 50,000
        $cogsLine = $lines->firstWhere('chartOfAccount.code', '5100');
        $this->assertEquals('0.00', $cogsLine->debit);
        $this->assertEquals('50000.00', $cogsLine->credit);

        // Total Debit == Total Credit = 170,000
        $this->assertEquals('170000.00', number_format($lines->sum('debit'), 2, '.', ''));
        $this->assertEquals('170000.00', number_format($lines->sum('credit'), 2, '.', ''));

        // Check shift reconciliation: 500,000 - 120,000 = 380,000 expected balance!
        $expectedBalance = $shiftService->calculateExpectedBalance($shift);
        $this->assertEquals(380000.0, $expectedBalance);
    }

    public function test_user_can_store_sales_return_with_bank_transfer(): void
    {
        $payload = [
            'return_date' => '2026-09-21',
            'customer_name' => 'Dewi Sartika',
            'refund_method' => 'transfer',
            'chart_of_account_id' => $this->bankAccount->id,
            'reason' => 'Transfer balik ke rekening BCA pelanggan',
            'items' => [
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 2,
                    'unit_price' => 45000, // 90,000
                    'unit_cost' => 15000,  // 30,000
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->post(route('backoffice.sales-returns.store'), $payload);

        $response->assertRedirect(route('backoffice.sales-returns.index'));

        $salesReturn = SalesReturn::withoutGlobalScopes()->where('customer_name', 'Dewi Sartika')->first();
        $this->assertEquals('90000.00', $salesReturn->total_amount);
        $this->assertEquals('30000.00', $salesReturn->total_cost);
        $this->assertEquals('transfer', $salesReturn->refund_method);

        // Check Cr Bank line
        $lines = $salesReturn->journalHeader->journalLines;
        $bankLine = $lines->firstWhere('chartOfAccount.code', '1120');
        $this->assertNotNull($bankLine);
        $this->assertEquals('90000.00', $bankLine->credit);

        // Check stock incremented: 20 + 2 = 22
        $this->productB->refresh();
        $this->assertEquals(22, $this->productB->stock);
    }

    public function test_store_validation_fails_for_invalid_input(): void
    {
        $response = $this->actingAs($this->cashier)->post(route('backoffice.sales-returns.store'), [
            'return_date' => '2026-09-21',
            'refund_method' => 'cash',
            'chart_of_account_id' => $this->cashAccount->id,
            'items' => [],
        ]);
        $response->assertSessionHasErrors(['items']);
    }

    public function test_transaction_viewer_shows_sales_return_details(): void
    {
        $service = app(SalesReturnService::class);
        $salesReturn = $service->processReturn(
            [
                'branch_id' => $this->branch->id,
                'customer_name' => 'Hendra Gunawan',
                'return_date' => '2026-09-21',
                'refund_method' => 'cash',
                'chart_of_account_id' => $this->cashAccount->id,
                'reason' => 'Viewer modal sales return test',
            ],
            [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => 120000,
                    'unit_cost' => 50000,
                ],
            ],
            $this->cashier
        );

        $response = $this->actingAs($this->cashier)->get("/backoffice/transactions/{$salesReturn->reference_number}/details");

        $response->assertStatus(200);
        $response->assertSee('Retur Penjualan (Sales Return)');
        $response->assertSee($salesReturn->reference_number);
        $response->assertSee('Hendra Gunawan');
        $response->assertSee('Case iPhone 15 Pro');
        $response->assertSee('120.000');
        $response->assertSee('Viewer modal sales return test');
        $response->assertSee('Pendapatan Penjualan');
        $response->assertSee('Persediaan');
        $response->assertSee('Harga Pokok Penjualan');
    }
}
