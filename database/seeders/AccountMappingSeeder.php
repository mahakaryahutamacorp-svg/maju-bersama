<?php

namespace Database\Seeders;

use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\Entity;
use Illuminate\Database\Seeder;

class AccountMappingSeeder extends Seeder
{
    public const SALES_CASH_KEY = 'sales.cash';

    /**
     * @var array<string, string>
     */
    public const DEFAULT_MAPPINGS = [
        self::SALES_CASH_KEY => '1110',
        'sales.ar' => '1130',
        'sales.revenue' => '4110',
        'sales.discount' => '4130',
        'sales.return' => '4140',
        'sales.ppn_out' => '2130',
        'inventory' => '1210',
        'cogs' => '5100',
        'cash.shortage' => '6810',
        'cash.overage' => '4150',
        'cash.suspense' => '1199',
        'employee.receivable' => '1135',
        'interbranch' => '1290',
        'interco.due_from' => '1295',
        'interco.due_to' => '2295',
    ];

    public function run(): void
    {
        foreach (Entity::query()->orderBy('id')->get() as $entity) {
            foreach (self::DEFAULT_MAPPINGS as $key => $code) {
                $account = ChartOfAccount::query()
                    ->where('entity_id', $entity->id)
                    ->where('code', $code)
                    ->first();

                if ($account === null) {
                    $this->command?->warn("Mapping {$entity->code}/{$key} dilewati: akun {$code} belum ada. Jalankan ChartOfAccountsV2Seeder.");

                    continue;
                }

                AccountMapping::updateOrCreate(
                    ['entity_id' => $entity->id, 'key' => $key, 'branch_id' => null, 'cash_register_id' => null],
                    ['account_id' => $account->id],
                );
            }
        }

        $drawerAccounts = ChartOfAccount::query()
            ->whereNotNull('entity_id')
            ->whereNotNull('cash_register_id')
            ->orderBy('cash_register_id')
            ->get(['id', 'entity_id', 'branch_id', 'cash_register_id']);

        foreach ($drawerAccounts as $account) {
            AccountMapping::updateOrCreate(
                [
                    'entity_id' => $account->entity_id,
                    'key' => self::SALES_CASH_KEY,
                    'branch_id' => $account->branch_id,
                    'cash_register_id' => $account->cash_register_id,
                ],
                ['account_id' => $account->id],
            );
        }
    }
}
