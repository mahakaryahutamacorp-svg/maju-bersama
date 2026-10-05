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

        // 1. Master Admin
        User::updateOrCreate(
            ['email' => 'admin@pusat.test'],
            [
                'branch_id' => $centralBranch->id,
                'name' => 'Admin Pusat',
                'password' => 'password',
                'role' => 'master',
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'master@majubersama.test'],
            [
                'branch_id' => $centralBranch->id,
                'name' => 'Master Backoffice',
                'password' => 'password',
                'role' => 'master',
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'branch_id' => $centralBranch->id,
                'name' => 'Superadmin Master',
                'password' => 'password',
                'role' => 'master',
                'is_active' => true,
            ],
        );

        // 2. Central Admin
        User::updateOrCreate(
            ['email' => 'central@majubersama.test'],
            [
                'branch_id' => $centralBranch->id,
                'name' => 'Central Admin Pusat',
                'password' => 'password',
                'role' => 'central_admin',
                'is_active' => true,
            ],
        );

        // 3. Kasir Pusat
        User::updateOrCreate(
            ['email' => 'kasir.pusat@majubersama.test'],
            [
                'branch_id' => $centralBranch->id,
                'name' => 'Kasir Utama Pusat',
                'password' => 'password',
                'role' => 'cashier',
                'is_active' => true,
            ],
        );

        // 4. Branch Admins & Kasir Cabang (Branches 1 to 5)
        foreach ($centralBranch->children()->orderBy('id')->get() as $index => $branch) {
            $branchNum = $index + 1;

            // Branch Admin
            User::updateOrCreate(
                ['email' => 'admin'.$branchNum.'@majubersama.test'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Admin Majubersama '.$branchNum,
                    'password' => 'password',
                    'role' => 'manager',
                    'is_active' => true,
                ],
            );

            // Kasir Cabang
            User::updateOrCreate(
                ['email' => 'kasir'.$branchNum.'@majubersama.test'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Kasir Majubersama '.$branchNum,
                    'password' => 'password',
                    'role' => 'cashier',
                    'is_active' => true,
                ],
            );
        }
    }
}
