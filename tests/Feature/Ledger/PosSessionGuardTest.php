<?php

namespace Tests\Feature\Ledger;

use App\Models\CashRegister;
use App\Models\User;
use Database\Seeders\ChartOfAccountsV2Seeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PosSessionGuardTest extends LedgerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLedgerStructure();
    }

    public function test_second_open_session_on_the_same_register_is_rejected(): void
    {
        $this->openSession($this->pupukRegister, $this->user);
        $otherCashier = User::factory()->create(['branch_id' => $this->pupukBranch->id]);

        $this->expectException(QueryException::class);
        $this->openSession($this->pupukRegister, $otherCashier);
    }

    public function test_second_open_session_for_the_same_cashier_is_rejected(): void
    {
        $this->openSession($this->pupukRegister, $this->user);
        $secondRegister = CashRegister::create(['branch_id' => $this->pupukBranch->id, 'name' => 'Kasir 2', 'is_active' => true]);
        $this->seed(ChartOfAccountsV2Seeder::class);

        $this->expectException(QueryException::class);
        $this->openSession($secondRegister, $this->user, 'closing');
    }

    public function test_register_can_be_reopened_after_the_previous_session_is_closed(): void
    {
        $firstId = $this->openSession($this->pupukRegister, $this->user);
        DB::table('pos_sessions')->where('id', $firstId)->update([
            'status' => 'closed',
            'closed_at' => now(),
            'expected_cash' => 500000,
            'counted_cash' => 495000,
        ]);

        $secondId = $this->openSession($this->pupukRegister, $this->user);

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(1, DB::table('pos_sessions')->where('cash_register_id', $this->pupukRegister->id)->where('status', 'open')->count());
        $this->assertEquals(-5000, DB::table('pos_sessions')->where('id', $firstId)->value('cash_difference'));
    }

    private function openSession(CashRegister $register, User $cashier, string $status = 'open'): int
    {
        static $sequence = 0;
        $sequence++;

        return DB::table('pos_sessions')->insertGetId([
            'session_number' => 'SES-TEST-'.$sequence,
            'entity_id' => $register->branch->entity_id,
            'branch_id' => $register->branch_id,
            'cash_register_id' => $register->id,
            'cash_account_id' => $this->drawerAccount($register)->id,
            'cashier_id' => $cashier->id,
            'opened_at' => now(),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
