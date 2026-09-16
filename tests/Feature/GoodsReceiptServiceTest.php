<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\User;
use App\Services\GoodsReceiptService;
use Tests\TestCase;

class GoodsReceiptServiceTest extends TestCase
{
    private GoodsReceiptService $service;
    private Branch $central;
    private User $master;
    private Category $category;
    private Product $productA;
    private Product $productB;
    private ChartOfAccount $accountCash;
    private ChartOfAccount $accountInventory;
    private ChartOfAccount $accountPayable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GoodsReceiptService();

        // 1. Setup Branch & User
        $this->central = Branch::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'parent_id' => null,
        ]);

        $this->master = User::factory()->create([
            'branch_id' => $this->central->id,
            'role' => 'master',
        ]);

        // 2. Setup Chart of Accounts
        $this->accountCash = ChartOfAccount::firstOrCreate(
            ['code' => '1110'],
            ['name' => 'Kas', 'type' => 'asset']
        );
        $this->accountInventory = ChartOfAccount::firstOrCreate(
            ['code' => '1210'],
            ['name' => 'Persediaan', 'type' => 'asset']
        );
        $this->accountPayable = ChartOfAccount::firstOrCreate(
            ['code' => '2110'],
            ['name' => 'Hutang Dagang', 'type' => 'liability']
        );

        // 3. Setup Categories & Products at Pusat
        $this->category = Category::create(['name' => 'Bahan Pokok']);

        $this->productA = Product::create([
            'branch_id' => $this->central->id,
            'category_id' => $this->category->id,
            'sku' => 'PRD-001',
            'name' => 'Minyak Goreng 1L',
            'purchase_price' => 14000,
            'selling_price' => 17000,
            'stock' => 10,
        ]);

        $this->productB = Product::create([
            'branch_id' => $this->central->id,
            'category_id' => $this->category->id,
            'sku' => 'PRD-002',
            'name' => 'Beras Premium 5kg',
            'purchase_price' => 60000,
            'selling_price' => 72000,
            'stock' => 5,
        ]);

        // Initial inventory rows
        Inventory::create([
            'branch_id' => $this->central->id,
            'product_id' => $this->productA->id,
            'quantity' => 10,
        ]);

        Inventory::create([
            'branch_id' => $this->central->id,
            'product_id' => $this->productB->id,
            'quantity' => 5,
        ]);
    }

    public function test_goods_receipt_cash_increments_inventory_and_records_balanced_cash_journal(): void
    {
        $payload = [
            'supplier_name' => 'PT Sumber Pangan Sejahtera',
            'date' => '2026-09-16',
            'payment_type' => 'cash',
            'notes' => 'Penerimaan stok tunai awal',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 20,
                    'unit_price' => 14000, // 20 * 14000 = 280,000
                ],
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 10,
                    'unit_price' => 60000, // 10 * 60000 = 600,000
                ],
            ],
        ];

        // Total should be 280,000 + 600,000 = 880,000
        $receipt = $this->service->processReceipt($payload, $this->master);

        // 1. Assert GoodsReceipt and Items created
        $this->assertInstanceOf(GoodsReceipt::class, $receipt);
        $this->assertEquals(880000.00, (float) $receipt->total_amount);
        $this->assertEquals('cash', $receipt->payment_type);
        $this->assertEquals('PT Sumber Pangan Sejahtera', $receipt->supplier_name);
        $this->assertCount(2, $receipt->items);

        // 2. Assert stock in inventories table is incremented
        $invA = Inventory::where('branch_id', $this->central->id)->where('product_id', $this->productA->id)->first();
        $invB = Inventory::where('branch_id', $this->central->id)->where('product_id', $this->productB->id)->first();
        $this->assertEquals(30, $invA->quantity, 'Product A inventory quantity should increment from 10 to 30');
        $this->assertEquals(15, $invB->quantity, 'Product B inventory quantity should increment from 5 to 15');

        // 3. Assert stock column in products table is synced
        $this->productA->refresh();
        $this->productB->refresh();
        $this->assertEquals(30, $this->productA->stock, 'Product A cached stock should increment from 10 to 30');
        $this->assertEquals(15, $this->productB->stock, 'Product B cached stock should increment from 5 to 15');

        // 4. Assert Journal Header and Lines are created and strictly balanced
        $journal = JournalHeader::with('journalLines')
            ->where('reference_number', $receipt->reference_number)
            ->first();

        $this->assertNotNull($journal, 'Journal header must be recorded');
        $this->assertEquals($this->central->id, $journal->branch_id);
        $this->assertEquals('2026-09-16', $journal->transaction_date->format('Y-m-d'));

        $lines = $journal->journalLines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('chart_of_account_id', $this->accountInventory->id);
        $creditLine = $lines->firstWhere('chart_of_account_id', $this->accountCash->id);

        $this->assertNotNull($debitLine, 'Must have Debit line for Persediaan (1210)');
        $this->assertNotNull($creditLine, 'Must have Credit line for Kas (1110)');

        $this->assertEquals(880000.00, (float) $debitLine->debit);
        $this->assertEquals(0.00, (float) $debitLine->credit);

        $this->assertEquals(0.00, (float) $creditLine->debit);
        $this->assertEquals(880000.00, (float) $creditLine->credit);

        // Double-entry balance check: Total Debit == Total Credit
        $totalDebit = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(880000.00, (float) $totalDebit);
    }

    public function test_goods_receipt_credit_records_payable_journal(): void
    {
        $payload = [
            'supplier_name' => 'CV Distribusi Nusantara',
            'date' => '2026-09-17',
            'payment_type' => 'credit',
            'notes' => 'Pembelian tempo 30 hari',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 15,
                    'unit_price' => 14000, // 15 * 14000 = 210,000
                ],
            ],
        ];

        $receipt = $this->service->processReceipt($payload, $this->master);

        $this->assertEquals('credit', $receipt->payment_type);
        $this->assertEquals(210000.00, (float) $receipt->total_amount);

        // Inventory incremented: 10 + 15 = 25
        $invA = Inventory::where('branch_id', $this->central->id)->where('product_id', $this->productA->id)->first();
        $this->assertEquals(25, $invA->quantity);

        // Journal verification: Dr 1210 (Persediaan), Cr 2110 (Hutang Dagang)
        $journal = JournalHeader::with('journalLines')
            ->where('reference_number', $receipt->reference_number)
            ->first();

        $this->assertNotNull($journal);

        $debitLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->accountInventory->id);
        $creditLine = $journal->journalLines->firstWhere('chart_of_account_id', $this->accountPayable->id);

        $this->assertNotNull($debitLine, 'Must have Debit line for Persediaan (1210)');
        $this->assertNotNull($creditLine, 'Must have Credit line for Hutang Dagang (2110)');

        $this->assertEquals(210000.00, (float) $debitLine->debit);
        $this->assertEquals(210000.00, (float) $creditLine->credit);

        $this->assertEquals($journal->journalLines->sum('debit'), $journal->journalLines->sum('credit'));
    }

    public function test_goods_receipt_creates_inventory_row_if_none_existed(): void
    {
        $newProduct = Product::create([
            'branch_id' => $this->central->id,
            'category_id' => $this->category->id,
            'sku' => 'PRD-003',
            'name' => 'Gula Pasir 1kg',
            'purchase_price' => 12500,
            'selling_price' => 15000,
            'stock' => 0,
        ]);

        $payload = [
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $newProduct->id,
                    'quantity' => 50,
                    'unit_price' => 12500,
                ],
            ],
        ];

        $this->service->processReceipt($payload, $this->master);

        $inv = Inventory::where('branch_id', $this->central->id)->where('product_id', $newProduct->id)->first();
        $this->assertNotNull($inv);
        $this->assertEquals(50, $inv->quantity);

        $newProduct->refresh();
        $this->assertEquals(50, $newProduct->stock);
    }
}
