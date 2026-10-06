<?php

namespace Database\Seeders;

use App\Models\CashRegister;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with Production-Ready Master Data.
     * All transactional and inventory data will be at 0.
     */
    public function run(): void
    {
        // 1. Chart of Accounts (COA)
        $this->call(ChartOfAccountSeeder::class);

        // 2. Customer Groups
        $this->call(CustomerGroupSeeder::class);

        // 3. Branches: Central Branch (Pusat) & Child Branches
        $branchId = DB::table('branches')->insertGetId([
            'code' => 'PUSAT',
            'name' => 'majubersamapusat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 5) as $number) {
            DB::table('branches')->insertGetId([
                'parent_id' => $branchId,
                'code' => 'MAJUBERSAMA-'.$number,
                'name' => 'majubersama '.$number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Users & Roles (Master Admin, Central Admin, Branch Admin, Kasir)
        $this->call(BranchAccessSeeder::class);

        // 5. Warehouses (Gudang Utama Pusat & Gudang Cabang 1-5)
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

        // 6. Suppliers (Master Supplier Data)
        Supplier::create([
            'branch_id' => $branchId,
            'name' => 'Distributor Nasional Utama',
            'contact_person' => 'Budi Santoso',
            'phone' => '081234567890',
            'address' => 'Jl. Industri Pergudangan No. 1, Jakarta',
            'is_active' => true,
        ]);

        Supplier::create([
            'branch_id' => $branchId,
            'name' => 'PT Pupuk & Agro Nusantara',
            'contact_person' => 'Hendra Setiawan',
            'phone' => '081398765432',
            'address' => 'Kawasan Agro Industri Blok C-4, Surabaya',
            'is_active' => true,
        ]);

        // 7. Customers (Master Customer Data)
        $retailGroup = CustomerGroup::where('name', 'Umum/Retail')->first();
        $petaniGroup = CustomerGroup::where('name', 'Petani')->first();

        Customer::create([
            'branch_id' => $branchId,
            'customer_group_id' => $retailGroup?->id,
            'name' => 'Pelanggan Umum (Walk-in)',
            'phone' => '0800000000',
            'address' => 'Pelanggan Tunai Langsung',
        ]);

        foreach (range(1, 5) as $number) {
            Customer::create([
                'branch_id' => $number + 1,
                'customer_group_id' => $retailGroup?->id,
                'name' => 'Pelanggan Umum Cabang '.$number,
                'phone' => '080000000'.$number,
                'address' => 'Pelanggan Tunai Cabang '.$number,
            ]);

            Customer::create([
                'branch_id' => $number + 1,
                'customer_group_id' => $petaniGroup?->id,
                'name' => 'Kelompok Tani Makmur '.$number,
                'phone' => '081299900'.$number,
                'address' => 'Sentra Tani Wilayah Cabang '.$number,
            ]);
        }

        // 8. Cash Registers (Kasir Utama Pusat & Kasir Cabang 1-5)
        CashRegister::create([
            'branch_id' => $branchId,
            'name' => 'Kasir Utama Pusat',
            'is_active' => true,
        ]);

        foreach (range(1, 5) as $number) {
            CashRegister::create([
                'branch_id' => $number + 1,
                'name' => 'Kasir Cabang '.$number,
                'is_active' => true,
            ]);
        }

        // 9. Expense Categories
        $expenseCoa = ChartOfAccount::where('code', '6100')->first()
            ?? ChartOfAccount::where('code', '5110')->first();
        $utilityCoa = ChartOfAccount::where('code', '6200')->first() ?? $expenseCoa;
        if ($expenseCoa) {
            ExpenseCategory::create([
                'branch_id' => $branchId,
                'chart_of_account_id' => $expenseCoa->id,
                'name' => 'Operasional Kantor',
                'is_active' => true,
            ]);
            ExpenseCategory::create([
                'branch_id' => $branchId,
                'chart_of_account_id' => $utilityCoa->id,
                'name' => 'Listrik, Air & Internet',
                'is_active' => true,
            ]);
        }

        // 10. Product Catalog (Stock = 0, Inventory = 0)
        $this->call(ProductSeeder::class);
    }
}
