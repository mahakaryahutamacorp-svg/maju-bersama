<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;
    private Branch $branchB;
    private User $userA;
    private User $masterUser;
    private Supplier $supplier;
    private Category $category;
    private Product $productA;
    private Product $productB;
    private PurchaseReturnService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::create([
            'name' => 'Cabang Surabaya',
            'code' => 'SBY01',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'name' => 'Cabang Malang',
            'code' => 'MLG01',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'branch_id' => $this->branchA->id,
            'role' => 'branch_admin',
        ]);

        $this->masterUser = User::factory()->create([
            'branch_id' => $this->branchA->id,
            'role' => 'master',
        ]);

        $this->supplier = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'name' => 'PT Mitra Sejahtera',
            'contact_person' => 'Hartono',
            'phone' => '081122334455',
            'is_active' => true,
        ]);

        $this->category = Category::create(['name' => 'Sembako']);

        $this->productA = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'category_id' => $this->category->id,
            'sku' => 'BERAS-01',
            'name' => 'Beras Premium 5kg',
            'purchase_price' => 60000,
            'selling_price' => 72000,
            'stock' => 100,
        ]);

        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'product_id' => $this->productA->id,
            'quantity' => 100,
        ]);

        $this->productB = Product::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'category_id' => $this->category->id,
            'sku' => 'MINYAK-01',
            'name' => 'Minyak Goreng 2L',
            'purchase_price' => 30000,
            'selling_price' => 36000,
            'stock' => 50,
        ]);

        Inventory::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'product_id' => $this->productB->id,
            'quantity' => 50,
        ]);

        $this->service = app(PurchaseReturnService::class);
    }

    public function test_process_return_success_creates_records_and_auto_reference_number(): void
    {
        $data = [
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'return_date' => '2026-09-21',
            'notes' => 'Kemasan rusak saat pengiriman',
        ];

        $items = [
            [
                'product_id' => $this->productA->id,
                'quantity' => 10,
                'unit_price' => 60000,
            ],
        ];

        $purchaseReturn = $this->service->processReturn($data, $items, $this->userA);

        $this->assertInstanceOf(PurchaseReturn::class, $purchaseReturn);
        $this->assertStringStartsWith('PRT-', $purchaseReturn->reference_number);
        $this->assertEquals('completed', $purchaseReturn->status);
        $this->assertEquals('600000.00', $purchaseReturn->total_amount);
        $this->assertEquals('Kemasan rusak saat pengiriman', $purchaseReturn->notes);
        $this->assertNotNull($purchaseReturn->journal_header_id);

        $this->assertDatabaseHas('purchase_returns', [
            'id' => $purchaseReturn->id,
            'reference_number' => $purchaseReturn->reference_number,
            'supplier_id' => $this->supplier->id,
            'total_amount' => '600000.00',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('purchase_return_items', [
            'purchase_return_id' => $purchaseReturn->id,
            'product_id' => $this->productA->id,
            'quantity' => 10,
            'unit_price' => '60000.00',
            'subtotal' => '600000.00',
        ]);
    }

    public function test_process_return_reduces_inventory_and_product_cached_stock(): void
    {
        $this->assertEquals(100, $this->productA->stock);
        $invBefore = Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->branchA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(100, $invBefore->quantity);

        $data = [
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'notes' => 'Retur sebagian stok cacat',
        ];

        $items = [
            [
                'product_id' => $this->productA->id,
                'quantity' => 15,
                'unit_price' => 60000,
            ],
        ];

        $this->service->processReturn($data, $items, $this->userA);

        $this->productA->refresh();
        $invAfter = Inventory::withoutGlobalScopes()
            ->where('branch_id', $this->branchA->id)
            ->where('product_id', $this->productA->id)
            ->first();

        // 100 - 15 = 85
        $this->assertEquals(85, $this->productA->stock);
        $this->assertEquals(85, $invAfter->quantity);
    }

    public function test_process_return_records_balanced_accounting_journal(): void
    {
        $data = [
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'return_date' => '2026-09-21',
            'notes' => 'Barang kadaluarsa',
        ];

        $items = [
            [
                'product_id' => $this->productA->id,
                'quantity' => 5,
                'unit_price' => 60000, // 300,000
            ],
        ];

        $purchaseReturn = $this->service->processReturn($data, $items, $this->userA);

        $journal = $purchaseReturn->journalHeader;
        $this->assertNotNull($journal);
        $this->assertEquals($purchaseReturn->reference_number, $journal->reference_number);
        $this->assertEquals("Retur Pembelian ke Supplier {$this->supplier->name} - Barang kadaluarsa", $journal->description);

        $lines = $journal->journalLines;
        $this->assertCount(2, $lines);

        // Debit: Hutang Dagang (2110)
        $debitLine = $lines->firstWhere('chartOfAccount.code', PurchaseReturnService::ACCOUNT_PAYABLE);
        $this->assertNotNull($debitLine);
        $this->assertEquals('300000.00', $debitLine->debit);
        $this->assertEquals('0.00', $debitLine->credit);

        // Kredit: Persediaan (1210)
        $creditLine = $lines->firstWhere('chartOfAccount.code', PurchaseReturnService::ACCOUNT_INVENTORY);
        $this->assertNotNull($creditLine);
        $this->assertEquals('0.00', $creditLine->debit);
        $this->assertEquals('300000.00', $creditLine->credit);

        // Seimbang
        $totalDebit = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEquals('300000.00', number_format($totalDebit, 2, '.', ''));
        $this->assertEquals('300000.00', number_format($totalCredit, 2, '.', ''));
    }

    public function test_process_return_with_goods_receipt_link(): void
    {
        $gr = GoodsReceipt::create([
            'branch_id' => $this->branchA->id,
            'reference_number' => 'GR-20260921-TEST1',
            'supplier_name' => $this->supplier->name,
            'date' => '2026-09-20',
            'total_amount' => 500000,
            'payment_type' => 'credit',
        ]);

        $data = [
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'goods_receipt_id' => $gr->id,
            'notes' => 'Retur dari penerimaan GR-20260921-TEST1',
        ];

        $items = [
            [
                'product_id' => $this->productA->id,
                'quantity' => 2,
                'unit_price' => 60000,
            ],
        ];

        $purchaseReturn = $this->service->processReturn($data, $items, $this->userA);

        $this->assertEquals($gr->id, $purchaseReturn->goods_receipt_id);
        $this->assertInstanceOf(GoodsReceipt::class, $purchaseReturn->goodsReceipt);
        $this->assertEquals('GR-20260921-TEST1', $purchaseReturn->goodsReceipt->reference_number);
    }

    public function test_process_return_with_multiple_items(): void
    {
        $data = [
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'notes' => 'Batch retur beberapa barang',
        ];

        $items = [
            [
                'product_id' => $this->productA->id,
                'quantity' => 5,
                'unit_price' => 60000, // 300,000
            ],
            [
                'product_id' => $this->productB->id,
                'quantity' => 10,
                'unit_price' => 30000, // 300,000
            ],
        ];

        $purchaseReturn = $this->service->processReturn($data, $items, $this->userA);

        $this->assertEquals('600000.00', $purchaseReturn->total_amount);
        $this->assertCount(2, $purchaseReturn->items);

        $this->productA->refresh();
        $this->productB->refresh();
        $this->assertEquals(95, $this->productA->stock); // 100 - 5
        $this->assertEquals(40, $this->productB->stock); // 50 - 10

        // Jurnal seimbang untuk multi item
        $lines = $purchaseReturn->journalHeader->journalLines;
        $this->assertEquals('600000.00', number_format($lines->sum('debit'), 2, '.', ''));
        $this->assertEquals('600000.00', number_format($lines->sum('credit'), 2, '.', ''));
    }

    public function test_process_return_validation_errors(): void
    {
        // 1. Empty items
        $this->expectException(ValidationException::class);
        $this->service->processReturn([
            'supplier_id' => $this->supplier->id,
        ], []);
    }

    public function test_process_return_fails_with_invalid_supplier(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->processReturn([
            'supplier_id' => 999999,
        ], [
            ['product_id' => $this->productA->id, 'quantity' => 1],
        ]);
    }

    public function test_purchase_return_has_branch_scope(): void
    {
        $supplierB = Supplier::withoutGlobalScopes()->create([
            'branch_id' => $this->branchB->id,
            'name' => 'Supplier Malang',
            'is_active' => true,
        ]);

        $retA = PurchaseReturn::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'reference_number' => 'PRT-A-001',
            'return_date' => '2026-09-21',
            'total_amount' => 100000,
            'status' => 'completed',
        ]);

        $retB = PurchaseReturn::withoutGlobalScopes()->create([
            'branch_id' => $this->branchB->id,
            'supplier_id' => $supplierB->id,
            'reference_number' => 'PRT-B-001',
            'return_date' => '2026-09-21',
            'total_amount' => 200000,
            'status' => 'completed',
        ]);

        // Branch A user only sees Branch A returns
        $this->actingAs($this->userA);
        $this->assertCount(1, PurchaseReturn::all());
        $this->assertEquals('PRT-A-001', PurchaseReturn::first()->reference_number);

        // Master user sees both
        $this->actingAs($this->masterUser);
        $this->assertCount(2, PurchaseReturn::all());
    }

    public function test_inverse_relationships(): void
    {
        $ret = PurchaseReturn::withoutGlobalScopes()->create([
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'reference_number' => 'PRT-REL-001',
            'return_date' => '2026-09-21',
            'total_amount' => 100000,
            'status' => 'completed',
        ]);

        $item = PurchaseReturnItem::create([
            'purchase_return_id' => $ret->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'subtotal' => 100000,
        ]);

        // Branch relation
        $this->assertTrue($this->branchA->purchaseReturns->contains($ret));

        // Supplier relation
        $this->assertTrue($this->supplier->purchaseReturns->contains($ret));

        // Product relation
        $this->assertTrue($this->productA->purchaseReturnItems->contains($item));

        // Item relations
        $this->assertEquals($ret->id, $item->purchaseReturn->id);
        $this->assertEquals($this->productA->id, $item->product->id);
    }
}
