<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;
    private Branch $branchB;
    private User $adminA;
    private User $adminB;
    private User $masterUser;
    private ChartOfAccount $cashAccount;
    private ChartOfAccount $expenseAccount;
    private ExpenseCategory $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::create([
            'code' => 'BR-A',
            'name' => 'Cabang Alpha',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'code' => 'BR-B',
            'name' => 'Cabang Beta',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create([
            'name' => 'Admin Cabang Alpha',
            'email' => 'admin.a@example.com',
            'branch_id' => $this->branchA->id,
            'role' => 'branch_admin',
        ]);

        $this->adminB = User::factory()->create([
            'name' => 'Admin Cabang Beta',
            'email' => 'admin.b@example.com',
            'branch_id' => $this->branchB->id,
            'role' => 'branch_admin',
        ]);

        $this->masterUser = User::factory()->create([
            'name' => 'Super Admin Master',
            'email' => 'superadmin@example.com',
            'branch_id' => $this->branchA->id,
            'role' => 'super_admin',
        ]);

        $this->cashAccount = ChartOfAccount::create([
            'code' => '1110',
            'name' => 'Kas Toko',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->expenseAccount = ChartOfAccount::create([
            'code' => '6100',
            'name' => 'Beban Operasional',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $this->expenseCategory = ExpenseCategory::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Operasional Harian Alpha',
            'chart_of_account_id' => $this->expenseAccount->id,
            'is_active' => true,
        ]);

        $this->expenseCategoryB = ExpenseCategory::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Operasional Harian Beta',
            'chart_of_account_id' => $this->expenseAccount->id,
            'is_active' => true,
        ]);
    }

    public function test_has_branch_scope_isolates_eloquent_queries_for_sale_expense_and_customer(): void
    {
        // Setup data for Branch A and Branch B
        $saleA = Sale::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'receipt_number' => 'INV-A-001',
            'subtotal' => 100000,
            'tax' => 0,
            'discount' => 0,
            'total_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $saleB = Sale::create([
            'branch_id' => $this->branchB->id,
            'user_id' => $this->adminB->id,
            'receipt_number' => 'INV-B-001',
            'subtotal' => 200000,
            'tax' => 0,
            'discount' => 0,
            'total_amount' => 200000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $expenseA = Expense::create([
            'branch_id' => $this->branchA->id,
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->cashAccount->id,
            'reference_number' => 'EXP-A-001',
            'expense_date' => now()->toDateString(),
            'amount' => 50000,
            'notes' => 'Biaya Operasional Alpha',
        ]);

        $expenseB = Expense::create([
            'branch_id' => $this->branchB->id,
            'expense_category_id' => $this->expenseCategoryB->id,
            'account_id' => $this->cashAccount->id,
            'reference_number' => 'EXP-B-001',
            'expense_date' => now()->toDateString(),
            'amount' => 75000,
            'notes' => 'Biaya Operasional Beta',
        ]);

        $customerA = Customer::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Pelanggan Alpha',
            'phone' => '0811111111',
            'address' => 'Jl. Alpha 1',
        ]);

        $customerB = Customer::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Pelanggan Beta',
            'phone' => '0822222222',
            'address' => 'Jl. Beta 1',
        ]);

        // 1. As Admin Branch A
        $this->actingAs($this->adminA);
        $this->assertCount(1, Sale::all());
        $this->assertEquals('INV-A-001', Sale::first()->receipt_number);
        $this->assertCount(1, Expense::all());
        $this->assertEquals('EXP-A-001', Expense::first()->reference_number);
        $this->assertCount(1, Customer::all());
        $this->assertEquals('Pelanggan Alpha', Customer::first()->name);

        // 2. As Admin Branch B
        $this->actingAs($this->adminB);
        $this->assertCount(1, Sale::all());
        $this->assertEquals('INV-B-001', Sale::first()->receipt_number);
        $this->assertCount(1, Expense::all());
        $this->assertEquals('EXP-B-001', Expense::first()->reference_number);
        $this->assertCount(1, Customer::all());
        $this->assertEquals('Pelanggan Beta', Customer::first()->name);

        // 3. As Superadmin (master/super_admin)
        $this->actingAs($this->masterUser);
        $this->assertCount(2, Sale::all());
        $this->assertCount(2, Expense::all());
        $this->assertCount(2, Customer::all());
    }

    public function test_branch_admin_web_reports_and_views_cannot_see_other_branch_data(): void
    {
        $saleA = Sale::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'receipt_number' => 'INV-ALPHA-999',
            'subtotal' => 150000,
            'tax' => 0,
            'discount' => 0,
            'total_amount' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $saleB = Sale::create([
            'branch_id' => $this->branchB->id,
            'user_id' => $this->adminB->id,
            'receipt_number' => 'INV-BETA-888',
            'subtotal' => 250000,
            'tax' => 0,
            'discount' => 0,
            'total_amount' => 250000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $expenseA = Expense::create([
            'branch_id' => $this->branchA->id,
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->cashAccount->id,
            'reference_number' => 'EXP-ALPHA-01',
            'expense_date' => now()->toDateString(),
            'amount' => 50000,
            'notes' => 'Biaya Operasional Toko Alpha',
        ]);

        $expenseB = Expense::create([
            'branch_id' => $this->branchB->id,
            'expense_category_id' => $this->expenseCategoryB->id,
            'account_id' => $this->cashAccount->id,
            'reference_number' => 'EXP-BETA-02',
            'expense_date' => now()->toDateString(),
            'amount' => 75000,
            'notes' => 'Biaya Operasional Toko Beta',
        ]);

        $customerA = Customer::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Pelanggan Alpha Setia',
            'phone' => '0811111111',
            'address' => 'Jl. Alpha 1',
        ]);

        $customerB = Customer::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Pelanggan Beta Eksklusif',
            'phone' => '0822222222',
            'address' => 'Jl. Beta 1',
        ]);

        // Branch A Admin checking Sales History report
        $salesRes = $this->actingAs($this->adminA)->get('/backoffice/reports/sales')->assertOk();
        $salesRes->assertSee('INV-ALPHA-999');
        $salesRes->assertDontSee('INV-BETA-888');

        // Branch A Admin checking Customers list
        $custRes = $this->actingAs($this->adminA)->get('/backoffice/customers')->assertOk();
        $custRes->assertSee('Pelanggan Alpha Setia');
        $custRes->assertDontSee('Pelanggan Beta Eksklusif');

        // Branch A Admin checking Expenses list
        $expRes = $this->actingAs($this->adminA)->get('/backoffice/expenses')->assertOk();
        $expRes->assertSee('EXP-ALPHA-01');
        $expRes->assertDontSee('EXP-BETA-02');

        // Branch A Admin cannot view details of Branch B sale in TransactionViewer
        $this->actingAs($this->adminA)
            ->get('/backoffice/transactions/INV-BETA-888/details')
            ->assertOk()
            ->assertSee('Rincian Transaksi Tidak Ditemukan');

        // But Branch A Admin CAN view details of Branch A sale
        $this->actingAs($this->adminA)
            ->get('/backoffice/transactions/INV-ALPHA-999/details')
            ->assertOk()
            ->assertSee('INV-ALPHA-999')
            ->assertDontSee('Rincian Transaksi Tidak Ditemukan');
    }

    public function test_branch_admin_cannot_choose_branch_and_branch_id_is_auto_filled_on_expense_creation(): void
    {
        // 1. Check form rendering for branch_admin: branch dropdown must be hidden/absent
        $formRes = $this->actingAs($this->adminA)->get('/backoffice/expenses/create')->assertOk();
        $formRes->assertDontSee('id="branch_id"', false);
        $formRes->assertDontSee('-- Pilih Cabang --');
        $formRes->assertSee($this->branchA->name);
        $formRes->assertSee('value="' . $this->branchA->id . '"', false);

        // Check form rendering for super_admin: branch dropdown must be present
        $superFormRes = $this->actingAs($this->masterUser)->get('/backoffice/expenses/create')->assertOk();
        $superFormRes->assertSee('id="branch_id"', false);
        $superFormRes->assertSee('-- Pilih Cabang --');

        // 2. Submit expense as branch_admin: attempts to spoof branch_id to Branch B
        $payload = [
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->cashAccount->id,
            'expense_date' => now()->toDateString(),
            'amount' => 88000,
            'notes' => 'Pembelian ATK Cabang Alpha',
            'branch_id' => $this->branchB->id, // Maliciously trying to charge Branch B
        ];

        $postRes = $this->actingAs($this->adminA)->post('/backoffice/expenses', $payload);
        $postRes->assertRedirect(route('backoffice.expenses.index'));

        // Verify the created expense is saved with Branch A, NOT Branch B
        $createdExpense = Expense::withoutGlobalScopes()->where('amount', 88000)->first();
        $this->assertNotNull($createdExpense);
        $this->assertEquals($this->branchA->id, $createdExpense->branch_id, 'branch_id must be forced to admin A branch');

        // 3. Submit expense as super_admin: can assign to Branch B
        $masterPayload = [
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->cashAccount->id,
            'expense_date' => now()->toDateString(),
            'amount' => 99000,
            'notes' => 'Pembelian Alat untuk Cabang Beta oleh Superadmin',
            'branch_id' => $this->branchB->id,
        ];

        $masterPostRes = $this->actingAs($this->masterUser)->post('/backoffice/expenses', $masterPayload);
        $masterPostRes->assertRedirect(route('backoffice.expenses.index'));

        $masterCreatedExpense = Expense::withoutGlobalScopes()->where('amount', 99000)->first();
        $this->assertNotNull($masterCreatedExpense);
        $this->assertEquals($this->branchB->id, $masterCreatedExpense->branch_id, 'Superadmin can explicitly choose branch_id');
    }
}
