<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalHeader;
use App\Models\User;
use App\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceDashboardWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    private User $adminA;

    private User $master;

    private ChartOfAccount $cashAccount;

    private ChartOfAccount $expenseAccount;

    private ExpenseCategory $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::create(['code' => 'FIN-A', 'name' => 'Cabang Finance A']);
        $this->branchB = Branch::create(['code' => 'FIN-B', 'name' => 'Cabang Finance B']);

        $this->adminA = User::factory()->create([
            'branch_id' => $this->branchA->id,
            'role' => 'branch_admin',
        ]);

        $this->master = User::factory()->create([
            'branch_id' => $this->branchA->id,
            'role' => 'master',
        ]);

        $this->cashAccount = ChartOfAccount::create([
            'code' => '1110',
            'name' => 'Kas Toko',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->expenseAccount = ChartOfAccount::create([
            'code' => '6110',
            'name' => 'Beban Operasional',
            'type' => 'expense',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '2110',
            'name' => 'Hutang Usaha',
            'type' => 'liability',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '3110',
            'name' => 'Modal',
            'type' => 'equity',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '4110',
            'name' => 'Pendapatan Penjualan',
            'type' => 'revenue',
            'is_active' => true,
        ]);

        $this->expenseCategory = ExpenseCategory::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Listrik Toko',
            'chart_of_account_id' => $this->expenseAccount->id,
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('backoffice.finance.dashboard'))->assertRedirect('/login');
    }

    public function test_dashboard_renders_four_tabs_accordions_and_quick_action_modals(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('backoffice.finance.dashboard'));

        $response->assertOk()
            ->assertSee('Dasbor Akuntansi')
            ->assertSee("tab: 'kas_bank'", false)
            ->assertSee('Kas &amp; Bank', false)
            ->assertSee('Jurnal &amp; Buku Besar', false)
            ->assertSee('Pengeluaran')
            ->assertSee('Laporan Keuangan')
            ->assertSee('Aset')
            ->assertSee('Kewajiban')
            ->assertSee('Ekuitas')
            ->assertSee('Pendapatan')
            ->assertSee('Beban')
            ->assertSee('Catat Pengeluaran')
            ->assertSee('Jurnal Manual')
            ->assertSee('name="return_to"', false)
            ->assertSee('value="finance"', false)
            ->assertSee(route('backoffice.finance.journals.store'), false)
            ->assertSee(route('backoffice.expenses.store'), false)
            ->assertDontSee('name="branch_id"', false);
    }

    public function test_master_sees_branch_filter_on_dashboard(): void
    {
        $this->actingAs($this->master)
            ->get(route('backoffice.finance.dashboard'))
            ->assertOk()
            ->assertSee('name="branch_id"', false)
            ->assertSee('Cabang Finance A')
            ->assertSee('Cabang Finance B');
    }

    public function test_branch_admin_cannot_see_other_branch_journals_or_expenses(): void
    {
        app(JournalPostingService::class)->post([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JU-ALPHA-ONLY',
            'description' => 'Koreksi kas cabang A',
            'lines' => [
                ['chart_of_account_id' => $this->cashAccount->id, 'debit' => 10000, 'credit' => 0],
                ['chart_of_account_id' => $this->expenseAccount->id, 'debit' => 0, 'credit' => 10000],
            ],
        ]);

        app(JournalPostingService::class)->post([
            'branch_id' => $this->branchB->id,
            'user_id' => $this->master->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JU-BETA-SECRET',
            'description' => 'Koreksi kas cabang B rahasia',
            'lines' => [
                ['chart_of_account_id' => $this->cashAccount->id, 'debit' => 25000, 'credit' => 0],
                ['chart_of_account_id' => $this->expenseAccount->id, 'debit' => 0, 'credit' => 25000],
            ],
        ]);

        Expense::withoutGlobalScopes()->create([
            'branch_id' => $this->branchB->id,
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 77777,
            'expense_date' => now()->toDateString(),
            'reference_number' => 'EXP-BETA-HIDDEN',
            'notes' => 'Biaya cabang B tidak boleh bocor',
        ]);

        $response = $this->actingAs($this->adminA)
            ->get(route('backoffice.finance.dashboard', ['tab' => 'jurnal']));

        $response->assertOk()
            ->assertSee('JU-ALPHA-ONLY')
            ->assertDontSee('JU-BETA-SECRET')
            ->assertDontSee('EXP-BETA-HIDDEN');
    }

    public function test_cash_bank_total_only_counts_kas_and_bank_per_branch(): void
    {
        $bank = ChartOfAccount::create(['code' => '1120', 'name' => 'Bank', 'type' => 'asset', 'is_active' => true]);
        $receivable = ChartOfAccount::create(['code' => '1130', 'name' => 'Piutang Usaha', 'type' => 'asset', 'is_active' => true]);
        $inventory = ChartOfAccount::create(['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset', 'is_active' => true]);
        $equity = ChartOfAccount::query()->where('code', '3110')->firstOrFail();
        $revenue = ChartOfAccount::query()->where('code', '4110')->firstOrFail();

        $post = function (int $branchId, string $reference, array $lines): void {
            app(JournalPostingService::class)->post([
                'branch_id' => $branchId,
                'user_id' => $this->master->id,
                'transaction_date' => now()->toDateString(),
                'reference_number' => $reference,
                'description' => $reference,
                'lines' => array_map(fn ($line) => [
                    'chart_of_account_id' => $line[0]->id,
                    'debit' => $line[1],
                    'credit' => $line[2],
                ], $lines),
            ]);
        };

        $post($this->branchA->id, 'JU-A-MODAL', [[$this->cashAccount, 300000, 0], [$equity, 0, 300000]]);
        $post($this->branchA->id, 'JU-A-BANK', [[$bank, 200000, 0], [$this->cashAccount, 0, 50000], [$revenue, 0, 150000]]);
        $post($this->branchA->id, 'JU-A-PIUTANG', [[$receivable, 900000, 0], [$revenue, 0, 900000]]);
        $post($this->branchA->id, 'JU-A-STOK', [[$inventory, 400000, 0], [$this->cashAccount, 0, 400000]]);
        $post($this->branchB->id, 'JU-B-MODAL', [[$this->cashAccount, 7000000, 0], [$equity, 0, 7000000]]);

        $this->actingAs($this->adminA)
            ->get(route('backoffice.finance.dashboard', ['branch_id' => $this->branchB->id]))
            ->assertOk()
            ->assertViewHas('dashboard', function (array $dashboard) {
                $balances = $dashboard['cash_accounts']->pluck('balance', 'code')->all();

                return $dashboard['cash_total'] === 50000.0
                    && $balances === ['1110' => -150000.0, '1120' => 200000.0];
            });

        $this->actingAs($this->master)
            ->get(route('backoffice.finance.dashboard', ['branch_id' => $this->branchB->id]))
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard) => $dashboard['cash_total'] === 7000000.0);
    }

    public function test_quick_action_posts_manual_journal_via_journal_posting_service(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('backoffice.finance.journals.store'), [
            'transaction_date' => now()->toDateString(),
            'description' => 'Koreksi saldo kas toko',
            'reference_number' => 'JU-QA-0001',
            'branch_id' => $this->branchB->id,
            'lines' => [
                [
                    'chart_of_account_id' => $this->cashAccount->id,
                    'debit' => 150000,
                    'credit' => 0,
                ],
                [
                    'chart_of_account_id' => $this->expenseAccount->id,
                    'debit' => 0,
                    'credit' => 150000,
                ],
            ],
        ]);

        $response->assertRedirect(route('backoffice.finance.dashboard', ['tab' => 'jurnal']));

        $header = JournalHeader::query()->where('reference_number', 'JU-QA-0001')->first();
        $this->assertNotNull($header);
        $this->assertSame($this->branchA->id, $header->branch_id);
        $this->assertSame('Koreksi saldo kas toko', $header->description);
        $this->assertCount(2, $header->journalLines);
    }

    public function test_unbalanced_manual_journal_is_rejected(): void
    {
        $this->actingAs($this->adminA)->post(route('backoffice.finance.journals.store'), [
            'transaction_date' => now()->toDateString(),
            'description' => 'Jurnal timpang',
            'lines' => [
                [
                    'chart_of_account_id' => $this->cashAccount->id,
                    'debit' => 10000,
                    'credit' => 0,
                ],
                [
                    'chart_of_account_id' => $this->expenseAccount->id,
                    'debit' => 0,
                    'credit' => 5000,
                ],
            ],
        ])->assertSessionHasErrors('lines');

        $this->assertDatabaseMissing('journal_headers', ['description' => 'Jurnal timpang']);
    }

    public function test_expense_quick_action_returns_to_finance_dashboard(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('backoffice.expenses.store'), [
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 55000,
            'expense_date' => now()->toDateString(),
            'notes' => 'Token PLN dari dasbor',
            'return_to' => 'finance',
        ]);

        $response->assertRedirect(route('backoffice.finance.dashboard', ['tab' => 'pengeluaran']));
        $this->assertDatabaseHas('expenses', [
            'notes' => 'Token PLN dari dasbor',
            'branch_id' => $this->branchA->id,
        ]);
    }
}
