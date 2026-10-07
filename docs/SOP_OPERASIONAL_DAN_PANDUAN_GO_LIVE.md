# PANDUAN STANDAR OPERASIONAL PROSEDUR (SOP) OPERASIONAL
# SISTEM ERP & POS MULTI-STORE "MAJU BERSAMA"
**Panduan Hari Pertama Peluncuran Sistem: Dari Titik Nol (Stok 0, Saldo 0) Menuju Operasional Penuh**

---

## 📌 DAFTAR ISI
1. [Prinsip Dasar: Memahami Alur Hulu ke Hilir](#1-prinsip-dasar-memahami-alur-hulu-ke-hilir)
2. [Divisi 1: IT Admin & Superadmin (Pondasi Sistem & Pengguna)](#2-divisi-1-it-admin--superadmin-pondasi-sistem--pengguna)
3. [Divisi 2: Keuangan & Akunting (Setup Saldo Awal / Opening Balance)](#3-divisi-2-keuangan--akunting-setup-saldo-awal--opening-balance)
4. [Divisi 3: Pengadaan & Master Data (Kategori, Produk & Supplier)](#4-divisi-3-pengadaan--master-data-kategori-produk--supplier)
5. [Divisi 4: Pergudangan & Logistik (PO & Penerimaan Barang Masuk)](#5-divisi-4-pergudangan--logistik-po--penerimaan-barang-masuk)
6. [Divisi 5: Kasir & Frontline Toko (Buka Shift, Penjualan & Tutup Kasir)](#6-divisi-5-kasir--frontline-toko-buka-shift-penjualan--tutup-kasir)
7. [Divisi 6: Supervisor & Manajer Toko (Rekonsiliasi & Evaluasi Sore)](#7-divisi-6-supervisor--manajer-toko-rekonsiliasi--evaluasi-sore)
8. [Divisi 7: Keuangan Malam Hari (Laba Rugi & Tutup Buku Harian)](#8-divisi-7-keuangan-malam-hari-laba-rugi--tutup-buku-harian)
9. [Tips Simulasi Integrasi 3 Hari (Playbook Transisi Mulus Tanpa Pusing)](#9-tips-simulasi-integrasi-3-hari-playbook-transisi-mulus-tanpa-pusing)

---

## 1. PRINSIP DASAR: MEMAHAMI ALUR HULU KE HILIR

Sistem ERP & POS Maju Bersama bekerja layaknya **aliran air sungai**:
- **Hulu (Awal)**: Uang modal dan data barang disiapkan terlebih dahulu.
- **Tengah (Proses)**: Barang dibeli dari supplier dan masuk ke gudang (stok bertambah).
- **Hilir (Muara)**: Kasir menjual barang ke pelanggan (stok berkurang, uang masuk laci).

```
   [ SUPERADMIN ]           [ KEUANGAN ]           [ PENGADAAN ]
Pengaturan Toko & User  ->  Isi Saldo Modal   ->  Input Data Barang
                                                        │
                                                        ▼
      [ KASIR ]            [ SUPERVISOR ]            [ GUDANG ]
  Jual Barang & Struk  <-  Buka Shift & Kasir  <-  Terima Barang Masuk
        │
        ▼
  [ LAPORAN ]
Laba Bersih & Neraca
```

> **Ingat Aturan Emas:**  
> Kasir **TIDAK BISA** menjual barang jika gudang belum menerima barang. Gudang **TIDAK BISA** menerima barang jika admin belum mendaftarkan nama produk. Maka, urutan pengerjaan wajib berjenjang dan tertib!

---

## 2. DIVISI 1: IT ADMIN & SUPERADMIN (PONDASI SISTEM & PENGGUNA)
*Waktu Pelaksanaan: Pagi Hari (Pukul 07.00 - 07.30)*  
*Tujuan: Memastikan pintu akses dan identitas cabang toko siap dipakai.*

### Langkah Demi Langkah:
1. **Login Akun Master**
   - Buka browser (Chrome/Edge), akses alamat sistem ERP Maju Bersama (`http://localhost:8000` atau domain server toko).
   - Masukkan email dan password tingkat **Master / Superadmin**.
2. **Cek Cabang Toko (Branch)**
   - Masuk ke menu: `Backoffice` > `Branches (Cabang)`.
   - Pastikan cabang utama atau toko cabang sudah terdaftar dengan nama resmi, alamat, dan nomor kontak yang benar.
3. **Cek Gudang (Warehouses)**
   - Masuk ke menu: `Backoffice` > `Warehouses (Gudang)`.
   - Pastikan minimal ada 1 gudang aktif untuk cabang tersebut (contoh: *Gudang Utama Toko Cabang 1*).
4. **Buat Akun Karyawan Sesuai Divisi**
   - Masuk ke menu: `Backoffice` > `Users`.
   - Daftarkan akun untuk staf yang bertugas hari ini:
     - **Admin Gudang / Operasional**: Peran (*Role*) `branch_admin`.
     - **Kasir Toko**: Peran (*Role*) `cashier`.
   - Berikan password sementara yang mudah diingat staf (misal: `kasir123`) dan minta mereka login untuk uji coba.

---

## 3. DIVISI 2: KEUANGAN & AKUNTING (SETUP SALDO AWAL / OPENING BALANCE)
*Waktu Pelaksanaan: Pagi Hari (Pukul 07.30 - 08.00)*  
*Tujuan: Mengisi saldo nol menjadi saldo riil modal usaha agar neraca keuangan seimbang.*

### Langkah Demi Langkah:
1. **Buka Formulir Saldo Awal**
   - Masuk ke menu: `Backoffice` > `Opening Balances (Saldo Awal)`.
2. **Hitung Uang Fisik & Rekening Hari Ini**
   - **Kas di Tangan / Brankas Toko (Cash in Drawer)**: Hitung uang tunai fisik yang ada di brankas toko (di luar uang kembalian kasir). Masukkan nominalnya (contoh: `Rp 5.000.000`).
   - **Saldo Bank Operasional**: Cek mutasi rekening bank toko (BCA/Mandiri dsb). Masukkan nominal saldo riil saat ini (contoh: `Rp 25.000.000`).
   - **Hutang Bawaan (Bila ada)**: Jika toko memiliki sisa hutang ke supplier dari periode sebelum ERP, masukkan nilai hutang berjalan. Jika toko benar-benar baru, biarkan `0`.
3. **Pilih Tanggal & Simpan Saldo Awal**
   - Masukkan tanggal hari ini.
   - Isi keterangan: *"Penetapan Saldo Awal Operasional Peluncuran Sistem ERP"*.
   - Klik **Simpan Saldo Awal**.
4. **Cek Neraca Saldo**
   - Buka menu: `Backoffice` > `Reports` > `Trial Balance (Neraca Saldo)`.
   - Pastikan sisi **Debit** dan **Kredit** seimbang sempurna (Balance).

---

## 4. DIVISI 3: PENGADAAN & MASTER DATA (KATEGORI, PRODUK & SUPPLIER)
*Waktu Pelaksanaan: Pagi Hari (Pukul 08.00 - 09.00)*  
*Tujuan: Mendaftarkan barang-barang yang akan dijual beserta harga resminya.*

### Langkah Demi Langkah:
1. **Daftarkan Kategori Produk**
   - Masuk ke menu: `Backoffice` > `Categories`.
   - Buat kelompok barang agar kasir mudah mencari barang, contoh:
     - *Minuman & Makanan Ringan*
     - *Sembako & Kebutuhan Dapur*
     - *Perlengkapan Rumah Tangga*
2. **Daftarkan Data Supplier / Pemasok**
   - Masuk ke menu: `Backoffice` > `Suppliers`.
   - Masukkan nama supplier langganan toko, nomor WhatsApp sales supplier, dan alamatnya (contoh: *PT Sumber Makmur Distributor*).
3. **Daftarkan Katalog Produk (Products)**
   - Masuk ke menu: `Backoffice` > `Products` > Klik **Tambah Produk Baru**.
   - Isi informasi produk:
     - **Barcode / SKU**: Scan barcode kemasan barang menggunakan barcode scanner (atau ketik manual kode unik).
     - **Nama Produk**: Contoh: *Minyak Goreng Sania 2L*.
     - **Kategori**: Pilih kategori yang sesuai.
     - **Satuan**: Pcs / Dus / Botol.
     - **Harga Beli Pokok (HPP)**: Harga modal saat beli dari pabrik/supplier (misal: `Rp 32.000`).
     - **Harga Jual Reguler / Eceran**: Harga jual umum di kasir (misal: `Rp 36.000`).
     - **Harga Grosir / Member (Jika ada)**: Skema harga bertingkat jika pelanggan membeli grosir atau member toko.
     - **Stok Minimum Peringatan**: Misal isi `5` (sistem akan memberi tanda bila stok hampir habis).
   - Klik **Simpan**. Ulangi untuk seluruh produk unggulan yang siap dijual hari ini.

---

## 5. DIVISI 4: PERGUDANGAN & LOGISTIK (PO & PENERIMAAN BARANG MASUK)
*Waktu Pelaksanaan: Pagi Hari (Pukul 09.00 - 10.00)*  
*Tujuan: Mengubah stok nol menjadi stok riil melalui pencatatan penerimaan fisik barang.*

### Langkah Demi Langkah:
Ada dua cara memasukkan stok pada hari pertama:
- **Jalur A (Jika barang baru datang dari Supplier via Surat Jalan)**: Gunakan fitur **Purchase Order & Goods Receipt**.
- **Jalur B (Jika barang sudah ada di rak toko sebagai stok awal)**: Gunakan fitur **Stock Adjustment (Opname Fisik)**.

#### Jalur A: Penerimaan Barang Dari Supplier (Purchase Order)
1. **Buat Purchase Order (PO)**
   - Masuk menu: `Backoffice` > `Purchase Orders` > Klik **Tambah PO**.
   - Pilih Supplier, tanggal rencana datang, lalu tambahkan produk dan jumlah pesanan (contoh: *Minyak Goreng Sania 2L = 50 pcs*).
   - Simpan PO.
2. **Penerimaan Fisik Barang di Gudang (Goods Receipt)**
   - Masuk menu: `Purchases` > `Goods Receipts` > Klik **Terima Barang Baru**.
   - Gunakan fitur **Tarik Data PO**: Pilih nomor PO yang tadi dibuat.
   - Staf gudang menghitung fisik barang yang diturunkan dari mobil box supplier.
   - Masukkan jumlah barang riil yang diterima (bisa terima penuh 50 pcs atau sebagian misal 40 pcs).
   - Klik **Konfirmasi Penerimaan**.
   - ⚡ **Hasil Otomatis**: Stok di gudang/toko langsung bertambah dari 0 menjadi 50, dan sistem otomatis membukukan jurnal Persediaan di sisi Akunting!

#### Jalur B: Stok Opname Fisik Awal (Barang Sudah Ada di Rak)
1. Masuk menu: `Inventory` > `Stock Adjustments` > Klik **Buat Penyesuaian Baru**.
2. Pilih gudang/toko.
3. Pilih produk, masukkan jumlah fisik riil di rak toko (contoh: *Kopi Kapal Api 10 pcs*).
4. Beri alasan: *"Penetapan Stok Fisik Awal Launching ERP"*.
5. Klik **Simpan Penyesuaian**. Stok langsung aktif dan siap dijual di kasir.

---

## 6. DIVISI 5: KASIR & FRONTLINE TOKO (BUKA SHIFT, PENJUALAN & TUTUP KASIR)
*Waktu Pelaksanaan: Jam Buka Toko (Pukul 10.00 - Tutup Toko)*  
*Tujuan: Melayani pembeli dengan cepat, akurat, dan mencatat uang masuk tanpa selisih.*

### Langkah Demi Langkah:

### A. Membuka Shift Kasir (Wajib di Awal Sesi)
1. Kasir login menggunakan akun kasir masing-masing.
2. Klik menu **POS Kasir** (alamat: `/pos`).
3. **Layar Blokir Shift Muncul Otomatis**:
   - Sistem melindungi kasir agar tidak salah catat. Layar terkunci sampai kasir membuka shift.
   - Pilih **Nomor Register / Laci Kasir** (contoh: *Laci Kasir 01*).
   - Masukkan **Modal Awal Kembalian (Opening Cash)**: Uang receh kembalian yang diberikan oleh manajer/supervisor (misal: `Rp 200.000`).
   - Gunakan tombol cepat nominal jika ada (100rb, 200rb, dsb).
   - Klik **Buka Shift Sekarang**. Layar POS terbuka penuh!

### B. Melayani Transaksi Pelanggan
1. **Cari Barang**:
   - Tembak barcode barang dengan scanner, ATAU
   - Ketik nama barang di kolom pencarian cepat, ATAU
   - Klik gambar/kartu produk pada kategori layar.
2. **Atur Jumlah & Level Harga**:
   - Tambah atau kurangi kuantitas item di keranjang belanja kanan.
   - Pilih level harga jika pembeli adalah pelanggan grosir atau member.
3. **Pembayaran (Checkout)**:
   - Pilih metode bayar: **Tunai (Cash)** atau **Non-Tunai / QRIS / Transfer**.
   - Jika tunai, masukkan uang yang diterima dari pembeli (contoh: belanja Rp 36.000, uang Rp 50.000).
   - Sistem langsung menampilkan uang kembalian (`Rp 14.000`).
   - Klik **Selesaikan Transaksi (Bayar)**.
4. **Cetak Struk**:
   - Printer thermal kasir otomatis mencetak struk belanja resmi untuk pelanggan.
   - Serahkan struk dan uang kembalian dengan ramah.

### C. Mencatat Pengeluaran Kas Kecil Toko (Jika Ada Beli Es Batu/Galon/Sapu)
- Jika selama jam operasional ada uang kasir yang dipakai untuk keperluan operasional darurat:
  - Hubungi supervisor untuk input melalui menu `Backoffice` > `Expenses`.
  - Pilih Kategori Biaya (misal: *Perlengkapan Toko / Air Minum*).
  - Masukkan nominal uang yang keluar dan beri catatan nota.
  - Ini mencegah kasir dituduh tekor uang saat tutup shift!

### D. Menutup Shift Kasir (Akhir Sesi / Pergantian Kasir)
1. Di layar POS, klik tombol **Tutup Shift (Close Register)** di pojok kanan atas.
2. **Keluarkan Seluruh Uang di Laci Kasir**:
   - Hitung bersama supervisor seluruh uang fisik kertas dan koin yang ada di laci.
   - Masukkan angka uang fisik riil ke kolom input rekonsiliasi.
3. **Lihat Hasil Rekonsiliasi Real-Time**:
   - Sistem akan membandingkan:
     `Uang Seharusnya = (Modal Awal Kembalian + Total Penjualan Tunai - Pengeluaran)`
   - Status akan langsung terbaca:
     - 🟢 **Seimbang (Balanced)**: Angka fisik pas dengan catatan sistem.
     - 🟡 **Lebih (Surplus)**: Uang fisik lebih banyak dari catatan.
     - 🔴 **Kurang (Defisit / Selisih)**: Uang fisik kurang.
4. Masukkan catatan bila ada alasan selisih (misal: *"Pembulatan koin permen"*).
5. Klik **Konfirmasi Tutup Shift**. Cetak slip rekapitulasi shift kasir untuk ditandatangani.

---

## 7. DIVISI 6: SUPERVISOR & MANAJER TOKO (REKONSILIASI & EVALUASI SORE)
*Waktu Pelaksanaan: Sore Hari (Pukul 17.00 - 18.00)*  
*Tujuan: Memastikan barang yang keluar sama dengan uang yang masuk.*

### Langkah Demi Langkah:
1. **Verifikasi Rekapitulasi Kasir**
   - Terima uang setoran fisik dari kasir beserta struk bukti tutup shift.
   - Simpan uang hasil penjualan ke brankas utama toko.
2. **Cek Laporan Penjualan Harian**
   - Masuk menu: `Backoffice` > `Reports` > `Sales (Laporan Penjualan)`.
   - Filter tanggal hari ini: Cek total omzet, total transaksi, dan barang paling laris (*fast moving*).
3. **Cek Kartu Stok Barang (Stock Card)**
   - Masuk menu: `Backoffice` > `Reports` > `Stock Card (Kartu Stok)`.
   - Pilih sampel 3 barang terlaris.
   - Periksa alurnya: Saldo Awal (0) + Masuk dari Gudang (50) - Terjual di Kasir (12) = Sisa Saldo Akhir (38).
   - Pastikan hitungan sistem cocok dengan sisa barang fisik di rak!

---

## 8. DIVISI 7: KEUANGAN MALAM HARI (LABA RUGI & TUTUP BUKU HARIAN)
*Waktu Pelaksanaan: Malam Hari (Pukul 18.00 - 19.00)*  
*Tujuan: Melihat profit bersih hari ini dan mengamankan data toko.*

### Langkah Demi Langkah:
1. **Cek Laporan Laba Rugi (Income Statement)**
   - Masuk menu: `Backoffice` > `Reports` > `Income Statement`.
   - Lihat ringkasan otomatis:
     - **Pendapatan Penjualan** (Omzet)
     - Dikurangi: **Harga Pokok Penjualan (HPP)**
     - Menghasilkan: **Laba Kotor (Gross Profit)**
     - Dikurangi: **Beban Biaya Operasional (Listrik, bensin, dll)**
     - Menghasilkan: **Laba Bersih Hari Ini (Net Profit)**.
2. **Cek Arus Kas (Cash Flow)**
   - Masuk menu: `Backoffice` > `Reports` > `Cash Flow`.
   - Pastikan arus kas masuk dari penjualan tunai dan arus kas keluar tercatat akurat.
3. **Backup Data Harian (Sangat Penting!)**
   - Masuk menu: `Backoffice` > `Pengaturan Sistem` > `Backup`.
   - Klik tombol **Generate Backup**.
   - Unduh file cadangan (*download backup database*) dan simpan di flashdisk atau Google Drive toko.
   - Selesai! Hari pertama sukses ditutup dengan aman dan rapi.

---

## 9. TIPS SIMULASI INTEGRASI 3 HARI (PLAYBOOK TRANSI DAN ADAPTASI ERP)
*Bagaimana cara bertransisi ke ERP Maju Bersama tanpa membuat staf panik, tanpa antrean kasir macet, dan tanpa langkah tumpang tindih? Terapkan strategi 3 hari berikut:*

```
┌────────────────────────────────────────────────────────────────────────┐
│                        PLAYBOOK 3 HARI GO-LIVE                         │
├──────────────────┬──────────────────┬──────────────────────────────────┤
│     HARI 1       │      HARI 2      │              HARI 3              │
│  LATIHAN BEBAS   │   INPUT DATA RIIL│          FULL GO-LIVE            │
│ (Sandbox/Bermain)│  & DRY-RUN 1 JAM │      (100% Mandiri & Nyata)      │
└──────────────────┴──────────────────┴──────────────────────────────────┘
```

### 📅 HARI KE-1: TAHAP "BERMAIN & FAMILIARISASI" (TANPA TEKANAN)
> **Prinsip**: *Jangan sentuh data toko yang asli dulu. Biarkan staf mencoba tombol-tombol sistem agar tidak takut salah.*

- **Pagi (09.00 - 11.00) - Setup Data Contoh (Dummy)**:
  - Admin membuat 5 produk contoh (misal: *Air Mineral Dummy*, *Biskuit Dummy*, *Sabun Dummy*).
  - Admin membuat modal saldo awal dummy Rp 1.000.000.
  - Gudang mencoba menerima 20 pcs produk dummy.
- **Siang (13.00 - 15.00) - Simulasi Kasir Bebas**:
  - Semua kasir bergantian duduk di komputer kasir.
  - Kasir latihan:
    1. Buka shift dengan uang modal dummy Rp 100.000.
    2. Scan barcode produk dummy, ketik manual jika barcode rusak.
    3. Latihan simulasi kasus nyata:
       - *"Bagaimana jika pembeli bayar uang pas?"*
       - *"Bagaimana jika pembeli bayar uang Rp 100.000?"*
       - *"Bagaimana jika pembeli ingin membatalkan 1 item barang belanjaan?"*
    4. Latihan tutup shift dan hitung uang dummy di laci.
- **Sore (15.00 - 16.00) - Evaluasi Santai**:
  - Tanyakan ke kasir dan staf gudang: *"Bagian mana yang tadi sempat bingung?"*
  - Berikan tips keyboard shortcut (misal: tekan Enter untuk bayar).
  - Staf pulang dengan rasa percaya diri karena sudah merasakan langsung mudahnya sistem.

---

### 📅 HARI KE-2: TAHAP "PERSIAPAN DATA RIIL & DRY-RUN SORE"
> **Prinsip**: *Data asli dimasukkan, lalu uji coba langsung 1 jam saat toko masih buka secara berdampingan.*

- **Pagi s/d Siang (08.00 - 14.00) - Entri Data Bersih**:
  - Hapus data contoh/dummy Hari ke-1.
  - Masukkan data riil:
    - Master Kategori & Master Produk riil (nama resmi, barcode asli, HPP, dan harga jual).
    - Supplier langganan toko.
    - Stok opname fisik awal toko (hitung barang di rak dan masukkan via Goods Receipt / Stock Adjustment).
    - Saldo modal awal resmi via menu `Opening Balances`.
- **Sore (15.00 - 16.00) - Dry Run 1 Jam (Uji Coba Nyata Berdampingan)**:
  - Buka 1 kasir khusus ERP Maju Bersama untuk melayani pembeli nyata selama 1 jam.
  - Supervisor berdiri di samping kasir untuk mendampingi jika kasir ragu-ragu.
  - Cetak struk pertama ke pelanggan.
- **Malam (17.00) - Tutup Shift Uji Coba**:
  - Lakukan tutup shift kasir 1 jam tersebut.
  - Verifikasi: Apakah uang fisik di laci cocok dengan struk penjualan? (Pasti cocok!).
  - Toko dinyatakan 100% SIAP untuk peluncuran resmi besok pagi.

---

### 📅 HARI KE-3: FULL GO-LIVE (OPERASIONAL PENUH 100%)
> **Prinsip**: *Tinggalkan cara lama (buku manual/sistem lama). Seluruh transaksi wajib lewat ERP Maju Bersama.*

- **Pagi (07.45)**:
  - Kasir datang, buka shift resmi dengan modal uang kembalian nyata dari supervisor.
- **Jam Buka Toko (08.00 - Selesai)**:
  - Semua transaksi pelanggan diproses melalui POS Maju Bersama.
  - Jika ada barang masuk dari mobil supplier di siang hari, staf gudang langsung klik `Goods Receipts` agar stok otomatis terisi.
  - Jika ada uang kasbon/beli kebutuhan toko, staf langsung catat di `Expenses`.
- **Malam (Tutup Toko)**:
  - Tutup shift kasir: Rekonsiliasi laci kasir (surplus/defisit/seimbang).
  - Manajer Keuangan membuka menu `Income Statement` dan melihat keuntungan bersih hari ini secara instan hanya dalam 1 klik!
  - Generate Backup database.

---

## 💡 5 ATURAN EMAS INTEGRASI AGAR TIDAK TUMPAH TINDIH

1. **Satu Pintu Input Barang Masuk**:  
   Jangan biarkan kasir mengubah stok! Penambahan barang HANYA boleh dilakukan oleh Divisi Gudang melalui menu *Goods Receipt* atau *Stock Adjustment*.
2. **Jangan Transaksi Sebelum Buka Shift**:  
   Kasir dilarang melayani transaksi jika belum memasukkan uang modal kembalian di layar awal.
3. **Ada Uang Keluar, Wajib Ada Bukti Nota**:  
   Setiap rupiah yang diambil dari laci kasir untuk keperluan beli galon/bensin/kebersihan wajib diinput di menu `Expenses` pada menit yang sama.
4. **Hitung Uang Fisik Sebelum Lihat Layar Tutup Shift**:  
   Saat tutup shift, kasir sebaiknya menghitung uang fisik di meja terlebih dahulu, baru mengetikkan hasilnya ke sistem. Ini menjamin kejujuran dan akurasi rekonsiliasi.
5. **Backup Setiap Malam**:  
   Luangkan waktu 1 menit sebelum pulang untuk mengklik menu Backup. Data toko adalah aset paling berharga Anda!
