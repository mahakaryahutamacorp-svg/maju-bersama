<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\User;
use App\Services\AccountingReportService;
use Tests\TestCase;

class AccountingReportServiceTest extends TestCase
{
    private AccountingReportService $reportService;
    private Branch $central;
    private Branch $branchOne;
    private User $master;
    private ChartOfAccount $cash;
    private ChartOfAccount $inventory;
    private ChartOfAccount $revenue;
    private ChartOfAccount $cogs;
    private ChartOfAccount $operatingExpense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportService = new AccountingReportService();

        $this->central = Branch::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $this->branchOne = Branch::create(['code' => 'CABANG-1', 'name' => 'Cabang 1', 'parent_id' => $this->central->id]);

        $this->master = User::factory()->create(['branch_id' => $this->central->id, 'role' => 'master']);

        $this->cash = ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        $this->inventory = ChartOfAccount::create(['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset']);
        $this->revenue = ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);
        $this->cogs = ChartOfAccount::create(['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense']);
        $this->operatingExpense = ChartOfAccount::create(['code' => '5110', 'name' => 'Biaya Operasional', 'type' => 'expense']);

        $this->seedSampleJournals();
    }

    private function seedSampleJournals(): void
    {
        // 1. Prior transaction on Central: 2026-08-31 (Opening balance for Sept 2026)
        // Sale: Cash 500, Revenue 500, COGS 300, Inventory 300
        $j1 = JournalHeader::create([
            'branch_id' => $this->central->id,
            'user_id' => $this->master->id,
            'transaction_date' => '2026-08-31',
            'reference_number' => 'INV-20260831-001',
            'description' => 'August Cash Sale',
        ]);
        $j1->journalLines()->createMany([
            ['chart_of_account_id' => $this->cash->id, 'debit' => 500, 'credit' => 0, 'memo' => 'Cash received'],
            ['chart_of_account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 500, 'memo' => 'Revenue'],
            ['chart_of_account_id' => $this->cogs->id, 'debit' => 300, 'credit' => 0, 'memo' => 'COGS'],
            ['chart_of_account_id' => $this->inventory->id, 'debit' => 0, 'credit' => 300, 'memo' => 'Inventory released'],
        ]);

        // 2. September transaction on Central: 2026-09-05
        // Sale: Cash 1000, Revenue 1000, COGS 600, Inventory 600
        $j2 = JournalHeader::create([
            'branch_id' => $this->central->id,
            'user_id' => $this->master->id,
            'transaction_date' => '2026-09-05',
            'reference_number' => 'INV-20260905-001',
            'description' => 'September Cash Sale',
        ]);
        $j2->journalLines()->createMany([
            ['chart_of_account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'memo' => 'Cash sale'],
            ['chart_of_account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000, 'memo' => 'Revenue'],
            ['chart_of_account_id' => $this->cogs->id, 'debit' => 600, 'credit' => 0, 'memo' => 'COGS'],
            ['chart_of_account_id' => $this->inventory->id, 'debit' => 0, 'credit' => 600, 'memo' => 'Inventory released'],
        ]);

        // 3. September expense on Central: 2026-09-10
        // Expense: Biaya Operasional 150, Cash 150
        $j3 = JournalHeader::create([
            'branch_id' => $this->central->id,
            'user_id' => $this->master->id,
            'transaction_date' => '2026-09-10',
            'reference_number' => 'EXP-20260910-001',
            'description' => 'Operational Expense',
        ]);
        $j3->journalLines()->createMany([
            ['chart_of_account_id' => $this->operatingExpense->id, 'debit' => 150, 'credit' => 0, 'memo' => 'Office supplies'],
            ['chart_of_account_id' => $this->cash->id, 'debit' => 0, 'credit' => 150, 'memo' => 'Cash spent'],
        ]);

        // 4. September transaction on Branch 1: 2026-09-12
        // Sale: Cash 400, Revenue 400, COGS 250, Inventory 250
        $j4 = JournalHeader::create([
            'branch_id' => $this->branchOne->id,
            'user_id' => $this->master->id,
            'transaction_date' => '2026-09-12',
            'reference_number' => 'INV-20260912-001',
            'description' => 'Branch 1 Cash Sale',
        ]);
        $j4->journalLines()->createMany([
            ['chart_of_account_id' => $this->cash->id, 'debit' => 400, 'credit' => 0, 'memo' => 'Branch 1 cash'],
            ['chart_of_account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 400, 'memo' => 'Branch 1 revenue'],
            ['chart_of_account_id' => $this->cogs->id, 'debit' => 250, 'credit' => 0, 'memo' => 'Branch 1 COGS'],
            ['chart_of_account_id' => $this->inventory->id, 'debit' => 0, 'credit' => 250, 'memo' => 'Branch 1 inventory'],
        ]);
    }

    public function test_ledger_calculates_opening_period_and_ending_balances(): void
    {
        // Query September 2026 for Central only
        $report = $this->reportService->getLedger($this->central->id, '2026-09-01', '2026-09-30');

        $this->assertSame($this->central->id, $report['branch']['id']);

        $cashAccount = collect($report['accounts'])->firstWhere('account.code', '1110');
        $this->assertNotNull($cashAccount);

        // August had 500 debit to cash, so opening balance for Sept should be 500
        $this->assertEquals(500.00, $cashAccount['beginning_balance']);
        // September on Central: debit 1000, credit 150
        $this->assertEquals(1000.00, $cashAccount['total_debit']);
        $this->assertEquals(150.00, $cashAccount['total_credit']);
        $this->assertEquals(850.00, $cashAccount['net_change']);
        // Ending balance = 500 + 850 = 1350
        $this->assertEquals(1350.00, $cashAccount['ending_balance']);

        // Revenue on Central: August credit 500. Opening balance = 500 (credit)
        $revAccount = collect($report['accounts'])->firstWhere('account.code', '4110');
        $this->assertNotNull($revAccount);
        $this->assertEquals(500.00, $revAccount['beginning_balance']);
        $this->assertEquals(0.00, $revAccount['total_debit']);
        $this->assertEquals(1000.00, $revAccount['total_credit']);
        $this->assertEquals(1000.00, $revAccount['net_change']);
        $this->assertEquals(1500.00, $revAccount['ending_balance']);
    }

    public function test_trial_balance_is_strictly_balanced_for_consolidated_and_individual_branch(): void
    {
        // 1. Consolidated Trial Balance for September
        $consolidated = $this->reportService->getTrialBalance(null, '2026-09-01', '2026-09-30');

        $this->assertTrue($consolidated['is_balanced'], 'Consolidated trial balance must be balanced');
        $this->assertEquals(0.0, $consolidated['difference']);
        $this->assertEquals($consolidated['total_ending_debit'], $consolidated['total_ending_credit']);
        $this->assertEquals($consolidated['total_opening_debit'], $consolidated['total_opening_credit']);
        $this->assertEquals($consolidated['total_movement_debit'], $consolidated['total_movement_credit']);

        // 2. Individual Branch 1 Trial Balance
        $branchReport = $this->reportService->getTrialBalance($this->branchOne->id, '2026-09-01', '2026-09-30');
        $this->assertTrue($branchReport['is_balanced'], 'Branch 1 trial balance must be balanced');
        $this->assertEquals(0.0, $branchReport['difference']);
        $this->assertEquals($branchReport['total_ending_debit'], $branchReport['total_ending_credit']);
    }

    public function test_income_statement_calculates_revenue_cogs_and_net_profit(): void
    {
        // Consolidated for September:
        // Revenue: Central (1000) + Branch 1 (400) = 1400
        // COGS: Central (600) + Branch 1 (250) = 850
        // Gross Profit: 1400 - 850 = 550
        // Operating Expense: Central (150) = 150
        // Net Profit: 550 - 150 = 400
        $report = $this->reportService->getIncomeStatement(null, '2026-09-01', '2026-09-30');

        $this->assertEquals(1400.00, $report['revenue']['total']);
        $this->assertEquals(850.00, $report['cogs']['total']);
        $this->assertEquals(550.00, $report['gross_profit']);
        $this->assertEquals(150.00, $report['operating_expenses']['total']);
        $this->assertEquals(400.00, $report['net_profit']);

        // Check Margins
        // Gross Margin = (550 / 1400) * 100 = 39.29%
        // Net Margin = (400 / 1400) * 100 = 28.57%
        $this->assertEquals(39.29, $report['gross_profit_margin']);
        $this->assertEquals(28.57, $report['net_profit_margin']);

        // Test Branch 1 only:
        // Revenue: 400, COGS: 250, Gross Profit: 150, Expense: 0, Net Profit: 150
        $branchReport = $this->reportService->getIncomeStatement($this->branchOne->id, '2026-09-01', '2026-09-30');
        $this->assertEquals(400.00, $branchReport['revenue']['total']);
        $this->assertEquals(250.00, $branchReport['cogs']['total']);
        $this->assertEquals(150.00, $branchReport['gross_profit']);
        $this->assertEquals(0.00, $branchReport['operating_expenses']['total']);
        $this->assertEquals(150.00, $branchReport['net_profit']);
    }
}
