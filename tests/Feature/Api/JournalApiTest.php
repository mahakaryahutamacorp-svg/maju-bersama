<?php

namespace Tests\Feature\Api;

use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Concerns\AssertsMoney;
use Tests\Feature\Api\Concerns\PreparesLedger;
use Tests\TestCase;

class JournalApiTest extends TestCase
{
    use AssertsMoney;
    use PreparesLedger;

    public function test_tc002_balanced_decimal_journal_is_accepted(): void
    {
        [, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        Sanctum::actingAs($master);

        $cash = ChartOfAccount::where('code', '1110')->firstOrFail();
        $revenue = ChartOfAccount::where('code', '4110')->firstOrFail();

        $response = $this->postJson('/api/journals', [
            'transaction_date' => '2026-10-10',
            'reference_number' => 'JV-TC002',
            'description' => 'Jurnal seimbang dua desimal',
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => '10.50', 'credit' => '0.00'],
                ['chart_of_account_id' => $revenue->id, 'debit' => '0.00', 'credit' => '10.50'],
            ],
        ]);

        $response->assertCreated();

        $journalId = (int) $response->json('journal.id');
        $lines = JournalLine::where('journal_header_id', $journalId)->get();

        $this->assertCount(2, $lines);
        $this->assertMoneySame(
            $lines->sum('debit'),
            $lines->sum('credit'),
        );
        $this->assertMoneySame($lines->sum('debit'), '10.50');
        $this->assertMoneyNotHundredfold($lines->sum('debit'), '10.50');
    }

    public function test_tc002_unbalanced_journal_is_rejected_without_a_header(): void
    {
        [, $master] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        Sanctum::actingAs($master);

        $cash = ChartOfAccount::where('code', '1110')->firstOrFail();
        $revenue = ChartOfAccount::where('code', '4110')->firstOrFail();

        $this->postJson('/api/journals', [
            'transaction_date' => '2026-10-10',
            'reference_number' => 'JV-TC002-BAD',
            'description' => 'Jurnal timpang',
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => '10.50', 'credit' => '0.00'],
                ['chart_of_account_id' => $revenue->id, 'debit' => '0.00', 'credit' => '10.49'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('lines');

        $this->assertSame(0, JournalHeader::count());
    }

    public function test_tc007_only_master_can_post_api_journals(): void
    {
        [, $master, $branchAdmin, $cashier] = $this->branchWithRoles();
        $this->seedRetailAccounts();

        $payload = [
            'transaction_date' => '2026-10-10',
            'reference_number' => 'JV-TC007',
            'description' => 'Jurnal hak akses',
            'lines' => [
                ['chart_of_account_id' => ChartOfAccount::where('code', '1110')->value('id'), 'debit' => '25.00', 'credit' => '0.00'],
                ['chart_of_account_id' => ChartOfAccount::where('code', '4110')->value('id'), 'debit' => '0.00', 'credit' => '25.00'],
            ],
        ];

        $this->postJson('/api/journals', $payload)->assertUnauthorized();

        Sanctum::actingAs($cashier);
        $this->postJson('/api/journals', $payload)->assertForbidden();

        Sanctum::actingAs($branchAdmin);
        $this->postJson('/api/journals', $payload)->assertForbidden();

        Sanctum::actingAs($master);
        $this->postJson('/api/journals', $payload)->assertCreated();

        $journalId = JournalHeader::query()->value('id');
        $lines = JournalLine::where('journal_header_id', $journalId)->get();
        $this->assertMoneySame($lines->sum('debit'), $lines->sum('credit'));
        $this->assertMoneySame($lines->sum('debit'), '25.00');

        // TODO TC007: admin cabang masih bisa membuat jurnal manual lewat
        // POST /backoffice/finance/journals. Belum ada rute ubah, hapus,
        // atau pembatalan jurnal yang dikunci ke master.
    }
}
