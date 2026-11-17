<?php

namespace App\Services\Assistant;

class SystemGuide
{
    /**
     * Peta lengkap letak UI dan arti data di dalam aplikasi.
     * Asisten hanya membaca peta ini untuk mengarahkan pengguna; tidak menulis data.
     *
     * @return array<int, array{id: string, title: string, keywords: array<int, string>, body: string, links: array<int, array{label: string, url: string}>}>
     */
    public function articles(): array
    {
        return [
            [
                'id' => 'beranda',
                'title' => 'Beranda / Panel Admin',
                'keywords' => ['beranda', 'dashboard', 'panel admin', 'overview', 'monitoring', 'ringkasan cabang'],
                'body' => 'Beranda ada di sidebar kiri atas (tombol Beranda) atau URL /backoffice dan /dashboard. Menampilkan ringkasan operasional cabang, monitoring penjualan, dan pintasan modul. Bukan tempat mencatat transaksi.',
                'links' => [
                    ['label' => 'Beranda', 'url' => url('/backoffice')],
                ],
            ],
            [
                'id' => 'pos',
                'title' => 'Kasir (POS)',
                'keywords' => ['kasir', 'pos', 'struk', 'shift', 'tunai', 'tempo', 'cetak struk', 'buka shift', 'tutup shift'],
                'body' => 'Kasir (POS) ada di sidebar kiri atas atau URL /pos. Dipakai untuk menjual barang, buka/tutup shift, cetak struk, dan (jika berhak) catat biaya cepat. Penjualan tunai masuk kas; penjualan tempo membuat piutang. Asisten tidak membuat penjualan.',
                'links' => [
                    ['label' => 'Kasir (POS)', 'url' => route('pos')],
                ],
            ],
            [
                'id' => 'riwayat-penjualan',
                'title' => 'Riwayat Penjualan',
                'keywords' => ['riwayat penjualan', 'penjualan', 'omzet', 'faktur', 'struk', 'transaksi', 'terbaru', 'terakhir', 'nota'],
                'body' => 'Riwayat Penjualan ada di sidebar grup Jualan. Menampilkan daftar faktur, total, status bayar, dan tautan rincian. Koreksi faktur (jika diizinkan) dibuka dari baris faktur tersebut. Transaksi terbaru juga bisa ditanyakan ke asisten secara baca saja.',
                'links' => [
                    ['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')],
                ],
            ],
            [
                'id' => 'terima-piutang',
                'title' => 'Terima Bayaran Piutang',
                'keywords' => ['piutang', 'terima bayaran', 'pelunasan', 'cicilan', 'debitur', 'tagihan', 'bayar piutang'],
                'body' => 'Terima Bayaran Piutang ada di sidebar grup Jualan. Pilih kas/bank, tanggal, nominal, lalu centang faktur pelanggan. Status faktur: UNPAID (belum lunas), PARTIAL (sebagian), PAID (lunas). Asisten hanya menunjukkan sisa tagihan dan halaman pencatatannya.',
                'links' => [
                    ['label' => 'Terima Bayaran Piutang', 'url' => route('backoffice.payments.receivables.create')],
                    ['label' => 'Piutang Pelanggan', 'url' => route('reports.ar-aging')],
                ],
            ],
            [
                'id' => 'status-piutang',
                'title' => 'Arti status piutang',
                'keywords' => ['status', 'unpaid', 'partial', 'paid', 'belum lunas', 'lunas', 'aging'],
                'body' => 'UNPAID = belum ada pembayaran. PARTIAL = sudah ada cicilan, masih ada sisa. PAID = lunas. Daftar aging dan nama debitur ada di Piutang Pelanggan (grup Jualan).',
                'links' => [
                    ['label' => 'Piutang Pelanggan', 'url' => route('reports.ar-aging')],
                ],
            ],
            [
                'id' => 'retur-pelanggan',
                'title' => 'Retur dari Pelanggan',
                'keywords' => ['retur pelanggan', 'retur penjualan', 'kembalikan barang pelanggan', 'sales return'],
                'body' => 'Retur dari Pelanggan ada di sidebar grup Jualan. Dipakai mencatat barang yang dikembalikan pelanggan dan menyesuaikan penjualan/stok sesuai dokumen retur. Asisten tidak membuat retur.',
                'links' => [
                    ['label' => 'Retur dari Pelanggan', 'url' => route('backoffice.sales-returns.index')],
                    ['label' => 'Laporan Retur Penjualan', 'url' => route('reports.sales-returns')],
                ],
            ],
            [
                'id' => 'pelanggan',
                'title' => 'Data Pelanggan',
                'keywords' => ['pelanggan', 'customer', 'debitur', 'multi harga', 'harga pelanggan'],
                'body' => 'Data Pelanggan ada di sidebar grup Jualan (bagi yang berhak mengelola katalog). Menyimpan nama, kontak, dan pengaturan harga. Dipakai saat penjualan tempo dan penerimaan piutang.',
                'links' => [
                    ['label' => 'Data Pelanggan', 'url' => route('backoffice.customers.index')],
                ],
            ],
            [
                'id' => 'pesan-barang',
                'title' => 'Pesan Barang (PO)',
                'keywords' => ['pesan barang', 'purchase order', 'po', 'pemesanan', 'order pemasok'],
                'body' => 'Pesan Barang (PO) adalah langkah 1 di sidebar grup Belanja Barang. Membuat pesanan ke pemasok sebelum barang diterima. Asisten tidak membuat PO.',
                'links' => [
                    ['label' => 'Pesan Barang', 'url' => route('backoffice.purchase-orders.index')],
                ],
            ],
            [
                'id' => 'terima-barang',
                'title' => 'Terima Barang',
                'keywords' => ['terima barang', 'goods receipt', 'penerimaan barang', 'gr', 'barang masuk'],
                'body' => 'Terima Barang adalah langkah 2 di sidebar grup Belanja Barang (URL /purchases/goods-receipts). Mencatat barang masuk ke gudang dan biasanya menambah stok serta hutang/jurnal terkait. Asisten tidak membuat penerimaan.',
                'links' => [
                    ['label' => 'Terima Barang', 'url' => route('purchases.goods-receipts.index')],
                ],
            ],
            [
                'id' => 'buku-pemasok',
                'title' => 'Buku Pemasok & Hutang',
                'keywords' => ['pemasok', 'supplier', 'hutang', 'utang', 'buku pemasok', 'bayar hutang', 'ap'],
                'body' => 'Buku Pemasok & Hutang adalah langkah 3 di sidebar grup Belanja Barang. Menyimpan data pemasok, sisa hutang, dan pencatatan pembayaran hutang (FIFO). Riwayat bayar hutang enterprise ada di menu Riwayat Bayar Hutang.',
                'links' => [
                    ['label' => 'Buku Pemasok', 'url' => route('backoffice.suppliers.index')],
                    ['label' => 'Hutang Pemasok (Aging)', 'url' => route('reports.ap-aging')],
                ],
            ],
            [
                'id' => 'retur-pemasok',
                'title' => 'Retur ke Pemasok',
                'keywords' => ['retur pemasok', 'retur pembelian', 'kembalikan ke supplier', 'purchase return'],
                'body' => 'Retur ke Pemasok ada di sidebar grup Belanja Barang (akses enterprise). Mengurangi hutang atau stok sesuai dokumen retur. Asisten tidak membuat retur.',
                'links' => [
                    ['label' => 'Retur ke Pemasok', 'url' => route('backoffice.purchase-returns.index')],
                ],
            ],
            [
                'id' => 'bayar-hutang',
                'title' => 'Riwayat Bayar Hutang',
                'keywords' => ['riwayat bayar hutang', 'supplier payment', 'pembayaran hutang', 'bayar supplier'],
                'body' => 'Riwayat Bayar Hutang ada di sidebar grup Belanja Barang (akses enterprise). Menampilkan histori pembayaran ke pemasok. Pencatatan bayar hutang harian juga bisa dari Buku Pemasok.',
                'links' => [
                    ['label' => 'Riwayat Bayar Hutang', 'url' => route('backoffice.supplier-payments.index')],
                    ['label' => 'Laporan Bayar Hutang', 'url' => route('reports.supplier-payments')],
                ],
            ],
            [
                'id' => 'stok',
                'title' => 'Stok Barang',
                'keywords' => ['stok', 'gudang', 'barang', 'sku', 'persediaan', 'qty', 'quantity', 'stok barang'],
                'body' => 'Stok Barang ada di sidebar grup Stok Gudang (URL /inventory). Menampilkan kuantitas yang tersedia per produk. Transfer, opname, dan stok awal punya menu terpisah di grup yang sama. Asisten hanya membaca stok, tidak mengubahnya.',
                'links' => [
                    ['label' => 'Stok Barang', 'url' => url('/inventory')],
                    ['label' => 'Transfer Stok', 'url' => route('stock-transfer')],
                ],
            ],
            [
                'id' => 'transfer-stok',
                'title' => 'Transfer Stok',
                'keywords' => ['transfer stok', 'pindah stok', 'mutasi stok', 'kirim ke cabang'],
                'body' => 'Transfer Stok ada di sidebar grup Stok Gudang. Pilih barang lewat pencarian nama/SKU, isi jumlah, lalu proses pindah antar lokasi/cabang. Cetak surat jalan dari detail transfer. Asisten tidak memindahkan stok.',
                'links' => [
                    ['label' => 'Transfer Stok', 'url' => route('stock-transfer')],
                ],
            ],
            [
                'id' => 'stok-awal',
                'title' => 'Input Stok Awal',
                'keywords' => ['stok awal', 'opening stock', 'saldo awal stok', 'input stok awal'],
                'body' => 'Input Stok Awal ada di sidebar grup Stok Gudang. Dipakai saat setup cabang untuk memasukkan kuantitas awal sebelum operasional penuh. Bukan untuk koreksi harian (gunakan Opname).',
                'links' => [
                    ['label' => 'Input Stok Awal', 'url' => route('inventory.opening-stock.create')],
                ],
            ],
            [
                'id' => 'opname',
                'title' => 'Hitung Ulang Stok (Opname)',
                'keywords' => ['opname', 'stok opname', 'hitung ulang', 'adjustment', 'selisih stok', 'koreksi stok'],
                'body' => 'Hitung Ulang Stok (Opname) ada di sidebar grup Stok Gudang. Membandingkan stok sistem dengan fisik lalu mencatat penyesuaian. Asisten tidak membuat adjustment.',
                'links' => [
                    ['label' => 'Hitung Ulang Stok', 'url' => route('inventory.adjustments.index')],
                ],
            ],
            [
                'id' => 'kartu-stok',
                'title' => 'Kartu Stok',
                'keywords' => ['kartu stok', 'stock card', 'mutasi barang', 'riwayat stok'],
                'body' => 'Kartu Stok ada di sidebar grup Stok Gudang (bagi yang melihat akuntansi) dan juga di Pusat Laporan. Menampilkan mutasi masuk/keluar per produk sepanjang periode.',
                'links' => [
                    ['label' => 'Kartu Stok', 'url' => route('reports.inventory.stock-card')],
                ],
            ],
            [
                'id' => 'gudang',
                'title' => 'Daftar Gudang',
                'keywords' => ['daftar gudang', 'warehouse', 'lokasi gudang', 'master gudang'],
                'body' => 'Daftar Gudang ada di sidebar grup Stok Gudang. Mengelola nama/lokasi gudang cabang yang dipakai transfer dan penyimpanan stok.',
                'links' => [
                    ['label' => 'Daftar Gudang', 'url' => route('backoffice.warehouses.index')],
                ],
            ],
            [
                'id' => 'akun',
                'title' => 'Kode akun utama',
                'keywords' => ['akun', '1130', '1110', '1120', '2110', 'kode akun', 'coa', 'bagan akun'],
                'body' => '1110 Kas dan 1120 Bank = uang tunai/rekening. 1130 Piutang Usaha bertambah saat penjualan kredit dan berkurang saat pelunasan. 2110 Hutang Dagang = kewajiban ke pemasok. Ringkasan dan jurnal ada di grup Uang & Akuntansi.',
                'links' => [
                    ['label' => 'Ringkasan Keuangan', 'url' => route('backoffice.finance.dashboard')],
                    ['label' => 'Jurnal Umum', 'url' => route('backoffice.finance.dashboard', ['tab' => 'jurnal'])],
                ],
            ],
            [
                'id' => 'keuangan',
                'title' => 'Ringkasan Keuangan',
                'keywords' => ['keuangan', 'ringkasan keuangan', 'finance', 'dashboard keuangan'],
                'body' => 'Ringkasan Keuangan ada di sidebar grup Uang & Akuntansi. Pintasan ke kas/bank, biaya, jurnal, dan ringkasan posisi uang cabang.',
                'links' => [
                    ['label' => 'Ringkasan Keuangan', 'url' => route('backoffice.finance.dashboard')],
                ],
            ],
            [
                'id' => 'kas',
                'title' => 'Kas dan bank',
                'keywords' => ['kas', 'bank', 'saldo', 'kas bank', 'posisi kas', 'mutasi kas', 'transfer kas'],
                'body' => 'Kas & Bank ada di sidebar grup Uang & Akuntansi (tab kas_bank). Posisi dihitung dari jurnal akun 1110/1120. Mutasi antar kas/bank dan transaksi kas manual ada di modul terkait. Asisten tidak memindahkan uang.',
                'links' => [
                    ['label' => 'Kas & Bank', 'url' => route('backoffice.finance.dashboard', ['tab' => 'kas_bank'])],
                    ['label' => 'Transfer Kas', 'url' => route('backoffice.cash-transfers.index')],
                ],
            ],
            [
                'id' => 'biaya',
                'title' => 'Biaya Operasional',
                'keywords' => ['biaya', 'pengeluaran', 'expense', 'operasional', 'beban'],
                'body' => 'Biaya Operasional ada di sidebar grup Uang & Akuntansi (tab pengeluaran) dan juga di /backoffice/expenses. Mencatat biaya cabang dari kas/bank. Kategori biaya dikelola di Pengaturan → Kategori Biaya (master).',
                'links' => [
                    ['label' => 'Biaya Operasional', 'url' => route('backoffice.finance.dashboard', ['tab' => 'pengeluaran'])],
                    ['label' => 'Daftar Pengeluaran', 'url' => route('backoffice.expenses.index')],
                ],
            ],
            [
                'id' => 'jurnal',
                'title' => 'Jurnal Umum',
                'keywords' => ['jurnal', 'pembukuan', 'debit', 'kredit', 'ledger', 'buku besar'],
                'body' => 'Jurnal Umum ada di sidebar grup Uang & Akuntansi (tab jurnal) bagi yang berhak melihat akuntansi. Mencatat debit-kredit seimbang. Penjualan, pelunasan, dan mutasi bernilai biasanya membuat jurnal otomatis. Buku besar formal juga di Pusat Laporan / laporan akuntansi.',
                'links' => [
                    ['label' => 'Jurnal Umum', 'url' => route('backoffice.finance.dashboard', ['tab' => 'jurnal'])],
                    ['label' => 'Buku Besar', 'url' => route('reports.accounting.ledger')],
                ],
            ],
            [
                'id' => 'harta-tetap',
                'title' => 'Harta Tetap',
                'keywords' => ['harta tetap', 'aset tetap', 'fixed asset', 'penyusutan', 'depresiasi'],
                'body' => 'Harta Tetap ada di sidebar grup Uang & Akuntansi. Mendaftarkan aset cabang dan menjalankan penyusutan. Laporan nilai buku dan depresiasi ada di Pusat Laporan.',
                'links' => [
                    ['label' => 'Harta Tetap', 'url' => route('backoffice.fixed-assets.index')],
                    ['label' => 'Laporan Harta Tetap', 'url' => route('reports.fixed-assets')],
                ],
            ],
            [
                'id' => 'saldo-awal',
                'title' => 'Saldo Awal (Setup)',
                'keywords' => ['saldo awal', 'opening balance', 'setup saldo', 'neraca awal'],
                'body' => 'Saldo Awal (Setup) ada di sidebar grup Uang & Akuntansi (khusus manage-system). Dipakai sekali saat setup cabang untuk memasukkan saldo akun awal. Bukan transaksi harian.',
                'links' => [
                    ['label' => 'Saldo Awal', 'url' => route('backoffice.opening-balances.create')],
                ],
            ],
            [
                'id' => 'katalog',
                'title' => 'Katalog Produk',
                'keywords' => ['katalog', 'produk', 'sku', 'harga jual', 'harga beli', 'master barang', 'tambah produk'],
                'body' => 'Katalog Produk ada di sidebar grup Pengaturan (atau tautan terpisah untuk kasir yang hanya melihat). Menyimpan SKU, nama, harga, dan atribut jual. Produk baru lintas cabang biasanya hanya dibuat master. Asisten tidak menambah/ubah produk.',
                'links' => [
                    ['label' => 'Katalog Produk', 'url' => route('backoffice.products.index')],
                ],
            ],
            [
                'id' => 'kategori-produk',
                'title' => 'Kategori Produk',
                'keywords' => ['kategori produk', 'kategori barang', 'category'],
                'body' => 'Kategori Produk ada di sidebar grup Pengaturan (akses sistem). Mengelompokkan barang di katalog.',
                'links' => [
                    ['label' => 'Kategori Produk', 'url' => route('backoffice.categories.index')],
                ],
            ],
            [
                'id' => 'staf',
                'title' => 'Staf & Kasir',
                'keywords' => ['staf', 'kasir', 'user', 'pengguna', 'akun login', 'role', 'hak akses'],
                'body' => 'Staf & Kasir ada di sidebar grup Pengaturan. Mengelola akun login, role (kasir/admin cabang/dll), dan cabang penempatan. Asisten tidak menambah atau mengubah user.',
                'links' => [
                    ['label' => 'Staf & Kasir', 'url' => route('backoffice.users.index')],
                ],
            ],
            [
                'id' => 'cabang',
                'title' => 'Manajemen Cabang',
                'keywords' => ['cabang', 'branch', 'unit bisnis', 'manajemen cabang'],
                'body' => 'Manajemen Cabang ada di sidebar grup Pengaturan (master/sistem). Mengelola daftar cabang dan konfigurasi unit bisnis. Admin cabang hanya melihat datanya sendiri; master melihat semua cabang.',
                'links' => [
                    ['label' => 'Manajemen Cabang', 'url' => route('backoffice.branches.index')],
                ],
            ],
            [
                'id' => 'kategori-biaya',
                'title' => 'Kategori Biaya',
                'keywords' => ['kategori biaya', 'expense category', 'jenis pengeluaran'],
                'body' => 'Kategori Biaya ada di sidebar grup Pengaturan. Dipakai saat mencatat Biaya Operasional agar pengeluaran terkalsifikasi.',
                'links' => [
                    ['label' => 'Kategori Biaya', 'url' => route('backoffice.expense-categories.index')],
                ],
            ],
            [
                'id' => 'pesan-internal',
                'title' => 'Pesan Internal',
                'keywords' => ['pesan', 'pesan internal', 'chat internal', 'komunikasi cabang'],
                'body' => 'Pesan Internal ada di sidebar sebagai tautan tersendiri. Untuk komunikasi antar cabang dan pusat. Asisten tidak mengirim pesan atas nama pengguna.',
                'links' => [
                    ['label' => 'Pesan Internal', 'url' => route('backoffice.messages.index')],
                ],
            ],
            [
                'id' => 'pusat-laporan',
                'title' => 'Pusat Laporan',
                'keywords' => ['pusat laporan', 'laporan', 'report center', 'aktivitas transaksi', 'modul laporan'],
                'body' => 'Pusat Laporan ada di sidebar bawah (badge Laporan). Ada dua tampilan: Modul Laporan (katalog laporan formal) dan Aktivitas Transaksi (buku kas harian / ledger aktivitas). Klik nomor referensi untuk membuka rincian transaksi.',
                'links' => [
                    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
                ],
            ],
            [
                'id' => 'laporan-keuangan',
                'title' => 'Laporan keuangan formal',
                'keywords' => ['neraca', 'laba rugi', 'laba', 'rugi', 'neraca saldo', 'trial balance', 'arus kas', 'cash flow', 'income statement', 'balance sheet'],
                'body' => 'Laporan formal ada di Pusat Laporan (akses akuntansi): Laba Rugi, Neraca, Neraca Saldo (Trial Balance), dan Arus Kas. Dipakai untuk membaca posisi keuangan, bukan untuk mencatat transaksi.',
                'links' => [
                    ['label' => 'Laba Rugi', 'url' => route('reports.income-statement')],
                    ['label' => 'Neraca', 'url' => route('reports.balance-sheet')],
                    ['label' => 'Neraca Saldo', 'url' => route('reports.trial-balance')],
                    ['label' => 'Arus Kas', 'url' => route('reports.cash-flow')],
                ],
            ],
            [
                'id' => 'laporan-pembelian',
                'title' => 'Laporan pembelian & gudang',
                'keywords' => ['laporan pembelian', 'laporan gudang', 'stok gudang laporan', 'warehouse inventory'],
                'body' => 'Di Pusat Laporan (akses akuntansi) tersedia Laporan Pembelian, Kartu Stok, Aging Hutang, Bayar Hutang, dan Inventaris per Gudang untuk membaca data belanja dan persediaan.',
                'links' => [
                    ['label' => 'Laporan Pembelian', 'url' => route('reports.purchases')],
                    ['label' => 'Inventaris Gudang', 'url' => route('reports.warehouse-inventory')],
                    ['label' => 'Aging Hutang', 'url' => route('reports.ap-aging')],
                ],
            ],
            [
                'id' => 'ito',
                'title' => 'Ito (asisten baca saja)',
                'keywords' => ['ito', 'asisten', 'assistant', 'ai', 'bantuan', 'tanya'],
                'body' => 'Ito adalah asisten baca saja di pojok kanan bawah (dan halaman /backoffice/assistant). Hanya membaca data yang boleh Anda lihat, menerjemahkan pertanyaan, dan mengarahkan ke halaman UI yang tepat. Dilarang menulis, mengedit, menghapus, menambah, posting, atau memproses apapun di aplikasi.',
                'links' => [
                    ['label' => 'Halaman Ito', 'url' => route('backoffice.assistant.index')],
                ],
            ],
            [
                'id' => 'belanja',
                'title' => 'Alur belanja barang',
                'keywords' => ['alur belanja', 'urutan belanja', 'cara belanja', 'pembelian'],
                'body' => 'Urutan belanja di sidebar Belanja Barang: 1) Pesan Barang (PO), 2) Terima Barang, 3) Buku Pemasok & Hutang. Retur ke pemasok dan riwayat bayar hutang ada di grup yang sama (akses sesuai role). Asisten tidak membuat pesanan.',
                'links' => [
                    ['label' => 'Pesan Barang', 'url' => route('backoffice.purchase-orders.index')],
                    ['label' => 'Terima Barang', 'url' => route('purchases.goods-receipts.index')],
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
                    $score += max(1, (int) floor(mb_strlen($keyword) / 4));
                }
            }
            if (str_contains($normalized, mb_strtolower($article['title']))) {
                $score += 3;
            }
            if ($score > 0) {
                $matches[] = ['score' => $score, 'article' => $article];
            }
        }

        usort($matches, fn (array $left, array $right) => $right['score'] <=> $left['score']);
        $matches = array_slice($matches, 0, 3);

        if ($matches === []) {
            return [
                'found' => false,
                'summary' => 'Tidak ditemukan panduan yang cocok di dalam aplikasi. Coba sebutkan nama menu (misalnya Kasir, Piutang, Stok, Jurnal, atau Pusat Laporan).',
                'links' => [
                    ['label' => 'Beranda', 'url' => url('/backoffice')],
                    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
                ],
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
