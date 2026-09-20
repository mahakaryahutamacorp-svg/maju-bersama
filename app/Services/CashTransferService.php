<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashTransfer;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashTransferService
{
    public function __construct(
        protected JournalPostingService $journalPostingService
    ) {}

    /**
     * Memproses mutasi / transfer dana antar kas & bank serta membukukan jurnal otomatis.
     *
     * @param array{
     *     branch_id?: int|null,
     *     from_account_id: int,
     *     to_account_id: int,
     *     amount: float|int|string,
     *     transfer_date?: string|null,
     *     reference_number?: string|null,
     *     notes?: string|null,
     *     user_id?: int|null
     * } $data
     * @throws ValidationException
     */
    public function processTransfer(array $data): CashTransfer
    {
        return DB::transaction(function () use ($data): CashTransfer {
            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Nominal transfer harus lebih besar dari 0.'],
                ]);
            }

            if ((int) $data['from_account_id'] === (int) $data['to_account_id']) {
                throw ValidationException::withMessages([
                    'to_account_id' => ['Akun asal dan akun tujuan transfer tidak boleh sama.'],
                ]);
            }

            // Ambil akun asal dan tujuan
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

            $transferDate = $data['transfer_date'] ?? now()->toDateString();
            $referenceNumber = ! empty($data['reference_number']) 
                ? (string) $data['reference_number'] 
                : $this->generateReferenceNumber();

            $notes = isset($data['notes']) && trim((string) $data['notes']) !== '' 
                ? trim((string) $data['notes']) 
                : null;

            // Keterangan jurnal: "Mutasi Kas: dari [Nama Akun Asal] ke [Nama Akun Tujuan] - [Catatan]"
            $description = "Mutasi Kas: dari {$fromAccount->name} ke {$toAccount->name}";
            if ($notes) {
                $description .= " - {$notes}";
            }

            // 1. Simpan data ke tabel cash_transfers
            $cashTransfer = CashTransfer::create([
                'branch_id'        => $branchId,
                'from_account_id'  => $fromAccount->id,
                'to_account_id'    => $toAccount->id,
                'amount'           => $amount,
                'transfer_date'    => $transferDate,
                'reference_number' => $referenceNumber,
                'notes'            => $notes,
            ]);

            // 2. Otomatisasi Jurnal: Panggil JournalPostingService untuk membuat jurnal:
            // Debit: Akun Tujuan (to_account_id) sebesar nilai amount.
            // Kredit: Akun Asal (from_account_id) sebesar nilai amount.
            $journal = $this->journalPostingService->post([
                'branch_id'        => $branchId,
                'user_id'          => $data['user_id'] ?? auth()->id(),
                'transaction_date' => $transferDate,
                'reference_number' => $referenceNumber,
                'description'      => $description,
                'lines'            => [
                    [
                        'chart_of_account_id' => $toAccount->id,
                        'debit'               => $amount,
                        'credit'              => 0,
                        'memo'                => $description,
                    ],
                    [
                        'chart_of_account_id' => $fromAccount->id,
                        'debit'               => 0,
                        'credit'              => $amount,
                        'memo'                => $description,
                    ],
                ],
            ]);

            // Tautkan journal_header_id ke rekaman cash_transfers
            $cashTransfer->update(['journal_header_id' => $journal->id]);

            return $cashTransfer->load(['fromAccount', 'toAccount', 'journalHeader.journalLines', 'branch']);
        });
    }

    /**
     * Generate reference number otomatis untuk mutasi kas. Format: TRF-{Ymd}-{random}
     */
    private function generateReferenceNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(3)));
        return "TRF-{$date}-{$random}";
    }
}
