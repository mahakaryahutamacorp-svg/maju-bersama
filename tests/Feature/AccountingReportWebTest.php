<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\User;
use Tests\TestCase;

class AccountingReportWebTest extends TestCase
{
    private Branch $central;
    private User $master;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = Branch::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $this->master = User::factory()->create(['branch_id' => $this->central->id, 'role' => 'master']);

        $cash = ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        $revenue = ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);
        $cogs = ChartOfAccount::create(['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense']);
        $expense = ChartOfAccount::create(['code' => '5110', 'name' => 'Beban Operasional', 'type' => 'expense']);

        $j = JournalHeader::create([
            'branch_id' => $this->central->id,
            'user_id' => $this->master->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JRN-TEST-001',
            'description' => 'Test Transaction',
        ]);

        $j->journalLines()->createMany([
            ['chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0, 'memo' => 'Cash in'],
            ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000, 'memo' => 'Revenue'],
            ['chart_of_account_id' => $cogs->id, 'debit' => 600, 'credit' => 0, 'memo' => 'COGS'],
            ['chart_of_account_id' => $cash->id, 'debit' => 0, 'credit' => 600, 'memo' => 'Cash out for COGS'],
            ['chart_of_account_id' => $expense->id, 'debit' => 100, 'credit' => 0, 'memo' => 'OpEx'],
            ['chart_of_account_id' => $cash->id, 'debit' => 0, 'credit' => 100, 'memo' => 'Cash out for OpEx'],
        ]);
    }

    public function test_trial_balance_page_renders_with_seimbang_badge(): void
    {
        $response = $this->actingAs($this->master)->get('/reports/accounting/trial-balance');

        $response->assertStatus(200);
        $response->assertSee('Neraca Saldo (Trial Balance)');
        $response->assertSee('SEIMBANG');
        $response->assertSee('Saldo Awal');
        $response->assertSee('Mutasi Periode');
        $response->assertSee('Saldo Akhir');
    }

    public function test_income_statement_page_renders_with_profit_margins(): void
    {
        $response = $this->actingAs($this->master)->get('/reports/accounting/income-statement');

        $response->assertStatus(200);
        $response->assertSee('Laporan Laba Rugi (Income Statement)');
        $response->assertSee('Gross Profit Margin');
        $response->assertSee('Net Profit Margin');
        $response->assertSee('GPM:');
        $response->assertSee('NPM:');
        $response->assertSee('Beban Operasional');
    }

    public function test_ledger_page_renders_consolidated(): void
    {
        $response = $this->actingAs($this->master)->get('/reports/accounting/ledger');

        $response->assertStatus(200);
        $response->assertSee('Buku Besar &mdash; <span class="text-slate-800">Konsolidasi Seluruh Cabang</span>', false);
        $response->assertSee('Konsolidasi Seluruh Cabang');
        $response->assertSee('Semua Cabang (Konsolidasi)');
        $response->assertSee('Cabang / Toko');
    }

    public function test_ledger_page_renders_filtered_by_branch(): void
    {
        $response = $this->actingAs($this->master)->get('/reports/accounting/ledger?branch_id=' . $this->central->id);

        $response->assertStatus(200);
        $response->assertSee('Buku Besar &mdash; <span class="text-sky-700 uppercase tracking-tight">' . $this->central->name . '</span>', false);
        $response->assertSee('Filter Toko: <strong class="uppercase">' . $this->central->name . '</strong>', false);
        $response->assertSee('Kas');
    }
}
