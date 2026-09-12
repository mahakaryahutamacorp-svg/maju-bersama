<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class BranchAccessSeeder extends Seeder
{
    public function run(): void
    {
        $centralBranch = Branch::query()->whereNull('parent_id')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@pusat.test'],
            [
                'branch_id' => $centralBranch->id,
                'name' => 'Admin Pusat',
                'password' => 'password',
                'role' => 'admin',
            ],
        );

        User::updateOrCreate(
            ['email' => 'master@majubersama.test'],
            [
                'branch_id' => $centralBranch->id,
                'name' => 'Master Backoffice',
                'password' => 'password',
                'role' => 'master',
            ],
        );

        foreach ($centralBranch->children()->orderBy('id')->get() as $index => $branch) {
            User::updateOrCreate(
                ['email' => 'admin'.($index + 1).'@majubersama.test'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Admin Majubersama '.($index + 1),
                    'password' => 'password',
                    'role' => 'manager',
                ],
            );
        }
    }
}