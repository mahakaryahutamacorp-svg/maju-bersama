<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashRegisterShiftService;
use App\Services\SalesReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserFlowRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $cashier;

    private User $admin;

    private Product $product;

    private Customer $customer;

    private ChartOfAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Cabang Uji', 'code' => 'UJI-01']);

        $this->cashier = User::factory()->create(['role' => 'cashier', 'branch_id' => $this->branch->id]);
        $this->admin = User::factory()->create(['role' => 'branch_admin', 'branch_id' => $this->branch->id]);

        $category = Category::create(['branch_id' => $this->branch->id, 'name' => 'Pestisida']);
        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'sku' => 'ARF-UJI',
            'name' => 'PRIMA GA3',
            'selling_price' => 4000,
            'purchase_price' => 2500,
            'stock' => 100,
        ]);
        Inventory::create(['branch_id' => $this->branch->id, 'product_id' => $this->product->id, 'quantity' => 100]);

        $this->customer = Customer::create(['branch_id' => $this->branch->id, 'name' => 'UJI Pelanggan Tempo']);

        foreach ([
            ['1110', 'Kas', 'asset'],
            ['1130', 'Piutang Usaha', 'asset'],
            ['1210', 'Persediaan', 'asset'],
            ['4110', 'Pendapatan', 'revenue'],
            ['4130', 'Potongan Penjualan', 'revenue'],
            ['5100', 'HPP', 'expense'],
        ] as [$code, $name, $type]) {
            ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true]);
        }
        $this->cash = ChartOfAccount::where('code', '1110')->first();

        $register = CashRegister::create(['branch_id' => $this->branch->id, 'name' => 'Laci Uji', 'is_active' => true]);
        app(CashRegisterShiftService::class)->openShift($register->id, $this->cashier->id, 100000);
    }

    private function checkout(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->cashier)->postJson('/pos', array_merge([
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $overrides));
    }

    public function test_repeated_checkout_with_same_idempotency_key_creates_one_sale(): void
    {
        $payload = ['idempotency_key' => 'klik-ganda-001'];

        $first = $this->checkout($payload)->assertCreated();
        $second = $this->checkout($payload)->assertCreated();

        $this->assertSame($first->json('receipt_number'), $second->json('receipt_number'));
        $this->assertSame(1, Sale::withoutGlobalScopes()->count());
        $this->assertEquals(99, $this->product->fresh()->stock);
    }

    public function test_tempo_down_payment_is_stored_journaled_and_printed(): void
    {
        $response = $this->checkout([
            'payment_method' => 'tempo',
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(30)->toDateString(),
            'paid_amount' => 10000,
            'items' => [['product_id' => $this->product->id, 'quantity' => 6]],
        ])->assertCreated();

        $sale = Sale::withoutGlobalScopes()->where('receipt_number', $response->json('receipt_number'))->firstOrFail();
        $this->assertEquals('24000.00', $sale->total_amount);
        $this->assertEquals('10000.00', $sale->paid_amount);
        $this->assertSame('PARTIAL', $sale->payment_status);

        $journalLines = JournalHeader::where('reference_number', $sale->receipt_number)->firstOrFail()
            ->journalLines()->with('chartOfAccount')->get();
        $this->assertEquals(10000, (float) $journalLines->firstWhere('chartOfAccount.code', '1110')->debit);
        $this->assertEquals(14000, (float) $journalLines->firstWhere('chartOfAccount.code', '1130')->debit);

        $this->actingAs($this->cashier)->get(route('pos.receipt', $sale->receipt_number))
            ->assertOk()
            ->assertSee('UANG MUKA (DP)')
            ->assertSee('10.000')
            ->assertSee('SISA PIUTANG')
            ->assertSee('14.000')
            ->assertDontSee('TUNAI:');

        $this->checkout([
            'payment_method' => 'tempo',
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(30)->toDateString(),
            'paid_amount' => 999999,
        ])->assertStatus(422);
    }

    public function test_expected_shift_cash_counts_cash_sales_and_tempo_down_payments(): void
    {
        $cashSale = $this->checkout(['items' => [['product_id' => $this->product->id, 'quantity' => 1]]])->assertCreated();
        $this->assertEquals(104000, $cashSale->json('expected_balance'));

        $this->checkout([
            'payment_method' => 'tempo',
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(30)->toDateString(),
            'paid_amount' => 5000,
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ])->assertCreated();

        $this->actingAs($this->cashier)->getJson(route('pos.shift.expected'))
            ->assertOk()
            ->assertJson(['expected_balance' => 109000]);

        $this->actingAs($this->cashier)->post(route('pos.shift.close'), ['actual_closing_balance' => 109000])
            ->assertRedirect(route('pos'));

        $this->actingAs($this->cashier)->get('/pos')->assertSee('Sesi Kasir Terkunci');
    }

    public function test_return_from_discounted_receipt_refunds_net_paid_amount(): void
    {
        $response = $this->checkout([
            'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'discount_amount' => 1000]],
        ])->assertCreated();
        $sale = Sale::withoutGlobalScopes()->where('receipt_number', $response->json('receipt_number'))->firstOrFail();
        $this->assertEquals('7000.00', $sale->total_amount);

        $salesReturn = app(SalesReturnService::class)->processReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'chart_of_account_id' => $this->cash->id,
        ], [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 4000],
        ], $this->admin);

        $this->assertEquals('7000.00', $salesReturn->total_amount);
    }

    public function test_fully_returned_tempo_invoice_is_no_longer_collectible(): void
    {
        $response = $this->checkout([
            'payment_method' => 'tempo',
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(30)->toDateString(),
            'paid_amount' => 0,
        ])->assertCreated();
        $sale = Sale::withoutGlobalScopes()->where('receipt_number', $response->json('receipt_number'))->firstOrFail();

        app(SalesReturnService::class)->processReturn([
            'sale_id' => $sale->id,
        ], [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ], $this->admin);

        $this->assertSame('PAID', $sale->fresh()->payment_status);

        $this->actingAs($this->admin)
            ->getJson(route('backoffice.payments.receivables.debtor-sales', ['customer' => $this->customer->id]))
            ->assertOk()
            ->assertJsonMissing(['receipt_number' => $sale->receipt_number]);
    }

    public function test_receivable_payment_must_match_allocation_and_balance(): void
    {
        $response = $this->checkout([
            'payment_method' => 'tempo',
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [['product_id' => $this->product->id, 'quantity' => 6]],
        ])->assertCreated();
        $sale = Sale::withoutGlobalScopes()->where('receipt_number', $response->json('receipt_number'))->firstOrFail();

        $base = [
            'account_id' => $this->cash->id,
            'payment_date' => now()->toDateString(),
        ];

        $this->actingAs($this->admin)->post(route('backoffice.payments.receivables.store'), $base + [
            'amount' => 24000 + 150000,
            'allocations' => [['sale_id' => $sale->id, 'allocated_amount' => 24000]],
        ])->assertSessionHasErrors('amount');

        $this->actingAs($this->admin)->post(route('backoffice.payments.receivables.store'), $base + [
            'amount' => 30000,
            'allocations' => [['sale_id' => $sale->id, 'allocated_amount' => 30000]],
        ])->assertSessionHasErrors('allocations');

        $this->assertEquals('0.00', $sale->fresh()->paid_amount);

        $this->actingAs($this->admin)->post(route('backoffice.payments.receivables.store'), $base + [
            'amount' => 24000,
            'allocations' => [['sale_id' => $sale->id, 'allocated_amount' => 24000]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('PAID', $sale->fresh()->payment_status);
    }

    public function test_branch_admin_cannot_edit_or_delete_master_account(): void
    {
        $master = User::factory()->create(['role' => 'master', 'branch_id' => $this->branch->id]);

        $this->actingAs($this->admin)->get(route('backoffice.users.edit', $master->id))->assertForbidden();
        $this->actingAs($this->admin)->put(route('backoffice.users.update', $master->id), [
            'name' => 'Diambil Alih',
            'email' => $master->email,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
            'role' => 'master',
        ])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('backoffice.users.destroy', $master->id))->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $master->id, 'name' => $master->name]);

        $this->actingAs($this->admin)->get(route('backoffice.users.edit', $this->cashier->id))->assertOk();
    }

    public function test_repeated_failed_logins_are_throttled_with_message(): void
    {
        RateLimiter::clear('login:'.strtolower($this->cashier->email).'|127.0.0.1');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => $this->cashier->email, 'password' => 'salah-'.$attempt]);
        }

        $this->post('/login', ['email' => $this->cashier->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertStringStartsWith('Terlalu banyak percobaan masuk. Coba lagi dalam', session('errors')->first('email'));
        $this->assertGuest();
    }
}
