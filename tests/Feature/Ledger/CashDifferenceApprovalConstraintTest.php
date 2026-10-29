<?php

namespace Tests\Feature\Ledger;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

#[Group('mysql')]
class CashDifferenceApprovalConstraintTest extends LedgerTestCase
{
    private int $sessionId;

    private int $suspenseEntryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipUnlessMysql();
        $this->seedLedgerStructure();

        $this->sessionId = DB::table('pos_sessions')->insertGetId([
            'session_number' => 'SES-CDA-1',
            'entity_id' => $this->pupukBranch->entity_id,
            'branch_id' => $this->pupukBranch->id,
            'cash_register_id' => $this->pupukRegister->id,
            'cash_account_id' => $this->drawerAccount($this->pupukRegister)->id,
            'cashier_id' => $this->user->id,
            'opened_at' => now(),
            'closed_at' => now(),
            'status' => 'pending_approval',
            'expected_cash' => 500000,
            'counted_cash' => 450000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->suspenseEntryId = $this->createPostedEntry($this->pupukBranch, $this->pupukRegister, 50000);
    }

    public function test_valid_shortage_resolved_as_expense_is_accepted(): void
    {
        $this->insertApproval('shortage', ['approved_resolution' => 'expense_6810']);

        $this->assertSame(1, DB::table('cash_difference_approvals')->count());
    }

    public function test_shortage_cannot_be_resolved_as_other_income(): void
    {
        $this->assertRejected('shortage', ['approved_resolution' => 'other_income_4150'], 'chk_cda_shortage');
    }

    public function test_overage_cannot_be_resolved_as_expense(): void
    {
        $this->assertRejected('overage', ['approved_resolution' => 'expense_6810'], 'chk_cda_overage');
    }

    public function test_employee_receivable_requires_an_employee(): void
    {
        $this->assertRejected('shortage', ['approved_resolution' => 'employee_receivable_1135'], 'chk_cda_employee');
    }

    public function test_employee_receivable_with_an_employee_is_accepted(): void
    {
        $this->insertApproval('shortage', ['approved_resolution' => 'employee_receivable_1135', 'employee_user_id' => $this->user->id]);

        $this->assertSame(1, DB::table('cash_difference_approvals')->count());
    }

    public function test_amount_must_be_positive(): void
    {
        $this->assertRejected('shortage', ['amount' => 0], 'chk_cda_amount');
    }

    public function test_resolved_status_requires_decision_and_resolution_journal(): void
    {
        $this->assertRejected('shortage', ['status' => 'resolved', 'approved_resolution' => 'expense_6810'], 'chk_cda_resolved');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertApproval(string $direction, array $attributes = []): int
    {
        return DB::table('cash_difference_approvals')->insertGetId($attributes + [
            'entity_id' => $this->pupukBranch->entity_id,
            'branch_id' => $this->pupukBranch->id,
            'pos_session_id' => $this->sessionId,
            'direction' => $direction,
            'amount' => 50000,
            'threshold_applied' => 10000,
            'status' => 'pending',
            'requested_by' => $this->user->id,
            'requested_at' => now(),
            'reason' => 'Selisih kas uji',
            'suspense_journal_entry_id' => $this->suspenseEntryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertRejected(string $direction, array $attributes, string $constraint): void
    {
        try {
            DB::transaction(fn () => $this->insertApproval($direction, $attributes));
        } catch (QueryException $exception) {
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        $this->fail("Insert seharusnya ditolak oleh {$constraint}");
    }
}
