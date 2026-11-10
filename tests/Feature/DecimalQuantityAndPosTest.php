<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\SupplierLedgerService;
use App\Support\Money;
use Tests\TestCase;

class DecimalQuantityAndPosTest extends TestCase
{
    private Branch $branch;

    private Branch $otherBranch;

    private User $master;

    private User $cashier;

    private Product $product;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['code' => 'PUSAT', 'name' => 'Gudang Pusat']);
        $this->otherBranch = Branch::create(['code' => 'CAB-2', 'name' => 'Cabang Dua', 'parent_id' => $this->branch->id]);

        $this->master = User::factory()->create([
            'email' => 'master@majubersama.178',
            'role' => 'master',
            'branch_id' => $this->branch->id,
        ]);

        $this->cashier = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);

        foreach ([
            ['1110', 'Kas', 'asset'],
            ['1210', 'Persediaan', 'asset'],
            ['2110', 'Hutang Dagang', 'liability'],
            ['4110', 'Pendapatan', 'revenue'],
            ['4130', 'Potongan Penjualan', 'revenue'],
            ['5100', 'Harga Pokok Penjualan', 'expense'],
        ] as [$code, $name, $type]) {
            ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type]);
        }

        $category = Category::create(['name' => 'Bahan Curah']);
        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'sku' => 'GULA-KG',
            'name' => 'Gula Pasir',
            'unit' => 'kg',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'stock' => 0,
        ]);

        $this->supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'name' => 'UD Manis Jaya',
            'phone' => '0813000001',
            'is_active' => true,
        ]);
    }

    public function test_decimal_goods_receipt_stores_exact_payable_and_stock(): void
    {
        $response = $this->actingAs($this->master)->post('/purchases/goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'date' => now()->toDateString(),
            'payment_type' => 'credit',
            'reference_number' => 'GR-DECIMAL-001',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2.25,
                'unit_price' => 12500.75,
            ]],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $receipt = GoodsReceipt::where('reference_number', 'GR-DECIMAL-001')->firstOrFail();
        $this->assertSame('28126.69', (string) $receipt->total_amount);
        $this->assertSame('2.250', (string) $receipt->items()->first()->quantity);
        $this->assertSame('12500.75', (string) $receipt->items()->first()->unit_price);
        $this->assertSame('28126.69', (string) $receipt->items()->first()->subtotal);
        $this->assertSame('2.250', (string) Inventory::withoutGlobalScopes()->where('product_id', $this->product->id)->value('quantity'));
        $this->assertSame('2.250', (string) $this->product->fresh()->stock);

        $journal = JournalHeader::with('journalLines')->where('reference_number', 'GR-DECIMAL-001')->firstOrFail();
        $debit = $journal->journalLines->sum(fn ($line) => (float) $line->debit);
        $credit = $journal->journalLines->sum(fn ($line) => (float) $line->credit);
        $this->assertEqualsWithDelta(28126.69, $debit, 0.001);
        $this->assertEqualsWithDelta($debit, $credit, 0.001);

        $ledger = app(SupplierLedgerService::class)->build($this->supplier);
        $this->assertSame('28126.69', $ledger['total_debt']);

        $this->actingAs($this->master)
            ->get(route('purchases.goods-receipts.show', $receipt))
            ->assertOk()
            ->assertSee('2,250')
            ->assertSee('28.126,69');

        $this->actingAs($this->master)
            ->get('/purchases/goods-receipts/create')
            ->assertOk()
            ->assertSee('step="0.001"', false)
            ->assertDontSee('step="100"', false);
    }

    public function test_supplier_payment_accepts_indonesian_decimal_amounts(): void
    {
        $cash = ChartOfAccount::where('code', '1110')->firstOrFail();

        $this->actingAs($this->master)->post('/backoffice/supplier-payments', [
            'supplier_id' => $this->supplier->id,
            'chart_of_account_id' => $cash->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'amount' => '12500,75',
            'reference_number' => 'PAY-SEN-001',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $payment = SupplierPayment::where('reference_number', 'PAY-SEN-001')->firstOrFail();
        $this->assertSame('12500.75', (string) $payment->amount);

        $journal = JournalHeader::with('journalLines')->findOrFail($payment->journal_header_id);
        $this->assertSame('12500.75', (string) $journal->journalLines->first()->debit);
        $this->assertEqualsWithDelta(
            (float) $journal->journalLines->sum('debit'),
            (float) $journal->journalLines->sum('credit'),
            0.001
        );

        $this->actingAs($this->master)->post('/purchases/goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'date' => now()->toDateString(),
            'payment_type' => 'credit',
            'reference_number' => 'GR-DECIMAL-PAY',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => '2.250',
                'unit_price' => '12500.75',
            ]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->master)->postJson(route('backoffice.suppliers.payments.store', $this->supplier), [
            'amount' => '12.500,75',
            'account_id' => $cash->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Bayar sen',
        ])->assertCreated();

        $ledger = app(SupplierLedgerService::class)->build($this->supplier->fresh());
        $this->assertSame('3125.19', $ledger['total_debt']);
        $this->assertTrue(Money::parse('12.500,75') === '12500.75');
    }

    public function test_pos_customer_search_is_scoped_and_keeps_price_tier(): void
    {
        $group = CustomerGroup::create(['name' => 'Petani']);
        $own = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Siti Aminah',
            'phone' => '0811111111',
            'customer_group_id' => $group->id,
        ]);
        Customer::create([
            'branch_id' => $this->otherBranch->id,
            'name' => 'Siti Aminah',
            'phone' => '0822222222',
            'customer_group_id' => $group->id,
        ]);
        ProductPrice::create([
            'product_id' => $this->product->id,
            'customer_group_id' => $group->id,
            'price' => 12000.5,
        ]);
        $this->product->update(['stock' => 5]);

        $cashierPage = $this->actingAs($this->cashier)->get('/pos');
        $cashierPage->assertOk();
        $cashierPage->assertSee('id="pos-customer-select"', false);
        $cashierPage->assertSee('Ketik nama atau telepon pelanggan');
        $cashierPage->assertSee('0811111111');
        $cashierPage->assertSee('Siti Aminah');
        $cashierPage->assertDontSee('0822222222');
        $cashierPage->assertSee('onCustomerChange()');

        $masterPage = $this->actingAs($this->master)->get('/pos');
        $masterPage->assertOk();
        $masterPage->assertSee('0811111111');
        $masterPage->assertSee('0822222222');
        $masterPage->assertSee($this->otherBranch->name);

        $created = $this->actingAs($this->cashier)->postJson(route('pos.customers.store'), [
            'name' => 'Budi Baru',
            'phone' => '0833333333',
            'customer_group_id' => $group->id,
        ]);
        $created->assertCreated();
        $this->assertSame($this->branch->id, Customer::where('phone', '0833333333')->value('branch_id'));

        $sale = $this->actingAs($this->cashier)->postJson('/pos', [
            'customer_id' => $own->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2.5,
            ]],
        ]);
        $sale->assertCreated();
        $this->assertSame('30001.25', number_format((float) $sale->json('sale.total_amount'), 2, '.', ''));
        $this->assertSame('2.500', number_format((float) $sale->json('sale.items.0.quantity'), 3, '.', ''));
        $this->assertSame('12000.50', number_format((float) $sale->json('sale.items.0.price'), 2, '.', ''));
        $this->assertSame('2.500', number_format((float) $this->product->fresh()->stock, 3, '.', ''));

        $saleJournal = JournalHeader::with('journalLines')->where('reference_number', $sale->json('receipt_number'))->firstOrFail();
        $this->assertEqualsWithDelta(
            (float) $saleJournal->journalLines->sum('debit'),
            (float) $saleJournal->journalLines->sum('credit'),
            0.001
        );
    }

    public function test_master_without_branch_can_open_pos_without_server_error(): void
    {
        $central = User::factory()->create([
            'email' => 'master.pusat@majubersama.178',
            'role' => 'master',
            'branch_id' => null,
        ]);
        $unassigned = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => null,
        ]);

        $this->actingAs($central)->get('/pos')
            ->assertOk()
            ->assertSee('Pilih cabang yang akan dilayani');

        $this->actingAs($central)->get('/pos?branch_id=999999')
            ->assertOk()
            ->assertSee('Cabang yang dipilih tidak ditemukan');

        $this->actingAs($central)->get('/pos?branch_id='.$this->branch->id)
            ->assertOk()
            ->assertSee('Kasir POS Multi-Store')
            ->assertSee($this->branch->name);

        $this->actingAs($unassigned)->get('/pos')
            ->assertOk()
            ->assertSee('belum terhubung ke cabang');
    }
}
