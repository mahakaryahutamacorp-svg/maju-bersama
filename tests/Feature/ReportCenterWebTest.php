<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCenterWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'code' => 'PUSAT',
            'name' => 'Kantor Pusat',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'master',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/backoffice/reports');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_report_center(): void
    {
        $response = $this->actingAs($this->user)->get('/backoffice/reports');

        $response->assertStatus(200);
        $response->assertSee('Pusat Laporan');
        $response->assertSee('x-data="{ activeTab: \'keuangan\'', false);
    }

    public function test_report_center_has_all_five_categories(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Keuangan');
        $response->assertSee('Penjualan &amp; Piutang', false);
        $response->assertSee('Pembelian &amp; Hutang', false);
        $response->assertSee('Persediaan / Barang');
        $response->assertSee('Harta Tetap');
    }

    public function test_report_center_contains_required_financial_and_inventory_reports(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.index'));

        $response->assertStatus(200);

        // Keuangan
        $response->assertSee('Laba Rugi Standar');
        $response->assertSee('Neraca Saldo');
        $response->assertSee('Neraca Standar');
        $response->assertSee('Arus Kas');

        // Persediaan
        $response->assertSee('Kartu Stok / Mutasi Barang');
        $response->assertSee('Daftar Barang per Gudang');
    }

    public function test_backoffice_sidebar_has_report_center_menu(): void
    {
        $response = $this->actingAs($this->user)->get('/backoffice');

        $response->assertStatus(200);
        $response->assertSee('Pusat Laporan');
        $response->assertSee(route('reports.index'), false);
    }

    public function test_report_center_links_to_income_statement(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee(route('reports.income-statement'), false);
    }

    public function test_income_statement_page_renders_with_header_filters_and_financial_table(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.income-statement'));

        $response->assertStatus(200);
        $response->assertSee('Laba Rugi Standar');
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('Pendapatan Usaha (Revenue)');
        $response->assertSee('Harga Pokok Penjualan (COGS)');
        $response->assertSee('Laba Kotor (Gross Profit)');
        $response->assertSee('Beban Operasional (Operating Expenses)');
        $response->assertSee('LABA BERSIH (NET PROFIT)');
        $response->assertSee('window.print()', false);
        $response->assertSee('name="start_date"', false);
        $response->assertSee('name="end_date"', false);
    }

    public function test_income_statement_service_computes_profit_accurately(): void
    {
        $cash = \App\Models\ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        $revenue = \App\Models\ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);
        $cogs = \App\Models\ChartOfAccount::create(['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense']);
        $expense = \App\Models\ChartOfAccount::create(['code' => '6100', 'name' => 'Beban Operasional', 'type' => 'expense']);

        $j = \App\Models\JournalHeader::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JRN-LR-001',
            'description' => 'Test Sales & Expenses',
        ]);

        $j->journalLines()->createMany([
            ['chart_of_account_id' => $cash->id, 'debit' => 1000000, 'credit' => 0, 'memo' => 'Penerimaan Kas'],
            ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000000, 'memo' => 'Pendapatan Penjualan'],
            ['chart_of_account_id' => $cogs->id, 'debit' => 600000, 'credit' => 0, 'memo' => 'Beban HPP'],
            ['chart_of_account_id' => $cash->id, 'debit' => 0, 'credit' => 600000, 'memo' => 'Kas Keluar HPP'],
            ['chart_of_account_id' => $expense->id, 'debit' => 150000, 'credit' => 0, 'memo' => 'Beban Operasional Toko'],
            ['chart_of_account_id' => $cash->id, 'debit' => 0, 'credit' => 150000, 'memo' => 'Kas Keluar Beban'],
        ]);

        $service = new \App\Services\Reports\IncomeStatementService();
        $data = $service->generate(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), $this->branch->id);

        $this->assertEquals(1000000.0, $data['revenue']['total']);
        $this->assertEquals(600000.0, $data['cogs']['total']);
        $this->assertEquals(400000.0, $data['gross_profit']);
        $this->assertEquals(150000.0, $data['operating_expenses']['total']);
        $this->assertEquals(250000.0, $data['net_profit']);
        $this->assertTrue($data['is_profitable']);

        $response = $this->actingAs($this->user)->get(route('reports.income-statement', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('1.000.000,00');
        $response->assertSee('600.000,00');
        $response->assertSee('400.000,00');
        $response->assertSee('150.000,00');
        $response->assertSee('250.000,00');
        $response->assertSee('Surplus');
    }

    public function test_report_center_links_to_all_financial_reports(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee(route('reports.income-statement'), false);
        $response->assertSee(route('reports.trial-balance'), false);
        $response->assertSee(route('reports.balance-sheet'), false);
        $response->assertSee(route('reports.cash-flow'), false);
    }

    public function test_trial_balance_renders_successfully(): void
    {
        $cash = \App\Models\ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        $revenue = \App\Models\ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);

        $j = \App\Models\JournalHeader::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JRN-TB-001',
            'description' => 'Test Trial Balance',
        ]);

        $j->journalLines()->createMany([
            ['chart_of_account_id' => $cash->id, 'debit' => 500000, 'credit' => 0, 'memo' => 'Debit Kas'],
            ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => 500000, 'memo' => 'Credit Revenue'],
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.trial-balance'));

        $response->assertStatus(200);
        $response->assertSee('Neraca Saldo (Trial Balance)');
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('SEIMBANG');
        $response->assertSee('500.000,00');
        $response->assertSee('window.print()', false);
    }

    public function test_balance_sheet_renders_successfully_with_net_income(): void
    {
        $cash = \App\Models\ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        $capital = \App\Models\ChartOfAccount::create(['code' => '3110', 'name' => 'Modal Usaha', 'type' => 'equity']);
        $revenue = \App\Models\ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);
        $cogs = \App\Models\ChartOfAccount::create(['code' => '5100', 'name' => 'HPP', 'type' => 'expense']);

        $j1 = \App\Models\JournalHeader::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'transaction_date' => now()->startOfYear()->toDateString(),
            'reference_number' => 'JRN-BS-001',
            'description' => 'Initial Capital',
        ]);
        $j1->journalLines()->createMany([
            ['chart_of_account_id' => $cash->id, 'debit' => 2000000, 'credit' => 0, 'memo' => 'Setoran Modal'],
            ['chart_of_account_id' => $capital->id, 'debit' => 0, 'credit' => 2000000, 'memo' => 'Modal Awal'],
        ]);

        $j2 = \App\Models\JournalHeader::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JRN-BS-002',
            'description' => 'Sales and COGS',
        ]);
        $j2->journalLines()->createMany([
            ['chart_of_account_id' => $cash->id, 'debit' => 1000000, 'credit' => 0, 'memo' => 'Penjualan'],
            ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000000, 'memo' => 'Pendapatan'],
            ['chart_of_account_id' => $cogs->id, 'debit' => 400000, 'credit' => 0, 'memo' => 'Beban HPP'],
            ['chart_of_account_id' => $cash->id, 'debit' => 0, 'credit' => 400000, 'memo' => 'Keluar Kas HPP'],
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.balance-sheet'));

        $response->assertStatus(200);
        $response->assertSee('Neraca Standar (Balance Sheet)');
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('TOTAL AKTIVA (ASSETS)');
        $response->assertSee('TOTAL KEWAJIBAN &amp; EKUITAS', false);
        $response->assertSee('Laba Bersih Periode Berjalan');
        $response->assertSee('SEIMBANG (BALANCED)');
    }

    public function test_cash_flow_renders_successfully(): void
    {
        $cash = \App\Models\ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        $revenue = \App\Models\ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);
        $expense = \App\Models\ChartOfAccount::create(['code' => '6100', 'name' => 'Beban Listrik', 'type' => 'expense']);

        $j = \App\Models\JournalHeader::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JRN-CF-001',
            'description' => 'Test Cash Flow Transaction',
        ]);
        $j->journalLines()->createMany([
            ['chart_of_account_id' => $cash->id, 'debit' => 750000, 'credit' => 0, 'memo' => 'Penerimaan Penjualan'],
            ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => 750000, 'memo' => 'Pendapatan'],
            ['chart_of_account_id' => $expense->id, 'debit' => 200000, 'credit' => 0, 'memo' => 'Beban Listrik'],
            ['chart_of_account_id' => $cash->id, 'debit' => 0, 'credit' => 200000, 'memo' => 'Pembayaran Listrik'],
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.cash-flow'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Arus Kas (Cash Flow)');
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('Total Penerimaan Kas');
        $response->assertSee('Total Pengeluaran Kas');
        $response->assertSee('750.000,00');
        $response->assertSee('200.000,00');
        $response->assertSee('550.000,00');
        $response->assertSee('SALDO AKHIR KAS &amp; BANK', false);
    }

    public function test_report_center_links_to_operational_reports(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee(route('reports.sales'), false);
        $response->assertSee(route('reports.purchases'), false);
        $response->assertSee(route('reports.stock-card'), false);
        $response->assertSee(route('reports.fixed-assets'), false);
    }

    public function test_sales_report_renders_successfully(): void
    {
        $cat = \App\Models\Category::create(['name' => 'Pupuk']);
        $product = \App\Models\Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'sku' => 'PPK-001',
            'name' => 'Pupuk NPK Mutiara 1kg',
            'unit' => 'Bungkus',
            'purchase_price' => 15000,
            'selling_price' => 20000,
            'stock' => 50,
        ]);

        $sale = \App\Models\Sale::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'POS-20260923-0001',
            'total_amount' => 40000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 20000,
            'subtotal' => 40000,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.sales'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Penjualan');
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('POS-20260923-0001');
        $response->assertSee('40.000,00');
        $response->assertSee('Pelanggan Umum (Walk-in)');
    }

    public function test_purchases_report_renders_successfully(): void
    {
        $supplier = \App\Models\Supplier::create([
            'branch_id' => $this->branch->id,
            'name' => 'PT Petrokimia Sentosa',
            'is_active' => true,
        ]);

        $cat = \App\Models\Category::create(['name' => 'Bibit']);
        $product = \App\Models\Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'sku' => 'BBT-001',
            'name' => 'Bibit Jagung Manis 500g',
            'unit' => 'Pack',
            'purchase_price' => 25000,
            'selling_price' => 35000,
            'stock' => 100,
        ]);

        $po = \App\Models\PurchaseOrder::create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-2026-0099',
            'order_date' => now()->toDateString(),
            'status' => 'completed',
            'total_amount' => 250000,
            'notes' => 'Pengadaan bibit jagung',
        ]);

        $po->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 25000,
            'subtotal' => 250000,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.purchases'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Pembelian');
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('PO-2026-0099');
        $response->assertSee('PT Petrokimia Sentosa');
        $response->assertSee('250.000,00');
    }

    public function test_inventory_stock_card_renders_successfully(): void
    {
        $cat = \App\Models\Category::create(['name' => 'Obat Hama']);
        $product = \App\Models\Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'sku' => 'OBT-001',
            'name' => 'Insektisida Regent 50ml',
            'unit' => 'Botol',
            'purchase_price' => 18000,
            'selling_price' => 25000,
            'stock' => 30,
        ]);

        \App\Models\Inventory::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'quantity' => 30,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.stock-card', [
            'product_id' => $product->id,
            'branch_id' => $this->branch->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Kartu Stok &amp; Mutasi Barang', false);
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('Insektisida Regent 50ml');
        $response->assertSee('OBT-001');
    }

    public function test_fixed_assets_report_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.fixed-assets'));

        $response->assertStatus(200);
        $response->assertSee('Daftar Aktiva Tetap &amp; Inventaris', false);
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('Modul Harta Tetap &amp; Depresiasi Terproteksi', false);
        $response->assertSee('Belum ada aset tetap yang tercatat');
    }

    public function test_report_center_links_to_ar_and_ap_aging(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee(route('reports.ar-aging'), false);
        $response->assertSee(route('reports.ap-aging'), false);
    }

    public function test_ar_aging_report_renders_successfully_with_unpaid_sales_and_aging_buckets(): void
    {
        $customerA = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Kelompok Tani Makmur Sentosa',
            'phone' => '081234567890',
        ]);

        $customerB = Customer::create([
            'branch_id' => $this->branch->id,
            'name' => 'Toko Subur Tani',
            'phone' => '089876543210',
        ]);

        // Sale 1: Customer A, 15 hari lalu -> 0-30 Hari
        Sale::create([
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customerA->id,
            'receipt_number' => 'INV-AR-001',
            'total_amount'   => 1000000,
            'paid_amount'    => 200000,
            'payment_status' => 'PARTIAL',
            'due_date'       => now()->subDays(15)->toDateString(),
        ]);

        // Sale 2: Customer A, 45 hari lalu -> 31-60 Hari
        $sale2 = Sale::create([
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customerA->id,
            'receipt_number' => 'INV-AR-002',
            'total_amount'   => 500000,
            'paid_amount'    => 0,
            'payment_status' => 'UNPAID',
        ]);
        $sale2->created_at = now()->subDays(45);
        $sale2->saveQuietly();

        // Sale 3: Customer B, 75 hari lalu -> 61-90 Hari
        Sale::create([
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customerB->id,
            'receipt_number' => 'INV-AR-003',
            'total_amount'   => 1200000,
            'paid_amount'    => 0,
            'payment_status' => 'UNPAID',
            'due_date'       => now()->subDays(75)->toDateString(),
        ]);

        // Sale 4: Customer B, 110 hari lalu -> > 90 Hari
        $sale4 = Sale::create([
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customerB->id,
            'receipt_number' => 'INV-AR-004',
            'total_amount'   => 300000,
            'paid_amount'    => 0,
            'payment_status' => 'UNPAID',
        ]);
        $sale4->created_at = now()->subDays(110);
        $sale4->saveQuietly();

        // Sale 5: Lunas (PAID) -> Tidak boleh masuk hitungan
        Sale::create([
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customerA->id,
            'receipt_number' => 'INV-AR-005',
            'total_amount'   => 2000000,
            'paid_amount'    => 2000000,
            'payment_status' => 'PAID',
            'due_date'       => now()->subDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.ar-aging'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Umur Piutang (AR Aging)');
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('Kelompok Tani Makmur Sentosa');
        $response->assertSee('Toko Subur Tani');
        $response->assertSee('Total Keseluruhan');

        // Customer A: Sisa 800.000 (0-30) + 500.000 (31-60) = 1.300.000
        $response->assertSee('Rp 1.300.000');
        $response->assertSee('Rp 800.000');
        $response->assertSee('Rp 500.000');

        // Customer B: Sisa 1.200.000 (61-90) + 300.000 (>90) = 1.500.000
        $response->assertSee('Rp 1.500.000');
        $response->assertSee('Rp 1.200.000');
        $response->assertSee('Rp 300.000');

        // Grand Total: 1.300.000 + 1.500.000 = 2.800.000
        $response->assertSee('Rp 2.800.000');
    }

    public function test_ap_aging_report_renders_successfully_with_unpaid_purchase_orders_and_aging_buckets(): void
    {
        $supplierA = Supplier::create([
            'branch_id' => $this->branch->id,
            'name'      => 'PT Petrokimia Kayaku',
            'phone'     => '021-5551234',
        ]);

        $supplierB = Supplier::create([
            'branch_id' => $this->branch->id,
            'name'      => 'CV Benih Unggul Nusantara',
            'phone'     => '0274-888999',
        ]);

        // PO 1: Supplier A, 10 hari lalu -> 0-30 Hari
        PurchaseOrder::create([
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $supplierA->id,
            'reference_number' => 'PO-AP-001',
            'order_date'       => now()->subDays(10)->toDateString(),
            'total_amount'     => 2000000,
            'paid_amount'      => 500000,
            'payment_status'   => 'PARTIAL',
            'status'           => 'completed',
        ]);

        // PO 2: Supplier A, 40 hari lalu -> 31-60 Hari
        PurchaseOrder::create([
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $supplierA->id,
            'reference_number' => 'PO-AP-002',
            'order_date'       => now()->subDays(40)->toDateString(),
            'total_amount'     => 1000000,
            'paid_amount'      => 0,
            'payment_status'   => 'UNPAID',
            'status'           => 'completed',
        ]);

        // PO 3: Supplier B, 70 hari lalu -> 61-90 Hari
        PurchaseOrder::create([
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $supplierB->id,
            'reference_number' => 'PO-AP-003',
            'order_date'       => now()->subDays(70)->toDateString(),
            'total_amount'     => 3000000,
            'paid_amount'      => 0,
            'payment_status'   => 'UNPAID',
            'status'           => 'completed',
        ]);

        // PO 4: Supplier B, 120 hari lalu -> > 90 Hari
        PurchaseOrder::create([
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $supplierB->id,
            'reference_number' => 'PO-AP-004',
            'order_date'       => now()->subDays(120)->toDateString(),
            'total_amount'     => 800000,
            'paid_amount'      => 0,
            'payment_status'   => 'UNPAID',
            'status'           => 'completed',
        ]);

        // PO 5: Lunas (PAID) -> Tidak boleh masuk hitungan
        PurchaseOrder::create([
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $supplierA->id,
            'reference_number' => 'PO-AP-005',
            'order_date'       => now()->subDays(5)->toDateString(),
            'total_amount'     => 5000000,
            'paid_amount'      => 5000000,
            'payment_status'   => 'PAID',
            'status'           => 'completed',
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.ap-aging'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Umur Hutang (AP Aging)');
        $response->assertSee('MAJU BERSAMA GRUP');
        $response->assertSee('PT Petrokimia Kayaku');
        $response->assertSee('CV Benih Unggul Nusantara');
        $response->assertSee('Total Keseluruhan');

        // Supplier A: Sisa 1.500.000 (0-30) + 1.000.000 (31-60) = 2.500.000
        $response->assertSee('Rp 2.500.000');
        $response->assertSee('Rp 1.500.000');
        $response->assertSee('Rp 1.000.000');

        // Supplier B: Sisa 3.000.000 (61-90) + 800.000 (>90) = 3.800.000
        $response->assertSee('Rp 3.800.000');
        $response->assertSee('Rp 3.000.000');
        $response->assertSee('Rp 800.000');

        // Grand Total: 2.500.000 + 3.800.000 = 6.300.000
        $response->assertSee('Rp 6.300.000');
    }
}

