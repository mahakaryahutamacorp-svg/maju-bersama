<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterShiftModelTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchOne;
    private Branch $branchTwo;
    private User $cashierOne;
    private User $cashierTwo;
    private User $masterUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchOne = Branch::create([
            'name'      => 'Cabang Jakarta Pusat',
            'code'      => 'JKT01',
            'is_active' => true,
        ]);

        $this->branchTwo = Branch::create([
            'name'      => 'Cabang Bandung',
            'code'      => 'BDG01',
            'is_active' => true,
        ]);

        $this->cashierOne = User::factory()->create([
            'branch_id' => $this->branchOne->id,
            'role'      => 'cashier',
        ]);

        $this->cashierTwo = User::factory()->create([
            'branch_id' => $this->branchTwo->id,
            'role'      => 'cashier',
        ]);

        $this->masterUser = User::factory()->create([
            'branch_id' => $this->branchOne->id,
            'role'      => 'master',
        ]);
    }

    public function test_cash_register_creation_and_attributes(): void
    {
        $register = CashRegister::create([
            'branch_id' => $this->branchOne->id,
            'name'      => 'Kasir Utama 01',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('cash_registers', [
            'id'        => $register->id,
            'branch_id' => $this->branchOne->id,
            'name'      => 'Kasir Utama 01',
            'is_active' => 1,
        ]);

        $this->assertTrue($register->is_active);
        $this->assertInstanceOf(Branch::class, $register->branch);
        $this->assertEquals($this->branchOne->id, $register->branch->id);
    }

    public function test_cash_register_has_branch_scope(): void
    {
        CashRegister::create([
            'branch_id' => $this->branchOne->id,
            'name'      => 'Laci JKT 1',
        ]);

        CashRegister::create([
            'branch_id' => $this->branchTwo->id,
            'name'      => 'Laci BDG 1',
        ]);

        // Cashier One should only see Branch One register
        $this->actingAs($this->cashierOne);
        $jktRegisters = CashRegister::all();
        $this->assertCount(1, $jktRegisters);
        $this->assertEquals('Laci JKT 1', $jktRegisters->first()->name);

        // Cashier Two should only see Branch Two register
        $this->actingAs($this->cashierTwo);
        $bdgRegisters = CashRegister::all();
        $this->assertCount(1, $bdgRegisters);
        $this->assertEquals('Laci BDG 1', $bdgRegisters->first()->name);

        // Master should see both registers
        $this->actingAs($this->masterUser);
        $allRegisters = CashRegister::all();
        $this->assertCount(2, $allRegisters);
    }

    public function test_cash_register_shift_lifecycle_and_calculations(): void
    {
        $register = CashRegister::create([
            'branch_id' => $this->branchOne->id,
            'name'      => 'Kasir 01',
        ]);

        // 1. Open shift
        $openedAt = now()->subHours(8);
        $shift = CashRegisterShift::create([
            'branch_id'                => $this->branchOne->id,
            'cash_register_id'         => $register->id,
            'user_id'                  => $this->cashierOne->id,
            'opened_at'                => $openedAt,
            'opening_balance'          => 200000.00,
            'expected_closing_balance' => 0.00,
            'actual_closing_balance'   => 0.00,
            'difference'               => 0.00,
            'status'                   => 'open',
            'notes'                    => 'Shift Pagi',
        ]);

        $this->assertTrue($shift->isOpen());
        $this->assertFalse($shift->isClosed());
        $this->assertEquals('200000.00', $shift->opening_balance);
        $this->assertEquals($shift->id, $register->currentShift()->id);

        // 2. Close shift with settlement
        $closedAt = now();
        $shift->update([
            'closed_at'                => $closedAt,
            'expected_closing_balance' => 1500000.00,
            'actual_closing_balance'   => 1520000.00,
            'difference'               => 20000.00, // surplus
            'status'                   => 'closed',
            'notes'                    => 'Shift Pagi selesai. Selisih lebih Rp 20.000',
        ]);

        $shift->refresh();
        $this->assertFalse($shift->isOpen());
        $this->assertTrue($shift->isClosed());
        $this->assertEquals('1500000.00', $shift->expected_closing_balance);
        $this->assertEquals('1520000.00', $shift->actual_closing_balance);
        $this->assertEquals('20000.00', $shift->difference);
        $this->assertNull($register->currentShift());
    }

    public function test_cash_register_shift_relations_and_sales_link(): void
    {
        $register = CashRegister::create([
            'branch_id' => $this->branchOne->id,
            'name'      => 'Kasir Utama',
        ]);

        $shift = CashRegisterShift::create([
            'branch_id'        => $this->branchOne->id,
            'cash_register_id' => $register->id,
            'user_id'          => $this->cashierOne->id,
            'opened_at'        => now(),
            'opening_balance'  => 100000.00,
            'status'           => 'open',
        ]);

        // Link Sale to Shift
        $sale = Sale::withoutGlobalScopes()->create([
            'branch_id'              => $this->branchOne->id,
            'cash_register_shift_id' => $shift->id,
            'receipt_number'         => 'INV-SHIFT-001',
            'total_amount'           => 75000,
            'payment_method'         => 'cash',
            'status'                 => 'completed',
            'created_by'             => $this->cashierOne->id,
        ]);

        // Verify shift relations
        $this->assertEquals($register->id, $shift->cashRegister->id);
        $this->assertEquals($this->cashierOne->id, $shift->user->id);
        $this->assertEquals($this->branchOne->id, $shift->branch->id);

        // Verify shift hasMany sales
        $this->assertCount(1, $shift->sales);
        $this->assertEquals($sale->id, $shift->sales->first()->id);

        // Verify sale belongsTo shift
        $this->assertInstanceOf(CashRegisterShift::class, $sale->cashRegisterShift);
        $this->assertEquals($shift->id, $sale->cashRegisterShift->id);

        // Verify Branch and User relations
        $this->assertTrue($this->branchOne->cashRegisters->contains($register));
        $this->assertTrue($this->branchOne->cashRegisterShifts->contains($shift));
        $this->assertTrue($this->cashierOne->cashRegisterShifts->contains($shift));
    }

    public function test_cash_register_shift_has_branch_scope(): void
    {
        $regOne = CashRegister::create([
            'branch_id' => $this->branchOne->id,
            'name'      => 'Kasir 1 JKT',
        ]);

        $regTwo = CashRegister::create([
            'branch_id' => $this->branchTwo->id,
            'name'      => 'Kasir 1 BDG',
        ]);

        CashRegisterShift::create([
            'branch_id'        => $this->branchOne->id,
            'cash_register_id' => $regOne->id,
            'user_id'          => $this->cashierOne->id,
            'opened_at'        => now(),
            'opening_balance'  => 100000.00,
            'status'           => 'open',
        ]);

        CashRegisterShift::create([
            'branch_id'        => $this->branchTwo->id,
            'cash_register_id' => $regTwo->id,
            'user_id'          => $this->cashierTwo->id,
            'opened_at'        => now(),
            'opening_balance'  => 150000.00,
            'status'           => 'open',
        ]);

        // Cashier 1 should only see Branch 1 shift
        $this->actingAs($this->cashierOne);
        $this->assertCount(1, CashRegisterShift::all());
        $this->assertEquals($this->branchOne->id, CashRegisterShift::first()->branch_id);

        // Cashier 2 should only see Branch 2 shift
        $this->actingAs($this->cashierTwo);
        $this->assertCount(1, CashRegisterShift::all());
        $this->assertEquals($this->branchTwo->id, CashRegisterShift::first()->branch_id);

        // Master should see both shifts
        $this->actingAs($this->masterUser);
        $this->assertCount(2, CashRegisterShift::all());
    }
}
