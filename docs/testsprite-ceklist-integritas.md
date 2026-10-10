# Ceklist Integritas Akuntansi — Maju Bersama

Sematkan berkas ini di TestSprite sebagai dokumen produk tambahan (format Markdown).

Tugas TestSprite: untuk setiap butir, tandai **SUDAH**, **SEBAGIAN**, atau **BELUM**, lalu buat pengujian hanya untuk perilaku yang memang harus lulus hari ini. Butir **SEBAGIAN** dan **BELUM** dicatat sebagai celah yang harus ditambah atau diperbaiki, bukan sebagai fitur yang sudah selesai.

Proyek: POS, gudang, dan pembukuan multi-cabang. Laravel 12, PHP 8.2, MySQL di produksi, SQLite di pengujian lokal. API memakai Laravel Sanctum. Antarmuka backoffice memakai Blade.

Endpoint lokal untuk uji: `http://localhost:8000`

Login lokal (akun seeder, bukan akun produksi):

- Master: `POST /api/login` dengan email `master@majubersama.test` dan password `password`. Simpan token Sanctum dan kirim sebagai `Authorization: Bearer`.
- Admin cabang dan kasir memakai pola email di `database/seeders/BranchAccessSeeder.php` dengan password yang sama.
- `POST /api/journals` hanya boleh berhasil untuk master. Admin cabang pada endpoint itu harus menerima 403.

Akun buku besar yang dipakai mesin penjualan:

| Kode | Nama | Peran |
| --- | --- | --- |
| 1110 | Kas | Debit penjualan tunai |
| 1130 | Piutang Usaha | Debit penjualan tempo |
| 1210 | Persediaan | Kredit saat barang keluar |
| 2110 | Hutang Dagang | Kredit pembelian kredit |
| 4110 | Pendapatan Penjualan | Kredit sebesar subtotal kotor |
| 4130 | Potongan Penjualan | Debit sebesar diskon |
| 5100 | Harga Pokok Penjualan | Debit HPP |

## Tahap 1 — Standarisasi logika akuntansi

### 1. Logika double-entry — SUDAH

Setiap jurnal harus menyentuh minimal dua akun dan saldo debit harus sama dengan kredit.

Yang sudah berjalan:

- `JournalPostingService::post()` menjumlahkan debit dan kredit dengan skala 2. Jika tidak sama, jurnal ditolak.
- Penjualan tunai menulis debit Kas (1110), kredit Pendapatan (4110), serta debit HPP (5100) dan kredit Persediaan (1210) bila ada harga pokok.
- Jika ada diskon, ditambah debit Potongan Penjualan (4130). Penjualan tempo mendebit Piutang Usaha (1130), bukan Kas.
- Penerimaan barang, transfer stok, opname, retur jual, retur beli, beban, pembayaran, dan saldo awal juga membuat jurnal berpasangan.

Uji yang harus lulus:

- `POST /api/journals` dengan debit sama dengan kredit diterima.
- `POST /api/journals` dengan debit tidak sama dengan kredit ditolak (422).
- `POST /api/checkout` satu barang berstok menghasilkan jurnal seimbang. Total debit jurnal sama dengan total kredit.

### 2. Studi referensi ERP terbuka — BELUM

Belum ada dokumen yang membedah relasi tabel keuangan dan inventori Odoo atau ERPNext.

TestSprite mencatat butir ini sebagai pekerjaan dokumentasi yang belum ada. Jangan membuat uji API untuk butir ini, dan jangan menandai lulus hanya karena tabel internal sudah ada.

### 3. Peta pemicu transaksi — SUDAH untuk jalur utama

| Aktivitas | Stok gudang | Uang dan jurnal |
| --- | --- | --- |
| Jualan POS (`POST /api/checkout`) | Berkurang | Jurnal penjualan |
| Pesanan beli | Belum berubah | Belum ada jurnal stok |
| Penerimaan barang (`POST /api/goods-receipts`) | Bertambah | Debit 1210, kredit kas atau 2110 |
| Retur penjualan | Bertambah kembali | Jurnal retur |
| Retur pembelian | Berkurang | Jurnal retur |
| Transfer antar cabang | Keluar di asal, masuk di tujuan | Jurnal mutasi |
| Opname | Menjadi kuantitas hasil hitung | Jurnal selisih persediaan |

Uji yang harus lulus:

- Checkout mengurangi stok dan membuat jurnal dalam satu kesatuan. Stok tidak boleh berkurang bila jurnal gagal.
- Checkout kuantitas di atas stok ditolak (422) dan stok tetap.
- Penerimaan barang menambah stok dan jurnal persediaan sekaligus.
- Pesanan beli sendiri tidak mengubah kuantitas stok.

## Tahap 2 — Integritas database

### 4. Transaksi database (ACID) — SUDAH

Posting penjualan, penerimaan, transfer, opname, retur, beban, pembayaran pemasok, dan saldo awal dibungkus transaksi database. Kegagalan di tengah membatalkan perubahan stok dan kas bersama-sama.

Uji yang harus lulus: penjualan yang ditolak karena stok habis tidak meninggalkan baris penjualan, tidak memotong stok, dan tidak menulis jurnal.

### 5. Kunci relasi hapus (RESTRICT) — SEBAGIAN

Yang sudah ketat:

- Produk yang sudah ada di pesanan beli, penerimaan barang, transfer stok, atau baris retur tidak bisa dihapus keras.
- Pemasok yang sudah punya pesanan beli tidak bisa dihapus keras.
- Pengguna tidak bisa menghapus cabang yang masih ditautkan lewat `users.branch_id`.

Yang harus ditambah atau diperbaiki:

- Produk yang sudah punya riwayat penjualan tetap bisa dihilangkan dari katalog. Penghapusan katalog hanya menandai produk terhapus dan menghapus baris stoknya, sehingga riwayat struk tidak menahan penghapusan.
- Pelanggan yang sudah punya penjualan tetap bisa dihapus. `sales.customer_id` melepas tautan saat pelanggan dihapus. `CustomerController::destroy` tidak menolak penghapusan itu.
- Baris inventori dan baris opname ikut terhapus bersama produk.
- Menghapus cabang ikut menghapus jurnal, penjualan, dan gudang cabang itu.

Uji yang harus mencatat celah, bukan lulus sebagai fitur selesai:

- Hapus pelanggan yang sudah punya minimal satu penjualan. Perilaku hari ini masih berhasil. Target yang benar: penghapusan ditolak selama riwayat transaksi ada.
- Hapus produk yang sudah punya baris `sale_items`. Perilaku hari ini masih berhasil lewat penghapusan katalog. Target yang benar: master produk dengan riwayat aktif tidak bisa dihapus.

### 6. Tipe data uang — SEBAGIAN

Tidak ada kolom harga atau saldo bertipe float atau double. Jurnal, diskon penjualan, dan nilai bayar memakai `DECIMAL`.

Yang harus diperbaiki:

- `sales.total_amount`, `sale_items.price`, dan `sale_items.subtotal` masih bilangan bulat. Pecahan rupiah dibulatkan di dokumen struk, sementara baris jurnal menyimpan dua angka di belakang koma.

Uji yang harus lulus hari ini: tidak ada selisih ratusan kali lipat antara harga master, total struk, dan jurnal. Uji yang mencatat celah: harga `10.50` tidak tersimpan utuh di harga baris struk.

## Tahap 3 — Keamanan backend

### 7. Validasi harga, diskon, dan stok di server — SEBAGIAN

Yang sudah berjalan:

- Harga satuan dihitung ulang dari `selling_price` atau harga grup pelanggan. Total kiriman klien tidak dipakai sebagai harga satuan.
- Stok dikunci dan diperiksa di server. Stok tidak cukup ditolak.

Yang masih dipercaya dari klien, lalu hanya dibatasi:

- `discount_amount` ditolak jika negatif atau lebih besar dari subtotal.
- `paid_amount` untuk penjualan tempo datang dari klien.

Uji yang harus lulus:

- Checkout dengan harga satuan yang dikirim klien berbeda dari harga master tetap menyimpan harga master.
- Diskon lebih besar dari subtotal ditolak (422).
- Kuantitas di atas stok ditolak (422).

Celah yang dicatat: belum ada penolakan khusus bila klien mengirim total transaksi palsu pada lapangan selain harga satuan. Diskon bertingkat (persen lalu nominal, atau dua lapisan diskon) belum dimodelkan. Yang ada hanya satu `discount_amount`.

### 8. Otorisasi berbasis peran — SEBAGIAN

Yang sudah terkunci ke master:

- `view-accounting` dan `manage-system` hanya master.
- `POST /api/journals` dan pembelian enterprise berada di belakang `view-accounting`.

Yang harus diperbaiki:

- `POST /backoffice/finance/journals` memakai izin `manage-branch-operations`, jadi admin cabang masih bisa membuat jurnal manual.
- Belum ada rute ubah, hapus, atau pembatalan jurnal. Target produk: revisi atau pembatalan jurnal hanya boleh dieksekusi master.

Uji yang harus lulus:

- Kasir tidak dapat membuat jurnal.
- Pengguna tanpa token menerima 401 pada `POST /api/journals`.
- Admin cabang yang memanggil `POST /api/journals` menerima 403.

Celah yang dicatat: admin cabang masih dapat membuat jurnal manual lewat dasbor keuangan web.

### 9. Sanitasi kueri — SUDAH pada jalur aplikasi

Interaksi data memakai query builder. Impor piutang memakai parameter terikat. SQL mentah di kartu stok adalah teks tetap, bukan input pengguna.

Uji yang harus lulus: nama pelanggan, nama produk, atau kata pencarian yang berisi tanda kutip dan `OR 1=1` diperlakukan sebagai teks, tidak mengubah hasil kueri menjadi seluruh tabel.

## Tahap 4 — Pengujian dan memori proyek

### 10. Uji tekanan saldo — SEBAGIAN

Yang sudah tercakup uji sebelumnya dan harus tetap lulus:

- Diskon satu tingkat pada POS dan jurnal empat baris tetap seimbang.
- Cicilan hutang dagang: sebagian, lalu pelunasan. Lebih bayar ditolak (422).
- Penjualan stok nol ditolak (422).
- Laporan laba rugi dan neraca selisihnya 0.

Yang belum ada dan harus ditambah sebelum butir ini dianggap selesai:

- Retur barang parsial, lalu cek stok, piutang atau kas, HPP, dan neraca tetap seimbang.
- Diskon bertingkat. Saat ini sistem hanya punya satu nominal diskon, jadi skenario ini adalah celah produk, bukan uji yang boleh ditandai lulus.
- Pembayaran split (sebagian kas, sebagian bank atau piutang) dalam satu struk. Saat ini penjualan tunai memakai satu metode. Penjualan tempo punya `paid_amount`, tetapi bukan pembayaran pecah kas dan bank.

### 11. Dokumentasi relasi final — SEBAGIAN

`architecture_decisions.md` menyimpan keputusan ADR-001 sampai ADR-019. Itu belum menjadi diagram relasi final yang dibandingkan dengan ERP terbuka.

Batasan yang harus disebut di setiap uji gudang:

- Satu produk masih satu baris stok per cabang. Produk yang sama belum bisa memiliki saldo terpisah di dua gudang pada cabang yang sama.
- Pembukuan mengikuti cabang (`journal_headers.branch_id`), bukan gudang.

### 12. Catatan riwayat bug — SEBAGIAN

`docs/BUG_REGISTRY.md` berisi BUG-001 sampai BUG-005. Dua kejadian berikut belum masuk catatan dan tidak boleh muncul lagi:

- Nilai uang membesar sekitar seratus kali karena skala desimal salah. Harga master, total struk, dan jurnal harus tetap satu skala rupiah.
- Beranda `/backoffice` rusak untuk akun pusat yang tidak punya cabang, atau cabangnya sudah dihapus lunak. Halaman harus tetap membuka dan menampilkan seluruh cabang.

## Batasan yang tidak boleh dilanggar uji

- Jangan menganggap pesanan beli sebagai penambah stok. Stok belanja naik saat penerimaan barang.
- Jangan mengirim total harga dari klien sebagai sumber harga satuan.
- Jangan memakai float untuk menguji kesamaan uang. Bandingkan dengan dua angka desimal.
- Neraca setelah rangkaian uji harus selisih 0.
- Satu produk di dua gudang pada cabang yang sama masih ditolak oleh desain saat ini. Uji itu mencatat batas, bukan kegagalan acak.
- Jangan menghapus data produksi. Uji memakai server lokal `http://localhost:8000`.

## Ringkasan untuk laporan TestSprite

| Butir | Status | Tindak lanjut |
| --- | --- | --- |
| 1. Double-entry | SUDAH | Pertahankan uji jurnal seimbang dan jurnal timpang |
| 2. Studi Odoo atau ERPNext | BELUM | Dokumentasi, bukan uji API |
| 3. Pemicu stok dan jurnal | SUDAH | Pertahankan uji POS, penerimaan, dan penolakan stok habis |
| 4. Transaksi ACID | SUDAH | Pastikan penolakan tidak meninggalkan stok atau jurnal |
| 5. Foreign key RESTRICT | SEBAGIAN | Tambah penolakan hapus produk dan pelanggan yang punya riwayat |
| 6. DECIMAL untuk uang | SEBAGIAN | Ubah total dan harga baris struk dari integer ke decimal |
| 7. Validasi server | SEBAGIAN | Harga satuan sudah aman. Diskon bertingkat dan pembayaran split belum |
| 8. RBAC jurnal | SEBAGIAN | Kunci jurnal manual web ke master. Tambah pembatalan jurnal khusus master |
| 9. Prepared statement | SUDAH | Pertahankan uji input berbahaya sebagai teks |
| 10. Stress test saldo | SEBAGIAN | Tambah retur parsial berulang. Diskon bertingkat dan split belum dimodelkan |
| 11. Dokumen relasi final | SEBAGIAN | Tulis diagram relasi dan alasan batas satu stok per produk per cabang |
| 12. Riwayat bug | SEBAGIAN | Catat bug skala desimal dan beranda akun pusat tanpa cabang |
