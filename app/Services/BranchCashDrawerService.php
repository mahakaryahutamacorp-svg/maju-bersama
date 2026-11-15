<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Setiap cabang dan gudang punya tiga laci, satu untuk tiap operator kasir.
 */
class BranchCashDrawerService
{
    public const DRAWER_NAMES = ['Laci 1', 'Laci 2', 'Laci 3'];

    /**
     * @return Collection<int, CashRegister>
     */
    public function ensure(int $branchId): Collection
    {
        return DB::transaction(function () use ($branchId): Collection {
            $registers = CashRegister::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach (self::DRAWER_NAMES as $name) {
                $existing = $registers->first(fn (CashRegister $register) => $register->name === $name);

                if ($existing instanceof CashRegister) {
                    if (! $existing->is_active) {
                        $existing->update(['is_active' => true]);
                    }

                    continue;
                }

                $registers->push(CashRegister::withoutGlobalScopes()->create([
                    'branch_id' => $branchId,
                    'name' => $name,
                    'is_active' => true,
                ]));
            }

            $openRegisterIds = CashRegisterShift::query()
                ->where('branch_id', $branchId)
                ->where('status', 'open')
                ->pluck('cash_register_id');

            foreach ($registers as $register) {
                if ($register->name !== 'Kasir Utama' || $openRegisterIds->contains($register->id)) {
                    continue;
                }

                if ($register->is_active) {
                    $register->update(['is_active' => false]);
                }
            }

            return CashRegister::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        });
    }
}
