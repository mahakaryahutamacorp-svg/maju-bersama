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
use App\Models\User;
use App\Services\CashRegisterShiftService;
use App\Services\SalePostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosShiftWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $cashier;
    private CashRegister $register;
    private Category $category;
    private Product $product;
    private CashRegisterShiftService $shiftService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shiftService = app(CashRegisterShiftService::class);

        $this->branch = Branch::create([
            'name' => 'Cabang Sudirman POS',
            'code' => 'SDR-01',
            'address' => 'Jl. Sudirman Kav 1',
            'phone' => '081234567890',
        ]);

        $this->cashier = User::create([
            'name' => 'Budi Kasir',
            'email' => 'budi.kasir@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);

        $this->register = CashRegister::create([
            'branch_id' => $this->branch->id,
            'name' => 'Laci Kasir A1',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Minuman Dingin',
        ]);

        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'MIN-001',
            'name' => 'Kopi Susu Gula Aren',
            'selling_price' => 20000,
            'purchase_price' => 12000,
            'stock' => 50,
        ]);

        Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);

        $this->seedAccounts();
    }

    private function seedAccounts(): void
    {
        $accounts = [
            ['code' => '1110', 'name' => 'Kas Toko', 'type' => 'asset'],
            ['code' => '1210', 'name' => 'Persediaan Barang Dagang', 'type' => 'asset'],
            ['code' => '4110', 'name' => 'Pendapatan Penjualan POS', 'type' => 'revenue'],
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan (HPP)', 'type' => 'expense'],
        ];

        foreach ($accounts as $acc) {
            ChartOfAccount::firstOrCreate(
                ['code' => $acc['code']],
                ['name' => $acc['name'], 'type' => $acc['type']]
            );
        }
    }

    public function test_pos_shows_blocking_modal_when_cashier_has_no_active_shift(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('Buka Shift Kasir Baru');
        $response->assertSee('Sesi Kasir Terkunci');
        $response->assertSee('Modal Awal Kembalian (Rp)');
        $response->assertSee('Laci Kasir A1');
        $response->assertSee('id="btn-submit-buka-shift"', false);
        $response->assertDontSee('id="btn-tutup-shift"', false);
    }

    public function test_cashier_can_open_shift_via_pos_screen(): void
    {
        $response = $this->actingAs($this->cashier)->post(route('pos.shift.open'), [
            'cash_register_id' => $this->register->id,
            'opening_balance' => 250000,
            'notes' => 'Modal pecahan 5rb, 10rb, dan 20rb',
        ]);

        $response->assertRedirect(route('pos'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cash_register_shifts', [
            'branch_id' => $this->branch->id,
            'cash_register_id' => $this->register->id,
            'user_id' => $this->cashier->id,
            'opening_balance' => 250000,
            'expected_closing_balance' => 250000,
            'status' => 'open',
            'notes' => 'Modal pecahan 5rb, 10rb, dan 20rb',
        ]);
    }

    public function test_pos_shows_tutup_shift_button_when_cashier_has_active_shift(): void
    {
        $this->shiftService->openShift(
            $this->register->id,
            $this->cashier->id,
            200000
        );

        $response = $this->actingAs($this->cashier)->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('id="btn-tutup-shift"', false);
        $response->assertSee('Tutup Shift & Rekonsiliasi', false);
        $response->assertSee('id="btn-submit-tutup-shift"', false);
        $response->assertDontSee('id="btn-submit-buka-shift"', false);
    }

    public function test_sale_posting_service_automatically_links_sale_to_active_shift(): void
    {
        $shift = $this->shiftService->openShift(
            $this->register->id,
            $this->cashier->id,
            150000
        );

        $postingService = app(SalePostingService::class);
        $sale = $postingService->post([
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ], $this->cashier);

        $this->assertNotNull($sale->cash_register_shift_id);
        $this->assertSame($shift->id, $sale->cash_register_shift_id);
        $this->assertEquals(4000000, $sale->total_amount); // 40.000 in cents
    }

    public function test_calculate_expected_balance_sums_opening_balance_and_cash_sales(): void
    {
        $shift = $this->shiftService->openShift(
            $this->register->id,
            $this->cashier->id,
            200000 // Rp 200.000
        );

        $postingService = app(SalePostingService::class);

        // Transaksi 1: Tunai 2 cup (Rp 40.000)
        $postingService->post([
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ], $this->cashier);

        // Transaksi 2: Non-tunai QRIS 1 cup (Rp 20.000) - tidak boleh masuk laci tunai
        $postingService->post([
            'payment_method' => 'qris',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ], $this->cashier);

        // Expected balance: 200.000 + 40.000 = 240.000
        $expected = $this->shiftService->calculateExpectedBalance($shift);
        $this->assertEquals(240000.00, $expected);
    }

    public function test_cashier_can_close_shift_with_reconciliation_and_difference(): void
    {
        $shift = $this->shiftService->openShift(
            $this->register->id,
            $this->cashier->id,
            200000
        );

        $postingService = app(SalePostingService::class);
        $postingService->post([
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2], // Rp 40.000
            ],
        ], $this->cashier);

        // Expected adalah 240.000. Kasir memasukkan fisik 238.000 (selisih -2.000)
        $response = $this->actingAs($this->cashier)->post(route('pos.shift.close'), [
            'actual_closing_balance' => 238000,
            'notes' => 'Selisih kurang 2000 karena pembulatan receh koin',
        ]);

        $response->assertRedirect(route('pos'));
        $response->assertSessionHas('success');

        $shift->refresh();
        $this->assertSame('closed', $shift->status);
        $this->assertEquals(240000.00, (float) $shift->expected_closing_balance);
        $this->assertEquals(238000.00, (float) $shift->actual_closing_balance);
        $this->assertEquals(-2000.00, (float) $shift->difference);
        $this->assertNotNull($shift->closed_at);
        $this->assertSame('Selisih kurang 2000 karena pembulatan receh koin', $shift->notes);
    }

    public function test_cashier_cannot_open_second_shift_while_one_is_active(): void
    {
        $this->shiftService->openShift(
            $this->register->id,
            $this->cashier->id,
            100000
        );

        $secondRegister = CashRegister::create([
            'branch_id' => $this->branch->id,
            'name' => 'Laci Kasir A2',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->cashier)->post(route('pos.shift.open'), [
            'cash_register_id' => $secondRegister->id,
            'opening_balance' => 100000,
        ]);

        $response->assertSessionHasErrors(['cash_register_id']);
    }
}
