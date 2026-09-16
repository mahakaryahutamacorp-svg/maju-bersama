<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Services\StockCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCardTest extends TestCase
{
    use RefreshDatabase;

    private StockCardService $service;
    private Branch $branchPusat;
    private Branch $branchCabang;
    private User $user;
    private Category $category;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StockCardService();

        // Seed COA standard
        ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);
        ChartOfAccount::create(['code' => '4120', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue']);
        ChartOfAccount::create(['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense']);
        ChartOfAccount::create(['code' => '5120', 'name' => 'Beban Selisih Persediaan', 'type' => 'expense']);

        $this->branchPusat = Branch::create([
            'name' => 'Gudang Pusat',
            'code' => 'PUSAT',
            'address' => 'Jl. Pusat Niaga No. 1',
            'phone' => '08123456789',
        ]);

        $this->branchCabang = Branch::create([
            'name' => 'Cabang Barat',
            'code' => 'CAB-BARAT',
            'address' => 'Jl. Barat No. 2',
            'phone' => '08123456788',
        ]);

        $this->user = User::create([
            'name' => 'Admin Gudang',
            'email' => 'admin.gudang@example.com',
            'password' => bcrypt('password'),
            'role' => 'master',
            'branch_id' => $this->branchPusat->id,
        ]);

        $this->category = Category::create([
            'branch_id' => $this->branchPusat->id,
            'name' => 'Minuman',
        ]);

        $this->product = Product::create([
            'branch_id' => $this->branchPusat->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-CARD-01',
            'name' => 'Susu Kotak UHT 1L',
            'selling_price' => 20000,
            'purchase_price' => 15000,
            'stock' => 37,
        ]);

        Inventory::create([
            'branch_id' => $this->branchPusat->id,
            'product_id' => $this->product->id,
            'quantity' => 37,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/reports/inventory/stock-card');
        $response->assertRedirect('/login');
    }

    public function test_user_can_open_stock_card_filter_page(): void
    {
        $response = $this->actingAs($this->user)->get('/reports/inventory/stock-card');
        $response->assertStatus(200);
        $response->assertSee('Laporan Mutasi &amp; Kartu Stok', false);
        $response->assertSee('Susu Kotak UHT 1L');
    }

    public function test_stock_card_accurately_computes_chronological_movements_and_running_balance(): void
    {
        // 1. Goods Receipt: Masuk 50 pcs (2026-09-01)
        $receipt = GoodsReceipt::create([
            'branch_id' => $this->branchPusat->id,
            'reference_number' => 'GR-TEST-001',
            'supplier_name' => 'PT Indofood',
            'date' => '2026-09-01',
            'total_amount' => 750000,
            'payment_type' => 'cash',
        ]);
        GoodsReceiptItem::create([
            'goods_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 15000,
            'subtotal' => 750000,
        ]);

        // 2. POS Sale: Keluar 10 pcs (2026-09-02)
        $sale = Sale::create([
            'branch_id' => $this->branchPusat->id,
            'receipt_number' => 'INV-TEST-001',
            'total_amount' => 200000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        \Illuminate\Support\Facades\DB::table('sales')->where('id', $sale->id)->update([
            'created_at' => '2026-09-02 10:00:00',
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price' => 20000,
            'subtotal' => 200000,
        ]);

        // 3. Stock Transfer Out: Keluar 5 pcs ke Cabang Barat (2026-09-03)
        $destProduct = Product::create([
            'branch_id' => $this->branchCabang->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-CARD-01',
            'name' => 'Susu Kotak UHT 1L (Cabang Barat)',
            'selling_price' => 20000,
            'purchase_price' => 15000,
            'stock' => 0,
        ]);

        $transfer = StockTransfer::create([
            'reference_number' => 'TRF-TEST-001',
            'source_branch_id' => $this->branchPusat->id,
            'destination_branch_id' => $this->branchCabang->id,
            'created_by' => $this->user->id,
            'transfer_date' => '2026-09-03',
            'status' => 'completed',
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'source_product_id' => $this->product->id,
            'destination_product_id' => $destProduct->id,
            'quantity' => 5,
            'unit_cost' => 15000,
        ]);

        // 4. Stock Adjustment Opname: Masuk +2 pcs (2026-09-04)
        $adj = StockAdjustment::create([
            'branch_id' => $this->branchPusat->id,
            'reference_number' => 'ADJ-TEST-001',
            'date' => '2026-09-04',
            'total_loss_value' => 0,
            'total_gain_value' => 30000,
        ]);
        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adj->id,
            'product_id' => $this->product->id,
            'expected_qty' => 35,
            'actual_qty' => 37,
            'difference_qty' => 2,
            'unit_cost' => 15000,
            'subtotal_value' => 30000,
        ]);

        // Eksekusi Service
        $card = $this->service->getStockCard($this->branchPusat->id, $this->product->id);

        $this->assertEquals(0, $card['beginning_balance']);
        $this->assertCount(4, $card['movements']);

        // Verifikasi pergerakan running balance baris demi baris:
        // Baris 1: GR (+50) -> Running 50
        $this->assertEquals('GR-TEST-001', $card['movements'][0]->reference_number);
        $this->assertEquals(50, $card['movements'][0]->qty_in);
        $this->assertEquals(0, $card['movements'][0]->qty_out);
        $this->assertEquals(50, $card['movements'][0]->running_balance);

        // Baris 2: Sale (-10) -> Running 40
        $this->assertEquals('INV-TEST-001', $card['movements'][1]->reference_number);
        $this->assertEquals(0, $card['movements'][1]->qty_in);
        $this->assertEquals(10, $card['movements'][1]->qty_out);
        $this->assertEquals(40, $card['movements'][1]->running_balance);

        // Baris 3: Transfer Out (-5) -> Running 35
        $this->assertEquals('TRF-TEST-001', $card['movements'][2]->reference_number);
        $this->assertEquals(0, $card['movements'][2]->qty_in);
        $this->assertEquals(5, $card['movements'][2]->qty_out);
        $this->assertEquals(35, $card['movements'][2]->running_balance);

        // Baris 4: Adjustment (+2) -> Running 37
        $this->assertEquals('ADJ-TEST-001', $card['movements'][3]->reference_number);
        $this->assertEquals(2, $card['movements'][3]->qty_in);
        $this->assertEquals(0, $card['movements'][3]->qty_out);
        $this->assertEquals(37, $card['movements'][3]->running_balance);

        // Ringkasan
        $this->assertEquals(52, $card['total_in']);
        $this->assertEquals(15, $card['total_out']);
        $this->assertEquals(37, $card['ending_balance']);
        $this->assertEquals(37, $card['current_stock']);

        // Verifikasi render HTML Web
        $webResponse = $this->actingAs($this->user)->get("/reports/inventory/stock-card?product_id={$this->product->id}");
        $webResponse->assertStatus(200);
        $webResponse->assertSee('GR-TEST-001');
        $webResponse->assertSee('INV-TEST-001');
        $webResponse->assertSee('TRF-TEST-001');
        $webResponse->assertSee('ADJ-TEST-001');
        $webResponse->assertSee('52'); // total in
        $webResponse->assertSee('15'); // total out
        $webResponse->assertSee('37'); // ending balance
    }

    public function test_stock_card_calculates_beginning_balance_accurately_with_date_filter(): void
    {
        // Mutasi tgl 2026-08-15 (+100)
        $receipt = GoodsReceipt::create([
            'branch_id' => $this->branchPusat->id,
            'reference_number' => 'GR-PRIOR-001',
            'supplier_name' => 'Supplier Lama',
            'date' => '2026-08-15',
            'total_amount' => 1500000,
            'payment_type' => 'cash',
        ]);
        GoodsReceiptItem::create([
            'goods_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'unit_price' => 15000,
            'subtotal' => 1500000,
        ]);

        // Mutasi tgl 2026-09-10 (Keluar 20 via Sale)
        $sale = Sale::create([
            'branch_id' => $this->branchPusat->id,
            'receipt_number' => 'INV-CURR-001',
            'total_amount' => 400000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        \Illuminate\Support\Facades\DB::table('sales')->where('id', $sale->id)->update([
            'created_at' => '2026-09-10 10:00:00',
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'price' => 20000,
            'subtotal' => 400000,
        ]);

        // Filter mulai tanggal 2026-09-01
        $card = $this->service->getStockCard($this->branchPusat->id, $this->product->id, '2026-09-01', '2026-09-30');

        // Saldo awal harus 100
        $this->assertEquals(100, $card['beginning_balance']);
        $this->assertCount(1, $card['movements']);

        // Mutasi dalam periode: Sale (-20), running balance = 100 - 20 = 80
        $this->assertEquals(80, $card['movements'][0]->running_balance);
        $this->assertEquals(80, $card['ending_balance']);
    }
}
