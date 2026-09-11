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
        ChartOfAccount::insert([
            ['code' => '1110', 'name' => 'Kas', 'type' => 'asset'],
            ['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset'],
            ['code' => '3110', 'name' => 'Modal', 'type' => 'equity'],
            ['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue'],
            ['code' => '5110', 'name' => 'Biaya Harian', 'type' => 'expense'],
        ]);
    }
}
