<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['code' => '1110', 'name' => 'Kas', 'type' => 'asset'],
            ['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset'],
            ['code' => '3110', 'name' => 'Modal', 'type' => 'equity'],
            ['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue'],
            // Cost of goods sold, required so that every POS sale can be recorded as a
            // four line entry (cash, revenue, COGS, inventory).
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense'],
            ['code' => '5110', 'name' => 'Biaya Harian', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(['code' => $account['code']], $account);
        }
    }
}
