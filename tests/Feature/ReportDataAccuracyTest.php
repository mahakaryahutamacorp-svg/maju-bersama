<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductService;
use App\Services\PurchaseReturnService;
use App\Services\Reports\AgingReportService;
use App\Services\Reports\CashFlowService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\TransactionLedgerService;
use App\Services\StockCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDataAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $owner;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['code' => 'PUSAT', 'name' => 'Kantor Pusat', 'is_active' => true]);
        $this->owner = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'master']);
        $this->category = Category::create(['name' => 'Pupuk']);
        $this->actingAs($this->owner);
    }

    public function test_ar_aging_deducts_returns_booked_against_receivable(): void
    {
        $receivable = ChartOfAccount::create(['code' => '1130', 'name' => 'Piutang Usaha', 'type' => 'asset']);
        $cash = ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);

        $sale = $this->creditSale(500000, 100000);
        $this->salesReturn($sale, $receivable, 150000);
        $this->salesReturn($sale, $cash, 50000);

        $report = app(AgingReportService::class)->getArAging();

        $this->assertEqualsWithDelta(250000, $report['totals']['total_amount'], 0.01);
    }

    public function test_ar_aging_drops_invoice_fully_settled_by_return(): void
    {
        $receivable = ChartOfAccount::create(['code' => '1130', 'name' => 'Piutang Usaha', 'type' => 'asset']);

        $sale = $this->creditSale(300000, 0);
        $this->salesReturn($sale, $receivable, 300000);

        $report = app(AgingReportService::class)->getArAging();

        $this->assertSame(0, $report['totals']['total_invoices']);
        $this->assertEqualsWithDelta(0, $report['totals']['total_amount'], 0.01);
    }

    public function test_stock_card_includes_purchase_return_and_matches_inventory(): void
    {
        $product = $this->productWithStock($this->branch, 'Urea 50kg', 40);
        $supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id,
            'name' => 'PT Pupuk Nusantara',
            'is_active' => true,
        ]);

        app(PurchaseReturnService::class)->processReturn([
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'return_date' => now()->toDateString(),
            'notes' => 'Karung sobek',
        ], [['product_id' => $product->id, 'quantity' => 6, 'unit_price' => 250000]], $this->owner);

        $card = app(StockCardService::class)->getStockCard($this->branch->id, $product->id, '2000-01-01', now()->toDateString());

        $this->assertSame(34, $card['current_stock']);
        $this->assertSame(34, $card['ending_balance']);
        $this->assertSame(6, $card['total_out']);
        $this->assertSame('Retur Pembelian', $card['movements']->last()->type);
        $this->assertStringContainsString('PT Pupuk Nusantara', $card['movements']->last()->notes);
    }

    public function test_product_created_with_stock_records_opening_stock_document(): void
    {
        $product = $this->productWithStock($this->branch, 'NPK Phonska', 25);

        $adjustment = StockAdjustment::query()->latest('id')->first();
        $this->assertNotNull($adjustment);
        $this->assertSame('Input Stok Awal', $adjustment->notes);
        $this->assertSame(25, (int) Inventory::withoutGlobalScopes()->where('product_id', $product->id)->value('quantity'));
        $this->assertSame(25, (int) $product->fresh()->stock);

        $card = app(StockCardService::class)->getStockCard($this->branch->id, $product->id, '2000-01-01', now()->toDateString());
        $this->assertSame(25, $card['ending_balance']);
        $this->assertSame($card['current_stock'], $card['ending_balance']);
    }

    public function test_product_stock_update_records_correction_document(): void
    {
        $product = $this->productWithStock($this->branch, 'KCL', 10);

        app(ProductService::class)->updateProduct($product, ['stock' => 7]);

        $card = app(StockCardService::class)->getStockCard($this->branch->id, $product->id, '2000-01-01', now()->toDateString());
        $this->assertSame(7, $card['current_stock']);
        $this->assertSame(7, $card['ending_balance']);
        $this->assertSame(3, $card['total_out']);
    }

    public function test_stock_card_without_branch_filter_uses_product_branch(): void
    {
        $otherBranch = Branch::create(['code' => 'CAB2', 'name' => 'Cabang Dua', 'is_active' => true]);
        $product = $this->productWithStock($otherBranch, 'Pestisida Cair', 12);

        $report = app(InventoryReportService::class)->getStockCard('2000-01-01', now()->toDateString(), null, $product->id);

        $this->assertSame($otherBranch->id, $report['branch']->id);
        $this->assertSame(12, $report['ending_balance']);
        $this->assertSame(12, $report['current_stock']);
    }

    public function test_purchase_report_includes_direct_goods_receipts_without_double_counting(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'CV Tani Jaya', 'is_active' => true]);
        $order = PurchaseOrder::create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-TEST-001',
            'order_date' => now()->toDateString(),
            'status' => 'completed',
            'total_amount' => 1000000,
        ]);
        GoodsReceipt::create([
            'branch_id' => $this->branch->id,
            'purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'GR-FROM-PO',
            'date' => now()->toDateString(),
            'total_amount' => 1000000,
            'payment_type' => 'credit',
        ]);
        GoodsReceipt::create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => 'CV Tani Jaya',
            'reference_number' => 'GR-DIRECT-001',
            'date' => now()->toDateString(),
            'total_amount' => 140000,
            'payment_type' => 'cash',
        ]);

        $report = app(PurchaseReportService::class)->getPurchases(now()->startOfMonth()->toDateString(), now()->toDateString());

        $this->assertEqualsWithDelta(1140000, $report['total_purchases'], 0.01);
        $this->assertSame(1, $report['total_orders']);
        $this->assertSame(1, $report['direct_receipts']);
        $this->assertEqualsCanonicalizing(['PO-TEST-001', 'GR-DIRECT-001'], $report['purchases']->pluck('reference_number')->all());

        $this->get(route('reports.purchases'))
            ->assertOk()
            ->assertSee('GR-DIRECT-001')
            ->assertSee('Terima langsung (tunai)')
            ->assertDontSee('GR-FROM-PO');
    }

    public function test_cash_flow_matches_transaction_ledger_and_classifies_activities(): void
    {
        $cash = ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        $bank = ChartOfAccount::create(['code' => '1120', 'name' => 'Bank', 'type' => 'asset']);
        $drawer = ChartOfAccount::create(['code' => '1150-01', 'name' => 'Laci Kasir 1', 'type' => 'asset', 'cash_flow_tag' => 'cash']);
        $revenue = ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue']);
        $fixedAsset = ChartOfAccount::create(['code' => '1310', 'name' => 'Aktiva Tetap', 'type' => 'asset']);
        $capital = ChartOfAccount::create(['code' => '3110', 'name' => 'Modal', 'type' => 'equity']);
        $bankLoanName = ChartOfAccount::create(['code' => '2210', 'name' => 'Hutang Bank Jangka Panjang', 'type' => 'liability']);

        $this->journal('JRN-MODAL', [[$cash, 5000000, 0], [$capital, 0, 5000000]]);
        $this->journal('JRN-JUAL', [[$drawer, 300000, 0], [$revenue, 0, 300000]]);
        $this->journal('JRN-SETOR', [[$bank, 2000000, 0], [$cash, 0, 2000000]]);
        $this->journal('JRN-ASET', [[$fixedAsset, 1500000, 0], [$bank, 0, 1500000]]);
        $this->journal('JRN-PINJAM', [[$bank, 1000000, 0], [$bankLoanName, 0, 1000000]]);

        $today = now()->toDateString();
        $flow = app(CashFlowService::class)->generate(now()->startOfMonth()->toDateString(), $today);
        $ledger = app(TransactionLedgerService::class)->getLedger($this->owner, [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => $today,
            'type' => 'all',
            'branch_id' => null,
            'search' => '',
        ]);

        $this->assertEqualsWithDelta(4800000, $flow['ending_balance'], 0.01);
        $this->assertEqualsWithDelta($ledger['closing_balance'], $flow['ending_balance'], 0.01);
        $this->assertEqualsWithDelta(6300000, $flow['cash_in']['total'], 0.01);
        $this->assertEqualsWithDelta(1500000, $flow['cash_out']['total'], 0.01);
        $this->assertNotContains('JRN-SETOR', array_column($flow['cash_in']['items'], 'reference'));

        $this->assertEqualsWithDelta(300000, $flow['activities']['operating']['net'], 0.01);
        $this->assertEqualsWithDelta(-1500000, $flow['activities']['investing']['net'], 0.01);
        $this->assertEqualsWithDelta(6000000, $flow['activities']['financing']['net'], 0.01);

        $this->get(route('reports.cash-flow'))
            ->assertOk()
            ->assertSee('Ringkasan per Aktivitas')
            ->assertSee('Aktivitas Investasi');
    }

    private function creditSale(float $total, float $paid): Sale
    {
        return Sale::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'INV-TEMPO-'.uniqid(),
            'total_amount' => $total,
            'paid_amount' => $paid,
            'payment_method' => 'tempo',
            'payment_status' => $paid > 0 ? 'PARTIAL' : 'UNPAID',
            'status' => 'completed',
            'created_by' => $this->owner->id,
            'due_date' => now()->addDays(14)->toDateString(),
        ]);
    }

    private function salesReturn(Sale $sale, ChartOfAccount $account, float $amount): void
    {
        SalesReturn::create([
            'branch_id' => $this->branch->id,
            'sale_id' => $sale->id,
            'user_id' => $this->owner->id,
            'customer_name' => 'Kelompok Tani',
            'return_date' => now()->toDateString(),
            'reference_number' => 'SR-'.uniqid(),
            'refund_method' => $account->code === '1130' ? 'tempo' : 'cash',
            'chart_of_account_id' => $account->id,
            'total_amount' => $amount,
            'total_cost' => 0,
            'status' => 'completed',
        ]);
    }

    private function productWithStock(Branch $branch, string $name, int $stock): Product
    {
        return app(ProductService::class)->createProduct([
            'category_id' => $this->category->id,
            'name' => $name,
            'purchase_price' => 250000,
            'selling_price' => 275000,
            'stock' => $stock,
        ], $branch->id);
    }

    /**
     * @param  list<array{0: ChartOfAccount, 1: float, 2: float}>  $lines
     */
    private function journal(string $reference, array $lines): void
    {
        $journal = JournalHeader::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->owner->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => $reference,
            'description' => $reference,
        ]);

        $journal->journalLines()->createMany(array_map(fn (array $line) => [
            'chart_of_account_id' => $line[0]->id,
            'debit' => $line[1],
            'credit' => $line[2],
            'memo' => $reference,
        ], $lines));
    }
}
