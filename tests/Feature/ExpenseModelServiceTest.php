<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalHeader;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpenseModelServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;
    private Branch $branch2;
    private User $branch1User;
    private User $masterUser;
    private ChartOfAccount $cashAccount;
    private ChartOfAccount $electricityAccount;
    private ChartOfAccount $fuelAccount;
    private ExpenseCategory $electricityCategory;
    private ExpenseService $expenseService;
    private JournalPostingService $journalPostingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expenseService = app(ExpenseService::class);
        $this->journalPostingService = app(JournalPostingService::class);

        $this->branch1 = Branch::create([
            'name'    => 'Cabang Jakarta Pusat',
            'code'    => 'JKT-01',
            'address' => 'Jl. Thamrin No. 1',
            'phone'   => '0811001122',
        ]);

        $this->branch2 = Branch::create([
            'name'    => 'Cabang Surabaya Barat',
            'code'    => 'SBY-01',
            'address' => 'Jl. HR Muhammad No. 5',
            'phone'   => '0822003344',
        ]);

        $this->branch1User = User::create([
            'name'      => 'Admin Cabang JKT',
            'email'     => 'admin.jkt@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'branch_admin',
            'branch_id' => $this->branch1->id,
        ]);

        $this->masterUser = User::create([
            'name'      => 'Owner Pusat',
            'email'     => 'owner@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'master',
            'branch_id' => $this->branch1->id,
        ]);

        // COA Kas/Bank (Aset Lancar - 11xx)
        $this->cashAccount = ChartOfAccount::create([
            'code'      => '1110',
            'name'      => 'Kas Toko Operasional',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        // COA Beban Operasional (6xxx)
        $this->electricityAccount = ChartOfAccount::create([
            'code'      => '6110',
            'name'      => 'Beban Listrik & Air',
            'type'      => 'expense',
            'is_active' => true,
        ]);

        $this->fuelAccount = ChartOfAccount::create([
            'code'      => '6120',
            'name'      => 'Beban Bensin & Transportasi',
            'type'      => 'expense',
            'is_active' => true,
        ]);

        // Kategori Biaya Operasional
        $this->electricityCategory = ExpenseCategory::create([
            'branch_id'           => $this->branch1->id,
            'name'                => 'Listrik',
            'chart_of_account_id' => $this->electricityAccount->id,
            'is_active'           => true,
        ]);
    }

    public function test_expense_category_creation_and_relations(): void
    {
        $category = ExpenseCategory::create([
            'branch_id'           => $this->branch1->id,
            'name'                => 'Bensin Motor Kurir',
            'chart_of_account_id' => $this->fuelAccount->id,
            'is_active'           => true,
        ]);

        $this->assertDatabaseHas('expense_categories', [
            'name'                => 'Bensin Motor Kurir',
            'chart_of_account_id' => $this->fuelAccount->id,
            'is_active'           => 1,
        ]);

        $this->assertSame($this->branch1->id, $category->branch->id);
        $this->assertSame($this->fuelAccount->id, $category->chartOfAccount->id);
        $this->assertSame($this->fuelAccount->id, $category->account->id);
    }

    public function test_expense_category_has_branch_scope(): void
    {
        $catBranch2 = ExpenseCategory::create([
            'branch_id'           => $this->branch2->id,
            'name'                => 'Sewa Ruko SBY',
            'chart_of_account_id' => $this->electricityAccount->id,
            'is_active'           => true,
        ]);

        // Branch 1 user should only see branch 1 categories
        $this->actingAs($this->branch1User);
        $categories = ExpenseCategory::all();
        $this->assertTrue($categories->contains('id', $this->electricityCategory->id));
        $this->assertFalse($categories->contains('id', $catBranch2->id));

        // Master user should see categories across all branches
        $this->actingAs($this->masterUser);
        $masterCategories = ExpenseCategory::all();
        $this->assertTrue($masterCategories->contains('id', $this->electricityCategory->id));
        $this->assertTrue($masterCategories->contains('id', $catBranch2->id));
    }

    public function test_expense_model_creation_and_relations(): void
    {
        $expense = Expense::create([
            'branch_id'           => $this->branch1->id,
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 350000.00,
            'expense_date'        => '2026-09-20',
            'reference_number'    => 'EXP-TEST-001',
            'notes'               => 'Pembelian token PLN 350rb',
        ]);

        $this->assertDatabaseHas('expenses', [
            'reference_number'    => 'EXP-TEST-001',
            'amount'              => 350000.00,
            'notes'               => 'Pembelian token PLN 350rb',
        ]);
        $this->assertSame('2026-09-20', $expense->expense_date->format('Y-m-d'));

        $this->assertSame($this->branch1->id, $expense->branch->id);
        $this->assertSame($this->electricityCategory->id, $expense->expenseCategory->id);
        $this->assertSame($this->electricityCategory->id, $expense->category->id);
        $this->assertSame($this->cashAccount->id, $expense->account->id);
        $this->assertSame($this->cashAccount->id, $expense->chartOfAccount->id);
    }

    public function test_expense_model_has_branch_scope(): void
    {
        $catBranch2 = ExpenseCategory::create([
            'branch_id'           => $this->branch2->id,
            'name'                => 'Kebersihan',
            'chart_of_account_id' => $this->electricityAccount->id,
            'is_active'           => true,
        ]);

        $exp1 = Expense::create([
            'branch_id'           => $this->branch1->id,
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 50000.00,
            'expense_date'        => '2026-09-20',
            'reference_number'    => 'EXP-JKT-01',
        ]);

        $exp2 = Expense::create([
            'branch_id'           => $this->branch2->id,
            'expense_category_id' => $catBranch2->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 75000.00,
            'expense_date'        => '2026-09-20',
            'reference_number'    => 'EXP-SBY-01',
        ]);

        // Branch 1 user
        $this->actingAs($this->branch1User);
        $expenses = Expense::all();
        $this->assertTrue($expenses->contains('id', $exp1->id));
        $this->assertFalse($expenses->contains('id', $exp2->id));

        // Master user
        $this->actingAs($this->masterUser);
        $masterExpenses = Expense::all();
        $this->assertTrue($masterExpenses->contains('id', $exp1->id));
        $this->assertTrue($masterExpenses->contains('id', $exp2->id));
    }

    public function test_expense_service_records_expense_and_creates_balanced_journal(): void
    {
        $expense = $this->expenseService->recordExpense([
            'branch_id'           => $this->branch1->id,
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 275000.00,
            'expense_date'        => '2026-09-20',
            'reference_number'    => 'EXP-20260920-0099',
            'notes'               => 'Pembayaran Token Listrik Ruko Toko',
            'user_id'             => $this->branch1User->id,
        ]);

        // Verifikasi entri expense
        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertDatabaseHas('expenses', [
            'id'                  => $expense->id,
            'branch_id'           => $this->branch1->id,
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 275000.00,
            'reference_number'    => 'EXP-20260920-0099',
            'notes'               => 'Pembayaran Token Listrik Ruko Toko',
        ]);

        // Verifikasi entri journal header
        $this->assertNotNull($expense->journal_header_id);
        $journal = JournalHeader::with('journalLines')->find($expense->journal_header_id);
        $this->assertNotNull($journal);
        $this->assertSame($this->branch1->id, $journal->branch_id);
        $this->assertSame($this->branch1User->id, $journal->user_id);
        $this->assertSame('EXP-20260920-0099', $journal->reference_number);
        $this->assertSame('Biaya Operasional: Listrik - Pembayaran Token Listrik Ruko Toko', $journal->description);

        // Verifikasi baris jurnal ganda (Debit Beban, Kredit Kas/Bank)
        $lines = $journal->journalLines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('debit', '>', 0);
        $creditLine = $lines->firstWhere('credit', '>', 0);

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);

        // Debit harus pada Akun Beban (6110)
        $this->assertSame($this->electricityAccount->id, $debitLine->chart_of_account_id);
        $this->assertEquals(275000.00, (float) $debitLine->debit);
        $this->assertEquals(0.00, (float) $debitLine->credit);

        // Kredit harus pada Akun Kas/Bank (1110)
        $this->assertSame($this->cashAccount->id, $creditLine->chart_of_account_id);
        $this->assertEquals(0.00, (float) $creditLine->debit);
        $this->assertEquals(275000.00, (float) $creditLine->credit);

        // Jurnal harus seimbang absolut
        $totalDebit = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEquals(275000.00, (float) $totalDebit);
        $this->assertEquals(275000.00, (float) $totalCredit);
    }

    public function test_expense_service_handles_notes_absence_in_journal_description(): void
    {
        $expense = $this->expenseService->recordExpense([
            'branch_id'           => $this->branch1->id,
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 100000.00,
        ]);

        $this->assertSame('Biaya Operasional: Listrik', $expense->journalHeader->description);
    }

    public function test_expense_service_rejects_inactive_category(): void
    {
        $inactiveCat = ExpenseCategory::create([
            'branch_id'           => $this->branch1->id,
            'name'                => 'Kategori Nonaktif',
            'chart_of_account_id' => $this->fuelAccount->id,
            'is_active'           => false,
        ]);

        $this->expectException(ValidationException::class);

        $this->expenseService->recordExpense([
            'branch_id'           => $this->branch1->id,
            'expense_category_id' => $inactiveCat->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 50000.00,
        ]);
    }

    public function test_expense_service_rejects_zero_or_negative_amount(): void
    {
        $this->expectException(ValidationException::class);

        $this->expenseService->recordExpense([
            'branch_id'           => $this->branch1->id,
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 0,
        ]);
    }

    public function test_journal_posting_service_rejects_unbalanced_lines(): void
    {
        $this->expectException(ValidationException::class);

        $this->journalPostingService->post([
            'branch_id'        => $this->branch1->id,
            'transaction_date' => '2026-09-20',
            'reference_number' => 'UNBALANCED-01',
            'description'      => 'Test Jurnal Tidak Seimbang',
            'lines'            => [
                [
                    'chart_of_account_id' => $this->electricityAccount->id,
                    'debit'               => 100000.00,
                    'credit'              => 0,
                ],
                [
                    'chart_of_account_id' => $this->cashAccount->id,
                    'debit'               => 0,
                    'credit'              => 90000.00, // Selisih 10.000!
                ],
            ],
        ]);
    }
}
