<?php

namespace App\Services\Assistant;

/**
 * Saran cara pakai & penerapan — hanya teks panduan.
 * Pengguna mengerjakan sendiri di UI; asisten tidak menulis data.
 */
class UsageAdvice
{
    /**
     * @return array<int, array{id: string, title: string, keywords: array<int, string>, steps: array<int, string>, links: array<int, array{label: string, url: string}>}>
     */
    public function playbooks(): array
    {
        return [
            [
                'id' => 'terima-piutang',
                'title' => 'Menerima bayaran piutang pelanggan',
                'keywords' => ['piutang', 'terima bayaran', 'pelunasan', 'cicilan', 'debitur', 'tagihan', 'bayar piutang', 'tempo'],
                'steps' => [
                    'Buka sidebar Jualan → Terima Bayaran Piutang.',
                    'Pilih akun kas/bank penerima, isi tanggal dan nominal yang diterima.',
                    'Cari nama pelanggan/debitur, lalu centang faktur yang dilunasi (boleh sebagian).',
                    'Periksa sisa tagihan di layar, lalu simpan sendiri di form tersebut.',
                    'Cek hasilnya di Piutang Pelanggan (aging) atau tanyakan lagi ke Ito secara baca saja.',
                ],
                'links' => [
                    ['label' => 'Terima Bayaran Piutang', 'url' => route('backoffice.payments.receivables.create')],
                    ['label' => 'Piutang Pelanggan', 'url' => route('reports.ar-aging')],
                ],
            ],
            [
                'id' => 'bayar-hutang',
                'title' => 'Membayar hutang pemasok',
                'keywords' => ['hutang', 'utang', 'pemasok', 'supplier', 'bayar hutang', 'pelunasan hutang', 'ap'],
                'steps' => [
                    'Buka sidebar Belanja Barang → Buku Pemasok & Hutang.',
                    'Pilih pemasok yang punya sisa hutang, lalu gunakan form pembayaran di halaman pemasok.',
                    'Isi tanggal, akun kas/bank, dan nominal; alokasi mengikuti urutan hutang (FIFO) di aplikasi.',
                    'Simpan sendiri di halaman itu. Riwayat bisa dicek di Riwayat Bayar Hutang / Aging Hutang (jika berhak).',
                ],
                'links' => [
                    ['label' => 'Buku Pemasok', 'url' => route('backoffice.suppliers.index')],
                    ['label' => 'Aging Hutang', 'url' => route('reports.ap-aging')],
                ],
            ],
            [
                'id' => 'jual-pos',
                'title' => 'Menjual di Kasir (POS)',
                'keywords' => ['kasir', 'pos', 'jual', 'penjualan tunai', 'penjualan tempo', 'struk', 'shift'],
                'steps' => [
                    'Buka Kasir (POS) dari sidebar. Buka shift kasir jika belum terbuka.',
                    'Cari produk, masukkan jumlah, pilih pelanggan bila penjualan tempo.',
                    'Pilih tunai atau tempo, selesaikan pembayaran, lalu cetak/simpan struk sendiri di kasir.',
                    'Tutup shift di akhir hari agar kas fisik cocok dengan sistem.',
                ],
                'links' => [
                    ['label' => 'Kasir (POS)', 'url' => route('pos')],
                    ['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')],
                ],
            ],
            [
                'id' => 'belanja',
                'title' => 'Alur belanja barang ke pemasok',
                'keywords' => ['belanja', 'pembelian', 'pesan barang', 'terima barang', 'po', 'purchase order', 'barang masuk'],
                'steps' => [
                    'Urutan yang disarankan: 1) Pesan Barang (PO) → 2) Terima Barang → 3) catat/bayar di Buku Pemasok.',
                    'Buat PO di Belanja Barang → Pesan Barang, pilih pemasok dan item.',
                    'Saat barang datang, catat di Terima Barang agar stok bertambah.',
                    'Bayar hutang kemudian di Buku Pemasok. Retur ke pemasok hanya jika barang dikembalikan.',
                ],
                'links' => [
                    ['label' => 'Pesan Barang', 'url' => route('backoffice.purchase-orders.index')],
                    ['label' => 'Terima Barang', 'url' => route('purchases.goods-receipts.index')],
                    ['label' => 'Buku Pemasok', 'url' => route('backoffice.suppliers.index')],
                ],
            ],
            [
                'id' => 'stok-opname',
                'title' => 'Menjaga stok akurat',
                'keywords' => ['stok', 'opname', 'hitung ulang', 'transfer stok', 'gudang', 'selisih'],
                'steps' => [
                    'Cek kuantitas di Stok Barang sebelum menjual atau mentransfer.',
                    'Pindah antar lokasi/cabang lewat Transfer Stok (isi SKU dan jumlah sendiri).',
                    'Koreksi fisik lewat Hitung Ulang Stok (Opname), bukan dengan mengubah angka sembarangan di tempat lain.',
                    'Pantau mutasi di Kartu Stok bila Anda berhak melihat akuntansi.',
                ],
                'links' => [
                    ['label' => 'Stok Barang', 'url' => url('/inventory')],
                    ['label' => 'Transfer Stok', 'url' => route('stock-transfer')],
                    ['label' => 'Hitung Ulang Stok', 'url' => route('inventory.adjustments.index')],
                ],
            ],
            [
                'id' => 'laporan',
                'title' => 'Membaca laporan dan memutuskan tindakan',
                'keywords' => ['laporan', 'neraca', 'laba', 'rugi', 'aging', 'pusat laporan', 'analisa', 'analisis'],
                'steps' => [
                    'Buka Pusat Laporan di sidebar bawah.',
                    'Pilih Modul Laporan untuk laporan formal, atau Aktivitas Transaksi untuk buku kas harian.',
                    'Klik nomor referensi untuk melihat rincian sebelum memutuskan pelunasan, retur, atau koreksi.',
                    'Gunakan Ito untuk menanyakan ringkasan data; keputusan dan pencatatan tetap Anda lakukan di menu terkait.',
                ],
                'links' => [
                    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
                ],
            ],
            [
                'id' => 'biaya-kas',
                'title' => 'Mencatat biaya dan memantau kas',
                'keywords' => ['biaya', 'pengeluaran', 'kas', 'bank', 'saldo', 'operasional'],
                'steps' => [
                    'Pantau posisi di Uang & Akuntansi → Kas & Bank.',
                    'Catat biaya operasional di Biaya Operasional / Pengeluaran dengan kategori yang sesuai.',
                    'Mutasi antar kas/bank memakai Transfer Kas bila tersedia di cabang Anda.',
                    'Setelah mencatat, cocokkan lagi saldo kas dengan fisik di akhir shift/hari.',
                ],
                'links' => [
                    ['label' => 'Kas & Bank', 'url' => route('backoffice.finance.dashboard', ['tab' => 'kas_bank'])],
                    ['label' => 'Biaya Operasional', 'url' => route('backoffice.finance.dashboard', ['tab' => 'pengeluaran'])],
                ],
            ],
            [
                'id' => 'retur',
                'title' => 'Menangani retur',
                'keywords' => ['retur', 'kembali', 'retur pelanggan', 'retur pemasok'],
                'steps' => [
                    'Retur dari pelanggan: Jualan → Retur dari Pelanggan; kaitkan ke faktur/barang yang dikembalikan.',
                    'Retur ke pemasok: Belanja Barang → Retur ke Pemasok (sesuai hak akses).',
                    'Setelah retur tersimpan, cek stok dan sisa piutang/hutang terkait.',
                ],
                'links' => [
                    ['label' => 'Retur dari Pelanggan', 'url' => route('backoffice.sales-returns.index')],
                    ['label' => 'Retur ke Pemasok', 'url' => route('backoffice.purchase-returns.index')],
                ],
            ],
        ];
    }

    /**
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    public function advise(string $query): array
    {
        $normalized = mb_strtolower($query);
        $matches = [];

        foreach ($this->playbooks() as $playbook) {
            $score = 0;
            foreach ($playbook['keywords'] as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    $score += max(1, (int) floor(mb_strlen($keyword) / 4));
                }
            }
            if ($score > 0) {
                $matches[] = ['score' => $score, 'playbook' => $playbook];
            }
        }

        usort($matches, fn (array $left, array $right) => $right['score'] <=> $left['score']);
        $matches = array_slice($matches, 0, 2);

        if ($matches === []) {
            return [
                'found' => true,
                'summary' => "Saran penerapan (Anda kerjakan sendiri):\n"
                    ."1. Sebutkan menu atau tujuan Anda (contoh: terima piutang, bayar hutang, jual di kasir, opname).\n"
                    ."2. Ito akan menunjukkan langkah dan tautan halaman.\n"
                    ."3. Ito tidak menulis, mengedit, menghapus, atau menambah data — hanya mengarahkan.",
                'links' => [
                    ['label' => 'Beranda', 'url' => url('/backoffice')],
                    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
                ],
            ];
        }

        $blocks = [];
        $links = [];
        foreach ($matches as $match) {
            $playbook = $match['playbook'];
            $steps = collect($playbook['steps'])
                ->values()
                ->map(fn (string $step, int $index) => ($index + 1).'. '.$step)
                ->implode("\n");
            $blocks[] = $playbook['title']."\nSaran penerapan (Anda kerjakan sendiri):\n".$steps;
            foreach ($playbook['links'] as $link) {
                $links[$link['url']] = $link;
            }
        }

        $footer = 'Catatan: Ito hanya membaca dan menyarankan. Semua simpan/ubah/hapus dilakukan oleh Anda di halaman di atas.';

        return [
            'found' => true,
            'summary' => implode("\n\n", $blocks)."\n\n".$footer,
            'links' => array_values($links),
        ];
    }
}
