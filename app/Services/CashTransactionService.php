<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashTransfer;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashTransactionService
{
    public function __construct(
        protected JournalEntryService $journalEntryService
    ) {}

    /**
     * Memproses mutasi / transfer dana antar kas & bank serta membukukan jurnal berpasangan ganda otomatis.
     *
     * Debit: Akun Tujuan (Ke) senilai Nominal Transfer
     * Kredit: Akun Sumber (Dari) senilai Nominal Transfer
     *
     * @param array{
     *     branch_id?: int|null,
     *     from_account_id: int,
     *     to_account_id: int,
     *     amount: float|int|string,
     *     transaction_date?: string|null,
     *     transfer_date?: string|null,
     *     reference_number?: string|null,
     *     notes?: string|null,
     *     description?: string|null,
     *     user_id?: int|null
     * } $data
     * @throws ValidationException
     */
    public function storeTransfer(array $data): CashTransfer
    {
        return DB::transaction(function () use ($data): CashTransfer {
            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Nominal transfer kas/bank harus lebih besar dari 0.'],
                ]);
            }

            if ((int) $data['from_account_id'] === (int) $data['to_account_id']) {
                throw ValidationException::withMessages([
                    'to_account_id' => ['Akun asal dan akun tujuan transfer tidak boleh sama.'],
                ]);
            }

            $fromAccount = ChartOfAccount::findOrFail($data['from_account_id']);
            $toAccount = ChartOfAccount::findOrFail($data['to_account_id']);

            if (! $fromAccount->is_active) {
                throw ValidationException::withMessages([
                    'from_account_id' => ["Akun asal '{$fromAccount->name}' berstatus non-aktif."],
                ]);
            }

            if (! $toAccount->is_active) {
                throw ValidationException::withMessages([
                    'to_account_id' => ["Akun tujuan '{$toAccount->name}' berstatus non-aktif."],
                ]);
            }

            $branchId = $data['branch_id']
                ?? auth()->user()?->branch_id
                ?? Branch::value('id');

            $date = $data['transaction_date'] 
                ?? $data['transfer_date'] 
                ?? now()->toDateString();

            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber();

            $notes = isset($data['notes']) && trim((string) $data['notes']) !== ''
                ? trim((string) $data['notes'])
                : (isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null);

            $description = "Mutasi Kas: dari {$fromAccount->name} ke {$toAccount->name}";
            if ($notes) {
                $description .= " - {$notes}";
            }

            // 1. Simpan rekaman mutasi kas
            $cashTransfer = CashTransfer::create([
                'branch_id'        => $branchId,
                'from_account_id'  => $fromAccount->id,
                'to_account_id'    => $toAccount->id,
                'amount'           => $amount,
                'transfer_date'    => $date,
                'reference_number' => $referenceNumber,
                'notes'            => $notes,
            ]);

            // 2. Integrasi Jurnal Transfer: Panggil JournalEntryService
            // - Debit: Akun Tujuan (Ke) senilai Nominal Transfer
            // - Kredit: Akun Sumber (Dari) senilai Nominal Transfer
            $journal = $this->journalEntryService->createEntry([
                'branch_id'        => $branchId,
                'user_id'          => $data['user_id'] ?? auth()->id(),
                'transaction_date' => $date,
                'reference_number' => $referenceNumber,
                'description'      => $description,
                'lines'            => [
                    [
                        'chart_of_account_id' => $toAccount->id,
                        'debit'               => $amount,
                        'credit'              => 0,
                        'memo'                => "Debit Akun Tujuan: {$toAccount->name}",
                    ],
                    [
                        'chart_of_account_id' => $fromAccount->id,
                        'debit'               => 0,
                        'credit'              => $amount,
                        'memo'                => "Kredit Akun Sumber: {$fromAccount->name}",
                    ],
                ],
            ]);

            $cashTransfer->update(['journal_header_id' => $journal->id]);

            return $cashTransfer->load(['fromAccount', 'toAccount', 'journalHeader.journalLines', 'branch']);
        });
    }

    /**
     * Menyimpan transaksi kas umum (IN / OUT / TRANSFER).
     *
     * @param array $data
     * @param array $lines
     * @return mixed
     */
    public function storeTransaction(array $data, array $lines = []): mixed
    {
        $type = strtoupper((string) ($data['type'] ?? 'TRANSFER'));

        if ($type === 'TRANSFER') {
            return $this->storeTransfer($data);
        }

        return DB::transaction(function () use ($data, $lines, $type) {
            $branchId = $data['branch_id']
                ?? auth()->user()?->branch_id
                ?? Branch::value('id');

            $date = $data['transaction_date'] ?? $data['date'] ?? now()->toDateString();
            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber($type);
            $notes = $data['notes'] ?? $data['description'] ?? null;

            $mainAccountId = (int) ($data['account_id'] ?? $data['from_account_id'] ?? $data['to_account_id'] ?? 0);
            $mainAccount = ChartOfAccount::findOrFail($mainAccountId);

            $totalAmount = 0;
            $journalLines = [];

            foreach ($lines as $line) {
                $lineAmount = (float) ($line['amount'] ?? 0);
                $totalAmount += $lineAmount;
                $oppAccountId = (int) ($line['chart_of_account_id'] ?? $line['account_id']);
                $lineMemo = $line['memo'] ?? $notes ?? "Baris transaksi kas";

                if ($type === 'IN') {
                    // Kas Masuk: Akun Lawan di Kredit
                    $journalLines[] = [
                        'chart_of_account_id' => $oppAccountId,
                        'debit'               => 0,
                        'credit'              => $lineAmount,
                        'memo'                => $lineMemo,
                    ];
                } else {
                    // Kas Keluar: Akun Lawan di Debit
                    $journalLines[] = [
                        'chart_of_account_id' => $oppAccountId,
                        'debit'               => $lineAmount,
                        'credit'              => 0,
                        'memo'                => $lineMemo,
                    ];
                }
            }

            if ($type === 'IN') {
                // Kas Masuk: Kas Utama di Debit
                array_unshift($journalLines, [
                    'chart_of_account_id' => $mainAccount->id,
                    'debit'               => $totalAmount,
                    'credit'              => 0,
                    'memo'                => "Penerimaan Kas - {$mainAccount->name}",
                ]);
            } else {
                // Kas Keluar: Kas Utama di Kredit
                $journalLines[] = [
                    'chart_of_account_id' => $mainAccount->id,
                    'debit'               => 0,
                    'credit'              => $totalAmount,
                    'memo'                => "Pengeluaran Kas - {$mainAccount->name}",
                ];
            }

            $description = ($type === 'IN' ? 'Kas Masuk' : 'Kas Keluar') . ": {$mainAccount->name}";
            if ($notes) {
                $description .= " - {$notes}";
            }

            return $this->journalEntryService->createEntry([
                'branch_id'        => $branchId,
                'user_id'          => $data['user_id'] ?? auth()->id(),
                'transaction_date' => $date,
                'reference_number' => $referenceNumber,
                'description'      => $description,
                'lines'            => $journalLines,
            ]);
        });
    }

    /**
     * Generate reference number otomatis.
     */
    private function generateReferenceNumber(string $prefix = 'TRF'): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(3)));
        return "{$prefix}-{$date}-{$random}";
    }
}
