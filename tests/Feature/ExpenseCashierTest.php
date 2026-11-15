<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\CashRegisterShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCashierTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $cashier;

    private ChartOfAccount $cashAccount;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Cabang Kasir',
            'code' => 'CSH-01',
            'address' => 'Jl. Test',
            'phone' => '0800',
        ]);

        $this->cashier = User::create([
            'name' => 'Kasir Satu',
            'email' => 'kasir@example.com',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);

        $this->cashAccount = ChartOfAccount::create([
            'code' => '1110',
            'name' => 'Kas Cabang',
            'type' => 'asset',
            'is_active' => true,
            'is_postable' => true,
        ]);

        $expenseCoa = ChartOfAccount::create([
            'code' => '6100',
            'name' => 'Beban Operasional',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $this->category = ExpenseCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Operasional',
            'chart_of_account_id' => $expenseCoa->id,
            'is_active' => true,
        ]);
    }

    public function test_cashier_can_record_expense_from_pos_using_branch_cash(): void
    {
        $register = CashRegister::create([
            'branch_id' => $this->branch->id,
            'name' => 'Laci 1',
            'is_active' => true,
        ]);

        $shift = CashRegisterShift::create([
            'branch_id' => $this->branch->id,
            'cash_register_id' => $register->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_balance' => 500000,
            'expected_closing_balance' => 500000,
            'actual_closing_balance' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->cashier)->post(route('pos.expenses.store'), [
            'expense_category_id' => $this->category->id,
            'amount' => 75000,
            'notes' => 'Beli ATK cabang',
        ]);

        $response->assertRedirect(route('pos'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'cash_register_shift_id' => $shift->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 75000.00,
        ]);

        $expected = app(CashRegisterShiftService::class)->calculateExpectedBalance($shift->fresh());
        $this->assertEquals(425000.0, $expected);
    }

    public function test_cashier_can_access_expenses_index(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('backoffice.expenses.index'));
        $response->assertOk();
    }
}