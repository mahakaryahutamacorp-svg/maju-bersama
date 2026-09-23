<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset']);
        ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);
        ChartOfAccount::create(['code' => '4120', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue']);
        ChartOfAccount::create(['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense']);
        ChartOfAccount::create(['code' => '5120', 'name' => 'Beban Selisih Persediaan', 'type' => 'expense']);

        $this->branch = Branch::create([
            'name' => 'Gudang Pusat',
            'code' => 'PUSAT',
            'address' => 'Jl. Pusat Niaga No. 10',
            'phone' => '08123456789',
        ]);

        $this->user = User::create([
            'name' => 'Admin Stock Opname',
            'email' => 'opname@example.com',
            'password' => bcrypt('password'),
            'role' => 'master',
            'branch_id' => $this->branch->id,
        ]);

        $this->category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Kebutuhan Pokok',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/inventory/adjustments');
        $response->assertRedirect('/login');

        $createResponse = $this->get('/inventory/adjustments/create');
        $createResponse->assertRedirect('/login');
    }

    public function test_user_can_view_index_and_create_pages(): void
    {
        $product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-OPN-01',
            'name' => 'Kopi Robusta 250g',
            'selling_price' => 30000,
            'purchase_price' => 22000,
            'stock' => 15,
        ]);

        Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'quantity' => 15,
        ]);

        $response = $this->actingAs($this->user)->get('/inventory/adjustments');
        $response->assertStatus(200);
        $response->assertSee('Riwayat Penyesuaian Stok (Opname)');

        $createResponse = $this->actingAs($this->user)->get('/inventory/adjustments/create');
        $createResponse->assertStatus(200);
        $createResponse->assertSee('Formulir Stock Opname');
        $createResponse->assertSee('Kopi Robusta 250g');
    }

    public function test_user_can_submit_opname_and_view_slip_with_journal(): void
    {
        $product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'SKU-OPN-02',
            'name' => 'Gula Pasir 1kg',
            'selling_price' => 18000,
            'purchase_price' => 14000,
            'stock' => 20,
        ]);

        Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        // Kirim hasil opname fisik: fisik 18 karung (selisih -2, rugi 28.000)
        $postData = [
            'branch_id' => $this->branch->id,
            'date' => '2026-09-16',
            'notes' => 'Audit Gula Pasir: 2 karung sobek',
            'items' => [
                [
                    'product_id' => $product->id,
                    'expected_qty' => 20,
                    'actual_qty' => 18,
                    'unit_cost' => 14000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post('/inventory/adjustments', $postData);

        $adjustment = StockAdjustment::first();
        $this->assertNotNull($adjustment);
        $this->assertEquals('28000.00', $adjustment->total_loss_value);

        $response->assertRedirect("/inventory/adjustments/{$adjustment->id}");
        $response->assertSessionHas('success');

        // Buka halaman show slip
        $showResponse = $this->actingAs($this->user)->get("/inventory/adjustments/{$adjustment->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee($adjustment->reference_number);
        $showResponse->assertSee('Gula Pasir 1kg');
        $showResponse->assertSee('28.000');
        $showResponse->assertSee('5120'); // Beban Selisih
        $showResponse->assertSee('1210'); // Persediaan
    }

    public function test_store_validation_fails_without_items(): void
    {
        $response = $this->actingAs($this->user)->post('/inventory/adjustments', [
            'branch_id' => $this->branch->id,
            'date' => '2026-09-16',
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');
    }

    /**
     * Uji skenario stok bertambah (Surplus / Gain).
     * Pastikan jurnal Debit Persediaan (1210) dan Kredit Pendapatan Lain-lain (4120) terbentuk seimbang.
     */
    public function test_user_can_submit_opname_with_surplus_gain_and_verify_journal(): void
    {
        $product = Product::create([
            'branch_id'      => $this->branch->id,
            'category_id'    => $this->category->id,
            'sku'            => 'SKU-SURPLUS-01',
            'name'           => 'Tepung Terigu 1kg',
            'selling_price'  => 15000,
            'purchase_price' => 12000,
            'stock'          => 10,
        ]);

        Inventory::create([
            'branch_id'  => $this->branch->id,
            'product_id' => $product->id,
            'quantity'   => 10,
        ]);

        // Hasil opname fisik: fisik 15 pack (surplus +5 pack, keuntungan 60.000)
        $postData = [
            'branch_id'       => $this->branch->id,
            'adjustment_date' => '2026-09-24',
            'notes'           => 'Audit Stok Tepung: Ditemukan 5 pack ekstra di rak belakang',
            'lines'           => [
                [
                    'product_id' => $product->id,
                    'system_qty' => 10,
                    'actual_qty' => 15,
                    'unit_cost'  => 12000,
                    'reason'     => 'Temuan fisik belum tercatat',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post('/inventory/adjustments', $postData);

        $adjustment = StockAdjustment::where('notes', 'Audit Stok Tepung: Ditemukan 5 pack ekstra di rak belakang')->first();
        $this->assertNotNull($adjustment);
        $this->assertEquals('60000.00', $adjustment->total_gain_value);
        $this->assertEquals('0.00', $adjustment->total_loss_value);
        $this->assertNotNull($adjustment->journal_header_id);

        $response->assertRedirect("/inventory/adjustments/{$adjustment->id}");
        $response->assertSessionHas('success');

        // 1. Verifikasi stock_adjustment_lines detail
        $this->assertDatabaseHas('stock_adjustment_lines', [
            'stock_adjustment_id' => $adjustment->id,
            'product_id'          => $product->id,
            'system_qty'          => 10,
            'actual_qty'          => 15,
            'difference_qty'      => 5,
            'unit_cost'           => 12000.0000,
        ]);

        // 2. Verifikasi stok inventori bertambah menjadi 15
        $inventory = Inventory::where('branch_id', $this->branch->id)->where('product_id', $product->id)->first();
        $this->assertEquals(15, $inventory->quantity);

        $product->refresh();
        $this->assertEquals(15, $product->stock);

        // 3. Verifikasi Jurnal Akuntansi Surplus:
        // Debit: Persediaan (1210) senilai Rp60.000
        // Kredit: Pendapatan Lain-lain (4120) senilai Rp60.000
        $journal = \App\Models\JournalHeader::with('lines.chartOfAccount')->find($adjustment->journal_header_id);
        $this->assertNotNull($journal);

        $debitInventory = $journal->lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '1210')->value('id'));
        $creditRevenue = $journal->lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '4120')->value('id'));

        $this->assertNotNull($debitInventory, 'Baris Debit Persediaan harus ada.');
        $this->assertNotNull($creditRevenue, 'Baris Kredit Pendapatan Lain-lain harus ada.');

        $this->assertEquals(60000.00, (float) $debitInventory->debit);
        $this->assertEquals(0.00, (float) $debitInventory->credit);

        $this->assertEquals(0.00, (float) $creditRevenue->debit);
        $this->assertEquals(60000.00, (float) $creditRevenue->credit);

        // Keseimbangan Jurnal
        $this->assertEquals(60000.00, (float) $journal->lines->sum('debit'));
        $this->assertEquals(60000.00, (float) $journal->lines->sum('credit'));
    }

    /**
     * Uji pemanggilan langsung method processAdjustment(array $data, array $lines) pada service.
     */
    public function test_service_process_adjustment_with_lines_array(): void
    {
        $product = Product::create([
            'branch_id'      => $this->branch->id,
            'category_id'    => $this->category->id,
            'sku'            => 'SKU-SVC-01',
            'name'           => 'Garam Dapur 500g',
            'selling_price'  => 8000,
            'purchase_price' => 5000,
            'stock'          => 20,
        ]);

        Inventory::create([
            'branch_id'  => $this->branch->id,
            'product_id' => $product->id,
            'quantity'   => 20,
        ]);

        $service = app(\App\Services\StockAdjustmentService::class);

        $adjustment = $service->processAdjustment([
            'branch_id'        => $this->branch->id,
            'adjustment_date'  => '2026-09-24',
            'reference_number' => 'SA-20260924120000',
            'notes'            => 'Uji pemanggilan service langsung',
        ], [
            [
                'product_id' => $product->id,
                'system_qty' => 20,
                'actual_qty' => 17, // Defisit 3 bungkus @ 5.000 = 15.000
                'unit_cost'  => 5000,
                'reason'     => 'Bungkus sobek',
            ],
        ], $this->user);

        $this->assertInstanceOf(StockAdjustment::class, $adjustment);
        $this->assertEquals('SA-20260924120000', $adjustment->reference_number);
        $this->assertEquals('15000.00', $adjustment->total_loss_value);
        $this->assertCount(1, $adjustment->lines);

        $line = $adjustment->lines->first();
        $this->assertEquals(-3, $line->difference_qty);
        $this->assertEquals('Bungkus sobek', $line->reason);

        // Verifikasi relasi journal
        $this->assertNotNull($adjustment->journal);
        $this->assertEquals('15000.00', (float) $adjustment->journal->lines->sum('debit'));
        $this->assertEquals('15000.00', (float) $adjustment->journal->lines->sum('credit'));
    }
}
