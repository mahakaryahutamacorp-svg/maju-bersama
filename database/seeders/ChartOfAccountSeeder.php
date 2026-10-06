<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Production-ready standard Chart of Accounts (Bagan Akun Standar).
     * All balances start at Rp 0 — balances are derived exclusively from
     * journal lines, and no journals are seeded.
     *
     * IMPORTANT: Codes 1110, 1120, 1130, 1210, 2110, 3110, 4110, 4120, 4130,
     * 5100, 5120 and 6100 are hard-referenced by the posting services
     * (SalePostingService, GoodsReceiptService, StockAdjustmentService, etc.).
     * Do NOT renumber them.
     */
    public function run(): void
    {
        $accounts = [
            // 1. ASET (Aktiva)
            ['code' => '1110', 'name' => 'Kas', 'type' => 'asset'],
            ['code' => '1120', 'name' => 'Bank', 'type' => 'asset'],
            ['code' => '1130', 'name' => 'Piutang Usaha', 'type' => 'asset'],
            ['code' => '1140', 'name' => 'Uang Muka Pembelian', 'type' => 'asset'],
            ['code' => '1150', 'name' => 'PPN Masukan', 'type' => 'asset'],
            ['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset'],
            ['code' => '1310', 'name' => 'Aktiva Tetap', 'type' => 'asset'],
            ['code' => '1320', 'name' => 'Akumulasi Penyusutan', 'type' => 'asset'],

            // 2. KEWAJIBAN (Liabilities)
            ['code' => '2110', 'name' => 'Hutang Dagang', 'type' => 'liability'],
            ['code' => '2120', 'name' => 'Hutang Biaya', 'type' => 'liability'],
            ['code' => '2130', 'name' => 'PPN Keluaran', 'type' => 'liability'],
            ['code' => '2210', 'name' => 'Hutang Bank Jangka Panjang', 'type' => 'liability'],

            // 3. EKUITAS (Modal)
            ['code' => '3110', 'name' => 'Modal', 'type' => 'equity'],
            ['code' => '3120', 'name' => 'Prive', 'type' => 'equity'],
            ['code' => '3210', 'name' => 'Laba Ditahan', 'type' => 'equity'],

            // 4. PENDAPATAN (Revenue)
            ['code' => '4110', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue'],
            ['code' => '4120', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue'],
            ['code' => '4130', 'name' => 'Potongan Penjualan', 'type' => 'revenue'],

            // 5. HARGA POKOK PENJUALAN & BEBAN PERSEDIAAN
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense'],
            ['code' => '5110', 'name' => 'Biaya Harian', 'type' => 'expense'],
            ['code' => '5120', 'name' => 'Beban Selisih Persediaan', 'type' => 'expense'],

            // 6. BEBAN OPERASIONAL
            ['code' => '6100', 'name' => 'Beban Operasional', 'type' => 'expense'],
            ['code' => '6200', 'name' => 'Beban Listrik, Air & Internet', 'type' => 'expense'],
            ['code' => '6300', 'name' => 'Beban Gaji & Upah', 'type' => 'expense'],
            ['code' => '6400', 'name' => 'Beban Sewa', 'type' => 'expense'],
            ['code' => '6500', 'name' => 'Beban Penyusutan', 'type' => 'expense'],
            ['code' => '6600', 'name' => 'Beban Transportasi & Pengiriman', 'type' => 'expense'],
            ['code' => '6700', 'name' => 'Beban Administrasi & Bank', 'type' => 'expense'],
            ['code' => '6900', 'name' => 'Beban Lain-lain', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(
                ['code' => $account['code']],
                $account + ['is_active' => true],
            );
        }
    }
}
