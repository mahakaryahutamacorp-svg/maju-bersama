<?php

namespace App\Services;

/**
 * Splits a tax-inclusive shelf price into DPP and PPN.
 * PPN is the remainder after rounding DPP, so the two always add back to the amount paid.
 */
class InclusiveTaxCalculator
{
    /**
     * @return array{dpp: string, tax: string}
     */
    public function split(string $inclusiveAmount, string $taxRate): array
    {
        $inclusive = $this->money($inclusiveAmount);

        if (bccomp($inclusive, '0.00', 2) !== 1) {
            return ['dpp' => '0.00', 'tax' => '0.00'];
        }

        $divisor = bcadd('1', bcdiv($this->money($taxRate), '100', 6), 6);
        if (bccomp($divisor, '1', 6) !== 1) {
            return ['dpp' => $inclusive, 'tax' => '0.00'];
        }

        $dpp = $this->money(bcdiv($inclusive, $divisor, 6));
        if (bccomp($dpp, $inclusive, 2) === 1) {
            $dpp = $inclusive;
        }

        return [
            'dpp' => $dpp,
            'tax' => bcsub($inclusive, $dpp, 2),
        ];
    }

    /**
     * Spread a transaction discount across line nets. Each share stays within its line.
     * Leftover cents land on the last lines that still have room.
     *
     * @param  list<string>  $lineNets
     * @return list<string>
     */
    public function allocateDiscount(string $discount, array $lineNets): array
    {
        $count = count($lineNets);
        $shares = array_fill(0, $count, '0.00');

        if ($count === 0) {
            return $shares;
        }

        $discountCents = $this->toCents($discount);
        $nets = array_map(fn (string $net) => $this->toCents($net), $lineNets);
        $base = array_sum($nets);

        if ($discountCents <= 0 || $base <= 0) {
            return $shares;
        }

        if ($discountCents > $base) {
            $discountCents = $base;
        }

        $assigned = 0;
        $cents = array_fill(0, $count, 0);

        foreach ($nets as $index => $netCents) {
            $floor = (int) bcdiv(bcmul((string) $discountCents, (string) $netCents, 0), (string) $base, 0);
            if ($floor > $netCents) {
                $floor = $netCents;
            }
            $cents[$index] = $floor;
            $assigned += $floor;
        }

        $left = $discountCents - $assigned;

        for ($index = $count - 1; $index >= 0 && $left > 0; $index--) {
            $room = $nets[$index] - $cents[$index];
            if ($room <= 0) {
                continue;
            }

            $give = min($room, $left);
            $cents[$index] += $give;
            $left -= $give;
        }

        foreach ($cents as $index => $share) {
            $shares[$index] = $this->fromCents($share);
        }

        return $shares;
    }

    /**
     * Proportional slice of a stored amount. The caller caps this against the remainder.
     */
    public function share(string $amount, int $quantity, int $soldQuantity): string
    {
        if ($quantity <= 0 || $soldQuantity <= 0) {
            return '0.00';
        }

        $amount = $this->money($amount);

        if ($quantity >= $soldQuantity) {
            return $amount;
        }

        return $this->money(bcdiv(bcmul($amount, (string) $quantity, 2), (string) $soldQuantity, 6));
    }

    public function money(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        $value = is_float($amount)
            ? number_format($amount, 3, '.', '')
            : trim((string) $amount);

        $half = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd(bcadd($value, $half, 3), '0', 2);
    }

    private function toCents(string $amount): int
    {
        return (int) bcmul($this->money($amount), '100', 0);
    }

    private function fromCents(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }
}
