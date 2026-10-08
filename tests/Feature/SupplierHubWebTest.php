<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\JournalHeader;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierHubWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private User $admin;

    private Supplier $supplier;

    private ChartOfAccount $cashAccount;

    private ChartOfAccount $payableAccount;

    private Product $soap;

    private Product $shampoo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['code' => 'HUB-A', 'name' => 'Toko Hub A']);
        $this->otherBranch = Branch::create(['code' => 'HUB-B', 'name' => 'Toko Hub B']);

        $this->admin = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'branch_admin',
        ]);

        $this->supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'name' => 'CV Sumber Rejeki',
            'contact_person' => 'Pak Darto',
            'phone' => '0812000111',
            'is_active' => true,
        ]);

        $this->cashAccount = ChartOfAccount::create(['code' => '1110', 'name' => 'Kas Toko', 'type' => 'asset', 'is_active' => true]);
        $this->payableAccount = ChartOfAccount::create(['code' => '2110', 'name' => 'Hutang Dagang', 'type' => 'liability', 'is_active' => true]);

        $category = Category::create(['name' => 'Kebutuhan Mandi']);
        $this->soap = $this->makeProduct($category->id, 'SBN-01', 'Sabun Mandi');
        $this->shampoo = $this->makeProduct($category->id, 'SMP-01', 'Sampo Sachet');
    }

    private function makeProduct(int $categoryId, string $sku, string $name): Product
    {
        return Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $categoryId,
            'sku' => $sku,
            'name' => $name,
            'purchase_price' => 1000,
            'selling_price' => 1500,
            'stock' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Nota yang barangnya sudah diterima (hutang muncul).
     */
    private function receivedInvoice(string $orderDate, float $amount, array $products, ?Supplier $supplier = null, ?Branch $branch = null): PurchaseOrder
    {
        $supplier ??= $this->supplier;
        $branch ??= $this->branch;

        $order = PurchaseOrder::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-'.uniqid(),
            'order_date' => $orderDate,
            'status' => 'completed',
            'total_amount' => $amount,
        ]);

        $receipt = GoodsReceipt::create([
            'branch_id' => $branch->id,
            'purchase_order_id' => $order->id,
            'reference_number' => 'GR-'.uniqid(),
            'supplier_name' => $supplier->name,
            'date' => $orderDate,
            'total_amount' => $amount,
            'payment_type' => 'credit',
        ]);

        $share = $amount / count($products);
        foreach ($products as $product) {
            $receipt->items()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $share,
                'subtotal' => $share,
            ]);
        }

        return $order;
    }

    public function test_hub_shows_debt_card_tabs_and_human_readable_descriptions(): void
    {
        $this->receivedInvoice('2026-09-01', 4000000, [$this->soap, $this->shampoo]);
        $receipt = GoodsReceipt::query()->latest('id')->first();

        PurchaseReturn::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'goods_receipt_id' => $receipt->id,
            'reference_number' => 'PRT-HIDDEN-001',
            'return_date' => '2026-09-05',
            'total_amount' => 500000,
            'status' => 'completed',
            'notes' => 'Barang Rusak',
        ]);

        $response = $this->actingAs($this->admin)->get(route('backoffice.suppliers.show', $this->supplier));

        $response->assertOk()
            ->assertSee('Total Sisa Hutang Kita')
            ->assertSee('Rp 3.500.000')
            ->assertSee('Riwayat Belanja')
            ->assertSee('Riwayat Pembayaran')
            ->assertSee('Riwayat Retur')
            ->assertSee('+ Bayar Hutang')
            ->assertSee('Nominal Dibayar')
            ->assertSee('Terima Barang - Sabun Mandi dkk')
            ->assertSee('Retur Barang - Barang Rusak')
            ->assertDontSee('PRT-HIDDEN-001')
            ->assertDontSee('Purchase Order')
            ->assertDontSee('Goods Receipt')
            ->assertDontSee('Account Payable');
    }

    public function test_partial_payment_is_applied_fifo_to_oldest_invoice_with_journal(): void
    {
        $oldest = $this->receivedInvoice('2026-08-01', 2000000, [$this->soap]);
        $newer = $this->receivedInvoice('2026-09-01', 10000000, [$this->shampoo]);

        $response = $this->actingAs($this->admin)->postJson(route('backoffice.suppliers.payments.store', $this->supplier), [
            'amount' => '3.250.000',
            'account_id' => $this->cashAccount->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Pembayaran Tunai ke Kurir',
        ]);

        $response->assertCreated()
            ->assertJsonPath('total_debt', 8750000)
            ->assertJsonPath('payment.description', 'Pembayaran Tunai ke Kurir (memotong 2 nota)');

        $this->assertSame('2000000.0000', $oldest->fresh()->paid_amount);
        $this->assertSame('PAID', $oldest->fresh()->payment_status);
        $this->assertSame('1250000.0000', $newer->fresh()->paid_amount);
        $this->assertSame('PARTIAL', $newer->fresh()->payment_status);

        $payment = Payment::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->supplier->id, $payment->supplier_id);
        $this->assertCount(2, $payment->allocations);

        $journal = JournalHeader::query()->findOrFail($payment->journal_header_id);
        $debit = $journal->journalLines->firstWhere('chart_of_account_id', $this->payableAccount->id);
        $credit = $journal->journalLines->firstWhere('chart_of_account_id', $this->cashAccount->id);
        $this->assertEquals(3250000, (float) $debit->debit);
        $this->assertEquals(3250000, (float) $credit->credit);
    }

    public function test_fifo_payment_covers_direct_credit_receipt_without_purchase_order(): void
    {
        $directReceipt = GoodsReceipt::create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'reference_number' => 'GR-DIRECT-OLD',
            'supplier_name' => $this->supplier->name,
            'date' => '2026-07-01',
            'total_amount' => 1500000,
            'payment_type' => 'credit',
        ]);
        $directReceipt->items()->create([
            'product_id' => $this->soap->id,
            'quantity' => 10,
            'unit_price' => 150000,
            'subtotal' => 1500000,
        ]);

        GoodsReceipt::create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'reference_number' => 'GR-DIRECT-CASH',
            'supplier_name' => $this->supplier->name,
            'date' => '2026-07-02',
            'total_amount' => 999000,
            'payment_type' => 'cash',
        ]);

        $order = $this->receivedInvoice('2026-08-01', 2000000, [$this->shampoo]);

        $this->actingAs($this->admin)
            ->get(route('backoffice.suppliers.show', $this->supplier))
            ->assertOk()
            ->assertSee('Rp 3.500.000')
            ->assertSee('Terima Barang - Sabun Mandi')
            ->assertDontSee('GR-DIRECT-OLD');

        $this->actingAs($this->admin)->postJson(route('backoffice.suppliers.payments.store', $this->supplier), [
            'amount' => '2.000.000',
            'account_id' => $this->cashAccount->id,
            'payment_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('total_debt', 1500000);

        $this->assertEquals(1500000, (float) $directReceipt->fresh()->paid_amount);
        $this->assertEquals(500000, (float) $order->fresh()->paid_amount);

        $payment = Payment::withoutGlobalScopes()->with('allocations')->firstOrFail();
        $this->assertSame($directReceipt->id, $payment->allocations->firstWhere('goods_receipt_id', '!=', null)->goods_receipt_id);
        $this->assertSame($order->id, $payment->allocations->firstWhere('purchase_order_id', '!=', null)->purchase_order_id);
    }

    public function test_overpayment_is_rejected_without_side_effects(): void
    {
        $this->receivedInvoice('2026-08-01', 1000000, [$this->soap]);

        $this->actingAs($this->admin)->postJson(route('backoffice.suppliers.payments.store', $this->supplier), [
            'amount' => '1.500.000',
            'account_id' => $this->cashAccount->id,
            'payment_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->assertSame(0, Payment::withoutGlobalScopes()->count());
        $this->assertSame(0, JournalHeader::query()->count());
    }

    public function test_non_js_submit_redirects_back_to_same_tab(): void
    {
        $this->receivedInvoice('2026-08-01', 1000000, [$this->soap]);

        $this->actingAs($this->admin)->post(route('backoffice.suppliers.payments.store', $this->supplier), [
            'amount' => 'Rp 400.000',
            'account_id' => $this->cashAccount->id,
            'payment_date' => now()->toDateString(),
            'tab' => 'riwayat_belanja',
        ])->assertRedirect(route('backoffice.suppliers.show', ['supplier' => $this->supplier, 'tab' => 'riwayat_belanja']));

        $this->actingAs($this->admin)
            ->get(route('backoffice.suppliers.show', ['supplier' => $this->supplier, 'tab' => 'riwayat_pembayaran']))
            ->assertOk()
            ->assertSee('Rp 600.000')
            ->assertSee('Bayar Hutang Tunai dari Kas Toko');
    }

    public function test_branch_admin_cannot_open_or_pay_other_branch_supplier(): void
    {
        $foreignSupplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->otherBranch->id,
            'name' => 'PT Cabang Lain',
            'is_active' => true,
        ]);
        $this->receivedInvoice('2026-08-01', 1000000, [$this->soap], $foreignSupplier, $this->otherBranch);

        $this->actingAs($this->admin)->get(route('backoffice.suppliers.show', $foreignSupplier))->assertNotFound();

        $this->actingAs($this->admin)->postJson(route('backoffice.suppliers.payments.store', $foreignSupplier), [
            'amount' => '100000',
            'account_id' => $this->cashAccount->id,
            'payment_date' => now()->toDateString(),
        ])->assertNotFound();

        $this->assertSame(0, Payment::withoutGlobalScopes()->count());
    }

    public function test_cashier_cannot_access_supplier_hub(): void
    {
        $cashier = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']);

        $this->actingAs($cashier)->get(route('backoffice.suppliers.show', $this->supplier))->assertForbidden();
        $this->actingAs($cashier)->postJson(route('backoffice.suppliers.payments.store', $this->supplier), [
            'amount' => '1000',
            'account_id' => $this->cashAccount->id,
            'payment_date' => now()->toDateString(),
        ])->assertForbidden();
    }
}
