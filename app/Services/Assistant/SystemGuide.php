<?php

namespace App\Services\Assistant;

class SystemGuide
{
    /**
     * @return array<int, array{id: string, title: string, keywords: array<int, string>, body: string, links: array<int, array{label: string, url: string}>}>
     */
    public function articles(): array
    {
        return [
            [
                'id' => 'terima-piutang',
                'title' => 'Terima Bayaran Piutang',
                'keywords' => ['piutang', 'terima bayaran', 'pelunasan', 'cicilan', 'debitur'],
                'body' => 'Penerimaan piutang dicatat sendiri oleh pengguna di menu Terima Bayaran Piutang. Pilih kas atau bank, isi tanggal dan nominal, lalu centang faktur pelanggan. Status faktur berjalan dari belum lunas (UNPAID), sebagian (PARTIAL), sampai lunas (PAID). Asisten hanya menunjukkan sisa tagihan dan halaman pencatatannya.',
                'links' => [
                    ['label' => 'Terima Bayaran Piutang', 'url' => route('backoffice.payments.receivables.create')],
                    ['label' => 'Piutang Pelanggan', 'url' => route('reports.ar-aging')],
                ],
            ],
            [
                'id' => 'status-piutang',
                'title' => 'Arti status piutang',
                'keywords' => ['status', 'unpaid', 'partial', 'paid', 'belum lunas', 'lunas'],
                'body' => 'UNPAID berarti belum ada pembayaran. PARTIAL berarti sudah ada cicilan dan masih ada sisa. PAID berarti tagihan lunas. Nama debitur, tanggal transaksi, dan keterangan barang tampil di daftar alokasi.',
                'links' => [
                    ['label' => 'Piutang Pelanggan', 'url' => route('reports.ar-aging')],
                ],
            ],
            [
                'id' => 'akun',
                'title' => 'Kode akun utama',
                'keywords' => ['akun', '1130', '1110', '1120', '2110', 'kode'],
                'body' => '1110 Kas dan 1120 Bank adalah uang yang diterima atau dikeluarkan. 1130 Piutang Usaha bertambah saat penjualan kredit dan berkurang saat pelunasan. 2110 Hutang Dagang adalah kewajiban ke pemasok. Jurnal penjualan kredit mendebit 1130 dan mengkredit pendapatan.',
                'links' => [
                    ['label' => 'Ringkasan Keuangan', 'url' => route('backoffice.finance.dashboard')],
                ],
            ],
            [
                'id' => 'stok',
                'title' => 'Stok gudang',
                'keywords' => ['stok', 'gudang', 'barang', 'opname', 'sku'],
                'body' => 'Stok yang bisa dijual atau ditransfer ada di menu Stok Barang. Transfer antar cabang dipilih lewat pencarian nama atau SKU, lalu jumlah diisi sendiri. Hitung ulang fisik dilakukan di Hitung Ulang Stok, bukan lewat asisten.',
                'links' => [
                    ['label' => 'Stok Barang', 'url' => url('/inventory')],
                    ['label' => 'Transfer Stok', 'url' => route('stock-transfer')],
                ],
            ],
            [
                'id' => 'penjualan',
                'title' => 'Riwayat penjualan',
                'keywords' => ['penjualan', 'omzet', 'faktur', 'struk', 'kasir', 'transaksi', 'terbaru', 'terakhir'],
                'body' => 'Setiap penjualan punya nomor faktur. Penjualan tunai masuk ke kas. Penjualan tempo membuat piutang. Transaksi terbaru bisa ditanyakan langsung. Akun master dan superadmin melihat seluruh cabang. Admin cabang hanya melihat cabangnya. Rincian satu faktur dibuka dari nomor referensinya.',
                'links' => [
                    ['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')],
                ],
            ],
            [
                'id' => 'kas',
                'title' => 'Kas dan bank',
                'keywords' => ['kas', 'bank', 'saldo'],
                'body' => 'Posisi kas dan bank dihitung dari jurnal akun 1110 dan 1120. Penerimaan piutang menambah saldo akun yang dipilih saat pencatatan. Asisten tidak memindahkan uang.',
                'links' => [
                    ['label' => 'Kas & Bank', 'url' => route('backoffice.finance.dashboard', ['tab' => 'kas_bank'])],
                ],
            ],
            [
                'id' => 'jurnal',
                'title' => 'Jurnal',
                'keywords' => ['jurnal', 'pembukuan', 'debit', 'kredit'],
                'body' => 'Jurnal mencatat debit dan kredit yang seimbang. Penjualan, pelunasan, dan mutasi stok yang bernilai membuat jurnal otomatis. Detail jurnal satu transaksi hanya tampil bagi pengguna yang berhak melihat akuntansi.',
                'links' => [
                    ['label' => 'Jurnal Umum', 'url' => route('backoffice.finance.dashboard', ['tab' => 'jurnal'])],
                ],
            ],
            [
                'id' => 'belanja',
                'title' => 'Alur belanja barang',
                'keywords' => ['hutang', 'pemasok', 'supplier', 'pembelian', 'terima barang'],
                'body' => 'Urutan belanja: pesan barang, terima barang, lalu catat pembayaran hutang di buku pemasok. Retur ke pemasok mengurangi hutang atau stok sesuai dokumen retur. Asisten tidak membuat pesanan.',
                'links' => [
                    ['label' => 'Pesan Barang', 'url' => route('backoffice.purchase-orders.index')],
                    ['label' => 'Buku Pemasok', 'url' => route('backoffice.suppliers.index')],
                ],
            ],
        ];
    }

    /**
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    public function search(string $query): array
    {
        $normalized = mb_strtolower($query);
        $matches = [];

        foreach ($this->articles() as $article) {
            $score = 0;
            foreach ($article['keywords'] as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $matches[] = ['score' => $score, 'article' => $article];
            }
        }

        usort($matches, fn (array $left, array $right) => $right['score'] <=> $left['score']);
        $matches = array_slice($matches, 0, 2);

        if ($matches === []) {
            return [
                'found' => false,
                'summary' => 'Tidak ditemukan panduan yang cocok di dalam aplikasi.',
                'links' => [],
            ];
        }

        $summary = collect($matches)
            ->map(fn (array $match) => $match['article']['title'].': '.$match['article']['body'])
            ->implode("\n\n");

        $links = [];
        foreach ($matches as $match) {
            foreach ($match['article']['links'] as $link) {
                $links[$link['url']] = $link;
            }
        }

        return [
            'found' => true,
            'summary' => $summary,
            'links' => array_values($links),
        ];
    }
}
