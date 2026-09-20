<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashRegisterShift;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CashRegisterShiftService
{
    /**
     * Membuka shift baru untuk register dan kasir tertentu dengan status 'open'.
     */
    public function openShift(int $registerId, int $userId, float|int|string $openingBalance, ?string $notes = null): CashRegisterShift
    {
        $register = CashRegister::withoutGlobalScopes()->findOrFail($registerId);
        $user = User::findOrFail($userId);

        // Cek apakah kasir ini masih memiliki shift aktif
        $existingUserShift = CashRegisterShift::where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if ($existingUserShift) {
            throw ValidationException::withMessages([
                'cash_register_id' => ["Kasir {$user->name} masih memiliki shift aktif (#{$existingUserShift->id}) yang belum ditutup."],
            ]);
        }

        // Cek apakah laci kasir ini sedang dipakai oleh kasir lain yang statusnya masih open
        $existingRegisterShift = CashRegisterShift::where('cash_register_id', $registerId)
            ->where('status', 'open')
            ->first();

        if ($existingRegisterShift) {
            $otherCashier = $existingRegisterShift->user?->name ?? 'kasir lain';
            throw ValidationException::withMessages([
                'cash_register_id' => ["Laci kasir {$register->name} sedang aktif digunakan oleh {$otherCashier}."],
            ]);
        }

        $balance = max(0, (float) $openingBalance);

        return CashRegisterShift::create([
            'branch_id'                => $register->branch_id,
            'cash_register_id'         => $register->id,
            'user_id'                  => $user->id,
            'opened_at'                => now(),
            'opening_balance'          => $balance,
            'expected_closing_balance' => $balance,
            'actual_closing_balance'   => 0.00,
            'difference'               => 0.00,
            'status'                   => 'open',
            'notes'                    => $notes,
        ]);
    }

    /**
     * Menghitung dinamis expected_closing_balance.
     * Rumus: opening_balance + Total nilai transaksi penjualan tunai/cash pada shift ini.
     */
    public function calculateExpectedBalance(CashRegisterShift $shift): float
    {
        $opening = (float) $shift->opening_balance;

        $cashSales = Sale::where('cash_register_shift_id', $shift->id)
            ->whereIn('payment_method', ['cash', 'Cash', 'tunai', 'Tunai'])
            ->where('status', 'completed')
            ->get();

        $totalCash = $cashSales->sum(function (Sale $sale) {
            // Nilai sale.total_amount disimpan dalam satuan sen (cents) di POS checkout
            return (float) ($sale->total_amount / 100);
        });

        // Kurangi refund retur penjualan tunai pada shift ini
        $cashRefunds = \App\Models\SalesReturn::withoutGlobalScopes()
            ->where('cash_register_shift_id', $shift->id)
            ->whereIn('refund_method', ['cash', 'Cash', 'tunai', 'Tunai'])
            ->where('status', 'completed')
            ->sum('total_amount');

        $netCash = bcsub((string) $totalCash, (string) $cashRefunds, 2);

        return (float) bcadd((string) $opening, (string) $netCash, 2);
    }

    /**
     * Menutup shift kasir:
     * Menghitung expected balance, menghitung selisih (difference = actual - expected),
     * memperbarui status menjadi 'closed', mengisi closed_at, dan menyimpan data.
     */
    public function closeShift(CashRegisterShift $shift, float|int|string $actualBalance, ?string $notes = null): CashRegisterShift
    {
        if ($shift->isClosed()) {
            throw ValidationException::withMessages([
                'shift' => ['Shift kasir ini sudah ditutup sebelumnya.'],
            ]);
        }

        $expected = $this->calculateExpectedBalance($shift);
        $actual = max(0, (float) $actualBalance);
        $diff = (float) bcsub((string) $actual, (string) $expected, 2);

        $shiftNotes = $notes;
        if (empty($shiftNotes)) {
            $shiftNotes = $shift->notes;
        }

        $shift->update([
            'closed_at'                => now(),
            'expected_closing_balance' => $expected,
            'actual_closing_balance'   => $actual,
            'difference'               => $diff,
            'status'                   => 'closed',
            'notes'                    => $shiftNotes,
        ]);

        return $shift->refresh();
    }

    /**
     * Ambil shift aktif untuk user tertentu.
     */
    public function getActiveShiftForUser(int $userId): ?CashRegisterShift
    {
        return CashRegisterShift::where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }
}
