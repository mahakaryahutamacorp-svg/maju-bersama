<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use Illuminate\Support\Collection;

class BranchCashAccountService
{
    /**
     * Akun kas utama cabang untuk pengeluaran operasional (default: Kas 1110 atau sub-akun kas aktif).
     */
    public function defaultForBranch(int $branchId): ChartOfAccount
    {
        $account = ChartOfAccount::query()
            ->whereIn('id', ChartOfAccount::cashAccountIds())
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('is_postable', true)
                    ->orWhereNull('is_postable');
            })
            ->orderByRaw("CASE WHEN code = '1110' THEN 0 WHEN code LIKE '1110%' THEN 1 WHEN code = '1120' THEN 2 ELSE 3 END")
            ->orderBy('code')
            ->first();

        if ($account instanceof ChartOfAccount) {
            return $account;
        }

        return ChartOfAccount::query()
            ->where('code', ChartOfAccount::CASH_BANK_CODES[0])
            ->firstOrFail();
    }

    /**
     * @return Collection<int, ChartOfAccount>
     */
    public function listForBranch(int $branchId): Collection
    {
        return ChartOfAccount::query()
            ->whereIn('id', ChartOfAccount::cashAccountIds())
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }
}