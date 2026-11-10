<?php

namespace App\Support;

/**
 * Uang rupiah tepat 2 angka di belakang koma.
 * Menerima format Indonesia (koma desimal, titik ribuan) maupun angka polos.
 */
class Money
{
    public static function parse(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return self::of($value);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $negative = str_starts_with($raw, '-');
        $raw = preg_replace('/[^\d,.]/', '', $raw) ?? '';
        if ($raw === '') {
            return null;
        }

        $hasComma = str_contains($raw, ',');
        $dotCount = substr_count($raw, '.');

        if ($hasComma && $dotCount > 0) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasComma) {
            $raw = str_replace(',', '.', $raw);
            $parts = explode('.', $raw);
            if (count($parts) > 2) {
                $fraction = array_pop($parts);
                $raw = implode('', $parts).'.'.$fraction;
            }
        } elseif ($dotCount > 1) {
            $raw = str_replace('.', '', $raw);
        } elseif ($dotCount === 1) {
            $decimals = explode('.', $raw, 2)[1] ?? '';
            if (strlen($decimals) === 3) {
                $raw = str_replace('.', '', $raw);
            }
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return self::of($negative ? '-'.$raw : $raw);
    }

    public static function of(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        $value = is_float($amount)
            ? number_format($amount, 4, '.', '')
            : trim((string) $amount);

        if (! is_numeric($value)) {
            return '0.00';
        }

        $half = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd(bcadd($value, $half, 3), '0', 2);
    }

    public static function mul(mixed $unitPrice, mixed $quantity): string
    {
        return self::of(bcmul(self::of($unitPrice), DecimalQuantity::normalize($quantity), 6));
    }

    public static function add(mixed $left, mixed $right): string
    {
        return bcadd(self::of($left), self::of($right), 2);
    }

    public static function rupiah(mixed $amount): string
    {
        $normalized = self::of($amount);
        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '00');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) ?: '0';

        return ($negative ? '-' : '').'Rp '.$grouped.','.$fraction;
    }
}
