<?php

namespace App\Services;

use App\Models\JournalHeader;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
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
            ?? \App\Models\User::where('branch_id', $data['branch_id'])->value('id') 
            ?? \App\Models\User::value('id');

        $header = JournalHeader::create([
            'branch_id'        => $data['branch_id'],
            'user_id'          => $userId,
            'transaction_date' => $data['transaction_date'],
            'reference_number' => $data['reference_number'],
            'description'      => $data['description'],
        ]);

        $header->journalLines()->createMany($data['lines']);

        return $header->load('journalLines');
    }
}
