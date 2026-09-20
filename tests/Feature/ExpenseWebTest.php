<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private ChartOfAccount $cashAccount;
    private ChartOfAccount $electricityAccount;
    private ExpenseCategory $electricityCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name'    => 'Cabang Jakarta Selatan',
            'code'    => 'JKT-SEL',
            'address' => 'Jl. Fatmawati No. 10',
            'phone'   => '0811998877',
        ]);

        $this->admin = User::create([
            'name'      => 'Bambang Admin',
            'email'     => 'bambang@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'branch_admin',
            'branch_id' => $this->branch->id,
        ]);

        $this->cashAccount = ChartOfAccount::create([
            'code'      => '1110',
            'name'      => 'Kas Toko Kasir',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        $this->electricityAccount = ChartOfAccount::create([
            'code'      => '6110',
            'name'      => 'Beban Listrik Toko',
            'type'      => 'expense',
            'is_active' => true,
        ]);

        $this->electricityCategory = ExpenseCategory::create([
            'branch_id'           => $this->branch->id,
            'name'                => 'Listrik & Air',
            'chart_of_account_id' => $this->electricityAccount->id,
            'is_active'           => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/backoffice/expenses')->assertRedirect('/login');
        $this->get('/backoffice/expenses/create')->assertRedirect('/login');
        $this->get('/backoffice/expense-categories')->assertRedirect('/login');
        $this->get('/backoffice/expense-categories/create')->assertRedirect('/login');
    }

    public function test_user_can_view_expense_categories_index_and_create(): void
    {
        $response = $this->actingAs($this->admin)->get('/backoffice/expense-categories');
        $response->assertStatus(200);
        $response->assertSee('Kategori Biaya Operasional');
        $response->assertSee('Listrik &amp; Air', false);
        $response->assertSee('6110');

        $createResponse = $this->actingAs($this->admin)->get('/backoffice/expense-categories/create');
        $createResponse->assertStatus(200);
        $createResponse->assertSee('Tambah Kategori Biaya Baru');
        $createResponse->assertSee('Beban Listrik Toko');
    }

    public function test_user_can_store_and_update_expense_category(): void
    {
        $storeResponse = $this->actingAs($this->admin)->post(route('backoffice.expense-categories.store'), [
            'name'                => 'Biaya Bensin Kurir',
            'chart_of_account_id' => $this->electricityAccount->id,
            'is_active'           => '1',
        ]);

        $storeResponse->assertRedirect(route('backoffice.expense-categories.index'));
        $storeResponse->assertSessionHas('success');

        $this->assertDatabaseHas('expense_categories', [
            'name'                => 'Biaya Bensin Kurir',
            'chart_of_account_id' => $this->electricityAccount->id,
            'is_active'           => 1,
        ]);

        $category = ExpenseCategory::where('name', 'Biaya Bensin Kurir')->first();

        $updateResponse = $this->actingAs($this->admin)->put(route('backoffice.expense-categories.update', $category->id), [
            'name'                => 'Bensin & Transportasi',
            'chart_of_account_id' => $this->electricityAccount->id,
            'is_active'           => '1',
        ]);

        $updateResponse->assertRedirect(route('backoffice.expense-categories.index'));
        $updateResponse->assertSessionHas('success');

        $this->assertDatabaseHas('expense_categories', [
            'id'   => $category->id,
            'name' => 'Bensin & Transportasi',
        ]);
    }

    public function test_user_can_view_expenses_index_and_create_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/backoffice/expenses');
        $response->assertStatus(200);
        $response->assertSee('Riwayat Kas Keluar');
        $response->assertSee('Catat Pengeluaran Kas');

        $createResponse = $this->actingAs($this->admin)->get('/backoffice/expenses/create');
        $createResponse->assertStatus(200);
        $createResponse->assertSee('Formulir Biaya Operasional');
        $createResponse->assertSee('Listrik &amp; Air', false);
        $createResponse->assertSee('Kas Toko Kasir');
        $createResponse->assertSee('Live Journal Preview');
    }

    public function test_user_can_store_expense_via_web_form(): void
    {
        $response = $this->actingAs($this->admin)->post(route('backoffice.expenses.store'), [
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 175000,
            'expense_date'        => '2026-09-20',
            'reference_number'    => 'NOTA-PLN-175',
            'notes'               => 'Pembelian Token PLN 175rb untuk toko',
        ]);

        $response->assertRedirect(route('backoffice.expenses.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'branch_id'           => $this->branch->id,
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 175000.00,
            'reference_number'    => 'NOTA-PLN-175',
            'notes'               => 'Pembelian Token PLN 175rb untuk toko',
        ]);

        $expense = Expense::where('reference_number', 'NOTA-PLN-175')->first();
        $this->assertNotNull($expense);
        $this->assertNotNull($expense->journal_header_id);

        $journal = JournalHeader::with('journalLines')->find($expense->journal_header_id);
        $this->assertSame('Biaya Operasional: Listrik & Air - Pembelian Token PLN 175rb untuk toko', $journal->description);
        $this->assertCount(2, $journal->journalLines);
        $this->assertEquals(175000.00, (float) $journal->journalLines->sum('debit'));
        $this->assertEquals(175000.00, (float) $journal->journalLines->sum('credit'));
    }

    public function test_user_can_view_expense_show_page(): void
    {
        $expense = Expense::create([
            'branch_id'           => $this->branch->id,
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 80000.00,
            'expense_date'        => '2026-09-20',
            'reference_number'    => 'EXP-SHOW-001',
            'notes'               => 'Beli sabun dan pembersih lantai',
        ]);

        $response = $this->actingAs($this->admin)->get(route('backoffice.expenses.show', $expense->id));
        $response->assertStatus(200);
        $response->assertSee('Voucher Kas Keluar');
        $response->assertSee('EXP-SHOW-001');
        $response->assertSee('80.000');
        $response->assertSee('Beli sabun dan pembersih lantai');
    }

    public function test_store_expense_validation_fails_without_notes_or_amount(): void
    {
        $response = $this->actingAs($this->admin)->post(route('backoffice.expenses.store'), [
            'expense_category_id' => $this->electricityCategory->id,
            'account_id'          => $this->cashAccount->id,
            'amount'              => 0,
            'expense_date'        => '2026-09-20',
            'notes'               => '', // Kosong padahal wajib
        ]);

        $response->assertSessionHasErrors(['amount', 'notes']);
    }
}
