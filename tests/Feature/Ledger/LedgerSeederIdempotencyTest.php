<?php

namespace Tests\Feature\Ledger;

use App\Enums\NormalBalance;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\ChartOfAccount;
use App\Models\Entity;
use Database\Seeders\AccountMappingSeeder;
use Database\Seeders\BusinessStructureSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\ChartOfAccountsV2Seeder;
use Database\Seeders\EntitySeeder;

class LedgerSeederIdempotencyTest extends LedgerTestCase
{
    private const LEDGER_SEEDERS = [
        EntitySeeder::class,
        ChartOfAccountsV2Seeder::class,
        AccountMappingSeeder::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ChartOfAccountSeeder::class, BusinessStructureSeeder::class]);

        foreach (['CBG-ABI', 'CBG-MAMA', 'PAKAN'] as $code) {
            CashRegister::create(['branch_id' => Branch::where('code', $code)->value('id'), 'name' => 'Kasir Utama', 'is_active' => true]);
        }
        CashRegister::create(['branch_id' => Branch::where('code', 'PAKAN')->value('id'), 'name' => 'Kasir Lama', 'is_active' => false]);
    }

    public function test_running_ledger_seeders_twice_produces_the_same_rows(): void
    {
        $this->seed(self::LEDGER_SEEDERS);
        $first = $this->snapshot();

        $this->seed(self::LEDGER_SEEDERS);

        $this->assertSame($first, $this->snapshot());
    }

    public function test_drawer_accounts_match_active_cash_registers_one_to_one(): void
    {
        $this->seed(self::LEDGER_SEEDERS);
        $this->seed(self::LEDGER_SEEDERS);

        $activeRegisterIds = CashRegister::where('is_active', true)->orderBy('id')->pluck('id')->all();
        $drawerRegisterIds = ChartOfAccount::whereNotNull('cash_register_id')->orderBy('cash_register_id')->pluck('cash_register_id')->all();
        $this->assertSame($activeRegisterIds, $drawerRegisterIds);

        $mbDrawers = ChartOfAccount::where('entity_id', $this->entityId(Entity::CODE_MB_PUPUK))->whereNotNull('cash_register_id')->orderBy('code')->pluck('code')->all();
        $dekaDrawers = ChartOfAccount::where('entity_id', $this->entityId(Entity::CODE_DEKA))->whereNotNull('cash_register_id')->pluck('code')->all();
        $this->assertSame(['1110001', '1110002'], $mbDrawers);
        $this->assertSame(['1110001'], $dekaDrawers);

        $drawer = ChartOfAccount::where('code', '1110001')->where('entity_id', $this->entityId(Entity::CODE_DEKA))->firstOrFail();
        $this->assertFalse($drawer->is_contra);
        $this->assertTrue($drawer->is_postable);
        $this->assertSame('Kas Laci CV.DEKA AGRO MANDIRI – Kasir Utama', $drawer->name);
        $this->assertSame($this->account(Entity::CODE_DEKA, '1110')->id, $drawer->parent_category_id);

        foreach ($activeRegisterIds as $registerId) {
            $this->assertTrue(AccountMapping::where('key', 'sales.cash')->where('cash_register_id', $registerId)->exists());
        }
    }

    public function test_renaming_a_register_keeps_the_drawer_account_code(): void
    {
        $this->seed(self::LEDGER_SEEDERS);
        $register = CashRegister::where('branch_id', Branch::where('code', 'CBG-ABI')->value('id'))->firstOrFail();
        $codeBefore = ChartOfAccount::where('cash_register_id', $register->id)->value('code');

        $register->update(['name' => 'Kasir Depan']);
        $this->seed(self::LEDGER_SEEDERS);

        $drawer = ChartOfAccount::where('cash_register_id', $register->id)->firstOrFail();
        $this->assertSame($codeBefore, $drawer->code);
        $this->assertSame('Kas Laci TOKO MAJU BERSAMA ABI – Kasir Depan', $drawer->name);
    }

    public function test_both_entities_have_the_required_ledger_accounts(): void
    {
        $this->seed(self::LEDGER_SEEDERS);

        foreach ([Entity::CODE_MB_PUPUK, Entity::CODE_DEKA] as $entityCode) {
            foreach (['1135', '1199', '4150', '6810', '1295', '2295', '1120001'] as $code) {
                $this->assertTrue($this->account($entityCode, $code)->is_postable, "{$entityCode}/{$code}");
            }

            foreach (['4130', '4140'] as $contraCode) {
                $account = $this->account($entityCode, $contraCode);
                $this->assertTrue($account->is_contra, "{$entityCode}/{$contraCode} contra");
                $this->assertSame(NormalBalance::Debit, $account->normal_balance, "{$entityCode}/{$contraCode} normal D");
            }

            $this->assertFalse($this->account($entityCode, '1110')->is_postable);
            $this->assertFalse($this->account($entityCode, '1120')->is_postable);
            $this->assertSame(15, AccountMapping::where('entity_id', $this->entityId($entityCode))->whereNull('cash_register_id')->count());
        }
    }

    public function test_branches_are_mapped_to_their_entities(): void
    {
        $this->seed(self::LEDGER_SEEDERS);

        $this->assertSame(Entity::CODE_DEKA, Branch::where('code', 'PAKAN')->firstOrFail()->entity->code);
        $this->assertSame(Entity::CODE_MB_PUPUK, Branch::where('code', 'CBG-ABI')->firstOrFail()->entity->code);
        $this->assertSame(Entity::CODE_MB_PUPUK, Branch::where('code', 'OFFICE-PUPUK')->firstOrFail()->entity->code);
    }

    public function test_legacy_accounts_are_kept_for_mb_pupuk_and_daily_expense_is_only_deactivated(): void
    {
        $legacyIds = ChartOfAccount::orderBy('id')->pluck('id', 'code')->all();

        $this->seed(self::LEDGER_SEEDERS);

        $mbPupukId = $this->entityId(Entity::CODE_MB_PUPUK);
        foreach ($legacyIds as $code => $id) {
            $account = ChartOfAccount::findOrFail($id);
            $this->assertSame($mbPupukId, $account->entity_id, "akun lama {$code}");
            $this->assertSame((string) $code, $account->code);
        }

        $dailyExpense = ChartOfAccount::findOrFail($legacyIds['5110']);
        $this->assertFalse($dailyExpense->is_active);
        $this->assertTrue($this->account(Entity::CODE_MB_PUPUK, '6110')->is_active);
    }

    public function test_legacy_coa_seeder_does_not_override_ledger_v2_accounts(): void
    {
        $this->seed(self::LEDGER_SEEDERS);
        $before = $this->snapshot();

        $this->seed(ChartOfAccountSeeder::class);

        $this->assertSame($before, $this->snapshot());
        $this->assertFalse(ChartOfAccount::where('code', '5110')->firstOrFail()->is_active);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'entities' => Entity::orderBy('id')->get(['id', 'code', 'legal_name'])->toArray(),
            'branches' => Branch::withTrashed()->orderBy('id')->pluck('entity_id', 'code')->all(),
            'accounts' => ChartOfAccount::withTrashed()->orderBy('id')
                ->get(['id', 'entity_id', 'code', 'name', 'type', 'is_active', 'parent_category_id', 'level', 'normal_balance', 'is_postable', 'is_contra', 'branch_id', 'cash_register_id', 'cash_flow_tag'])
                ->toArray(),
            'mappings' => AccountMapping::orderBy('id')->get(['id', 'entity_id', 'key', 'branch_id', 'cash_register_id', 'account_id'])->toArray(),
        ];
    }
}
