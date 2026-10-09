<?php

namespace App\Services\Assistant;

final class AssistantContract
{
    public const TOOLS = [
        'search_guide',
        'stock_on_hand',
        'list_receivables',
        'sales_summary',
        'recent_transactions',
        'cash_position',
        'trace_document',
    ];

    public static function allows(string $tool): bool
    {
        return in_array($tool, self::TOOLS, true);
    }

    public static function isActionRequest(string $question): bool
    {
        $normalized = mb_strtolower($question);

        return preg_match('/\b(lunasi|lunaskan|bayarkan|hapus|hapuskan|ubah|kurangi|catat|posting|simpan|proseskan)\b/u', $normalized) === 1
            || preg_match('/\btolong\s+(bayar|lunasi|hapus|ubah|catat|transfer|proses)\b/u', $normalized) === 1
            || preg_match('/\b(buat|buka)\s+(faktur|pembayaran|jurnal)\b/u', $normalized) === 1;
    }

    public static function mentionsApplication(string $question): bool
    {
        $normalized = mb_strtolower($question);
        $needles = [
            'stok', 'barang', 'sku', 'produk', 'gudang', 'opname', 'transfer',
            'piutang', 'debitur', 'pelanggan', 'faktur', 'struk', 'invoice', 'penjualan', 'omzet', 'transaksi', 'terbaru', 'terakhir',
            'kas', 'bank', 'saldo', 'jurnal', 'akun', '1130', '1110', '1120', '2110',
            'hutang', 'pemasok', 'supplier', 'retur', 'pembayaran', 'menu', 'halaman',
            'neraca', 'laba', 'laporan', 'cabang', 'kasir', 'inv-',
            'cara', 'bagaimana', 'di mana', 'dimana', 'apa arti', 'status',
        ];

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
