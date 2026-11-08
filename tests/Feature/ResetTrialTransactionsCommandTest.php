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
use App\Services\JournalPostingService;
use App\Services\SalePostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetTrialTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $cashier;

    private Product $product;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['1110', 'Kas', 'asset'],
            ['1130', 'Piutang Usaha', 'asset'],
            ['1210', 'Persediaan', 'asset'],
            ['3110', 'Modal Awal', 'equity'],
            ['4110', 'Pendapatan', 'revenue'],
            ['5100', 'HPP', 'expense'],
        ] as [$code, $name, $type]) {
            ChartOfAccount::create(['code' => $code, 'name' => $name, 'type' => $type]);
        }

        $this->branch = Branch::create(['name' => 'Toko Pusat', 'code' => 'PUSAT', 'address' => 'Jl. Pusat', 'phone' => '0811']);

        $this->cashier = User::create([
            'name' => 'Kasir Uji',
            'email' => 'kasir.uji@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);

        $category = Category::create(['branch_id' => $this->branch->id, 'name' => 'Pestisida']);

        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'sku' => 'PST-001',
            'name' => 'Roundup 1 Liter',
            'purchase_price' => 85000,
            'selling_price' => 98000,
            'stock' => 20,
        ]);

        Inventory::create(['branch_id' => $this->branch->id, 'product_id' => $this->product->id, 'quantity' => 20]);

        $this->customer = Customer::create(['branch_id' => $this->branch->id, 'name' => 'Pak Tani']);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $sale = $this->postPosSale();

        $this->artisan('data:reset-uji-coba')->assertExitCode(0);

        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertSame(18, (int) Inventory::where('product_id', $this->product->id)->value('quantity'));
    }

    public function test_execute_removes_trial_pos_data_and_zeroes_stock_but_keeps_protected_data(): void
    {
        $shiftService = app(CashRegisterShiftService::class);
        $register = CashRegister::create(['branch_id' => $this->branch->id, 'name' => 'Laci 1', 'is_active' => true]);
        $shift = $shiftService->openShift($register->id, $this->cashier->id, 100000);

        $cashSale = $this->postPosSale();
        $tempoSale = $this->postPosSale('tempo');
        $manualReceivable = $this->createManualReceivable();

        $this->assertSame($shift->id, (int) $cashSale->cash_register_shift_id);

        $this->artisan('data:reset-uji-coba', ['--execute' => true, '--force' => true])->assertExitCode(0);

        foreach ([$cashSale, $tempoSale] as $sale) {
            $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
            $this->assertDatabaseMissing('sale_items', ['sale_id' => $sale->id]);
            $this->assertDatabaseMissing('journal_headers', ['reference_number' => $sale->receipt_number]);
        }
        $this->assertDatabaseMissing('cash_register_shifts', ['id' => $shift->id]);

        $this->assertDatabaseHas('sales', ['id' => $manualReceivable->id, 'total_amount' => 750000]);
        $this->assertDatabaseHas('journal_headers', ['reference_number' => 'JV-'.$manualReceivable->receipt_number]);

        $this->assertSame(0, (int) Inventory::where('product_id', $this->product->id)->value('quantity'));
        $this->product->refresh();
        $this->assertSame(0, (int) $this->product->stock);
        $this->assertSame('Roundup 1 Liter', $this->product->name);
        $this->assertEquals(85000, (float) $this->product->purchase_price);
        $this->assertEquals(98000, (float) $this->product->selling_price);

        $this->assertDatabaseHas('users', ['id' => $this->cashier->id]);
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
    }

    public function test_keep_unpaid_preserves_outstanding_pos_tempo_sales(): void
    {
        $cashSale = $this->postPosSale();
        $tempoSale = $this->postPosSale('tempo');

        $this->artisan('data:reset-uji-coba', ['--execute' => true, '--force' => true, '--keep-unpaid' => true])->assertExitCode(0);

        $this->assertDatabaseMissing('sales', ['id' => $cashSale->id]);
        $this->assertDatabaseHas('sales', ['id' => $tempoSale->id]);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $tempoSale->id]);
    }

    public function test_until_option_only_targets_older_pos_sales(): void
    {
        $oldSale = $this->postPosSale();
        DB::table('sales')->where('id', $oldSale->id)->update(['created_at' => '2026-10-01 10:00:00']);
        $recentSale = $this->postPosSale();

        $this->artisan('data:reset-uji-coba', ['--execute' => true, '--force' => true, '--until' => '2026-10-31'])->assertExitCode(0);

        $this->assertDatabaseMissing('sales', ['id' => $oldSale->id]);
        $this->assertDatabaseHas('sales', ['id' => $recentSale->id]);
    }

    private function postPosSale(string $paymentMethod = 'cash'): Sale
    {
        return app(SalePostingService::class)->post([
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payment_method' => $paymentMethod,
            'customer_id' => $paymentMethod === 'tempo' ? $this->customer->id : null,
        ], $this->cashier);
    }

    private function createManualReceivable(): Sale
    {
        $sale = new Sale;
        $sale->branch_id = $this->branch->id;
        $sale->customer_id = $this->customer->id;
        $sale->receipt_number = 'NOTA-LAMA-001';
        $sale->total_amount = 750000;
        $sale->discount_amount = 0;
        $sale->paid_amount = 0;
        $sale->payment_status = 'UNPAID';
        $sale->payment_method = 'kredit';
        $sale->status = 'completed';
        $sale->created_by = $this->cashier->id;
        $sale->save();

        app(JournalPostingService::class)->post([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JV-NOTA-LAMA-001',
            'description' => 'Saldo Awal Piutang - Pak Tani (Nota: NOTA-LAMA-001)',
            'lines' => [
                ['chart_of_account_id' => ChartOfAccount::where('code', '1130')->value('id'), 'debit' => 750000, 'credit' => 0],
                ['chart_of_account_id' => ChartOfAccount::where('code', '3110')->value('id'), 'debit' => 0, 'credit' => 750000],
            ],
        ]);

        $this->assertTrue(JournalHeader::where('reference_number', 'JV-NOTA-LAMA-001')->exists());

        return $sale;
    }
}
