<?php

namespace App\Services;

use App\Models\JournalHeader;
use Illuminate\Validation\ValidationException;

class JournalEntryService
{
    public function __construct(
        protected JournalPostingService $journalPostingService
    ) {}

    /**
     * Membukukan entri jurnal akuntansi berpasangan ganda yang seimbang (double-entry).
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
    public function createEntry(array $data): JournalHeader
    {
        return $this->journalPostingService->post($data);
    }

    /**
     * Alias untuk konsistensi pemanggilan jurnal posting.
     */
    public function post(array $data): JournalHeader
    {
        return $this->journalPostingService->post($data);
    }
}
