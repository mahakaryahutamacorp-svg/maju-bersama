<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
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
}
