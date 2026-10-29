<?php

namespace Tests\Feature\Ledger;

use App\Models\ChartOfAccount;
use App\Models\Entity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

#[Group('mysql')]
class LedgerTriggerTest extends LedgerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipUnlessMysql();
        $this->seedLedgerStructure();
    }

    public function test_balanced_entry_can_be_posted_and_totals_are_set_by_trigger(): void
    {
        $entryId = $this->createPostedEntry($this->pupukBranch, $this->pupukRegister, 150000);

        $entry = DB::table('journal_entries')->find($entryId);
        $this->assertSame('posted', $entry->status);
        $this->assertSame('150000.00', $entry->total_debit);
        $this->assertSame('150000.00', $entry->total_credit);
        $this->assertNotNull($entry->posted_at);
    }

    public function test_unbalanced_entry_cannot_be_posted(): void
    {
        $entryId = $this->createEntry($this->pupukBranch);
        $this->addItem($entryId, $this->pupukBranch, $this->drawerAccount($this->pupukRegister), 100000, 0);
        $this->addItem($entryId, $this->pupukBranch, $this->account(Entity::CODE_MB_PUPUK, '4110'), 0, 90000);

        $this->assertRejected(fn () => $this->postEntry($entryId), 'tidak seimbang');
    }

    public function test_entry_with_a_single_line_cannot_be_posted(): void
    {
        $entryId = $this->createEntry($this->pupukBranch);
        $this->addItem($entryId, $this->pupukBranch, $this->drawerAccount($this->pupukRegister), 100000, 0);

        $this->assertRejected(fn () => $this->postEntry($entryId), 'tidak seimbang');
    }

    public function test_items_of_a_posted_entry_cannot_be_updated(): void
    {
        $entryId = $this->createPostedEntry($this->pupukBranch, $this->pupukRegister);

        $this->assertRejected(
            fn () => DB::table('journal_items')->where('journal_entry_id', $entryId)->where('line_no', 1)->update(['debit' => 1]),
            'immutable',
        );
    }

    public function test_items_of_a_posted_entry_cannot_be_deleted(): void
    {
        $entryId = $this->createPostedEntry($this->pupukBranch, $this->pupukRegister);

        $this->assertRejected(fn () => DB::table('journal_items')->where('journal_entry_id', $entryId)->delete(), 'immutable');
    }

    public function test_items_cannot_be_added_to_a_posted_entry(): void
    {
        $entryId = $this->createPostedEntry($this->pupukBranch, $this->pupukRegister);

        $this->assertRejected(
            fn () => $this->addItem($entryId, $this->pupukBranch, $this->account(Entity::CODE_MB_PUPUK, '6100'), 1000, 0),
            'sudah posted',
        );
    }

    public function test_entry_date_of_a_posted_entry_cannot_be_changed(): void
    {
        $entryId = $this->createPostedEntry($this->pupukBranch, $this->pupukRegister);

        $this->assertRejected(
            fn () => DB::table('journal_entries')->where('id', $entryId)->update(['entry_date' => '2026-12-06']),
            'immutable',
        );
    }

    public function test_posted_entry_cannot_be_deleted(): void
    {
        $entryId = $this->createPostedEntry($this->pupukBranch, $this->pupukRegister);

        $this->assertRejected(fn () => DB::table('journal_entries')->where('id', $entryId)->delete(), 'gunakan reversal');
    }

    public function test_posted_entry_can_be_reversed_once_and_reversal_is_final(): void
    {
        $originalId = $this->createPostedEntry($this->pupukBranch, $this->pupukRegister);

        $reversalId = $this->createEntry($this->pupukBranch, ['reversal_of_id' => $originalId, 'source_event' => 'reverse']);
        $this->addItem($reversalId, $this->pupukBranch, $this->account(Entity::CODE_MB_PUPUK, '4110'), 100000, 0);
        $this->addItem($reversalId, $this->pupukBranch, $this->drawerAccount($this->pupukRegister), 0, 100000);
        $this->postEntry($reversalId);

        DB::table('journal_entries')->where('id', $originalId)->update(['status' => 'reversed', 'reversed_by_id' => $reversalId]);

        $this->assertSame('reversed', DB::table('journal_entries')->where('id', $originalId)->value('status'));
        $this->assertRejected(
            fn () => DB::table('journal_entries')->where('id', $originalId)->update(['description' => 'ubah']),
            'final',
        );
    }

    public function test_posted_entry_cannot_be_reversed_without_reversal_reference(): void
    {
        $entryId = $this->createPostedEntry($this->pupukBranch, $this->pupukRegister);

        $this->assertRejected(
            fn () => DB::table('journal_entries')->where('id', $entryId)->update(['status' => 'reversed']),
            'immutable',
        );
    }

    public function test_item_using_an_account_of_another_entity_is_rejected(): void
    {
        $entryId = $this->createEntry($this->pupukBranch);

        $this->assertRejected(
            fn () => $this->addItem($entryId, $this->pupukBranch, $this->account(Entity::CODE_DEKA, '4110'), 0, 100000),
            'entitas tidak konsisten',
        );
    }

    public function test_entry_whose_branch_belongs_to_another_entity_is_rejected(): void
    {
        $this->assertRejected(
            fn () => $this->createEntry($this->pupukBranch, ['entity_id' => $this->entityId(Entity::CODE_DEKA)]),
            'branch bukan milik entity',
        );
    }

    public function test_new_entry_must_start_as_draft(): void
    {
        $this->assertRejected(fn () => $this->createEntry($this->pupukBranch, ['status' => 'posted']), 'wajib berstatus draft');
    }

    public function test_posting_into_a_closed_period_is_rejected(): void
    {
        DB::table('accounting_periods')->insert([
            'entity_id' => $this->pupukBranch->entity_id,
            'branch_id' => null,
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-31',
            'status' => 'closed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertRejected(
            fn () => $this->createPostedEntry($this->pupukBranch, $this->pupukRegister),
            'periode sudah ditutup',
        );
    }

    public function test_posting_to_a_header_account_is_rejected(): void
    {
        $entryId = $this->createEntry($this->pupukBranch);

        $this->assertRejected(
            fn () => $this->addItem($entryId, $this->pupukBranch, $this->account(Entity::CODE_MB_PUPUK, '1110'), 100000, 0),
            'akun header',
        );
    }

    public function test_branch_entity_cannot_change_after_posting(): void
    {
        $this->createPostedEntry($this->pupukBranch, $this->pupukRegister);

        $this->assertRejected(
            fn () => DB::table('branches')->where('id', $this->pupukBranch->id)->update(['entity_id' => $this->entityId(Entity::CODE_DEKA)]),
            'entity_id tidak boleh diubah',
        );
    }

    public function test_account_branch_must_belong_to_account_entity(): void
    {
        $this->assertRejected(
            fn () => ChartOfAccount::create([
                'entity_id' => $this->entityId(Entity::CODE_MB_PUPUK),
                'code' => '1110999',
                'name' => 'Laci salah entitas',
                'type' => 'asset',
                'branch_id' => $this->dekaBranch->id,
            ]),
            'branch_id harus milik entity yang sama',
        );
    }

    private function assertRejected(callable $statement, string $message): void
    {
        try {
            DB::transaction(fn () => $statement());
        } catch (QueryException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }

        $this->fail("Statement seharusnya ditolak dengan pesan: {$message}");
    }
}
