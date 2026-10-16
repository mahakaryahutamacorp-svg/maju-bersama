<?php

namespace App\Services\Reports;

/**
 * Mengelompokkan akun laba rugi dari kode bagan akun.
 * Kode menang atas nama akun, supaya "Beban Selisih Persediaan" (5120)
 * tetap harga pokok dan tidak tertarik ke beban operasional hanya karena kata "beban".
 */
class ProfitAndLossClassifier
{
    public const REVENUE = 'revenue';

    public const COGS = 'cogs';

    public const OPERATING = 'operating';

    public const OTHER = 'other';

    public function classify(string $code, string $name, string $type): string
    {
        if (str_starts_with($code, '4') || ($type === 'revenue' && ! str_starts_with($code, '5') && ! str_starts_with($code, '6'))) {
            return self::REVENUE;
        }

        // 5110 Biaya Harian dan seluruh kepala 6 adalah beban operasional.
        if (str_starts_with($code, '6') || str_starts_with($code, '511')) {
            return self::OPERATING;
        }

        // 5100 HPP, 5120 selisih persediaan, dan kepala 5 lain.
        if (str_starts_with($code, '5')) {
            return self::COGS;
        }

        $normalized = strtolower($name);
        if (
            $type === 'expense'
            || str_contains($normalized, 'operasional')
            || str_contains($normalized, 'biaya')
            || str_contains($normalized, 'beban')
        ) {
            return self::OPERATING;
        }

        return self::OTHER;
    }
}
