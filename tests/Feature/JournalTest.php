<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_balanced_journal_for_their_branch(): void
    {
        [$user, $branch, $cash, $revenue] = $this->journalSetup();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/journals', [
            'branch_id' => $branch->id + 999,
            'transaction_date' => '2026-09-11',
            'reference_number' => 'JV-001',
            'description' => 'Cash sale',
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('journal_headers', [
            'id' => $response->json('journal.id'),
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseCount('journal_lines', 2);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        [$user, , $cash, $revenue] = $this->journalSetup();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/journals', [
            'transaction_date' => '2026-09-11',
            'reference_number' => 'JV-002',
            'description' => 'Invalid journal',
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => 90],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('lines');
        $this->assertDatabaseCount('journal_headers', 0);
    }

    private function journalSetup(): array
    {
        $branch = Branch::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $cash = ChartOfAccount::create(['code' => '1110', 'name' => 'Kas', 'type' => 'asset']);
        $revenue = ChartOfAccount::create(['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue']);

        return [$user, $branch, $cash, $revenue];
    }
}