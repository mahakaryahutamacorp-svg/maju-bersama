<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
    public const ACCOUNT_CASH = '1110';

    public const ACCOUNT_INVENTORY = '1210';

    public const ACCOUNT_AR = '1130';

    public const ACCOUNT_REVENUE = '4110';

    public const ACCOUNT_COGS = '5100';

    /**
     * Reverse a POS sale: Dr Pendapatan / Cr Kas atau Piutang, then Dr Persediaan / Cr HPP.
     *
     * @param  array{
     *     branch_id: int,
     *     user_id?: int|null,
     *     transaction_date: string|\DateTimeInterface,
     *     reference_number: string,
     *     description: string,
     *     refund_account_id: int,
     *     total_amount: float|int|string,
     *     total_cost: float|int|string
     * }  $data
     */
    public function postSalesReturn(array $data): ?JournalHeader
    {
        $totalAmount = number_format((float) $data['total_amount'], 2, '.', '');
        $totalCost = number_format((float) $data['total_cost'], 2, '.', '');

        if (bccomp($totalAmount, '0.00', 2) === 0 && bccomp($totalCost, '0.00', 2) === 0) {
            return null;
        }

        $revenueAccount = $this->firstOrCreateAccount(self::ACCOUNT_REVENUE, 'Pendapatan Penjualan', 'revenue');
        $inventoryAccount = $this->firstOrCreateAccount(self::ACCOUNT_INVENTORY, 'Persediaan', 'asset');
        $cogsAccount = ChartOfAccount::whereIn('code', [self::ACCOUNT_COGS, '5110'])->first()
            ?? $this->firstOrCreateAccount(self::ACCOUNT_COGS, 'Harga Pokok Penjualan', 'expense');

        $reference = $data['reference_number'];
        $lines = [];

        if (bccomp($totalAmount, '0.00', 2) > 0) {
            $lines[] = [
                'chart_of_account_id' => $revenueAccount->id,
                'debit' => $totalAmount,
                'credit' => 0,
                'memo' => "Pengurangan pendapatan atas retur penjualan {$reference}",
            ];
            $lines[] = [
                'chart_of_account_id' => (int) $data['refund_account_id'],
                'debit' => 0,
                'credit' => $totalAmount,
                'memo' => 'Pengembalian dana pelanggan (Kas/Piutang)',
            ];
        }

        if (bccomp($totalCost, '0.00', 2) > 0) {
            $lines[] = [
                'chart_of_account_id' => $inventoryAccount->id,
                'debit' => $totalCost,
                'credit' => 0,
                'memo' => 'Penerimaan kembali persediaan barang retur ke gudang',
            ];
            $lines[] = [
                'chart_of_account_id' => $cogsAccount->id,
                'debit' => 0,
                'credit' => $totalCost,
                'memo' => 'Pengurangan harga pokok penjualan (HPP) atas retur barang',
            ];
        }

        return $this->post([
            'branch_id' => $data['branch_id'],
            'user_id' => $data['user_id'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'reference_number' => $reference,
            'description' => $data['description'],
            'lines' => $lines,
        ]);
    }

    private function firstOrCreateAccount(string $code, string $name, string $type): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'type' => $type, 'is_active' => true]
        );
    }

    /**
     * Post a balanced double-entry accounting journal.
     *
     * @param array{
     *     branch_id: int,
     *     user_id?: int|null,
     *     transaction_date: string|\DateTimeInterface,
     *     reference_number: string,
     *     description: string,
     *     lines: array<int, array{
     *         chart_of_account_id: int,
     *         debit: float|int|string,
     *         credit: float|int|string,
     *         memo?: string|null
     *     }>
     * } $data
     *
     * @throws ValidationException
     */
    public function post(array $data): JournalHeader
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($data['lines'] as $line) {
            $totalDebit = bcadd($totalDebit, (string) ($line['debit'] ?? 0), 2);
            $totalCredit = bcadd($totalCredit, (string) ($line['credit'] ?? 0), 2);
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw ValidationException::withMessages([
                'journal' => ["Jurnal akuntansi tidak seimbang. Total Debit ({$totalDebit}) != Total Kredit ({$totalCredit})."],
            ]);
        }

        $userId = $data['user_id']
            ?? auth()->id()
            ?? User::where('branch_id', $data['branch_id'])->value('id')
            ?? User::value('id');

        $header = JournalHeader::create([
            'branch_id' => $data['branch_id'],
            'user_id' => $userId,
            'transaction_date' => $data['transaction_date'],
            'reference_number' => $data['reference_number'],
            'description' => $data['description'],
        ]);

        $header->journalLines()->createMany($data['lines']);

        return $header->load('journalLines');
    }
}
