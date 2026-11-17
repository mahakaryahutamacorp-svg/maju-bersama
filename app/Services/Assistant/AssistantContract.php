<?php

namespace App\Services\Assistant;

final class AssistantContract
{
    /**
     * Hanya alat baca. Alat tulis/ubah/hapus tidak pernah didaftarkan.
     */
    public const TOOLS = [
        'search_guide',
        'usage_advice',
        'smart_search',
        'stock_on_hand',
        'list_receivables',
        'sales_summary',
        'recent_transactions',
        'cash_position',
        'trace_document',
    ];

    public const ROLE = 'Asisten baca saja untuk Maju Bersama. Hanya melihat, membaca, memahami letak UI dan data, memberi saran cara pakai, lalu mengarahkan pengguna. Dilarang menulis, mengedit, menghapus, menambahkan, posting, memproses, atau mengubah apapun di aplikasi.';

    public static function allows(string $tool): bool
    {
        return in_array($tool, self::TOOLS, true);
    }

    public static function wantsAdvice(string $question): bool
    {
        $normalized = mb_strtolower($question);

        return preg_match('/\b(cara|bagaimana|saran|sebaiknya|langkah|prosedur|penerapan|panduan|tips|alur|workflow|petunjuk|apa\s+yang\s+(harus|perlu)|saya\s+(mau|ingin|ingin\s+tanya)|ingin\s+(bayar|lunasi|catat|jual|transfer|opname)|bayarnya|lunasinya|caranya)\b/u', $normalized) === 1;
    }

    /**
     * Follow-up singkat yang mengandalkan 2–3 pertanyaan sebelumnya.
     */
    public static function isFollowUp(string $question): bool
    {
        $normalized = mb_strtolower(trim($question));

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/\b(lalu|terus|selanjutnya|berikutnya|tersebut|tadi|yang\s+tadi|yang\s+sama|gimana\s+lanjut|lanjutannya|bayarnya|lunasinya|caranya|detailnya|rinciannya)\b/u', $normalized) === 1) {
            return true;
        }

        // Pertanyaan pendek tanpa entitas: "bagaimana caranya?", "terus?".
        return mb_strlen($normalized) <= 48
            && self::entityTerm($normalized) === ''
            && (
                self::wantsAdvice($normalized)
                || preg_match('/\b(bayar|lunasi|lanjut|detail|rinci|gimana|bagaimana)\b/u', $normalized) === 1
            );
    }

    public static function isActionRequest(string $question): bool
    {
        $normalized = mb_strtolower($question);

        // Cara pakai / saran penerapan bukan perintah eksekusi.
        if (preg_match('/\b(di\s*mana|dimana|cara|bagaimana|menu|halaman|letak|posisi|apa\s+arti|saran|sebaiknya|langkah|prosedur|penerapan|panduan|tips|alur|petunjuk)\b/u', $normalized) === 1) {
            return false;
        }

        return preg_match('/\b(lunasi|lunaskan|bayarkan|hapus|hapuskan|ubah|edit|kurangi|tambahkan|catat|posting|simpan|proseskan|update|create|delete|insert|remove|kirimkan|eksekusi)\b/u', $normalized) === 1
            || preg_match('/\btolong\s+(bayar|lunasi|hapus|ubah|edit|catat|transfer|proses|tambah|simpan|buat|kirim)\b/u', $normalized) === 1
            || preg_match('/\b(buat|buka|tambah(?:kan)?)\s+(faktur|pembayaran|jurnal|produk|user|pengguna|po|pesanan|retur|pengeluaran|biaya|gudang|cabang)\b/u', $normalized) === 1
            || preg_match('/\b(hapus|ubah|edit|update)\s+(faktur|produk|stok|piutang|hutang|jurnal|user|data)\b/u', $normalized) === 1;
    }

    public static function mentionsApplication(string $question): bool
    {
        $normalized = mb_strtolower($question);
        $needles = [
            'stok', 'barang', 'sku', 'produk', 'katalog', 'gudang', 'opname', 'transfer',
            'piutang', 'debitur', 'pelanggan', 'faktur', 'struk', 'invoice', 'penjualan', 'omzet', 'transaksi', 'terbaru', 'terakhir',
            'kas', 'bank', 'saldo', 'jurnal', 'akun', '1130', '1110', '1120', '2110',
            'hutang', 'utang', 'pemasok', 'supplier', 'retur', 'pembayaran', 'menu', 'halaman', 'sidebar',
            'neraca', 'laba', 'rugi', 'laporan', 'cabang', 'kasir', 'pos', 'inv-',
            'po', 'pesan barang', 'terima barang', 'harta tetap', 'penyusutan', 'biaya', 'pengeluaran',
            'pesan internal', 'staf', 'user', 'kategori', 'ito', 'asisten', 'assistant',
            'cara', 'bagaimana', 'di mana', 'dimana', 'apa arti', 'status', 'letak', 'posisi',
            'cari', 'info', 'informasi', 'data', 'saran', 'langkah', 'prosedur', 'penerapan', 'panduan', 'tips', 'alur',
        ];

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        if (preg_match('/\b(inv|gr|po|ar|ap|jr)[-_][a-z0-9-]+/i', $question) === 1) {
            return true;
        }

        // Query singkat mirip nama/SKU: "budi", "pupuk npk", "abacel 500".
        $entity = self::entityTerm($question);
        if ($entity !== '' && self::wordCount($question) <= 5 && ! self::looksLikeGeneralChat($normalized)) {
            return true;
        }

        return false;
    }

    public static function entityTerm(string $question): string
    {
        if (preg_match('/\b((?:inv|gr|po|ar|ap|jr)[-_][a-z0-9-]+)/i', $question, $match) === 1) {
            return $match[1];
        }

        $stop = 'berapa|sisa|stok|piutang|hutang|utang|bagaimana|cara|tolong|dimana|di|mana|nama|pelanggan|debitur|pemasok|supplier|produk|barang|sku|hari|ini|bulan|posisi|kas|bank|penjualan|omzet|cek|lihat|tampilkan|saldo|faktur|telusuri|jurnal|dan|yang|ada|apa|arti|transaksi|terbaru|terakhir|data|minta|diminta|semua|seluruh|cabang|cari|info|informasi|mengenai|tentang|untuk|dari|ke|atau|dengan|dong|ya|yah|nih|si|sih|punya|milik|saran|langkah|prosedur|penerapan|panduan|tips|alur|petunjuk|sebaiknya|workflow|terima|bayaran|bayar|lunasi|pelunasan|mencatat|menggunakan|penggunaan|aplikasi|menu|halaman|lalu|terus|selanjutnya|berikutnya|tersebut|tadi|bayarnya|lunasinya|caranya|detailnya|rinciannya|gimana|lanjut|lanjutannya|konteks|sebelumnya';
        $cleaned = preg_replace('/\b('.$stop.')\b/iu', ' ', $question) ?? $question;
        $cleaned = preg_replace('/[^\p{L}\p{N}\s\-_]/u', ' ', (string) $cleaned) ?? '';
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $cleaned));

        return mb_substr($cleaned, 0, 60);
    }

    private static function wordCount(string $question): int
    {
        $parts = preg_split('/\s+/u', trim($question)) ?: [];

        return count(array_filter($parts, fn (string $part) => $part !== ''));
    }

    private static function looksLikeGeneralChat(string $normalized): bool
    {
        return preg_match('/\b(presiden|cuaca|resep|politik|olahraga|film|lagu|agama|negara|ibu\s+kota|siapa\s+anda|siapa\s+kamu)\b/u', $normalized) === 1;
    }
}
