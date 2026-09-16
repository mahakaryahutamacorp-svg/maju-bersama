<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockAdjustmentService $service;
    private Branch $branch;
    private User $actor;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StockAdjustmentService();

        // Seed COA standard
        ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);
        ChartOfAccount::create(['code' => '4120', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue']);
        ChartOfAccount::create(['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense']);
        ChartOfAccount::create(['code' => '5120', 'name' => 'Beban Selisih Persediaan', 'type' => 'expense']);

        $this->branch = Branch::create([
            'name' => 'Kantor Pusat',
            'code' => 'PUSAT',
            'address' => 'Jl. Merdeka No. 1',
            'phone' => '08123456789',
        ]);

        $this->actor = User::create([
            'name' => 'Supervisor Gudang',
            'email' => 'spv.gudang@example.com',
            'password' => bcrypt('password'),
            'role' => 'master',
            'branch_id' => $this->branch->id,
        ]);

        $this->category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Sembako',
        ]);
    }

    public function test_negative_difference_loss_adjusts_inventory_and_creates_loss_journal(): void
    {
        $product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'PRD-LOSS-001',
            'name' => 'Minyak Goreng 2L',
            'selling_price' => 35000,
            'purchase_price' => 30000,
            'stock' => 10,
        ]);

        Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // Opname fisik menemukan hanya 7 botol (hilang/rusak 3 botol)
        $data = [
            'branch_id' => $this->branch->id,
            'reference_number' => 'ADJ-TEST-LOSS',
            'date' => '2026-09-16',
            'notes' => 'Opname Minyak Goreng: 3 botol bocor/hilang',
            'items' => [
                [
                    'product_id' => $product->id,
                    'expected_qty' => 10,
                    'actual_qty' => 7,
                    'unit_cost' => 30000,
                ],
            ],
        ];

        $adjustment = $this->service->processAdjustment($data, $this->actor);

        // 1. Assert StockAdjustment header & items
        $this->assertInstanceOf(StockAdjustment::class, $adjustment);
        $this->assertEquals('ADJ-TEST-LOSS', $adjustment->reference_number);
        $this->assertEquals('90000.00', $adjustment->total_loss_value); // 3 x 30.000
        $this->assertEquals('0.00', $adjustment->total_gain_value);
        $this->assertCount(1, $adjustment->items);

        $item = $adjustment->items->first();
        $this->assertEquals(10, $item->expected_qty);
        $this->assertEquals(7, $item->actual_qty);
        $this->assertEquals(-3, $item->difference_qty);
        $this->assertEquals('30000.00', $item->unit_cost);
        $this->assertEquals('-90000.00', $item->subtotal_value);

        // 2. Assert inventory & cached stock decreased
        $inventory = Inventory::where('branch_id', $this->branch->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(7, $inventory->quantity);

        $product->refresh();
        $this->assertEquals(7, $product->stock);

        // 3. Assert Journal Header & Double-entry lines (Dr 5120 Expense, Cr 1210 Inventory)
        $journal = JournalHeader::where('reference_number', 'ADJ-TEST-LOSS')->first();
        $this->assertNotNull($journal);
        $this->assertCount(2, $journal->lines);

        $expenseLine = $journal->lines->where('debit', '90000.00')->first();
        $this->assertNotNull($expenseLine);
        $this->assertEquals('5120', $expenseLine->account->code);

        $inventoryLine = $journal->lines->where('credit', '90000.00')->first();
        $this->assertNotNull($inventoryLine);
        $this->assertEquals('1210', $inventoryLine->account->code);

        // Assert balance
        $totalDebit = $journal->lines->sum('debit');
        $totalCredit = $journal->lines->sum('credit');
        $this->assertEquals('90000.00', number_format($totalDebit, 2, '.', ''));
        $this->assertEquals('90000.00', number_format($totalCredit, 2, '.', ''));
    }

    public function test_positive_difference_gain_adjusts_inventory_and_creates_gain_journal(): void
    {
        $product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'PRD-GAIN-001',
            'name' => 'Beras Pandan Wangi 5kg',
            'selling_price' => 75000,
            'purchase_price' => 65000,
            'stock' => 5,
        ]);

        Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        // Opname fisik menemukan 8 karung (kelebihan 3 karung)
        $data = [
            'branch_id' => $this->branch->id,
            'reference_number' => 'ADJ-TEST-GAIN',
            'date' => '2026-09-16',
            'notes' => 'Opname Beras: kelebihan stok fisik',
            'items' => [
                [
                    'product_id' => $product->id,
                    'actual_qty' => 8,
                    'unit_cost' => 65000,
                ],
            ],
        ];

        $adjustment = $this->service->processAdjustment($data, $this->actor);

        // 1. Assert header & items
        $this->assertEquals('0.00', $adjustment->total_loss_value);
        $this->assertEquals('195000.00', $adjustment->total_gain_value); // 3 x 65.000
        $this->assertCount(1, $adjustment->items);

        $item = $adjustment->items->first();
        $this->assertEquals(5, $item->expected_qty);
        $this->assertEquals(8, $item->actual_qty);
        $this->assertEquals(3, $item->difference_qty);
        $this->assertEquals('195000.00', $item->subtotal_value);

        // 2. Assert stock updated
        $inventory = Inventory::where('branch_id', $this->branch->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(8, $inventory->quantity);

        $product->refresh();
        $this->assertEquals(8, $product->stock);

        // 3. Assert Journal Header & Double-entry lines (Dr 1210 Inventory, Cr 4120 Revenue)
        $journal = JournalHeader::where('reference_number', 'ADJ-TEST-GAIN')->first();
        $this->assertNotNull($journal);
        $this->assertCount(2, $journal->lines);

        $inventoryLine = $journal->lines->where('debit', '195000.00')->first();
        $this->assertNotNull($inventoryLine);
        $this->assertEquals('1210', $inventoryLine->account->code);

        $revenueLine = $journal->lines->where('credit', '195000.00')->first();
        $this->assertNotNull($revenueLine);
        $this->assertEquals('4120', $revenueLine->account->code);

        // Assert balance
        $totalDebit = $journal->lines->sum('debit');
        $totalCredit = $journal->lines->sum('credit');
        $this->assertEquals('195000.00', number_format($totalDebit, 2, '.', ''));
        $this->assertEquals('195000.00', number_format($totalCredit, 2, '.', ''));
    }

    public function test_mixed_adjustment_batch_creates_fully_balanced_journal(): void
    {
        $productLoss = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'PRD-MIX-001',
            'name' => 'Kecap Manis 600ml',
            'selling_price' => 25000,
            'purchase_price' => 20000,
            'stock' => 15,
        ]);
        Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $productLoss->id,
            'quantity' => 15,
        ]);

        $productGain = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'PRD-MIX-002',
            'name' => 'Saus Sambal 600ml',
            'selling_price' => 20000,
            'purchase_price' => 15000,
            'stock' => 5,
        ]);
        Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $productGain->id,
            'quantity' => 5,
        ]);

        // Product Loss: 15 -> 10 (selisih -5 x 20.000 = loss 100.000)
        // Product Gain: 5 -> 9 (selisih +4 x 15.000 = gain 60.000)
        $data = [
            'branch_id' => $this->branch->id,
            'reference_number' => 'ADJ-TEST-MIXED',
            'items' => [
                [
                    'product_id' => $productLoss->id,
                    'actual_qty' => 10,
                ],
                [
                    'product_id' => $productGain->id,
                    'actual_qty' => 9,
                ],
            ],
        ];

        $adjustment = $this->service->processAdjustment($data, $this->actor);

        $this->assertEquals('100000.00', $adjustment->total_loss_value);
        $this->assertEquals('60000.00', $adjustment->total_gain_value);

        // Verifikasi stock
        $this->assertEquals(10, $productLoss->fresh()->stock);
        $this->assertEquals(9, $productGain->fresh()->stock);

        // Verifikasi Jurnal Ganda Seimbang
        $journal = JournalHeader::where('reference_number', 'ADJ-TEST-MIXED')->first();
        $this->assertNotNull($journal);
        $this->assertCount(4, $journal->lines);

        $totalDebit = $journal->lines->sum('debit');
        $totalCredit = $journal->lines->sum('credit');

        // Total Debit = Loss (100.000) + Gain (60.000) = 160.000
        // Total Kredit = Loss (100.000) + Gain (60.000) = 160.000
        $this->assertEquals('160000.00', number_format($totalDebit, 2, '.', ''));
        $this->assertEquals('160000.00', number_format($totalCredit, 2, '.', ''));
    }

    public function test_creates_inventory_record_if_none_existed(): void
    {
        $product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'PRD-NEW-001',
            'name' => 'Barang Baru Ditemukan',
            'selling_price' => 10000,
            'purchase_price' => 8000,
            'stock' => 0,
        ]);

        // Belum ada row di table inventories untuk produk ini di branch
        $data = [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'actual_qty' => 4,
                ],
            ],
        ];

        $adjustment = $this->service->processAdjustment($data, $this->actor);

        $inventory = Inventory::where('branch_id', $this->branch->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($inventory);
        $this->assertEquals(4, $inventory->quantity);
        $this->assertEquals(4, $product->fresh()->stock);
        $this->assertEquals('32000.00', $adjustment->total_gain_value);
    }
}
