<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Entity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChartOfAccountsV2Seeder extends Seeder
{
    public const DRAWER_HEADER_CODE = '1110';

    public const OPERATING_BANK_CODE = '1120001';

    public const RETIRED_DAILY_EXPENSE_CODE = '5110';

    /**
     * Urutan wajib parent sebelum child. Kolom: kode, nama, type, normal, postable, contra, cash_flow_tag, parent.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: bool, 6: string, 7: ?string}>
     */
    public const ACCOUNTS = [
        ['1000', 'ASET', 'asset', 'D', false, false, 'none', null],
        ['1100', 'Kas & Setara Kas', 'asset', 'D', false, false, 'none', '1000'],
        ['1110', 'Kas Laci Kasir (header)', 'asset', 'D', false, false, 'cash', '1100'],
        ['1111', 'Kas Kecil', 'asset', 'D', true, false, 'cash', '1100'],
        ['1112', 'Kas Besar / Brankas Pusat', 'asset', 'D', true, false, 'cash', '1100'],
        ['1115', 'Kas Dalam Perjalanan', 'asset', 'D', true, false, 'cash', '1100'],
        ['1120', 'Bank (header)', 'asset', 'D', false, false, 'cash', '1100'],
        ['1120001', 'Bank – Rekening Operasional', 'asset', 'D', true, false, 'cash', '1120'],
        ['1125', 'Piutang EDC/QRIS/Transfer (Clearing)', 'asset', 'D', true, false, 'none', '1100'],
        ['1130', 'Piutang Usaha', 'asset', 'D', true, false, 'none', '1000'],
        ['1131', 'Cadangan Kerugian Piutang', 'asset', 'K', true, true, 'none', '1000'],
        ['1135', 'Piutang Karyawan / Kasbon', 'asset', 'D', true, false, 'none', '1000'],
        ['1140', 'Uang Muka Pembelian', 'asset', 'D', true, false, 'none', '1000'],
        ['1150', 'PPN Masukan', 'asset', 'D', true, false, 'none', '1000'],
        ['1199', 'Selisih Kas Belum Diselesaikan (Suspense)', 'asset', 'D', true, false, 'none', '1000'],
        ['1210', 'Persediaan Barang Dagang', 'asset', 'D', true, false, 'none', '1000'],
        ['1215', 'Persediaan Dalam Perjalanan', 'asset', 'D', true, false, 'none', '1000'],
        ['1290', 'Rekening Antar Cabang', 'asset', 'D', true, false, 'none', '1000'],
        ['1295', 'Piutang Antar Entitas (Due from Entity)', 'asset', 'D', true, false, 'none', '1000'],
        ['1310', 'Aset Tetap', 'asset', 'D', true, false, 'investing', '1000'],
        ['1320', 'Akumulasi Penyusutan', 'asset', 'K', true, true, 'none', '1000'],
        ['2000', 'LIABILITAS', 'liability', 'K', false, false, 'none', null],
        ['2110', 'Utang Dagang', 'liability', 'K', true, false, 'none', '2000'],
        ['2120', 'Utang Biaya / Akrual', 'liability', 'K', true, false, 'none', '2000'],
        ['2130', 'PPN Keluaran', 'liability', 'K', true, false, 'none', '2000'],
        ['2140', 'Uang Muka / Deposit Pelanggan', 'liability', 'K', true, false, 'none', '2000'],
        ['2150', 'Utang Refund Pelanggan', 'liability', 'K', true, false, 'none', '2000'],
        ['2210', 'Utang Bank Jangka Panjang', 'liability', 'K', true, false, 'financing', '2000'],
        ['2295', 'Utang Antar Entitas (Due to Entity)', 'liability', 'K', true, false, 'none', '2000'],
        ['3000', 'EKUITAS', 'equity', 'K', false, false, 'none', null],
        ['3110', 'Modal Disetor', 'equity', 'K', true, false, 'financing', '3000'],
        ['3120', 'Prive', 'equity', 'D', true, false, 'financing', '3000'],
        ['3210', 'Laba Ditahan', 'equity', 'K', true, false, 'none', '3000'],
        ['3220', 'Laba Tahun Berjalan', 'equity', 'K', false, false, 'none', '3000'],
        ['3900', 'Ekuitas Saldo Awal', 'equity', 'K', true, false, 'none', '3000'],
        ['4000', 'PENDAPATAN', 'revenue', 'K', false, false, 'none', null],
        ['4110', 'Penjualan Barang Dagang', 'revenue', 'K', true, false, 'operating', '4000'],
        ['4120', 'Pendapatan Lain-lain', 'revenue', 'K', true, false, 'operating', '4000'],
        ['4130', 'Potongan Penjualan', 'revenue', 'D', true, true, 'operating', '4000'],
        ['4140', 'Retur Penjualan', 'revenue', 'D', true, true, 'operating', '4000'],
        ['4150', 'Pendapatan Selisih Kas Lebih', 'revenue', 'K', true, false, 'operating', '4000'],
        ['5000', 'HARGA POKOK PENJUALAN', 'expense', 'D', false, false, 'none', null],
        ['5100', 'HPP Barang Dagang', 'expense', 'D', true, false, 'operating', '5000'],
        ['5120', 'Selisih / Penyusutan Persediaan', 'expense', 'D', true, false, 'operating', '5000'],
        ['5130', 'Ongkos Angkut Pembelian', 'expense', 'D', true, false, 'operating', '5000'],
        ['6000', 'BEBAN OPERASIONAL', 'expense', 'D', false, false, 'none', null],
        ['6100', 'Beban Operasional Umum', 'expense', 'D', true, false, 'operating', '6000'],
        ['6110', 'Biaya Harian Toko', 'expense', 'D', true, false, 'operating', '6000'],
        ['6200', 'Beban Listrik, Air & Internet', 'expense', 'D', true, false, 'operating', '6000'],
        ['6300', 'Beban Gaji & Upah', 'expense', 'D', true, false, 'operating', '6000'],
        ['6400', 'Beban Sewa', 'expense', 'D', true, false, 'operating', '6000'],
        ['6500', 'Beban Penyusutan', 'expense', 'D', true, false, 'none', '6000'],
        ['6600', 'Beban Transportasi & Pengiriman', 'expense', 'D', true, false, 'operating', '6000'],
        ['6700', 'Beban Administrasi & Bank', 'expense', 'D', true, false, 'operating', '6000'],
        ['6810', 'Beban Selisih Kas Kurang', 'expense', 'D', true, false, 'operating', '6000'],
        ['6820', 'Beban Kerugian Piutang', 'expense', 'D', true, false, 'operating', '6000'],
        ['6900', 'Beban Lain-lain', 'expense', 'D', true, false, 'operating', '6000'],
    ];

    public function run(): void
    {
        $entities = Entity::query()->orderBy('id')->get();
        $legacyOwner = $entities->firstWhere('code', Entity::CODE_MB_PUPUK)
            ?? throw new RuntimeException('Entitas '.Entity::CODE_MB_PUPUK.' belum ada. Jalankan EntitySeeder terlebih dahulu.');

        DB::transaction(function () use ($entities, $legacyOwner) {
            $this->assignLegacyAccounts($legacyOwner);

            foreach ($entities as $entity) {
                $this->seedStandardAccounts($entity);
                $this->retireDailyExpenseAccount($entity);
            }

            $this->seedDrawerAccounts();
        });
    }

    private function assignLegacyAccounts(Entity $owner): void
    {
        $legacyAccounts = ChartOfAccount::withTrashed()->whereNull('entity_id')->orderBy('id')->get(['id', 'code']);

        foreach ($legacyAccounts as $account) {
            $conflict = ChartOfAccount::withTrashed()
                ->where('entity_id', $owner->id)
                ->where('code', $account->code)
                ->exists();

            if ($conflict) {
                $this->command?->warn("Akun lama {$account->code} (id {$account->id}) tidak di-assign: kode sudah ada di {$owner->code}.");

                continue;
            }

            DB::table('chart_of_accounts')->where('id', $account->id)->update(['entity_id' => $owner->id]);
        }
    }

    private function seedStandardAccounts(Entity $entity): void
    {
        $seeded = [];

        foreach (self::ACCOUNTS as [$code, $name, $type, $normalBalance, $isPostable, $isContra, $cashFlowTag, $parentCode]) {
            $parent = $parentCode === null ? null : $seeded[$parentCode];

            $account = ChartOfAccount::withTrashed()->updateOrCreate(
                ['entity_id' => $entity->id, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => $normalBalance,
                    'is_postable' => $isPostable,
                    'is_contra' => $isContra,
                    'is_system' => true,
                    'cash_flow_tag' => $cashFlowTag,
                    'parent_category_id' => $parent?->id,
                    'level' => $parent === null ? 1 : $parent->level + 1,
                    'is_active' => true,
                ],
            );

            if ($account->trashed()) {
                $this->command?->warn("Akun {$entity->code}/{$code} berstatus terhapus (soft delete); dibiarkan terhapus.");
            }

            $seeded[$code] = $account;
        }
    }

    private function retireDailyExpenseAccount(Entity $entity): void
    {
        ChartOfAccount::withTrashed()
            ->where('entity_id', $entity->id)
            ->where('code', self::RETIRED_DAILY_EXPENSE_CODE)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Satu akun kas per laci aktif. Kode tidak pernah diganti setelah dibuat;
     * pemanggilan ulang hanya menyegarkan nama.
     */
    private function seedDrawerAccounts(): void
    {
        $registers = DB::table('cash_registers')
            ->join('branches', 'branches.id', '=', 'cash_registers.branch_id')
            ->where('cash_registers.is_active', true)
            ->orderBy('cash_registers.id')
            ->get([
                'cash_registers.id',
                'cash_registers.name',
                'cash_registers.branch_id',
                'branches.name as branch_name',
                'branches.entity_id',
            ]);

        foreach ($registers as $register) {
            $name = "Kas Laci {$register->branch_name} – {$register->name}";
            $existing = ChartOfAccount::withTrashed()->where('cash_register_id', $register->id)->first();

            if ($existing !== null) {
                if ($existing->name !== $name) {
                    $existing->update(['name' => $name]);
                }

                continue;
            }

            if ($register->entity_id === null) {
                $this->command?->warn("Laci {$register->name} (id {$register->id}) dilewati: cabangnya belum punya entity.");

                continue;
            }

            $header = ChartOfAccount::query()
                ->where('entity_id', $register->entity_id)
                ->where('code', self::DRAWER_HEADER_CODE)
                ->firstOrFail();

            ChartOfAccount::create([
                'entity_id' => $register->entity_id,
                'code' => $this->nextDrawerCode((int) $register->entity_id),
                'name' => $name,
                'type' => 'asset',
                'normal_balance' => 'D',
                'is_postable' => true,
                'is_contra' => false,
                'is_system' => true,
                'branch_id' => $register->branch_id,
                'cash_register_id' => $register->id,
                'cash_flow_tag' => 'cash',
                'parent_category_id' => $header->id,
                'level' => $header->level + 1,
                'is_active' => true,
            ]);
        }
    }

    private function nextDrawerCode(int $entityId): string
    {
        $lastSequence = ChartOfAccount::withTrashed()
            ->where('entity_id', $entityId)
            ->where('code', 'like', self::DRAWER_HEADER_CODE.'___')
            ->pluck('code')
            ->filter(fn (string $code) => strlen($code) === 7 && ctype_digit($code))
            ->map(fn (string $code) => (int) substr($code, 4))
            ->max() ?? 0;

        if ($lastSequence >= 999) {
            throw new RuntimeException("Nomor akun laci untuk entity {$entityId} sudah habis (1110999).");
        }

        return self::DRAWER_HEADER_CODE.str_pad((string) ($lastSequence + 1), 3, '0', STR_PAD_LEFT);
    }
}
