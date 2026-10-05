<?php

namespace Database\Seeders;

use App\Models\CashRegister;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ChartOfAccountSeeder::class);
        $this->call(CustomerGroupSeeder::class);

        $branchId = DB::table('branches')->insertGetId([
            'code' => 'PUSAT',
            'name' => 'majubersamapusat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 5) as $number) {
            $childBranchId = DB::table('branches')->insertGetId([
                'parent_id' => $branchId,
                'code' => 'MAJUBERSAMA-'.$number,
                'name' => 'majubersama '.$number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            User::factory()->create([
                'branch_id' => $childBranchId,
                'name' => 'Admin Majubersama '.$number,
                'email' => 'admin'.$number.'@majubersama.test',
                'password' => 'password',
                'role' => 'manager',
            ]);
        }

        User::factory()->create([
            'branch_id' => $branchId,
            'name' => 'Admin Pusat',
            'email' => 'admin@pusat.test',
            'role' => 'master',
        ]);

        // Seed Gudang Utama for Central Branch and child branches
        Warehouse::create([
            'branch_id' => $branchId,
            'name' => 'Gudang Utama',
            'code' => 'GUD-PUSAT',
            'is_active' => true,
        ]);

        foreach (range(1, 5) as $number) {
            Warehouse::create([
                'branch_id' => $number + 1,
                'name' => 'Gudang Cabang '.$number,
                'code' => 'GUD-CAB-'.$number,
                'is_active' => true,
            ]);
        }

        // Seed Supplier for Central Branch (for PO & Goods Receipt)
        Supplier::create([
            'branch_id' => $branchId,
            'name' => 'Distributor Nasional Utama',
            'contact_person' => 'Budi Santoso',
            'phone' => '081234567890',
            'address' => 'Jl. Industri Pergudangan No. 1, Jakarta',
            'is_active' => true,
        ]);

        // Seed Cash Registers
        CashRegister::create([
            'branch_id' => $branchId,
            'name' => 'Kasir Utama Pusat',
            'is_active' => true,
        ]);

        // Seed Expense Categories
        $expenseCoa = ChartOfAccount::where('code', '6100')->first()
            ?? ChartOfAccount::where('code', '5110')->first();
        if ($expenseCoa) {
            ExpenseCategory::create([
                'branch_id' => $branchId,
                'chart_of_account_id' => $expenseCoa->id,
                'name' => 'Operasional Kantor',
                'is_active' => true,
            ]);
            ExpenseCategory::create([
                'branch_id' => $branchId,
                'chart_of_account_id' => $expenseCoa->id,
                'name' => 'Listrik, Air & Internet',
                'is_active' => true,
            ]);
        }

        $this->call(BranchAccessSeeder::class);
        $this->call(ProductSeeder::class);
    }
}
