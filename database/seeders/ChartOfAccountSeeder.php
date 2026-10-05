<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
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
            ['code' => '1120', 'name' => 'Bank', 'type' => 'asset'],
            ['code' => '1130', 'name' => 'Piutang Usaha', 'type' => 'asset'],
            ['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset'],
            ['code' => '1310', 'name' => 'Aktiva Tetap', 'type' => 'asset'],
            ['code' => '1320', 'name' => 'Akumulasi Penyusutan', 'type' => 'asset'],
            ['code' => '2110', 'name' => 'Hutang Dagang', 'type' => 'liability'],
            ['code' => '2120', 'name' => 'Hutang Biaya', 'type' => 'liability'],
            ['code' => '3110', 'name' => 'Modal', 'type' => 'equity'],
            ['code' => '3210', 'name' => 'Laba Ditahan', 'type' => 'equity'],
            ['code' => '4110', 'name' => 'Pendapatan', 'type' => 'revenue'],
            ['code' => '4120', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue'],
            ['code' => '4130', 'name' => 'Potongan Penjualan', 'type' => 'revenue'],
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense'],
            ['code' => '5110', 'name' => 'Biaya Harian', 'type' => 'expense'],
            ['code' => '5120', 'name' => 'Beban Selisih Persediaan', 'type' => 'expense'],
            ['code' => '6100', 'name' => 'Beban Operasional', 'type' => 'expense'],
            ['code' => '6200', 'name' => 'Beban Listrik, Air & Internet', 'type' => 'expense'],
            ['code' => '6300', 'name' => 'Beban Gaji & Upah', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(['code' => $account['code']], $account);
        }
    }
}
