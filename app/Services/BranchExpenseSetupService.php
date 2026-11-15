<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;

class BranchExpenseSetupService
{
    /**
     * Pastikan cabang memiliki kategori biaya operasional minimal agar formulir pengeluaran tidak kosong.
     */
    public function ensureForBranch(int $branchId): void
    {
        if (ExpenseCategory::withoutGlobalScopes()->where('branch_id', $branchId)->exists()) {
            return;
        }

        $expenseCoa = ChartOfAccount::query()->where('code', '6100')->first()
            ?? ChartOfAccount::query()->where('code', '5110')->first()
            ?? ChartOfAccount::query()->where('type', 'expense')->orderBy('code')->first();

        if (! $expenseCoa) {
            return;
        }

        $utilityCoa = ChartOfAccount::query()->where('code', '6200')->first() ?? $expenseCoa;

        ExpenseCategory::create([
            'branch_id' => $branchId,
            'chart_of_account_id' => $expenseCoa->id,
            'name' => 'Operasional Cabang',
            'is_active' => true,
        ]);

        ExpenseCategory::create([
            'branch_id' => $branchId,
            'chart_of_account_id' => $utilityCoa->id,
            'name' => 'Listrik, Air & Internet',
            'is_active' => true,
        ]);
    }

    public function ensureAllBranches(): void
    {
        Branch::query()->pluck('id')->each(fn (int $id) => $this->ensureForBranch($id));
    }
}