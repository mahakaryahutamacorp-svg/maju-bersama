<?php

namespace Tests\Feature\Api\Concerns;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\User;

trait PreparesLedger
{
    /**
     * @return array{0: Branch, 1: User, 2: User, 3: User}
     */
    protected function branchWithRoles(): array
    {
        $branch = Branch::create([
            'code' => 'PUSAT',
            'name' => 'Pusat',
        ]);

        $master = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'master',
            'password' => 'password',
        ]);

        $branchAdmin = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'branch_admin',
            'password' => 'password',
        ]);

        $cashier = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'kasir',
            'password' => 'password',
        ]);

        return [$branch, $master, $branchAdmin, $cashier];
    }

    protected function seedRetailAccounts(): void
    {
        ChartOfAccount::insert([
            ['code' => '1110', 'name' => 'Kas', 'type' => 'asset', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1130', 'name' => 'Piutang Usaha', 'type' => 'asset', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '2110', 'name' => 'Hutang Dagang', 'type' => 'liability', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '4110', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '4130', 'name' => 'Potongan Penjualan', 'type' => 'revenue', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function sellableProduct(Branch $branch, int $stock = 5, string $sellingPrice = '110000.00', string $purchasePrice = '100000.00'): Product
    {
        $category = Category::create(['name' => 'Pupuk']);

        return Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'SKU-'.uniqid(),
            'name' => 'Produk Uji',
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'stock' => $stock,
        ]);
    }
}
