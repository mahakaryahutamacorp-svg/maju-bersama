<?php

namespace App\Support;

/**
 * Kuantitas barang disimpan 3 angka di belakang koma (contoh 2,500 kg).
 * Angka bulat dikembalikan sebagai int supaya pemanggil lama tetap cocok.
 */
class DecimalQuantity
{
    public const SCALE = 3;

    /**
     * @return list<string>
     */
    public static function rules(bool $required = true, bool $allowZero = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'numeric',
            'regex:/^(?:0|[1-9]\d*)(?:\.\d{1,3})?$/',
            $allowZero ? 'min:0' : 'gt:0',
        ];
    }

    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        if (is_string($value)) {
            $value = str_replace(' ', '', trim($value));
            if (str_contains($value, ',') && ! str_contains($value, '.')) {
                $value = str_replace(',', '.', $value);
            }
        }

        if (! is_numeric($value)) {
            return '0.000';
        }

        $raw = is_float($value) ? number_format($value, 6, '.', '') : (string) $value;
        $half = str_starts_with($raw, '-') ? '-0.0005' : '0.0005';

        return bcadd(bcadd($raw, $half, 4), '0', self::SCALE);
    }

    public static function present(mixed $value): int|string
    {
        $normalized = self::normalize($value);
        $asInt = (int) $normalized;

        if (bccomp($normalized, (string) $asInt, self::SCALE) === 0) {
            return $asInt;
        }

        return $normalized;
    }

    public static function add(mixed $left, mixed $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function sub(mixed $left, mixed $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function sum(iterable $values): string
    {
        $total = '0.000';
        foreach ($values as $value) {
            $total = self::add($total, $value);
        }

        return $total;
    }
}
