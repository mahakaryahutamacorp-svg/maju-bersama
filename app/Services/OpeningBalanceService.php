<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceService
{
    public const CODE_CASH = '1110';
    public const CODE_BANK = '1120';
    public const CODE_INVENTORY = '1210';
    public const CODE_PAYABLE = '2110';
    public const CODE_EQUITY = '3110';

    public function __construct(
        protected JournalPostingService $journalPostingService
    ) {}

    /**
     * Memeriksa apakah cabang sudah memiliki jurnal saldo awal.
     */
    public static function hasOpeningBalance(?int $branchId): bool
    {
        if (! $branchId) {
            return false;
        }

        return JournalHeader::where('branch_id', $branchId)
            ->where(function ($q) {
                $q->where('description', 'like', '%Saldo Awal%')
                  ->orWhere('reference_number', 'like', 'OB-%');
            })
            ->exists();
    }

    /**
     * Mendapatkan jurnal saldo awal yang telah dibukukan untuk cabang.
     */
    public static function getExistingOpeningBalance(?int $branchId): ?JournalHeader
    {
        if (! $branchId) {
            return null;
        }

        return JournalHeader::with(['journalLines.chartOfAccount'])
            ->where('branch_id', $branchId)
            ->where(function ($q) {
                $q->where('description', 'like', '%Saldo Awal%')
                  ->orWhere('reference_number', 'like', 'OB-%');
            })
            ->latest('id')
            ->first();
    }

    /**
     * Membukukan Saldo Awal (Opening Balance) perusahaan/cabang.
     *
     * Logika Akuntansi:
     * - Debit: Seluruh saldo aset (Kas Laci, Bank BCA, Persediaan Barang)
     * - Kredit: Seluruh saldo kewajiban (Hutang Supplier)
     * - Kredit (Penyeimbang): Ekuitas/Modal (Selisih Total Aset - Total Hutang)
     *
     * @param array{
     *     branch_id?: int|null,
     *     user_id?: int|null,
     *     transaction_date?: string|null,
     *     reference_number?: string|null,
     *     notes?: string|null,
     *     cash_in_drawer?: float|int|string|null,
     *     bank_bca?: float|int|string|null,
     *     inventory?: float|int|string|null,
     *     payable?: float|int|string|null,
     *     cash_account_id?: int|null,
     *     bank_account_id?: int|null,
     *     inventory_account_id?: int|null,
     *     payable_account_id?: int|null,
     *     equity_account_id?: int|null
     * } $data
     * @throws ValidationException
     */
    public function postOpeningBalance(array $data, ?User $actor = null): JournalHeader
    {
        return DB::transaction(function () use ($data, $actor): JournalHeader {
            $branchId = $data['branch_id'] 
                ?? $actor?->branch_id 
                ?? auth()->user()?->branch_id 
                ?? Branch::value('id');

            // Cek apakah cabang sudah pernah input saldo awal
            if (self::hasOpeningBalance($branchId) && empty($data['force'])) {
                throw ValidationException::withMessages([
                    'general' => ['Cabang ini sudah memiliki pencatatan Saldo Awal Sistem.'],
                ]);
            }

            $cashVal = max(0, (float) ($data['cash_in_drawer'] ?? 0));
            $bankVal = max(0, (float) ($data['bank_bca'] ?? 0));
            $invVal  = max(0, (float) ($data['inventory'] ?? 0));
            $payVal  = max(0, (float) ($data['payable'] ?? 0));

            $totalAssets = $cashVal + $bankVal + $invVal;
            $totalLiabilities = $payVal;

            if ($totalAssets <= 0 && $totalLiabilities <= 0) {
                throw ValidationException::withMessages([
                    'general' => ['Harap masukkan minimal satu saldo awal (Kas, Bank, Persediaan, atau Hutang) yang bernilai lebih dari 0.'],
                ]);
            }

            // Modal Bersih (Ekuitas) = Total Aset - Total Hutang
            $netEquity = $totalAssets - $totalLiabilities;

            // Resolusi akun-akun buku besar
            $cashAccount = ! empty($data['cash_account_id'])
                ? ChartOfAccount::findOrFail($data['cash_account_id'])
                : $this->resolveAccount(self::CODE_CASH, 'Kas Toko Utama', 'asset');

            $bankAccount = ! empty($data['bank_account_id'])
                ? ChartOfAccount::findOrFail($data['bank_account_id'])
                : $this->resolveAccount(self::CODE_BANK, 'Bank BCA Operasional', 'asset');

            $invAccount = ! empty($data['inventory_account_id'])
                ? ChartOfAccount::findOrFail($data['inventory_account_id'])
                : $this->resolveAccount(self::CODE_INVENTORY, 'Persediaan Barang', 'asset');

            $payAccount = ! empty($data['payable_account_id'])
                ? ChartOfAccount::findOrFail($data['payable_account_id'])
                : $this->resolveAccount(self::CODE_PAYABLE, 'Hutang Dagang', 'liability');

            $equityAccount = ! empty($data['equity_account_id'])
                ? ChartOfAccount::findOrFail($data['equity_account_id'])
                : $this->resolveAccount(self::CODE_EQUITY, 'Modal Awal', 'equity');

            $lines = [];

            // 1. Baris Debit: Semua input saldo aset
            if ($cashVal > 0) {
                $lines[] = [
                    'chart_of_account_id' => $cashAccount->id,
                    'debit'               => $cashVal,
                    'credit'              => 0,
                    'memo'                => 'Saldo awal kas laci / kasir',
                ];
            }

            if ($bankVal > 0) {
                $lines[] = [
                    'chart_of_account_id' => $bankAccount->id,
                    'debit'               => $bankVal,
                    'credit'              => 0,
                    'memo'                => 'Saldo awal rekening bank BCA',
                ];
            }

            if ($invVal > 0) {
                $lines[] = [
                    'chart_of_account_id' => $invAccount->id,
                    'debit'               => $invVal,
                    'credit'              => 0,
                    'memo'                => 'Saldo awal nilai persediaan barang dagang',
                ];
            }

            // 2. Baris Kredit: Semua input saldo kewajiban
            if ($payVal > 0) {
                $lines[] = [
                    'chart_of_account_id' => $payAccount->id,
                    'debit'               => 0,
                    'credit'              => $payVal,
                    'memo'                => 'Saldo awal hutang kepada supplier',
                ];
            }

            // 3. Baris Penyeimbang: Modal / Ekuitas
            if ($netEquity > 0) {
                $lines[] = [
                    'chart_of_account_id' => $equityAccount->id,
                    'debit'               => 0,
                    'credit'              => $netEquity,
                    'memo'                => 'Modal awal bersih (penyeimbang aset dan kewajiban)',
                ];
            } elseif ($netEquity < 0) {
                $lines[] = [
                    'chart_of_account_id' => $equityAccount->id,
                    'debit'               => abs($netEquity),
                    'credit'              => 0,
                    'memo'                => 'Defisit ekuitas saldo awal (kewajiban melebihi aset)',
                ];
            }

            $date = $data['transaction_date'] ?? now()->toDateString();
            $referenceNumber = ! empty($data['reference_number'])
                ? trim($data['reference_number'])
                : ('OB-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2))));

            $notes = ! empty($data['notes']) ? trim($data['notes']) : null;
            $description = 'Setor Saldo Awal Sistem' . ($notes ? " - {$notes}" : '');

            return $this->journalPostingService->post([
                'branch_id'        => $branchId,
                'user_id'          => $data['user_id'] ?? $actor?->id ?? auth()->id(),
                'transaction_date' => $date,
                'reference_number' => $referenceNumber,
                'description'      => $description,
                'lines'            => $lines,
            ]);
        });
    }

    /**
     * Resolusi akun standar berdasarkan kode, atau buat jika belum ada.
     */
    private function resolveAccount(string $code, string $name, string $type): ChartOfAccount
    {
        $account = ChartOfAccount::where('code', $code)->first();
        if ($account) {
            return $account;
        }

        return ChartOfAccount::create([
            'code'      => $code,
            'name'      => $name,
            'type'      => $type,
            'is_active' => true,
        ]);
    }
}
