<?php

namespace Tests\Feature\Ledger;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\ChartOfAccount;
use App\Models\Entity;
use App\Models\User;
use Database\Seeders\AccountMappingSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\ChartOfAccountsV2Seeder;
use Database\Seeders\EntitySeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class LedgerTestCase extends TestCase
{
    protected Branch $pupukBranch;

    protected Branch $dekaBranch;

    protected CashRegister $pupukRegister;

    protected CashRegister $dekaRegister;

    protected User $user;

    protected function seedLedgerStructure(): void
    {
        $this->pupukBranch = Branch::create(['code' => 'CBG-ABI', 'name' => 'TOKO MAJU BERSAMA ABI']);
        $this->dekaBranch = Branch::create(['code' => 'PAKAN', 'name' => 'CV.DEKA AGRO MANDIRI']);
        $this->pupukRegister = CashRegister::create(['branch_id' => $this->pupukBranch->id, 'name' => 'Kasir Utama', 'is_active' => true]);
        $this->dekaRegister = CashRegister::create(['branch_id' => $this->dekaBranch->id, 'name' => 'Kasir Utama', 'is_active' => true]);
        $this->user = User::factory()->create(['branch_id' => $this->pupukBranch->id]);

        $this->seed([
            ChartOfAccountSeeder::class,
            EntitySeeder::class,
            ChartOfAccountsV2Seeder::class,
            AccountMappingSeeder::class,
        ]);

        $this->pupukBranch->refresh();
        $this->dekaBranch->refresh();
    }

    protected function skipUnlessMysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Trigger dan CHECK constraint ledger hanya ada di MySQL.');
        }
    }

    protected function entityId(string $code): int
    {
        return Entity::where('code', $code)->value('id');
    }

    protected function account(string $entityCode, string $code): ChartOfAccount
    {
        return ChartOfAccount::where('entity_id', $this->entityId($entityCode))->where('code', $code)->firstOrFail();
    }

    protected function drawerAccount(CashRegister $register): ChartOfAccount
    {
        return ChartOfAccount::where('cash_register_id', $register->id)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createEntry(Branch $branch, array $attributes = []): int
    {
        static $sequence = 0;
        $sequence++;

        return DB::table('journal_entries')->insertGetId($attributes + [
            'entry_number' => 'JE-TEST-'.$sequence,
            'entity_id' => $branch->entity_id,
            'branch_id' => $branch->id,
            'entry_date' => '2026-12-05',
            'status' => 'draft',
            'source_type' => 'test',
            'source_id' => $sequence,
            'description' => 'Jurnal uji',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function addItem(int $entryId, Branch $branch, ChartOfAccount $account, float $debit, float $credit, ?int $entityId = null): int
    {
        $lineNo = DB::table('journal_items')->where('journal_entry_id', $entryId)->max('line_no') + 1;

        return DB::table('journal_items')->insertGetId([
            'journal_entry_id' => $entryId,
            'entity_id' => $entityId ?? $branch->entity_id,
            'line_no' => $lineNo,
            'account_id' => $account->id,
            'branch_id' => $branch->id,
            'debit' => $debit,
            'credit' => $credit,
            'created_at' => now(),
        ]);
    }

    protected function postEntry(int $entryId): void
    {
        DB::table('journal_entries')->where('id', $entryId)->update(['status' => 'posted', 'posted_by' => $this->user->id]);
    }

    /**
     * Jurnal seimbang 2 baris (kas laci D / penjualan K) yang sudah posted.
     */
    protected function createPostedEntry(Branch $branch, CashRegister $register, float $amount = 100000): int
    {
        $entityCode = $branch->entity_id === $this->entityId(Entity::CODE_DEKA) ? Entity::CODE_DEKA : Entity::CODE_MB_PUPUK;
        $entryId = $this->createEntry($branch);
        $this->addItem($entryId, $branch, $this->drawerAccount($register), $amount, 0);
        $this->addItem($entryId, $branch, $this->account($entityCode, '4110'), 0, $amount);
        $this->postEntry($entryId);

        return $entryId;
    }
}
