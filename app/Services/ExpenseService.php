<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        protected JournalPostingService $journalPostingService
    ) {}

    /**
     * Catat biaya operasional baru dan buat jurnal akuntansi ganda otomatis.
     *
     * @param array{
     *     branch_id?: int|null,
     *     expense_category_id: int,
     *     account_id: int,
     *     amount: float|int|string,
     *     expense_date?: string|null,
     *     reference_number?: string|null,
     *     notes?: string|null,
     *     user_id?: int|null
     * } $data
     * @throws ValidationException
     */
    public function recordExpense(array $data): Expense
    {
        return DB::transaction(function () use ($data): Expense {
            $amount = max(0, (float) $data['amount']);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Nominal biaya operasional harus lebih besar dari 0.'],
                ]);
            }

            // Ambil master kategori pengeluaran
            $category = ExpenseCategory::withoutGlobalScopes()->findOrFail($data['expense_category_id']);
            if (! $category->is_active) {
                throw ValidationException::withMessages([
                    'expense_category_id' => ["Kategori biaya '{$category->name}' saat ini berstatus non-aktif."],
                ]);
            }

            // Ambil akun sumber dana kas/bank
            $account = ChartOfAccount::findOrFail($data['account_id']);

            $branchId = $data['branch_id'] ?? $category->branch_id;
            $expenseDate = $data['expense_date'] ?? now()->toDateString();
            $referenceNumber = $data['reference_number'] ?? $this->generateReferenceNumber($branchId);
            $notes = isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null;

            // Keterangan jurnal: "Biaya Operasional: [Nama Kategori] - [Catatan]"
            $description = "Biaya Operasional: {$category->name}";
            if ($notes) {
                $description .= " - {$notes}";
            }

            // 1. Simpan data ke tabel expenses
            $expense = Expense::create([
                'branch_id'           => $branchId,
                'expense_category_id' => $category->id,
                'account_id'          => $account->id,
                'amount'              => $amount,
                'expense_date'        => $expenseDate,
                'reference_number'    => $referenceNumber,
                'notes'               => $notes,
            ]);

            // 2. Panggil JournalPostingService untuk membuat jurnal otomatis:
            // Debit: Akun Beban (expense_categories.chart_of_account_id) sebesar $data['amount']
            // Kredit: Akun Kas/Bank ($data['account_id']) sebesar $data['amount']
            $journal = $this->journalPostingService->post([
                'branch_id'        => $branchId,
                'user_id'          => $data['user_id'] ?? null,
                'transaction_date' => $expenseDate,
                'reference_number' => $referenceNumber,
                'description'      => $description,
                'lines'            => [
                    [
                        'chart_of_account_id' => $category->chart_of_account_id,
                        'debit'               => $amount,
                        'credit'              => 0,
                        'memo'                => $description,
                    ],
                    [
                        'chart_of_account_id' => $account->id,
                        'debit'               => 0,
                        'credit'              => $amount,
                        'memo'                => "Pengeluaran dana operasional via {$account->name}",
                    ],
                ],
            ]);

            // Tautkan id jurnal ke entri expense
            $expense->update(['journal_header_id' => $journal->id]);

            return $expense->load(['expenseCategory.chartOfAccount', 'account', 'journalHeader.journalLines']);
        });
    }

    private function generateReferenceNumber(int $branchId): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(2)));
        return "EXP-{$branchId}-{$date}-{$random}";
    }
}
