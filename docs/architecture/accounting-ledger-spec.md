# Spesifikasi Arsitektur: General Ledger Double-Entry Multi-Cabang

**Proyek:** Maju Bersama ERP (Laravel 12, MySQL 8 InnoDB produksi, SQLite untuk testing; lihat `architecture_decisions.md` ADR-008 baris 121-130)
**Status:** v1.1 — Keputusan §8 DIPUTUSKAN pengguna pada 30 Okt 2026 (Moving Average per cabang, selisih kas Hybrid, cutover 1 Des 2026, pemisahan entitas legal wajib, retur via 4140). Belum ada perubahan kode/migrasi. Ringkasan eksekusi developer: `docs/architecture/ledger-ddl-migration-summary.md`.
**Tanggal:** 30 Oktober 2026
**Sumber inspeksi:** repo `mahakaryahutamacorp-svg/maju-bersama`, branch `main`, dibaca jarak jauh (read-only).

> Catatan aturan: file `.cursor/rules/01-architect.mdc` **tidak ada** di repo. Satu-satunya rule adalah
> `.cursor/rules/approved-changes-to-main.mdc` (alwaysApply): tidak boleh push/merge/deploy tanpa persetujuan,
> dan deploy dijalankan pengguna sendiri lewat `deploy-direct.ps1`. Dokumen ini mematuhinya: hanya spesifikasi.

---

## 0. Ringkasan Eksekutif

Hipotesis awal ("laporan ditarik langsung dari tabel operasional, tanpa ledger double-entry") **hanya benar sebagian**:

| Laporan di `/backoffice/reports` | Sumber data saat ini | Bukti |
|---|---|---|
| Laba-Rugi (`income-statement`) | **Jurnal** (`journal_lines` JOIN `journal_headers`) | `app/Services/Reports/IncomeStatementService.php:22-46` |
| Arus/Mutasi Kas (`cash-flow`) | **Jurnal**, tetapi akun kas dideteksi via `LIKE '111%'/'112%'` + nama `%kas%`/`%bank%` | `app/Services/Reports/CashFlowService.php:24-63` |
| Neraca Saldo / Buku Besar | **Jurnal** | `app/Services/AccountingReportService.php:59-102, 219-260` |
| **Aging AR** (`ar-aging`) | **Tabel operasional `sales`** (`total_amount - paid_amount`, `payment_status != 'PAID'`) | `app/Services/Reports/AgingReportService.php:33-60` |
| Laporan Penjualan | Tabel operasional `sales`/`sale_items` | `app/Services/Reports/SalesReportService.php:34-108` |

Jadi ledger double-entry **sudah ada** (`chart_of_accounts`, `journal_headers`, `journal_lines`) dan POS sudah memposting jurnal 4 baris. Masalahnya adalah **ledger yang lemah**: tidak ada penegakan keseimbangan di DB, tidak ada tautan sumber (source_type/source_id), jurnal bisa dihapus (soft delete & cascade), Kas tunggal untuk semua cabang/laci, HPP tidak dibekukan per baris, selisih kas shift tidak pernah dijurnal, dan Aging AR tidak direkonsiliasi ke akun 1130. Spesifikasi ini **mengevolusi** ledger yang ada, bukan membuat paralel dari nol.

---

## 1. Temuan Inspeksi Skema Saat Ini

### 1.1 Transaksi Kasir (POS)
- `sales` — `database/migrations/2026_09_13_000001_create_sales_table.php:11-21`: `branch_id` (FK cascadeOnDelete, baris 13), `receipt_number` unique, `total_amount` (awalnya `integer`, baris 15), `payment_method` string bebas default `'cash'` (baris 16), `status`.
- Tambahan: `cash_register_shift_id` nullable (`2026_09_20_000004_...:12-16`), `paid_amount` + `payment_status` ENUM UNPAID/PARTIAL/PAID (`2026_09_24_000001_...:12-13`), `customer_id` + `due_date` (`2026_09_24_000008_...:16-19`), `tax_category/currency_code/exchange_rate` (`2026_09_23_104854_...:15-17`), konversi uang ke `decimal(15,2)` (`2026_10_10_000003_...:16-23`).
- `sale_items` — `2026_09_13_000002_...:11-20`: `quantity` integer, `price`, `subtotal`, + `discount_amount` (`2026_10_11_000001_...:12`). **Tidak ada kolom `unit_cost`/`cogs_amount`.**
- Posting: `app/Services/SalePostingService.php` — seluruh stok, sale, dan jurnal dalam satu `DB::transaction` (baris 54). Jurnal: Dr Kas `1110` / Piutang `1130`, Dr Potongan `4130`, Cr Pendapatan `4110`, Cr PPN `2130`, Dr HPP `5100` / Cr Persediaan `1210` (baris 280-373). Cek keseimbangan hanya di PHP (baris 360-371).
- Shift: `cash_register_shifts` — `2026_09_20_000003_...:11-28` (opening/expected/actual/difference, status open/closed). `cash_registers` per cabang (`2026_09_20_000002_...:11-18`).

### 1.2 Penjualan Kredit/Tempo & Customer
- Tidak ada tabel invoice terpisah. Penjualan tempo = baris `sales` dengan `payment_method IN ('tempo','piutang','kredit')` → `payment_status='UNPAID'` (`SalePostingService.php:233-234, 298-300`).
- `customers` — `2026_09_24_000006_add_group_to_customers.php:15-26`: `branch_id` **nullable**, `customer_group_id`. Tidak ada `credit_limit`, `payment_terms`, kode pelanggan.

### 1.3 Pembayaran Piutang
- `payments` (type ENUM AR/AP) + `payment_allocations` — `2026_09_24_000002_...:11-39`. `payments.customer_id` adalah `unsignedBigInteger` **tanpa FK** (baris 15); `amount decimal(15,4)` (baris 20) berbeda presisi dengan jurnal `decimal(15,2)`.
- `app/Services/ARAPPaymentService.php:48-140`: update `sales.paid_amount` (baris 97-98) lalu jurnal Dr Kas/Bank (`account_id` pilihan user) / Cr Piutang, dan simpan `journal_header_id` di `payments` (baris 140).

### 1.4 HPP/COGS: di-freeze atau dinamis?
- **Dinamis pada saat jual, tidak dibekukan per baris.** `SalePostingService.php:107` mengambil `$product->purchase_price` (harga beli terakhir di master produk, `2026_09_11_000005_create_products_table.php:17`) saat checkout, lalu hanya total agregatnya yang masuk jurnal (baris 344-357). `sale_items` tidak menyimpan biaya.
- Akibat: (a) Laba kotor per item/produk tidak bisa dihitung ulang dari data transaksi; (b) jika `purchase_price` berubah (mis. migrasi `2026_10_13_130000_reset_live_transactions_and_share_catalog.php:211` menyalin `purchase_price` antar cabang), retur memakai biaya baru, bukan biaya saat jual: `SalesReturnService.php:280-282` fallback ke `$product->purchase_price`; (c) bukan moving-average maupun FIFO.
- Hanya `sales_return_items` (`2026_09_21_000002_...:40-42`), `stock_transfer_items`, `stock_adjustment_*` yang menyimpan `unit_cost`.

### 1.5 Isolasi `branch_id`
- Proyek **bukan Supabase/PostgreSQL**, jadi **tidak ada RLS**. Isolasi murni di aplikasi:
  - Trait global scope `app/Traits/HasBranchScope.php:16-29`: filter `branch_id = user.branch_id` kecuali `isMaster()` (`app/Models/User.php:78-81`: role master/superadmin/super_admin). Dipakai 20 model (Sale, Payment, Customer, CashRegisterShift, dll.).
  - **`JournalHeader`, `JournalLine`, `ChartOfAccount` tidak memakai `HasBranchScope`** → isolasi jurnal bergantung pada filter manual `->when($branchId, ...)` di tiap service laporan (mis. `CashFlowService.php:43,59`, `IncomeStatementService.php:36`). Lupa satu `when` = kebocoran data lintas cabang.
  - `ReportController.php:63-65, 99-101`: non-master dipaksa ke `branch_id` sendiri; master boleh `null` (konsolidasi).
  - Banyak service memanggil `withoutGlobalScopes()` (mis. `SalePostingService.php:424-433`, `CashRegisterShiftService.php:19,77`), sehingga perlindungan scope mudah terlewati.
- Cabang aktual (`database/seeders/BusinessStructureSeeder.php:64-72`): `OFFICE-PUPUK` (Pusat), `GUDANG-PUPUK` (Gudang), `CBG-ABI`, `CBG-MAMA`, `CBG-AROFAH`, `CBG-TULUSAYU`, `CBG-LUBUKHARJO`, dan `PAKAN` (CV. Deka Agro Mandiri — badan usaha terpisah). Hierarki via `branches.parent_id` (`2026_09_12_000001_...`).
- Hanya `journal_headers.branch_id` — `journal_lines` tidak punya `branch_id`, dan COA global (satu akun `1110 Kas` untuk semua cabang dan semua laci).

---

## 2. Kelemahan Skema (Akar Masalah Kekacauan Laporan)

1. **Keseimbangan debit=kredit hanya di PHP** (`JournalPostingService.php:145-157`, `SalePostingService.php:360-371`). Tidak ada constraint/trigger DB; insert manual, seeder, atau migrasi data (mis. `ImportPiutangAwal`, `OpeningBalanceService`) bisa menulis jurnal tak seimbang.
2. **Jurnal tidak immutable.** `journal_headers` punya `softDeletes` (`2026_09_11_000002_...:19`) dan `journal_lines` `cascadeOnDelete` (`2026_09_11_000003_...:13`); migrasi `2026_10_13_130000_...:114-123` menghapus jurnal secara fisik. Tidak ada mekanisme reversal → jejak audit hilang.
3. **Tidak ada tautan sumber.** `journal_headers` hanya punya `reference_number` string **non-unique** (`...000002:16`). Tautan balik hanya satu arah dan hanya di beberapa tabel (`payments.journal_header_id`, `sales_returns.journal_header_id`); `sales` **tidak** menyimpan `journal_header_id` → tidak bisa membuktikan "tiap penjualan punya tepat satu jurnal".
4. **`branch_id` cabang di-cascade delete** (`journal_headers` baris 13, `sales` baris 13): menghapus cabang menghapus seluruh buku besarnya.
5. **Kas tunggal (1110) untuk semua laci & cabang.** Mutasi kas per laci tidak mungkin; sumber dana tidak terlacak; Cash Flow mendeteksi akun kas dengan `LIKE '%kas%'` (`CashFlowService.php:24-34`) yang rawan salah klasifikasi (mis. akun bernama "Kasbon").
6. **Selisih kas shift tidak pernah dijurnal.** `CashRegisterShiftService::closeShift` (baris 93-119) hanya menyimpan `difference` di tabel shift. Ekspektasi kas (baris 65-86) hanya menghitung penjualan tunai − refund tunai; **tidak** memasukkan pelunasan piutang tunai, biaya kas kecil, setoran ke bank, atau kas transfer. Hasilnya selisih "semu" dan selisih nyata tidak tercermin di Laba-Rugi.
7. **Payment method string bebas** (`'cash','Cash','tunai','Tunai'` — `CashRegisterShiftService.php:70,79`; `'tempo','piutang','kredit'` — `SalePostingService.php:233`). Klasifikasi kas vs non-tunai rapuh.
8. **HPP tidak dibekukan** (lihat 1.4) → retur dan analisis margin tidak konsisten dengan jurnal penjualan.
9. **Aging AR dari `sales.paid_amount`** (`AgingReportService.php:35-50`), bukan dari subledger yang direkonsiliasi ke 1130. Piutang awal, retur kredit, dan koreksi jurnal manual ke 1130 tidak terlihat di Aging → Aging ≠ saldo 1130 di Neraca.
10. **Akun dibuat on-the-fly** via `ChartOfAccount::firstOrCreate` (`SalePostingService.php:329-332, 389-397`; `JournalPostingService.php:116-121`) — COA bisa "tumbuh" diam-diam; `chart_of_accounts.type` string bebas tanpa normal balance, tanpa hierarki wajib, tanpa flag postable.
11. **Klasifikasi akun salah di seeder** (`ChartOfAccountSeeder.php`): `4130 Potongan Penjualan` bertipe `revenue` (seharusnya contra-revenue saldo normal debit); `1320 Akumulasi Penyusutan` bertipe `asset` tanpa penanda contra; `5110 Biaya Harian` berada di kelompok HPP padahal beban operasional.
12. **Presisi uang tidak konsisten**: `decimal(15,4)` di payments/sales.paid_amount vs `decimal(15,2)` di jurnal dan sales.total_amount → selisih pembulatan pada status PAID/PARTIAL.
13. **Tanggal jurnal = `now()`** (`SalePostingService.php:293`) tanpa periode akuntansi/closing → transaksi bisa diposting ke periode yang sudah dilaporkan.
14. **`payments.customer_id` tanpa FK** dan `customers.branch_id` nullable → piutang bisa yatim / ambigu kepemilikan cabang.

---

## 3. Chart of Accounts (COA) Standar Multi-Cabang

Prinsip:
- Kode numerik 4 digit level akun + **sub-akun 7 digit** untuk dimensi fisik (laci/rekening): `AAAA-BBB` disimpan sebagai `code` `'1111001'` dsb.
- **Satu COA global** (dikonsolidasikan), dengan **`branch_id` pada setiap `journal_items`** sebagai dimensi. Hanya akun kas laci & rekening bank yang dipecah fisik per cabang (karena itu benda nyata yang harus dihitung).
- Kolom wajib: `normal_balance` (D/K), `is_postable` (akun header tidak bisa diposting), `is_contra`, `cash_flow_tag`.
- **Pemisahan entitas legal (DIPUTUSKAN):** dua entitas — `MB-PUPUK` (jaringan ritel pupuk Maju Bersama: OFFICE-PUPUK, GUDANG-PUPUK, CBG-ABI, CBG-MAMA, CBG-AROFAH, CBG-TULUSAYU, CBG-LUBUKHARJO) dan `DEKA` (CV. Deka Agro Mandiri: cabang PAKAN). COA **di-scope per entitas** (`chart_of_accounts.entity_id`, unique `(entity_id, code)`): kode akun sama di kedua entitas, tetapi baris akunnya berbeda sehingga saldo tidak pernah bercampur. Setiap jurnal hanya milik satu entitas; transaksi lintas entitas memakai akun **1295 Piutang Antar Entitas (Due from)** / **2295 Utang Antar Entitas (Due to)** dengan dua jurnal terpisah (satu per entitas), dikaitkan `intercompany_group_id`.
- 1290 Rekening Antar Cabang hanya untuk cabang **dalam entitas yang sama**.

| Kode | Nama | Tipe | Normal | Postable | Catatan |
|---|---|---|---|---|---|
| **1** | **ASET** | asset | D | tidak | |
| 1100 | Kas & Setara Kas | asset | D | tidak | |
| 1110 | Kas Laci Kasir (header) | asset | D | tidak | dipecah per laci |
| 1110001 | Kas Laci – Toko ABI – Laci 1 | asset | D | ya | `cash_register_id` terikat |
| 1110002 | Kas Laci – Toko MAMA – Laci 1 | asset | D | ya | |
| 1110xxx | Kas Laci – {cabang} – {laci} | asset | D | ya | satu per `cash_registers` |
| 1111 | Kas Kecil (Petty Cash) per cabang | asset | D | ya | dimensi branch_id |
| 1112 | Kas Besar / Brankas Pusat | asset | D | ya | OFFICE-PUPUK |
| 1115 | Kas Dalam Perjalanan (setoran belum diterima bank) | asset | D | ya | |
| 1120 | Bank (header) | asset | D | tidak | |
| 1120001 | Bank BCA – Rek. Operasional Pusat | asset | D | ya | per rekening |
| 1120xxx | Bank {nama} – {no rek} | asset | D | ya | |
| 1125 | Piutang EDC/QRIS/Transfer (Clearing) | asset | D | ya | non-tunai belum settle |
| 1130 | Piutang Usaha | asset | D | ya | **kontrol subledger AR** |
| 1131 | Cadangan Kerugian Piutang | asset | K | ya | contra |
| 1135 | Piutang Karyawan / Kasbon | asset | D | ya | resolusi selisih kas yang dibebankan ke kasir |
| 1140 | Uang Muka Pembelian | asset | D | ya | (ada) |
| 1150 | PPN Masukan | asset | D | ya | (ada) |
| 1210 | Persediaan Barang Dagang | asset | D | ya | dimensi branch_id/warehouse |
| 1215 | Persediaan Dalam Perjalanan (transfer antar cabang) | asset | D | ya | |
| 1290 | Rekening Antar Cabang (Due from/to Branch) | asset | D | ya | dalam satu entitas; nol saat konsolidasi entitas |
| 1295 | Piutang Antar Entitas (Due from Entity) | asset | D | ya | lawan: 2295 di entitas lain; eliminasi saat konsolidasi grup |
| 1199 | Selisih Kas Belum Diselesaikan (Suspense) | asset | D | ya | kas kurang/lebih > ambang; wajib nol tiap akhir bulan |
| 1310 | Aset Tetap | asset | D | ya | (ada) |
| 1320 | Akumulasi Penyusutan | asset | K | ya | contra (perbaiki flag) |
| **2** | **LIABILITAS** | liability | K | tidak | |
| 2110 | Utang Dagang | liability | K | ya | kontrol subledger AP |
| 2120 | Utang Biaya / Akrual | liability | K | ya | |
| 2130 | PPN Keluaran | liability | K | ya | |
| 2140 | Uang Muka / Deposit Pelanggan | liability | K | ya | kelebihan bayar & DP |
| 2150 | Utang Refund Pelanggan | liability | K | ya | retur belum dibayar |
| 2295 | Utang Antar Entitas (Due to Entity) | liability | K | ya | pasangan 1295 |
| 2210 | Utang Bank Jangka Panjang | liability | K | ya | |
| **3** | **EKUITAS** | equity | K | tidak | |
| 3110 | Modal Disetor | equity | K | ya | |
| 3120 | Prive | equity | D | ya | |
| 3210 | Laba Ditahan | equity | K | ya | |
| 3220 | Laba Tahun Berjalan | equity | K | tidak (sistem) | hasil closing |
| 3900 | Saldo Awal / Ekuitas Pembukaan (Opening Balance Equity) | equity | K | ya | hanya untuk cutover, harus dinolkan |
| **4** | **PENDAPATAN** | revenue | K | tidak | |
| 4110 | Penjualan Barang Dagang | revenue | K | ya | (ada) |
| 4120 | Pendapatan Lain-lain | revenue | K | ya | (ada) |
| 4130 | Potongan Penjualan | revenue | D | ya | **contra-revenue** |
| 4140 | Retur Penjualan | revenue | D | ya | **contra-revenue, is_contra=1** — satu-satunya akun debit untuk retur; 4110 tidak pernah didebit retur |
| 4150 | Pendapatan Selisih Kas Lebih | revenue | K | ya | kas lebih ≤ ambang (otomatis); di atas ambang via 1199 + approval |
| **5** | **HARGA POKOK PENJUALAN** | expense | D | tidak | |
| 5100 | HPP Barang Dagang | expense | D | ya | dinilai Moving Average per cabang |
| 5120 | Selisih / Penyusutan Persediaan (Stock Opname) | expense | D | ya | (ada) |
| 5130 | Ongkos Angkut Pembelian | expense | D | ya | |
| **6** | **BEBAN OPERASIONAL** | expense | D | tidak | |
| 6100 | Beban Operasional Umum | expense | D | ya | |
| 6110 | Biaya Harian Toko | expense | D | ya | **pindahan dari 5110** |
| 6200 | Beban Listrik, Air & Internet | expense | D | ya | |
| 6300 | Beban Gaji & Upah | expense | D | ya | |
| 6400 | Beban Sewa | expense | D | ya | |
| 6500 | Beban Penyusutan | expense | D | ya | |
| 6600 | Beban Transportasi & Pengiriman | expense | D | ya | |
| 6700 | Beban Administrasi & Bank (MDR EDC/QRIS) | expense | D | ya | |
| 6810 | Beban Selisih Kas Kurang | expense | D | ya | kas kurang ≤ ambang (otomatis) atau hasil resolusi approval |
| 6820 | Beban Kerugian Piutang | expense | D | ya | |
| 6900 | Beban Lain-lain | expense | D | ya | |

Akun **Suspense** `1199` **wajib** (bukan opsional) untuk kebijakan Hybrid §5e. Seluruh COA di atas di-seed **dua kali**, sekali per entitas.

---

## 4. DDL (MySQL 8 / InnoDB)

Tabel `journal_entries`/`journal_items` adalah **pengganti versi kuat** dari `journal_headers`/`journal_lines`. Strategi: buat tabel baru, backfill, lalu `journal_headers/lines` dijadikan read-only (atau VIEW kompatibilitas) agar `JournalHeader`/`JournalLine` model lama tetap jalan selama transisi. Nilai uang `DECIMAL(18,2)` seragam.

> Ditulis sebagai SQL mentah agar trigger jelas; di Laravel dibungkus `DB::unprepared()` dalam migrasi. Trigger MySQL tidak berjalan di SQLite testing — test suite perlu helper `assertBalanced()` dan/atau menjalankan test ledger terhadap MySQL (Dusk/CI service container).

### 4.0 `entities` (entitas legal) & relasi cabang

```sql
CREATE TABLE entities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,                       -- 'MB-PUPUK', 'DEKA'
  legal_name VARCHAR(200) NOT NULL,                -- 'Maju Bersama (Jaringan Ritel Pupuk)', 'CV. Deka Agro Mandiri'
  npwp VARCHAR(30) NULL,
  base_currency CHAR(3) NOT NULL DEFAULT 'IDR',
  cash_shortage_auto_threshold DECIMAL(18,2) NOT NULL DEFAULT 10000.00,  -- ambang Hybrid kas kurang
  cash_overage_auto_threshold  DECIMAL(18,2) NOT NULL DEFAULT 10000.00,  -- ambang kas lebih
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_entity_code (code),
  CONSTRAINT chk_entity_thr CHECK (cash_shortage_auto_threshold >= 0 AND cash_overage_auto_threshold >= 0)
) ENGINE=InnoDB;

ALTER TABLE branches
  ADD COLUMN entity_id BIGINT UNSIGNED NULL AFTER id,     -- di-backfill lalu dijadikan NOT NULL
  ADD KEY idx_branches_entity (entity_id),
  ADD CONSTRAINT fk_branches_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE RESTRICT;
-- Backfill: PAKAN -> DEKA; semua cabang lain -> MB-PUPUK; kemudian MODIFY entity_id NOT NULL.
-- Trigger: branches.entity_id tidak boleh diubah bila cabang sudah punya journal_entries posted.
```

### 4.1 `chart_of_accounts` (ALTER tabel yang sudah ada, di-scope per entitas)

```sql
ALTER TABLE chart_of_accounts
  DROP INDEX chart_of_accounts_code_unique,
  ADD COLUMN entity_id BIGINT UNSIGNED NULL AFTER id,     -- backfill ke MB-PUPUK, lalu NOT NULL; DEKA di-seed ulang
  ADD UNIQUE KEY uq_coa_entity_code (entity_id, code),
  ADD CONSTRAINT fk_coa_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE RESTRICT,
  MODIFY code VARCHAR(20) NOT NULL,
  MODIFY type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
  ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN normal_balance ENUM('D','K') NOT NULL DEFAULT 'D',
  ADD COLUMN is_postable TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN is_contra TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0,          -- akun yang dipakai kode posting, tidak boleh diubah/nonaktif
  ADD COLUMN branch_id BIGINT UNSIGNED NULL,                    -- diisi hanya untuk akun fisik (kas laci, kas kecil, bank cabang)
  ADD COLUMN cash_register_id BIGINT UNSIGNED NULL,             -- 1:1 dengan laci kasir
  ADD COLUMN cash_flow_tag ENUM('cash','operating','investing','financing','none') NOT NULL DEFAULT 'none',
  ADD CONSTRAINT fk_coa_parent   FOREIGN KEY (parent_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_coa_branch   FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_coa_register FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE RESTRICT,
  ADD UNIQUE KEY uq_coa_register (cash_register_id),
  ADD KEY idx_coa_type (type, is_active),
  ADD KEY idx_coa_cashtag (cash_flow_tag);
-- Hapus kebiasaan firstOrCreate di service; posting gagal bila akun sistem tidak ada.
-- Aturan: chart_of_accounts.branch_id (bila diisi) harus milik entity_id yang sama (dicek trigger trg_coa_biu).

-- Biaya rata-rata bergerak (Moving Average) per cabang per produk — DIPUTUSKAN
CREATE TABLE product_branch_costs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty_on_hand DECIMAL(18,4) NOT NULL DEFAULT 0,      -- harus = SUM(inventories.quantity) cabang tsb
  avg_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  inventory_value DECIMAL(18,2) NOT NULL DEFAULT 0,  -- harus = saldo 1210 cabang/produk (subledger)
  last_movement_at DATETIME NULL,
  version BIGINT UNSIGNED NOT NULL DEFAULT 0,        -- optimistic lock tambahan; utama: SELECT ... FOR UPDATE
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_pbc (branch_id, product_id),
  KEY idx_pbc_entity (entity_id),
  CONSTRAINT fk_pbc_entity  FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pbc_branch  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pbc_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Kartu biaya (audit setiap perubahan rata-rata)
CREATE TABLE inventory_cost_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('receipt','sale','sales_return','purchase_return','transfer_in','transfer_out','adjustment','opening') NOT NULL,
  source_type VARCHAR(50) NOT NULL, source_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(18,4) NOT NULL,                        -- + masuk / - keluar
  unit_cost DECIMAL(18,4) NOT NULL,                  -- biaya yang dipakai/di-freeze
  value DECIMAL(18,2) NOT NULL,
  qty_after DECIMAL(18,4) NOT NULL, avg_cost_after DECIMAL(18,4) NOT NULL, value_after DECIMAL(18,2) NOT NULL,
  journal_entry_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  KEY idx_icm (branch_id, product_id, id),
  KEY idx_icm_source (source_type, source_id),
  CONSTRAINT fk_icm_entity  FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE RESTRICT,
  CONSTRAINT fk_icm_branch  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
  CONSTRAINT fk_icm_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
```

**Aturan recompute Moving Average (per `branch_id, product_id`, di dalam transaksi dokumen, baris `product_branch_costs` di-`lockForUpdate`):**
1. **Penerimaan barang** (`goods_receipts`, `transfer_in`, `opening`, adjustment plus): `nilai_baru = inventory_value + qty_masuk × biaya_masuk` (biaya_masuk = harga beli neto DPP setelah diskon + ongkos angkut teralokasi, tanpa PPN Masukan yang dapat dikreditkan; untuk transfer_in = `unit_cost` beku dari cabang asal); `qty_baru = qty_on_hand + qty_masuk`; `avg_unit_cost = ROUND(nilai_baru / qty_baru, 4)`. Bila `qty_on_hand ≤ 0` sebelum penerimaan, avg = biaya_masuk dan nilai negatif lama di-true-up ke 5120.
2. **Pengeluaran** (sale, transfer_out, purchase_return, adjustment minus): pakai `avg_unit_cost` saat itu, **di-freeze** ke dokumen (`sale_items.unit_cost`, `stock_transfer_items.unit_cost`, dst.); `avg_unit_cost` **tidak berubah**; `inventory_value -= ROUND(qty × avg, 2)`; bila qty menjadi 0, sisa nilai pembulatan di-nolkan ke 5120.
3. **Retur penjualan**: barang masuk kembali dengan `unit_cost` = **`sale_items.unit_cost` asal** (bukan avg sekarang) → avg dihitung ulang seperti penerimaan; disimpan di `sales_return_items.unit_cost`.
4. **Retur pembelian**: keluar memakai biaya penerimaan asal; selisih terhadap avg → 5120.
5. Koreksi harga beli susulan (landed cost): tidak mengubah HPP historis; dibukukan ke 1210 bila stok masih ada (recompute avg) atau ke 5100 untuk porsi yang sudah terjual.
6. `products.purchase_price` hanya informasi "harga beli terakhir"; **tidak pernah** dipakai untuk HPP.
```

### 4.2 `accounting_periods` (pendukung, wajib untuk immutability per periode)

```sql
CREATE TABLE accounting_periods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_id BIGINT UNSIGNED NOT NULL,           -- periode fiskal per entitas legal
  branch_id BIGINT UNSIGNED NULL,               -- NULL = berlaku untuk seluruh cabang entitas
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  status ENUM('open','soft_closed','closed') NOT NULL DEFAULT 'open',
  closed_by BIGINT UNSIGNED NULL,
  closed_at DATETIME NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_period (entity_id, branch_id, period_start),
  CONSTRAINT fk_period_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE RESTRICT,
  CONSTRAINT fk_period_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
```

### 4.3 `journal_entries`

```sql
CREATE TABLE journal_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entry_number VARCHAR(40) NOT NULL,                      -- JE-{ENTITY}-{BRANCHCODE}-{YYYYMM}-{seq}
  entity_id BIGINT UNSIGNED NOT NULL,                     -- harus = branches.entity_id
  intercompany_group_id CHAR(26) NULL,                    -- ULID pengikat pasangan jurnal antar entitas
  branch_id BIGINT UNSIGNED NOT NULL,                     -- cabang pemilik dokumen
  entry_date DATE NOT NULL,                               -- tanggal akuntansi
  posted_at DATETIME NULL,
  status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'draft',
  source_type VARCHAR(50) NOT NULL,                       -- 'sale','sales_return','ar_payment','pos_session_close','expense','goods_receipt','stock_transfer','stock_adjustment','cash_transfer','opening_balance','manual','legacy_import','reversal','pos_session_resolve','intercompany'
  source_id BIGINT UNSIGNED NULL,
  source_event VARCHAR(30) NOT NULL DEFAULT 'post',       -- membedakan >1 jurnal per dokumen (mis. 'post','cogs','settle')
  reference_number VARCHAR(100) NULL,                     -- no. struk / no. dokumen (informasi)
  description VARCHAR(500) NOT NULL,
  pos_session_id BIGINT UNSIGNED NULL,
  reversal_of_id BIGINT UNSIGNED NULL,                    -- diisi pada jurnal pembalik
  reversed_by_id BIGINT UNSIGNED NULL,                    -- diisi pada jurnal asli
  total_debit  DECIMAL(18,2) NOT NULL DEFAULT 0,          -- cache, divalidasi trigger
  total_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
  currency_code CHAR(3) NOT NULL DEFAULT 'IDR',
  exchange_rate DECIMAL(15,6) NOT NULL DEFAULT 1,
  legacy_journal_header_id BIGINT UNSIGNED NULL,          -- jejak backfill
  created_by BIGINT UNSIGNED NOT NULL,
  posted_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_je_number (entry_number),
  UNIQUE KEY uq_je_source (source_type, source_id, source_event),   -- idempoten: satu dokumen = satu jurnal per event
  UNIQUE KEY uq_je_legacy (legacy_journal_header_id),
  KEY idx_je_branch_date (branch_id, entry_date, status),
  KEY idx_je_entity_date (entity_id, entry_date, status),
  KEY idx_je_ic (intercompany_group_id),
  CONSTRAINT fk_je_entity   FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE RESTRICT,
  KEY idx_je_session (pos_session_id),
  KEY idx_je_reversal (reversal_of_id),
  CONSTRAINT fk_je_branch   FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
  CONSTRAINT fk_je_reversal FOREIGN KEY (reversal_of_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CONSTRAINT fk_je_revby    FOREIGN KEY (reversed_by_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CONSTRAINT fk_je_creator  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_je_totals  CHECK (total_debit >= 0 AND total_credit >= 0)
) ENGINE=InnoDB;
-- pos_session_id FK ditambahkan setelah pos_sessions dibuat (4.5).
```

### 4.4 `journal_items`

```sql
CREATE TABLE journal_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  journal_entry_id BIGINT UNSIGNED NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,                     -- denormalisasi; harus = header & akun & cabang
  line_no SMALLINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,                     -- dimensi cabang per baris (memungkinkan jurnal antar cabang)
  debit  DECIMAL(18,2) NOT NULL DEFAULT 0,
  credit DECIMAL(18,2) NOT NULL DEFAULT 0,
  customer_id BIGINT UNSIGNED NULL,                       -- subledger AR (wajib bila akun 1130/2140)
  supplier_id BIGINT UNSIGNED NULL,                       -- subledger AP (wajib bila akun 2110)
  sale_id BIGINT UNSIGNED NULL,                           -- dokumen piutang yang di-open/settle (untuk Aging)
  product_id BIGINT UNSIGNED NULL,
  warehouse_id BIGINT UNSIGNED NULL,
  cash_register_id BIGINT UNSIGNED NULL,
  memo VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  UNIQUE KEY uq_ji_line (journal_entry_id, line_no),
  KEY idx_ji_account_branch (account_id, branch_id, journal_entry_id),
  KEY idx_ji_entity_account (entity_id, account_id),
  CONSTRAINT fk_ji_entity   FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE RESTRICT,
  KEY idx_ji_customer (customer_id, account_id),
  KEY idx_ji_sale (sale_id),
  KEY idx_ji_supplier (supplier_id, account_id),
  CONSTRAINT fk_ji_entry    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ji_account  FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ji_branch   FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ji_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ji_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ji_sale     FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE RESTRICT,
  CONSTRAINT chk_ji_one_side CHECK (
      debit >= 0 AND credit >= 0
      AND ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))
  )
) ENGINE=InnoDB;
```

### 4.5 `pos_sessions` (evolusi dari `cash_register_shifts`)

```sql
CREATE TABLE pos_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_number VARCHAR(40) NOT NULL,                    -- SES-{BRANCH}-{YYYYMMDD}-{seq}
  entity_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  cash_register_id BIGINT UNSIGNED NOT NULL,
  cash_account_id BIGINT UNSIGNED NOT NULL,               -- akun 1110xxx milik laci ini
  cashier_id BIGINT UNSIGNED NOT NULL,
  opened_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  status ENUM('open','closing','closed','pending_approval','reconciled') NOT NULL DEFAULT 'open',
  -- closed = selisih 0 / dalam ambang (sudah dijurnal otomatis); pending_approval = di atas ambang, saldo di 1199; reconciled = approval resolved
  opening_float DECIMAL(18,2) NOT NULL DEFAULT 0,         -- modal laci (harus = saldo 1110xxx saat buka)
  -- dihitung dari journal_items akun cash_account_id dalam sesi ini (bukan dari tabel sales)
  expected_cash DECIMAL(18,2) NULL,
  counted_cash  DECIMAL(18,2) NULL,                       -- hasil hitung fisik
  cash_difference DECIMAL(18,2) GENERATED ALWAYS AS (counted_cash - expected_cash) STORED,
  denomination_json JSON NULL,                            -- rincian pecahan uang
  cash_sales_total     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- ringkasan informasi
  cash_refunds_total   DECIMAL(18,2) NOT NULL DEFAULT 0,
  ar_collections_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  cash_out_total       DECIMAL(18,2) NOT NULL DEFAULT 0,  -- kas kecil, setoran, transfer keluar
  non_cash_total       DECIMAL(18,2) NOT NULL DEFAULT 0,  -- QRIS/EDC/transfer
  difference_journal_entry_id BIGINT UNSIGNED NULL,
  difference_resolution ENUM('none','auto_expense','auto_other_income','suspense_pending','resolved_expense','resolved_employee_receivable','resolved_other_income') NOT NULL DEFAULT 'none',
  threshold_applied DECIMAL(18,2) NULL,                   -- snapshot ambang entitas saat tutup
  closed_by BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  legacy_shift_id BIGINT UNSIGNED NULL,                   -- jejak dari cash_register_shifts
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  open_register_guard BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status IN ('open','closing'), cash_register_id, NULL)) STORED,
  open_cashier_guard  BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status IN ('open','closing'), cashier_id, NULL)) STORED,
  UNIQUE KEY uq_session_number (session_number),
  UNIQUE KEY uq_one_open_per_register (open_register_guard),   -- satu sesi terbuka per laci (ditegakkan DB)
  UNIQUE KEY uq_one_open_per_cashier (open_cashier_guard),     -- satu sesi terbuka per kasir
  UNIQUE KEY uq_legacy_shift (legacy_shift_id),
  KEY idx_session_branch (branch_id, status, opened_at),
  CONSTRAINT fk_ps_entity   FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ps_branch   FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ps_register FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ps_account  FOREIGN KEY (cash_account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ps_cashier  FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ps_diff_je  FOREIGN KEY (difference_journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CONSTRAINT chk_ps_closed  CHECK (status NOT IN ('closed','pending_approval','reconciled') OR (closed_at IS NOT NULL AND counted_cash IS NOT NULL AND expected_cash IS NOT NULL))
) ENGINE=InnoDB;

-- Workflow approval selisih kas di atas ambang (Hybrid) — DIPUTUSKAN
CREATE TABLE cash_difference_approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  pos_session_id BIGINT UNSIGNED NOT NULL,
  direction ENUM('shortage','overage') NOT NULL,
  amount DECIMAL(18,2) NOT NULL,                          -- nilai absolut selisih
  threshold_applied DECIMAL(18,2) NOT NULL,
  status ENUM('pending','approved','rejected','resolved') NOT NULL DEFAULT 'pending',
  requested_resolution ENUM('expense_6810','employee_receivable_1135','other_income_4150') NULL,
  approved_resolution  ENUM('expense_6810','employee_receivable_1135','other_income_4150') NULL,
  employee_user_id BIGINT UNSIGNED NULL,                  -- wajib bila resolusi 1135
  requested_by BIGINT UNSIGNED NOT NULL,
  requested_at DATETIME NOT NULL,
  reason TEXT NOT NULL,                                   -- penjelasan kasir (wajib)
  decided_by BIGINT UNSIGNED NULL,                        -- supervisor; ≠ requested_by dan ≠ kasir sesi (dicek service)
  decided_at DATETIME NULL,
  decision_notes TEXT NULL,
  suspense_journal_entry_id BIGINT UNSIGNED NOT NULL,     -- jurnal tutup shift ke 1199
  resolution_journal_entry_id BIGINT UNSIGNED NULL,       -- jurnal pemindahan dari 1199
  resubmission_of_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  KEY idx_cda_status (entity_id, branch_id, status),
  KEY idx_cda_session (pos_session_id),
  CONSTRAINT fk_cda_entity  FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cda_branch  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cda_session FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cda_susp_je FOREIGN KEY (suspense_journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cda_res_je  FOREIGN KEY (resolution_journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cda_emp     FOREIGN KEY (employee_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cda_req     FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cda_dec     FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cda_resub   FOREIGN KEY (resubmission_of_id) REFERENCES cash_difference_approvals(id) ON DELETE RESTRICT,
  CONSTRAINT chk_cda_amount CHECK (amount > 0),
  CONSTRAINT chk_cda_emp    CHECK (approved_resolution IS NULL OR approved_resolution <> 'employee_receivable_1135' OR employee_user_id IS NOT NULL),
  CONSTRAINT chk_cda_short  CHECK (direction = 'overage'  OR approved_resolution IS NULL OR approved_resolution IN ('expense_6810','employee_receivable_1135')),
  CONSTRAINT chk_cda_over   CHECK (direction = 'shortage' OR approved_resolution IS NULL OR approved_resolution = 'other_income_4150'),
  CONSTRAINT chk_cda_resolved CHECK (status <> 'resolved' OR (decided_by IS NOT NULL AND resolution_journal_entry_id IS NOT NULL))
) ENGINE=InnoDB;
-- Gate Laravel baru: 'approve-cash-difference' (role supervisor/master pada entitas yang sama).

ALTER TABLE journal_entries
  ADD CONSTRAINT fk_je_session FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id) ON DELETE RESTRICT;

-- Tautan dokumen operasional:
ALTER TABLE sales
  ADD COLUMN pos_session_id BIGINT UNSIGNED NULL,
  ADD COLUMN journal_entry_id BIGINT UNSIGNED NULL,
  ADD COLUMN payment_type ENUM('cash','qris','edc','transfer','credit') NULL,   -- menggantikan string bebas
  ADD KEY idx_sales_session (pos_session_id);
ALTER TABLE sale_items
  ADD COLUMN unit_cost DECIMAL(18,4) NULL,       -- HPP DIBEKUKAN saat jual = product_branch_costs.avg_unit_cost
  ADD COLUMN cogs_amount DECIMAL(18,2) NULL;     -- ROUND(unit_cost * quantity, 2); NOT NULL setelah backfill
ALTER TABLE sales_return_items
  MODIFY unit_cost DECIMAL(18,4) NOT NULL,       -- = sale_items.unit_cost asal (bukan avg saat retur)
  ADD COLUMN sale_item_id BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_sri_sale_item FOREIGN KEY (sale_item_id) REFERENCES sale_items(id) ON DELETE RESTRICT;
```

### 4.6 Penegakan Keseimbangan & Immutability (Trigger)

MySQL tidak punya deferred constraint trigger, maka pola-nya: item ditulis saat `status='draft'`, lalu `UPDATE status='posted'` — trigger memvalidasi total saat transisi itu. Setelah posted, item & header terkunci.

```sql
DELIMITER $$

-- (1) Item hanya boleh ditambah/diubah/dihapus selama header masih draft
CREATE TRIGGER trg_ji_bi BEFORE INSERT ON journal_items FOR EACH ROW
BEGIN
  IF (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: jurnal sudah posted, gunakan reversal';
  END IF;
  IF (SELECT is_postable FROM chart_of_accounts WHERE id = NEW.account_id) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: akun header tidak dapat diposting';
  END IF;
  -- Larangan mencampur entitas: header, akun, dan cabang baris harus satu entitas
  IF NEW.entity_id <> (SELECT entity_id FROM journal_entries WHERE id = NEW.journal_entry_id)
     OR NEW.entity_id <> (SELECT entity_id FROM chart_of_accounts WHERE id = NEW.account_id)
     OR NEW.entity_id <> (SELECT entity_id FROM branches WHERE id = NEW.branch_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: entitas tidak konsisten (dilarang mencampur entitas)';
  END IF;
END$$

CREATE TRIGGER trg_je_bi BEFORE INSERT ON journal_entries FOR EACH ROW
BEGIN
  IF NEW.entity_id <> (SELECT entity_id FROM branches WHERE id = NEW.branch_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: branch bukan milik entity';
  END IF;
END$$

CREATE TRIGGER trg_ji_bu BEFORE UPDATE ON journal_items FOR EACH ROW
BEGIN
  IF (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: immutable setelah posted';
  END IF;
END$$

CREATE TRIGGER trg_ji_bd BEFORE DELETE ON journal_items FOR EACH ROW
BEGIN
  IF (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: immutable setelah posted';
  END IF;
END$$

-- (2) Validasi saldo saat posting + kunci header
CREATE TRIGGER trg_je_bu BEFORE UPDATE ON journal_entries FOR EACH ROW
BEGIN
  DECLARE d DECIMAL(18,2); DECLARE c DECIMAL(18,2); DECLARE n INT; DECLARE p VARCHAR(20);

  IF OLD.status = 'draft' AND NEW.status = 'posted' THEN
    SELECT COALESCE(SUM(debit),0), COALESCE(SUM(credit),0), COUNT(*)
      INTO d, c, n FROM journal_items WHERE journal_entry_id = NEW.id;
    IF n < 2 OR d <> c OR d = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: jurnal tidak seimbang / kosong';
    END IF;
    SELECT status INTO p FROM accounting_periods
      WHERE entity_id = NEW.entity_id AND (branch_id = NEW.branch_id OR branch_id IS NULL)
        AND NEW.entry_date BETWEEN period_start AND period_end
      ORDER BY branch_id IS NULL LIMIT 1;
    IF p = 'closed' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: periode sudah ditutup';
    END IF;
    SET NEW.total_debit = d, NEW.total_credit = c, NEW.posted_at = COALESCE(NEW.posted_at, NOW());

  ELSEIF OLD.status = 'posted' THEN
    -- hanya transisi posted -> reversed dengan pengisian reversed_by_id yang diizinkan
    IF NOT (NEW.status = 'reversed' AND OLD.reversed_by_id IS NULL AND NEW.reversed_by_id IS NOT NULL
            AND NEW.entry_date = OLD.entry_date AND NEW.branch_id = OLD.branch_id
            AND NEW.total_debit = OLD.total_debit AND NEW.total_credit = OLD.total_credit) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: jurnal posted bersifat immutable';
    END IF;
  ELSEIF OLD.status = 'reversed' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: jurnal reversed bersifat final';
  END IF;
END$$

CREATE TRIGGER trg_je_bd BEFORE DELETE ON journal_entries FOR EACH ROW
BEGIN
  IF OLD.status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: tidak boleh dihapus, gunakan reversal';
  END IF;
END$$

DELIMITER ;
```

Lapisan aplikasi (`LedgerPostingService`, pengganti `JournalPostingService` + `JournalEntryService` + bagian jurnal di `SalePostingService`):
- Satu-satunya pintu tulis ke ledger; menerima DTO `{source_type, source_id, source_event, branch_id, entry_date, lines[]}`, insert draft + items, lalu `UPDATE status='posted'`, semua dalam `DB::transaction` dokumen sumber.
- Idempoten lewat `uq_je_source`.
- `reverse(JournalEntry $je, string $reason)`: buat jurnal baru `source_type='reversal'`, `reversal_of_id=$je->id`, baris debit/kredit ditukar, lalu set `$je->status='reversed', reversed_by_id`.
- Akun di-resolve dari tabel konfigurasi `account_mappings(key, branch_id NULL, account_id)` (mis. `sales.revenue`, `sales.discount`, `pos.cash` per laci), bukan `firstOrCreate`.
- Izin DB: user aplikasi produksi tanpa hak `DELETE` pada `journal_*` (opsional, defense-in-depth).

### 4.7 Isolasi entitas & cabang (tanpa RLS)

Karena MySQL tidak punya RLS:
0. Trait baru `HasEntityScope` (filter `entity_id` = entitas aktif user) dipasang **bersama** `HasBranchScope` pada `JournalEntry`, `JournalItem`, `ChartOfAccount`, `PosSession`, `ProductBranchCost`, `CashDifferenceApproval`, `AccountingPeriod`. User master memilih entitas aktif secara eksplisit (session `active_entity_id`); laporan **tidak pernah** menjumlah lintas entitas kecuali laporan "Konsolidasi Grup" khusus yang mengeliminasi 1295/2295.
1. Tambahkan `HasBranchScope` ke model `JournalEntry`/`JournalItem` (filter `journal_items.branch_id`).
2. Semua laporan memakai satu query builder `LedgerQuery::forUser($user)->branch($id)` yang memaksa filter; larang `withoutGlobalScopes()` di layer laporan (cek via test arsitektur Pest/PHPUnit).
3. VIEW per-cabang opsional untuk BI eksternal: `CREATE VIEW v_ledger_cbg_abi AS ... WHERE branch_id = <id>` dengan user DB read-only terbatas.
4. Transaksi **antar entitas** (mis. DEKA menjual pakan ke toko pupuk): dua jurnal terpisah dengan `intercompany_group_id` sama — entitas penjual Dr 1295 / Kr 4110 (+ Dr 5100 / Kr 1210), entitas pembeli Dr 1210 / Kr 2295. Settlement: pembeli Dr 2295 / Kr Bank, penjual Dr Bank / Kr 1295. Rekonsiliasi: saldo 1295 entitas A = saldo 2295 entitas B.
5. Jurnal antar cabang **dalam satu entitas** (transfer stok, setoran kas ke pusat) wajib menyeimbangkan per cabang memakai `1290 Rekening Antar Cabang` sehingga **Neraca per cabang juga seimbang**. Validasi tambahan di service: `SUM(debit-credit) GROUP BY branch_id = 0` per entry.

---

## 5. Matriks Aturan Jurnal

Notasi: **Dr** = Debit, **Kr** = Kredit. `Kas Laci` = akun `1110xxx` milik `pos_sessions.cash_account_id`. Semua baris membawa `branch_id` cabang transaksi. Bila cabang PPN (`branches.tax_enabled`), harga sudah termasuk PPN (`InclusiveTaxCalculator`), maka Pendapatan = DPP dan PPN dipisah.

Contoh angka: harga jual 3 karung @ Rp111.000 (termasuk PPN 11%) = Rp333.000; diskon Rp3.330; neto bayar Rp329.670 → DPP Rp297.000, PPN Rp32.670; HPP beku @ Rp80.000 = Rp240.000. (Potongan dicatat di sisi DPP: DPP kotor 300.000 − DPP neto 297.000 = 3.000.)

### a) Penjualan Tunai POS (`source_type='sale'`)

| # | Akun | Dr | Kr | Keterangan |
|---|---|---|---|---|
| 1 | 1110xxx Kas Laci (sesi aktif) | 329.670 | | uang diterima; untuk QRIS/EDC/transfer ganti ke 1125 Clearing atau 1120xxx Bank |
| 2 | 4130 Potongan Penjualan | 3.000 | | porsi DPP dari diskon (0 bila tanpa diskon) |
| 3 | 4110 Penjualan Barang Dagang | | 300.000 | DPP kotor |
| 4 | 2130 PPN Keluaran | | 32.670 | hanya cabang PPN |
| 5 | 5100 HPP | 240.000 | | `SUM(sale_items.cogs_amount)` — **dibekukan** |
| 6 | 1210 Persediaan | | 240.000 | dimensi `warehouse_id` |

Aturan: `sale_items.unit_cost` = `product_branch_costs.avg_unit_cost` cabang penjual saat posting (Moving Average, §4.1), dibekukan dan tidak pernah dihitung ulang; `sales.pos_session_id` wajib untuk metode tunai — POS menolak transaksi tunai tanpa sesi terbuka.

### b) Penjualan Kredit/Tempo (`source_type='sale'`, `payment_type='credit'`)

| # | Akun | Dr | Kr | Keterangan |
|---|---|---|---|---|
| 1 | 1130 Piutang Usaha | 329.670 | | `customer_id` + `sale_id` WAJIB (subledger AR); `due_date` wajib |
| 2 | 4130 Potongan Penjualan | 3.000 | | |
| 3 | 4110 Penjualan | | 300.000 | |
| 4 | 2130 PPN Keluaran | | 32.670 | |
| 5 | 5100 HPP | 240.000 | | |
| 6 | 1210 Persediaan | | 240.000 | |

Dengan DP (uang muka sebagian) X: baris 1 dipecah → Dr Kas Laci X, Dr 1130 (329.670 − X). Validasi: customer wajib, cek `credit_limit` (kolom baru di `customers`).

### c) Pembayaran Cicilan Piutang (`source_type='ar_payment'`)

Pembayaran Rp100.000 atas invoice di atas:

| # | Akun | Dr | Kr | Keterangan |
|---|---|---|---|---|
| 1 | 1110xxx Kas Laci (bila diterima kasir dalam sesi) / 1120xxx Bank / 1112 Kas Pusat | 100.000 | | `pos_session_id` diisi bila tunai di laci → masuk expected_cash |
| 2 | 1130 Piutang Usaha | | 100.000 | `customer_id`, `sale_id` per alokasi (satu baris per `payment_allocations`) |

Varian:
- Biaya transfer ditanggung toko Rp6.500: Dr Bank 93.500, Dr 6700 Beban Bank 6.500, Kr 1130 100.000.
- Kelebihan bayar: kelebihan Kr 2140 Deposit Pelanggan (bukan menaikkan `paid_amount` > total).
- Potongan pelunasan (diskon tunai): Dr 4130 Potongan, Kr 1130.
- Pembayaran diterima di cabang B atas piutang cabang A: Cabang B Dr Kas B / Kr 1290; Cabang A Dr 1290 / Kr 1130 (dalam satu entry, seimbang per cabang).

### d) Retur Penjualan (`source_type='sales_return'`)

Retur 1 karung (bagian dari transaksi di atas, neto Rp109.890 = DPP 99.000 + PPN 10.890; HPP beku Rp80.000 dari `sale_items.unit_cost` asal, **bukan** `products.purchase_price` maupun avg saat retur).

**Aturan wajib (DIPUTUSKAN):** pengurangan pendapatan karena retur **selalu** Dr `4140 Retur Penjualan` (contra-revenue, normal debit). Akun `4110` **tidak pernah** didebit oleh retur; `LedgerPostingService` menolak baris Dr 4110 dengan `source_type='sales_return'`. Laba-Rugi: Penjualan Kotor (4110) − Potongan (4130) − Retur (4140) = Penjualan Neto. Barang yang masuk kembali memicu recompute Moving Average dengan biaya beku asal.

**d.1 Retur atas penjualan tunai (refund tunai dari laci):**

| # | Akun | Dr | Kr |
|---|---|---|---|
| 1 | 4140 Retur Penjualan (contra-revenue) | 99.000 | |
| 2 | 2130 PPN Keluaran | 10.890 | |
| 3 | 1110xxx Kas Laci (sesi aktif) | | 109.890 |
| 4 | 1210 Persediaan | 80.000 | |
| 5 | 5100 HPP | | 80.000 |

Refund via transfer: baris 3 → Kr 1120xxx Bank. Refund belum dibayar: Kr 2150 Utang Refund Pelanggan.

**d.2 Retur atas penjualan kredit (mengurangi piutang):**

| # | Akun | Dr | Kr |
|---|---|---|---|
| 1 | 4140 Retur Penjualan | 99.000 | |
| 2 | 2130 PPN Keluaran | 10.890 | |
| 3 | 1130 Piutang Usaha (`customer_id`, `sale_id`) | | 109.890 |
| 4 | 1210 Persediaan | 80.000 | |
| 5 | 5100 HPP | | 80.000 |

Jika piutang invoice sudah lunas/lebih kecil dari nilai retur: sisa → Kr 2140 Deposit Pelanggan atau Kr Kas (refund).
Barang retur rusak (tidak bisa dijual): baris 4 diganti Dr 5120 Selisih/Penyusutan Persediaan.
Catatan: saat ini `JournalPostingService::postSalesReturn` (baris 61-88) mendebit langsung `4110` — **wajib diubah** ke `4140`; jurnal retur historis direklasifikasi saat backfill (§7.3 langkah 5b).

### e) Tutup Shift — Selisih Kas Fisik vs Sistem (`source_type='pos_session_close'`)

`expected_cash` = saldo akun Kas Laci dari `journal_items` yang `pos_session_id = sesi` + `opening_float` (yaitu: penjualan tunai + pelunasan piutang tunai − refund tunai − kas keluar/kas kecil − setoran). **Tidak lagi** dihitung dari tabel `sales`.

`cash_difference = counted_cash − expected_cash`.

**Kebijakan Hybrid (DIPUTUSKAN 30 Okt 2026).** Ambang `T` = `entities.cash_shortage_auto_threshold` (kas kurang) dan `entities.cash_overage_auto_threshold` (kas lebih), default **Rp 10.000**, dapat diatur per entitas; nilai yang dipakai di-snapshot ke `pos_sessions.threshold_applied`. Perbandingan memakai nilai absolut; `≤ T` = otomatis.

**e.1 Kas KURANG ≤ T (contoh −Rp7.500) → otomatis, sesi `closed`, `difference_resolution='auto_expense'`:**

| # | Akun | Dr | Kr |
|---|---|---|---|
| 1 | 6810 Beban Selisih Kas Kurang | 7.500 | |
| 2 | 1110xxx Kas Laci | | 7.500 |

**e.2 Kas KURANG > T (contoh −Rp20.000) → suspense + approval, sesi `pending_approval`:**

Langkah 1 — saat tutup shift (`source_type='pos_session_close'`), otomatis:

| # | Akun | Dr | Kr |
|---|---|---|---|
| 1 | 1199 Selisih Kas Belum Diselesaikan | 20.000 | |
| 2 | 1110xxx Kas Laci | | 20.000 |

Sekaligus dibuat `cash_difference_approvals` (`status='pending'`, `reason` wajib diisi kasir).

Langkah 2 — keputusan supervisor (`source_type='pos_session_resolve'`, `source_id = cash_difference_approvals.id`, tanggal jurnal = tanggal keputusan):

| Resolusi disetujui | Dr | Kr |
|---|---|---|
| `expense_6810` (ditanggung toko) | 6810 Beban Selisih Kas Kurang 20.000 | 1199 20.000 |
| `employee_receivable_1135` (dibebankan ke kasir; `employee_user_id` wajib) | 1135 Piutang Karyawan 20.000 | 1199 20.000 |

Pelunasan piutang karyawan kemudian: Dr Kas / Kr 1135 (atau via potong gaji).

Workflow `cash_difference_approvals`: `pending` → `approved` → `resolved` (approve + posting jurnal resolusi dalam satu transaksi DB), **atau** `pending` → `rejected` (saldo tetap di 1199; wajib diajukan ulang dengan `resubmission_of_id`). Pemutus wajib punya Gate `approve-cash-difference` pada entitas yang sama dan bukan kasir sesi/pengaju. Sesi menjadi `reconciled` setelah approval `resolved`. Kontrol akhir bulan: saldo 1199 per cabang **harus 0**; `closePeriod` menolak bila masih ada approval `pending`/`rejected`.

**e.3 Kas LEBIH (konsisten dengan Hybrid):**
- ≤ T (contoh +Rp5.000), otomatis, `auto_other_income`: Dr 1110xxx Kas Laci 5.000 / Kr 4150 Pendapatan Selisih Kas Lebih 5.000.
- > T (contoh +Rp25.000): Dr 1110xxx 25.000 / Kr **1199** 25.000 + `cash_difference_approvals(direction='overage')`; resolusi supervisor `other_income_4150`: Dr 1199 / Kr 4150. Bila terbukti milik pelanggan: tolak, lalu jurnal manual (dengan approval) Dr 1199 / Kr 2140 Deposit Pelanggan.

**e.4 Setoran kas laci saat tutup shift (bukan selisih, tapi wajib agar sumber dana terlacak):**

| Akun | Dr | Kr |
|---|---|---|
| 1115 Kas Dalam Perjalanan / 1112 Kas Pusat / 1120xxx Bank | jumlah setor | |
| 1110xxx Kas Laci | | jumlah setor |

Sisa di laci = modal laci sesi berikutnya → `opening_float` sesi berikut **harus sama** dengan saldo akun laci (validasi saat buka sesi; perbedaan = indikasi kebocoran).


### Ringkasan arah saldo per akun

| Akun | Bertambah | Berkurang |
|---|---|---|
| Kas/Bank/Piutang/Persediaan/HPP/Beban/Potongan/Retur (normal D) | Debit | Kredit |
| Utang/PPN Keluaran/Deposit/Ekuitas/Pendapatan (normal K) | Kredit | Debit |

---

## 6. Rewiring Laporan `/backoffice/reports`

| Laporan | Sumber baru |
|---|---|
| Mutasi Kas | `journal_items` WHERE `account.cash_flow_tag='cash'` (bukan `LIKE '%kas%'`), per akun laci/bank/cabang, dengan lawan akun (counter-account) dan `source_type` → sumber dana terlacak |
| Laba-Rugi | `journal_items` akun 4–6 per `entity_id` + `branch_id` (4110 − 4130 − 4140 = Penjualan Neto), status posted (reversal ikut menetralkan); grouping via `parent_id` |
| Aging AR | Subledger: `journal_items` akun 1130 GROUP BY `customer_id, sale_id` (open item), umur dari `sales.due_date`; total Aging **harus = saldo 1130** |
| Rekonsiliasi Kasir (baru) | `pos_sessions` + drilldown `journal_items` per sesi + antrian `cash_difference_approvals` & saldo 1199 |
| Penjualan | boleh tetap dari `sales` (laporan operasional), tetapi footer menampilkan rekonsiliasi ke 4110/4130/4140 |

---

## 7. Rencana Migrasi & Backfill

### 7.1 Persiapan (Minggu 0)
1. Freeze rilis fitur keuangan. Backup penuh MySQL produksi (mysqldump + binlog position).
2. Bekukan & dokumentasikan perilaku saat ini lewat test karakterisasi (`tests/Feature/AccountingReportServiceTest.php`, `ReportCenterWebTest.php`).
3. **Cutover DIPUTUSKAN: 1 Desember 2026 00:00 WIB**, karena data transaksi live baru dimulai setelah reset `2026_10_13_130000_reset_live_transactions_and_share_catalog.php` (jumlah data relatif kecil → backfill penuh realistis).

### 7.2 Fase 1 — Skema (additive, tanpa memutus kode lama)
1. Migrasi: CREATE `entities`, `branches.entity_id`, ALTER `chart_of_accounts` (+`entity_id`), CREATE `product_branch_costs`, `inventory_cost_movements`, `cash_difference_approvals`, `accounting_periods`, `journal_entries`, `journal_items`, `pos_sessions`, `account_mappings`; kolom baru di `sales`, `sale_items`, `customers` (`credit_limit`, `payment_terms_days`, `code`), FK `payments.customer_id`.
2. Seeder entitas (`MB-PUPUK`, `DEKA`, ambang 10.000) lalu COA v2 **per entitas**: akun baru (4140, 4150, 6110, 6810, 1199, 1115, 1125, 1135, 1290, 1295, 2295, 2140, 2150, 3900), perbaiki `normal_balance/is_contra` (4130, 1320), buat sub-akun `1110xxx` per `cash_registers` aktif dan `1120xxx` per rekening.
3. Trigger dipasang **setelah** backfill selesai (agar backfill bisa menulis status `posted` langsung secara terkontrol), atau backfill menulis draft lalu posting normal.

### 7.3 Fase 2 — Backfill data historis (sejak reset Oktober 2026 s/d cutover)
0. **Penetapan entitas**: cabang PAKAN → DEKA, sisanya → MB-PUPUK. Jurnal lama yang mencampur cabang dua entitas (diverifikasi; seharusnya tidak ada) dipecah per entitas + pasangan 1295/2295.
1. **Saldo awal (opening balance) per entitas & cabang** per tanggal reset/go-live: kas fisik per laci & brankas (hasil hitung), saldo bank per rekening (rekening koran), persediaan (stock opname × biaya), piutang per invoice (`ImportPiutangAwal` / `OpeningBalanceService`), utang per supplier. Lawan: `3900 Ekuitas Pembukaan` → lalu reklasifikasi ke `3110/3210`.
2. **Migrasi jurnal lama**: setiap `journal_headers` (termasuk yang soft-deleted) → `journal_entries` dengan `legacy_journal_header_id`, `source_type` ditebak dari tautan (`payments.journal_header_id` → `ar_payment`, `sales_returns.journal_header_id` → `sales_return`, `reference_number = sales.receipt_number` → `sale`, sisanya `legacy_import`). `journal_lines` → `journal_items` dengan `branch_id` dari header. Jurnal soft-deleted dimigrasi lalu dibuat **reversal** (bukan dibuang) agar jejak audit ada.
3. **Reklasifikasi Kas**: jurnal sale dengan `cash_register_shift_id` → pindahkan baris 1110 ke `1110xxx` laci shift tersebut (jurnal reklasifikasi per sesi, bukan edit baris). Tanpa shift → `1111 Kas Kecil` cabang.
4. **`cash_register_shifts` → `pos_sessions`** (`legacy_shift_id`); hitung ulang `expected_cash` dari ledger; untuk shift closed dengan `difference ≠ 0`, terapkan Hybrid §5e bertanggal `closed_at`: ≤ Rp10.000 langsung 6810/4150; > Rp10.000 ke 1199 + approval pending yang **wajib diselesaikan supervisor sebelum 1 Des 2026**.
5. **HPP beku & Moving Average**: replay kronologis seluruh pergerakan stok per `branch_id, product_id` (opening → goods_receipts → sales → returns → transfers → adjustments) untuk membangun `inventory_cost_movements` dan `product_branch_costs`. Isi `sale_items.unit_cost` dengan avg hasil replay; `sales_return_items.unit_cost` = `sale_items.unit_cost` asal. Selisih HPP replay vs Dr 5100 jurnal lama dibukukan sebagai **jurnal koreksi** (5100 vs 1210) per cabang per bulan, bukan mengedit jurnal lama.
5b. **Reklasifikasi retur**: jurnal retur lama yang mendebit 4110 → jurnal reklasifikasi Dr 4140 / Kr 4110 sebesar nilai yang sama.
6. **Subledger AR**: untuk baris 1130 isi `customer_id`/`sale_id` dari `sales` dan `payment_allocations`. Penjualan tempo tanpa jurnal (bila ada) → buat jurnal `legacy_import`.
7. **Normalisasi `payment_method`** → `payment_type` enum; daftar nilai tak dikenal dilaporkan untuk keputusan manual.

### 7.4 Fase 3 — Rekonsiliasi (wajib lulus sebelum go-live)
Query kontrol (semua harus 0 / sama):
1. Setiap entry: `SUM(debit) = SUM(credit)` dan per `branch_id`.
2. Total trial balance lama (`journal_lines`) = baru (`journal_items`) per akun per cabang per bulan (setelah memperhitungkan reklasifikasi).
3. Setiap `sales` status completed punya tepat satu `journal_entries(source_type='sale')`; setiap `payments` & `sales_returns` juga.
4. `SUM(sale_items.cogs_amount)` per sale = Dr 5100 entry sale.
5. Saldo 1130 per customer = `SUM(total_amount − paid_amount)` sales tempo = total Aging AR baru.
6. Saldo 1210 per cabang = `SUM(product_branch_costs.inventory_value)` dan `qty_on_hand = inventories.quantity` (selisih → jurnal penyesuaian 5120 dengan approval).
7. Saldo 1110xxx per laci pada cutover = hitung fisik; 1199 & 3900 = 0 setelah reklasifikasi; tidak ada `cash_difference_approvals` pending.
8. Tidak ada `journal_items` yang akunnya `is_postable = 0`.
9. Tidak ada journal_entry dengan baris berbeda `entity_id`; saldo 1295 entitas A = saldo 2295 entitas B.
10. Tidak ada baris Dr 4110 dengan `source_type='sales_return'`.

### 7.5 Fase 4 — November 2026: Dual-write & Automated Testing → Cutover 1 Des 2026 (DIPUTUSKAN)
- **1–7 Nov**: migrasi Fase 1 + seeder di-merge **hanya setelah persetujuan pengguna**; deploy dijalankan pengguna via `deploy-direct.ps1`. Backfill Fase 2 di staging (salinan produksi) + suite rekonsiliasi §7.4.
- **8 Nov**: aktifkan **dual-write** (feature flag `ledger_v2_dual_write`): setiap service posting menulis ke `journal_headers/lines` lama **dan** `journal_entries/items` baru dalam transaksi DB yang sama; 3 hari pertama log-only, lalu fail-closed.
- **8–30 Nov**: job harian 23:30 WIB `ledger:reconcile --date=yesterday` (cek §7.4 + perbandingan lama vs baru per akun/cabang/entitas); laporan v2 di `/backoffice/reports/v2/...` untuk master/finance. CI dengan MySQL 8: unit (Moving Average, ambang Hybrid, guard entitas), feature (POS tunai/tempo, cicilan, retur tunai/kredit, tutup shift ≤/> ambang, approval), dan test trigger DB. Target: 0 selisih 7 hari berturut-turut sebelum 28 Nov.
- **15 Nov**: `pos_sessions` wajib di POS; tutup shift memakai Hybrid.
- **28 Nov**: go/no-go review.
- **30 Nov malam**: tutup semua sesi, hitung fisik, setor; selesaikan semua approval 1199; rekonsiliasi final; pasang trigger immutability; tutup periode November (`soft_closed`) per entitas.
- **1 Des 2026 00:00 WIB — cutover**: ledger v2 menjadi sumber kebenaran; laporan dialihkan berurutan Mutasi Kas → Rekonsiliasi Kasir → Aging AR → Laba-Rugi → Neraca/Neraca Saldo; `journal_headers/journal_lines` read-only, lalu diganti VIEW kompatibilitas setelah satu periode stabil.
- Service yang dialihkan ke `LedgerPostingService`: `SalePostingService`, `ARAPPaymentService`, `SalesReturnService`, `CashRegisterShiftService`, `ExpenseService`, `CashTransactionService`, `CashTransferService`, `GoodsReceiptService`, `StockTransferService`, `StockAdjustmentService`, `PurchaseReturnService`, `SupplierPaymentService`, `OpeningBalanceService`, `FixedAssetService`.
- Hentikan migrasi destruktif seperti `reset_live_transactions_*` (guard: tolak bila ada `journal_entries` posted).
- Rollback: selama November cukup mematikan flag (sistem lama utuh); setelah cutover hanya rollback-maju (jurnal pembalik).

### 7.6 Risiko & Mitigasi
| Risiko | Mitigasi |
|---|---|
| Trigger MySQL tidak aktif di SQLite test | Test ledger di MySQL (CI service), + assertion aplikasi |
| Biaya historis tidak akurat | Replay Moving Average + jurnal koreksi 5100/1210; didokumentasikan sebagai estimasi pra-cutover |
| Kasir menolak sesi wajib | UI POS memblokir transaksi tunai tanpa sesi; SOP di `docs/SOP_OPERASIONAL_DAN_PANDUAN_GO_LIVE.md` diperbarui |
| PAKAN (badan usaha lain) tercampur | `entity_id` wajib + trigger larangan campur entitas + akun 1295/2295 |

---

## 8. Keputusan — DIPUTUSKAN (30 Okt 2026)

| # | Topik | Keputusan | Diterapkan di |
|---|---|---|---|
| 1 | Metode biaya persediaan | **Moving Average per cabang per produk**; `unit_cost` di-freeze di `sale_items` & `sales_return_items` saat transaksi | §4.1, §5a, §5d, §7.3 |
| 2 | Selisih kas | **Hybrid**: kurang ≤ Rp10.000 → otomatis 6810; kurang > Rp10.000 → 1199 + approval supervisor → 6810 atau 1135. Lebih ≤ ambang → 4150; > ambang → 1199 + approval → 4150. Ambang per entitas, default 10.000 | §3, §4.0, §4.5, §5e, §7.3 |
| 3 | Cutover | **1 Desember 2026 00:00 WIB**; November = dual-write + automated testing | §7.1, §7.5 |
| 4 | Pemisahan entitas | **Wajib**: tabel `entities` (MB-PUPUK vs DEKA/PAKAN); `entity_id` di branches, COA, journal_entries, journal_items, pos_sessions, accounting_periods; dilarang mencampur entitas; antar entitas via 1295/2295 | §3, §4.0–4.7, §7.3 |
| 5 | Retur penjualan | **4140 Retur Penjualan** (contra-revenue, normal debit); 4110 tidak pernah didebit retur | §3, §5d, §7.3 |
