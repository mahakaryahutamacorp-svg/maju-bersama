<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\JournalHeader;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionViewerBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $ownBranch;

    private Branch $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownBranch = Branch::create(['code' => 'OWN', 'name' => 'Cabang Sendiri']);
        $this->otherBranch = Branch::create(['code' => 'OTH', 'name' => 'Cabang Lain']);
        $master = User::factory()->create(['branch_id' => $this->ownBranch->id, 'role' => 'master']);

        Sale::withoutGlobalScopes()->create([
            'branch_id' => $this->otherBranch->id,
            'receipt_number' => 'INV-OTHER-001',
            'total_amount' => 990000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $master->id,
        ]);

        JournalHeader::create([
            'branch_id' => $this->otherBranch->id,
            'user_id' => $master->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'OB-OTHER-001',
            'description' => 'Saldo awal rahasia cabang lain',
        ]);

        JournalHeader::create([
            'branch_id' => $this->ownBranch->id,
            'user_id' => $master->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => 'JU-OWN-001',
            'description' => 'Jurnal cabang sendiri',
        ]);
    }

    public function test_non_master_cannot_view_other_branch_transactions(): void
    {
        foreach (['cashier', 'branch_admin'] as $role) {
            $user = User::factory()->create(['branch_id' => $this->ownBranch->id, 'role' => $role]);

            $this->actingAs($user)
                ->get('/backoffice/transactions/OB-OTHER-001/details')
                ->assertOk()
                ->assertSee('Tidak Ditemukan')
                ->assertDontSee('Saldo awal rahasia cabang lain');

            $this->actingAs($user)
                ->get('/backoffice/transactions/INV-OTHER-001/details')
                ->assertOk()
                ->assertSee('Tidak Ditemukan');
        }
    }

    public function test_non_master_can_view_own_branch_transaction(): void
    {
        $branchAdmin = User::factory()->create(['branch_id' => $this->ownBranch->id, 'role' => 'branch_admin']);

        $this->actingAs($branchAdmin)
            ->get('/backoffice/transactions/JU-OWN-001/details')
            ->assertOk()
            ->assertSee('Jurnal cabang sendiri')
            ->assertDontSee('Tidak Ditemukan');
    }

    public function test_master_can_view_any_branch_transaction(): void
    {
        $master = User::factory()->create(['branch_id' => $this->ownBranch->id, 'role' => 'master']);

        $this->actingAs($master)
            ->get('/backoffice/transactions/OB-OTHER-001/details')
            ->assertOk()
            ->assertSee('Saldo awal rahasia cabang lain');
    }
}
