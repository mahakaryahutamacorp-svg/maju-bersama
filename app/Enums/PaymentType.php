<?php

namespace App\Enums;

enum PaymentType: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Edc = 'edc';
    case Transfer = 'transfer';
    case Credit = 'credit';
    case Mixed = 'mixed';

    /**
     * Memetakan `sales.payment_method` lama (string bebas) ke tipe baru.
     * Nilai yang tidak dikenali menghasilkan null agar backfill tidak menebak.
     */
    public static function fromLegacy(?string $method): ?self
    {
        return match (strtolower(trim((string) $method))) {
            'cash', 'tunai' => self::Cash,
            'tempo', 'piutang', 'kredit' => self::Credit,
            default => null,
        };
    }
}
