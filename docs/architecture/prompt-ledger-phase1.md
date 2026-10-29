# PROMPT — Developer Agent: Ledger v2 Tahap 1 (Migrasi 1–12)

## 1. Peran & Konteks
Kamu adalah **Senior Laravel Developer** (Laravel 12, PHP 8.3, MySQL 8 InnoDB) di repo **maju-bersama** (lokal: `D:\MAJUBERSAMA_CURSOR`, remote `mahakaryahutamacorp-svg/maju-bersama`). Tugasmu adalah mengimplementasikan **Tahap 1 ledger double-entry v2**, yaitu skema, seeder, dan trigger, **tanpa** mengubah perilaku posting yang berjalan.

**Baca dulu sampai selesai sebelum menulis kode:**
1. `docs/architecture/accounting-ledger-spec.md` (v1.1, keputusan §8 DIPUTUSKAN 30 Okt 2026). Bagian utama: §3 COA, §4.0–§4.7 DDL dan trigger, §5 matriks jurnal.
2. `docs/architecture/ledger-ddl-migration-summary.md`: urutan migrasi, seeder, dan aturan.
3. `.cursor/rules/approved-changes-to-main.mdc`.
4. Migrasi existing yang di-ALTER: `database/migrations/0000_12_31_000000_create_branches_table.php`, `2026_09_11_000001_create_chart_of_accounts_table.php`, `2026_09_23_105031_add_parent_category_id_to_accounts.php`, `2026_09_13_000001/2_create_sales*/sale_items`, `2026_09_21_000002_create_sales_returns_table.php`, `2026_09_24_000002_create_payments_and_allocations_tables.php`, `2026_09_24_000006_add_group_to_customers.php`, `2026_09_20_000002/3_cash_registers/cash_register_shifts`, `2026_10_23_000001_add_retail_tax_controls.php`.

Jika file docs belum ada di repo lokal, minta pengguna menyalinnya. Jangan mengarang isi spec.

### Fakta skema existing (sudah dicek di `main`, wajib dipakai)
| Fakta | Sumber | Implikasi |
|---|---|---|
| Semua PK `$table->id()` (BIGINT UNSIGNED): branches, users, products, customers, suppliers, sales, sale_items, sales_returns, sales_return_items, cash_registers, cash_register_shifts, chart_of_accounts, journal_headers | migrasi create masing-masing | FK baru pakai `foreignId()` / `unsignedBigInteger` |
| `chart_of_accounts`: `code` string **unique global** (index `chart_of_accounts_code_unique`), `type` **string bebas**, `is_active`, `softDeletes`, plus `parent_category_id` (self-FK nullOnDelete) | `2026_09_11_000001` baris 13-18; `2026_09_23_105031` | **Jangan** buat `parent_id` baru; **pakai ulang `parent_category_id`** sebagai parent COA. Drop unique global, ganti unique `(entity_id, code)` |
| `sales.payment_method` = string bebas default `'cash'`; nilai di kode: `cash/Cash/tunai/Tunai`, `tempo/piutang/kredit`; validasi `nullable|string|max:255` | `2026_09_13_000001` baris 16; `SalePostingService.php:233`; `CashRegisterShiftService.php:70` | **Belum ada enum payment.** `payment_type` adalah kolom **baru**; `payment_method` lama **tidak diubah** |
| `sales.payment_status` ENUM `UNPAID/PARTIAL/PAID`; `cash_register_shifts.status` ENUM `open/closed`; `payments.type` ENUM `AR/AP`; `sales_returns.status` ENUM `pending/completed` | migrasi terkait | Codebase memakai `$table->enum()` untuk status. Jangan ubah enum lama di tahap 1 |
| `sales_return_items.unit_cost` **sudah ada** `decimal(15,2)` default 0, `subtotal_cost` `decimal(15,2)` | `2026_09_21_000002` baris 40-42 | Jangan add. **MODIFY** ke `decimal(18,4)` (nullable tidak perlu karena sudah default 0) |
| `sale_items` belum punya kolom biaya; sudah punya `discount_amount`, `is_taxable`, `tax_rate`, `dpp_amount`, `tax_amount` | `2026_10_11_000001`, `2026_10_23_000001` | Add `unit_cost`, `cogs_amount`, `cost_source` |
| `payments.customer_id` `unsignedBigInteger` **tanpa FK** | `2026_09_24_000002` baris 15 | Tambah FK hanya bila data bersih. Lihat migrasi 11 |
| `customers` dibuat kondisional (`if (! Schema::hasTable('customers'))`) | `2026_09_24_000006` baris 14 | Pakai `Schema::hasColumn` guard saat menambah kolom |
| Kode cabang: `OFFICE-PUPUK`, `GUDANG-PUPUK`, `CBG-ABI`, `CBG-MAMA`, `CBG-AROFAH`, `CBG-TULUSAYU`, `CBG-LUBUKHARJO`, `PAKAN` (cabang PAKAN lama digabung ke kode `PAKAN`) | `BusinessStructureSeeder.php:64-72`; `2026_10_15_124500_unite_pakan_office_warehouse_store.php:26-36` | `PAKAN` → entitas DEKA; sisanya → MB-PUPUK |
| Tidak ada tabel `pos_sessions`, `entities`, `journal_entries` | tree repo | Semua tabel baru |
| Laravel 12 mendukung `->change()` native (tanpa doctrine/dbal) | — | Jangan install doctrine/dbal |

## 2. Git Workflow (WAJIB)
1. `git checkout main && git pull --ff-only origin main` lalu `git checkout -b feature/ledger-v2-phase1`.
2. **Jangan pernah** commit, merge, rebase paksa, atau push ke `main`. Jangan force-push. Satu migrasi (beserta test-nya) = satu commit kecil, misalnya `feat(ledger): migration 01 create entities table`.
3. Boleh `git push -u origin feature/ledger-v2-phase1` dan membuka **PR ke main hanya untuk review** (draft PR). **Jangan merge PR.** Merge ke main hanya setelah pengguna menyetujui, sesuai `approved-changes-to-main.mdc`.
4. **Tanpa deploy**: jangan jalankan `deploy-direct.ps1`, jangan SSH, jangan `git pull` di server.
5. Jalankan migrasi **hanya** terhadap DB lokal/test (MySQL 8 lokal atau Docker `mysql:8.0`, plus SQLite untuk suite existing). **Dilarang** memakai kredensial atau `.env` produksi. Sebelum `migrate`, cek `php artisan env` dan `DB_DATABASE` bukan DB produksi.

## 3. Urutan Migrasi 1–12 (dependensi FK: parent sebelum child)
Urutan dependensi: `entities` → `branches.entity_id` → COA → `account_mappings` → `product_branch_costs`/`inventory_cost_movements` → `accounting_periods` → `journal_entries` → `journal_items` → `pos_sessions` (+FK balik `journal_entries.pos_session_id`) → `cash_difference_approvals` → link di tabel sales → **trigger terakhir**.

Aturan umum:
- Semua `entity_id` **nullable** di tahap 1. NOT NULL baru di migrasi 13 (tahap 2).
- FK baru `->restrictOnDelete()`. Uang `decimal(18,2)`, biaya satuan `decimal(18,4)`.
- Setiap `down()` wajib berjalan: urutan drop FK → drop index → drop kolom/tabel; kembalikan unique `chart_of_accounts.code` dan tipe `sales_return_items.unit_cost` `decimal(15,2)`.
- Gunakan guard `Schema::hasTable/hasColumn` agar idempoten di SQLite test.
- Prefix tanggal `2026_11_01_0000NN_`.

**01 `2026_11_01_000001_create_entities_table.php`**
`entities`: `id`, `code` string(20) unique, `legal_name` string(200), `npwp` string(30) null, `base_currency` char(3) default 'IDR', `cash_shortage_auto_threshold` decimal(18,2) default 10000, `cash_overage_auto_threshold` decimal(18,2) default 10000, `is_active` bool default true, timestamps. CHECK threshold ≥ 0 (raw, hanya MySQL).

**02 `2026_11_01_000002_add_entity_id_to_branches_table.php`**
`branches.entity_id` foreignId nullable after `id`, `constrained('entities')->restrictOnDelete()`, index. down: `dropConstrainedForeignId('entity_id')`.

**03 `2026_11_01_000003_upgrade_chart_of_accounts_for_ledger_v2.php`**
- `entity_id` foreignId nullable constrained entities.
- `dropUnique('chart_of_accounts_code_unique')`, lalu `unique(['entity_id','code'],'uq_coa_entity_code')`. Catatan: NULL entity_id tidak bentrok di MySQL unique. Akun lama di-assign seeder.
- `code` → string(20) `->change()`.
- `level` tinyint unsigned default 1, `normal_balance` enum('D','K') default 'D', `is_postable` bool default true, `is_contra` bool default false, `is_system` bool default false.
- `branch_id` foreignId nullable constrained branches, `cash_register_id` foreignId nullable constrained cash_registers + unique.
- `cash_flow_tag` enum('cash','operating','investing','financing','none') default 'none'.
- `type` tetap **string** di tahap 1 (data lama). Validasi nilai `asset|liability|equity|revenue|expense` lewat PHP enum `AccountType`. Konversi ke ENUM ditunda ke tahap 2 setelah data dibersihkan.
- Parent: pakai `parent_category_id` existing. Index `(type, is_active)`, `cash_flow_tag`.
- down: kebalikan semuanya. Sebelum mengembalikan unique `code`, hapus duplikat seed DEKA: `DELETE ... WHERE entity_id = (DEKA)`.

**04 `2026_11_01_000004_create_account_mappings_table.php`**
`account_mappings`: `id`, `entity_id` FK nullable, `key` string(60), `branch_id` FK null, `cash_register_id` FK null, `account_id` FK chart_of_accounts, timestamps; unique `(entity_id, key, branch_id, cash_register_id)`.

**05 `2026_11_01_000005_create_product_branch_costs_table.php`** (dua tabel; down drop keduanya)
- `product_branch_costs`: `entity_id` FK null, `branch_id` FK, `product_id` FK, `qty_on_hand` decimal(18,4) 0, `avg_unit_cost` decimal(18,4) 0, `inventory_value` decimal(18,2) 0, `last_movement_at` datetime null, `version` unsignedBigInteger 0, timestamps; unique `(branch_id, product_id)`, index `entity_id`.
- `inventory_cost_movements`: `entity_id`, `branch_id`, `product_id` (FK), `movement_type` enum('receipt','sale','sales_return','purchase_return','transfer_in','transfer_out','adjustment','opening'), `source_type` string(50), `source_id` unsignedBigInteger, `qty` decimal(18,4), `unit_cost` decimal(18,4), `value` decimal(18,2), `qty_after` decimal(18,4), `avg_cost_after` decimal(18,4), `value_after` decimal(18,2), `journal_entry_id` unsignedBigInteger null (FK ditambah di migrasi 07), `created_at`; index `(branch_id, product_id, id)`, `(source_type, source_id)`.

**06 `2026_11_01_000006_create_accounting_periods_table.php`**
`entity_id` FK null, `branch_id` FK null, `period_start` date, `period_end` date, `status` enum('open','soft_closed','closed') default 'open', `closed_by` FK users null, `closed_at` datetime null, timestamps; unique `(entity_id, branch_id, period_start)`.

**07 `2026_11_01_000007_create_journal_entries_table.php`**
Kolom sesuai spec §4.3: `entry_number` string(40) unique, `entity_id` FK null, `intercompany_group_id` char(26) null index, `branch_id` FK, `entry_date` date, `posted_at` datetime null, `status` enum('draft','posted','reversed') default 'draft', `source_type` string(50), `source_id` unsignedBigInteger null, `source_event` string(30) default 'post', `reference_number` string(100) null, `description` string(500), `pos_session_id` unsignedBigInteger null (FK di 09), `reversal_of_id`/`reversed_by_id` self-FK null, `total_debit`/`total_credit` decimal(18,2) 0, `currency_code` char(3) 'IDR', `exchange_rate` decimal(15,6) 1, `legacy_journal_header_id` FK journal_headers null unique, `created_by` FK users, `posted_by` FK users null, timestamps.
Unique `(source_type, source_id, source_event)`; index `(branch_id, entry_date, status)`, `(entity_id, entry_date, status)`.
Tambah FK `inventory_cost_movements.journal_entry_id` di sini. down: drop FK itu dulu.

**08 `2026_11_01_000008_create_journal_items_table.php`**
`journal_entry_id` FK, `entity_id` FK null, `line_no` smallint unsigned, `account_id` FK chart_of_accounts, `branch_id` FK, `debit`/`credit` decimal(18,2) 0, `customer_id`/`supplier_id`/`sale_id`/`product_id`/`warehouse_id`/`cash_register_id` FK null, `memo` string(255) null, `created_at`.
Unique `(journal_entry_id, line_no)`; index `(account_id, branch_id, journal_entry_id)`, `(entity_id, account_id)`, `(customer_id, account_id)`, `sale_id`, `(supplier_id, account_id)`.
CHECK (MySQL raw): `debit>=0 AND credit>=0 AND ((debit>0 AND credit=0) OR (credit>0 AND debit=0))`.

**09 `2026_11_01_000009_create_pos_sessions_table.php`**
Sesuai spec §4.5: `session_number` unique, `entity_id` FK null, `branch_id`, `cash_register_id`, `cash_account_id` (FK chart_of_accounts), `cashier_id` (FK users), `opened_at`, `closed_at` null, `status` enum('open','closing','closed','pending_approval','reconciled') default 'open', `opening_float`, `expected_cash` null, `counted_cash` null, `cash_difference` **storedAs** `counted_cash - expected_cash`, `denomination_json` json null, `cash_sales_total`, `cash_refunds_total`, `ar_collections_total`, `cash_out_total`, `non_cash_total` (decimal 18,2 default 0), `difference_journal_entry_id` FK null, `difference_resolution` enum('none','auto_expense','auto_other_income','suspense_pending','resolved_expense','resolved_employee_receivable','resolved_other_income') default 'none', `threshold_applied` decimal(18,2) null, `closed_by` FK null, `notes` text null, `legacy_shift_id` FK cash_register_shifts null unique, timestamps.
Generated `open_register_guard` = `IF(status IN ('open','closing'), cash_register_id, NULL)` storedAs + unique, `open_cashier_guard` serupa (hanya MySQL; di SQLite lewati dengan guard driver). Index `(branch_id, status, opened_at)`.
Lalu FK `journal_entries.pos_session_id` → `pos_sessions`. down: drop FK itu dulu.

**10 `2026_11_01_000010_create_cash_difference_approvals_table.php`**
Sesuai spec §4.5: `entity_id`, `branch_id`, `pos_session_id`, `direction` enum('shortage','overage'), `amount` decimal(18,2), `threshold_applied` decimal(18,2), `status` enum('pending','approved','rejected','resolved') default 'pending', `requested_resolution`/`approved_resolution` enum('expense_6810','employee_receivable_1135','other_income_4150') null, `employee_user_id` FK users null, `requested_by` FK users, `requested_at` datetime, `reason` text, `decided_by` FK users null, `decided_at` null, `decision_notes` text null, `suspense_journal_entry_id` FK journal_entries, `resolution_journal_entry_id` FK null, `resubmission_of_id` self-FK null, timestamps; index `(entity_id, branch_id, status)`, `pos_session_id`.
CHECK (MySQL): `amount>0`; resolusi 1135 butuh `employee_user_id`; shortage hanya 6810/1135; overage hanya 4150; `resolved` butuh `decided_by` dan `resolution_journal_entry_id`.

**11 `2026_11_01_000011_add_ledger_links_to_sales_tables.php`**
- `sales`: `pos_session_id` FK null, `journal_entry_id` FK null, `payment_type` **string(20) null** (lihat §4), index `pos_session_id`. `payment_method` lama **tidak disentuh**.
- `sale_items`: `unit_cost` decimal(18,4) **null**, `cogs_amount` decimal(18,2) null (nama per spec; ini yang disebut "cost_total"), `cost_source` string(20) null (`moving_average` | `legacy_backfill` | `opening`). Baris lama tetap NULL sampai backfill tahap 2.
- `sales_return_items`: `unit_cost` `->decimal(18,4)->default(0)->change()`, `subtotal_cost` tetap; tambah `sale_item_id` FK sale_items null, `cost_source` string(20) null. down: kembalikan `decimal(15,2)`.
- `customers` (guard hasColumn): `code` string(30) null unique, `credit_limit` decimal(18,2) null, `payment_terms_days` smallint unsigned null.
- `payments.customer_id` FK → customers `nullOnDelete`: **hanya** bila `SELECT COUNT(*) FROM payments p LEFT JOIN customers c ON c.id=p.customer_id WHERE p.customer_id IS NOT NULL AND c.id IS NULL` = 0. Bila tidak 0, lewati FK, log warning, dan laporkan sebagai open question.

**12 `2026_11_01_000012_create_ledger_integrity_triggers.php`** (lihat §7)

## 4. Penanganan Enum
**Rekomendasi:**
- **Kolom status internal baru** (journal_entries.status, pos_sessions.status, cash_difference_approvals.status/direction/resolution, accounting_periods.status, normal_balance, cash_flow_tag, movement_type): pakai **MySQL ENUM via `$table->enum()`**, konsisten dengan codebase (`payment_status`, `cash_register_shifts.status`, `payments.type` sudah ENUM). Nilainya stabil dan dikontrol sistem. Padankan dengan **PHP backed enum** di `app/Enums/` (`JournalStatus`, `PosSessionStatus`, `CashDifferenceStatus`, `CashDifferenceDirection`, `CashDifferenceResolution`, `PeriodStatus`, `NormalBalance`, `CashFlowTag`, `AccountType`) dan Eloquent `$casts` ke enum tersebut. Di tahap 1 cukup dibuat enum-nya; cast dipasang di model baru.
- **`sales.payment_type`**: pakai **string(20) + PHP backed enum `PaymentType`** (`cash`, `qris`, `edc`, `transfer`, `credit`, `mixed`), **bukan** MySQL ENUM. Alasannya: (a) nilai metode bayar cenderung bertambah (e-wallet, giro), dan ALTER ENUM di tabel `sales` yang besar memerlukan table rebuild; (b) backfill dari `payment_method` lama yang kotor (`Cash`, `Tunai`, `tempo`, `piutang`, `kredit`) lebih aman ke string nullable; (c) SQLite test tidak punya ENUM native. Validasi nilai lewat `Rule::enum(PaymentType::class)` (dipakai mulai tahap 2). Tambah juga helper `PaymentType::fromLegacy(string $method): ?PaymentType` (cash/tunai → cash, tempo/piutang/kredit → credit; lainnya null) beserta unit test. Helper ini hanya disiapkan, belum dipanggil.
- **`chart_of_accounts.type`**: tetap string di tahap 1. Contra direpresentasikan dengan `is_contra` + `normal_balance`, bukan nilai type baru.
- **ALTER enum existing**: tahap 1 **tidak** mengubah enum lama. Bila nanti perlu, pakai `DB::statement("ALTER TABLE ... MODIFY col ENUM(<semua nilai lama>, <nilai baru>) ...")` dengan nilai lama tetap dipertahankan, dibungkus guard `DB::getDriverName()==='mysql'`, dan `down()` menolak bila ada baris bernilai baru. Jangan pakai doctrine/dbal.

## 5. Pembekuan HPP
- Tahap 1 **hanya skema**: `sale_items.unit_cost/cogs_amount/cost_source`, `sales_return_items.unit_cost` (18,4) + `sale_item_id`, `product_branch_costs`, `inventory_cost_movements`.
- **Jangan** ubah `SalePostingService` (yang masih memakai `products.purchase_price` di baris 107), `SalesReturnService`, maupun `JournalPostingService` di tahap 1. Hook `InventoryCostService` (Moving Average per cabang: rata-rata dihitung ulang saat barang masuk, `unit_cost` dibekukan saat keluar, retur memakai biaya asal) dikerjakan di **tahap 2**. Tulis TODO di PR description, bukan di kode produksi.
- Boleh membuat model kosong `ProductBranchCost` dan `InventoryCostMovement` (fillable, casts, relasi) tanpa logika.

## 6. Seeder
Buat seeder berikut dan daftarkan di `DatabaseSeeder` **setelah** `BusinessStructureSeeder` dan `ChartOfAccountSeeder` lama:
1. **`EntitySeeder`**: `updateOrCreate(['code'=>...])` untuk `MB-PUPUK` ("Maju Bersama – Jaringan Ritel Pupuk") dan `DEKA` ("CV. Deka Agro Mandiri"), threshold 10000/10000. Set `branches.entity_id`: `PAKAN` → DEKA; `OFFICE-PUPUK`, `GUDANG-PUPUK`, `CBG-ABI`, `CBG-MAMA`, `CBG-AROFAH`, `CBG-TULUSAYU`, `CBG-LUBUKHARJO` → MB-PUPUK. Kode lain yang tidak dikenal: jangan diisi, cetak warning.
2. **`ChartOfAccountsV2Seeder`**: akun lama (entity_id NULL) di-assign ke MB-PUPUK. Lalu untuk **setiap entitas** jalankan `updateOrCreate(['entity_id'=>$e->id,'code'=>$code], [...])`. Akun header `is_postable=false`; `parent_category_id` di-set ke header-nya. Akun lama `5110 Biaya Harian`: `is_active=false` (jangan hapus) dan buat `6110`.
3. **Akun kas per laci**: untuk setiap `cash_registers` aktif (join branches → entity), buat akun `1110` + 3 digit urut per entitas (`1110001`, `1110002`, …), nama `Kas Laci {branch.name} – {register.name}`, `branch_id`, `cash_register_id`, `cash_flow_tag='cash'`, `is_postable=true`, parent = `1110`. Urutan stabil berdasarkan `cash_registers.id`. Idempoten berdasarkan `cash_register_id`: bila sudah ada, update nama saja dan jangan ganti kode.
4. **Bank**: buat `1120001 Bank – Rekening Operasional` per entitas (placeholder, rekening riil diisi admin).
5. **`AccountMappingSeeder`**: `updateOrCreate` per entitas untuk key `sales.cash`(1110 default; per laci via `cash_register_id`), `sales.ar`(1130), `sales.revenue`(4110), `sales.discount`(4130), `sales.return`(4140), `sales.ppn_out`(2130), `inventory`(1210), `cogs`(5100), `cash.shortage`(6810), `cash.overage`(4150), `cash.suspense`(1199), `employee.receivable`(1135), `interbranch`(1290), `interco.due_from`(1295), `interco.due_to`(2295).
6. Dilarang memakai `firstOrCreate` akun di kode posting baru. Seeder memakai `updateOrCreate` saja.

**Daftar kode COA v2 (di-seed untuk MB-PUPUK dan DEKA):**

| Kode | Nama | type | normal | postable | contra | cash_flow_tag |
|---|---|---|---|---|---|---|
| 1000 | ASET | asset | D | ✗ | | none |
| 1100 | Kas & Setara Kas | asset | D | ✗ | | none |
| 1110 | Kas Laci Kasir (header) | asset | D | ✗ | | cash |
| 1110001… | Kas Laci {cabang} – {laci} | asset | D | ✓ | | cash |
| 1111 | Kas Kecil | asset | D | ✓ | | cash |
| 1112 | Kas Besar / Brankas Pusat | asset | D | ✓ | | cash |
| 1115 | Kas Dalam Perjalanan | asset | D | ✓ | | cash |
| 1120 | Bank (header) | asset | D | ✗ | | cash |
| 1120001 | Bank – Rekening Operasional | asset | D | ✓ | | cash |
| 1125 | Piutang EDC/QRIS/Transfer (Clearing) | asset | D | ✓ | | none |
| 1130 | Piutang Usaha | asset | D | ✓ | | none |
| 1131 | Cadangan Kerugian Piutang | asset | K | ✓ | ✓ | none |
| 1135 | Piutang Karyawan / Kasbon | asset | D | ✓ | | none |
| 1140 | Uang Muka Pembelian | asset | D | ✓ | | none |
| 1150 | PPN Masukan | asset | D | ✓ | | none |
| 1199 | Selisih Kas Belum Diselesaikan (Suspense) | asset | D | ✓ | | none |
| 1210 | Persediaan Barang Dagang | asset | D | ✓ | | none |
| 1215 | Persediaan Dalam Perjalanan | asset | D | ✓ | | none |
| 1290 | Rekening Antar Cabang | asset | D | ✓ | | none |
| 1295 | Piutang Antar Entitas (Due from Entity) | asset | D | ✓ | | none |
| 1310 | Aset Tetap | asset | D | ✓ | | investing |
| 1320 | Akumulasi Penyusutan | asset | K | ✓ | ✓ | none |
| 2000 | LIABILITAS | liability | K | ✗ | | none |
| 2110 | Utang Dagang | liability | K | ✓ | | none |
| 2120 | Utang Biaya / Akrual | liability | K | ✓ | | none |
| 2130 | PPN Keluaran | liability | K | ✓ | | none |
| 2140 | Uang Muka / Deposit Pelanggan | liability | K | ✓ | | none |
| 2150 | Utang Refund Pelanggan | liability | K | ✓ | | none |
| 2210 | Utang Bank Jangka Panjang | liability | K | ✓ | | financing |
| 2295 | Utang Antar Entitas (Due to Entity) | liability | K | ✓ | | none |
| 3000 | EKUITAS | equity | K | ✗ | | none |
| 3110 | Modal Disetor | equity | K | ✓ | | financing |
| 3120 | Prive | equity | D | ✓ | | financing |
| 3210 | Laba Ditahan | equity | K | ✓ | | none |
| 3220 | Laba Tahun Berjalan | equity | K | ✗ | | none |
| 3900 | Ekuitas Saldo Awal | equity | K | ✓ | | none |
| 4000 | PENDAPATAN | revenue | K | ✗ | | none |
| 4110 | Penjualan Barang Dagang | revenue | K | ✓ | | operating |
| 4120 | Pendapatan Lain-lain | revenue | K | ✓ | | operating |
| 4130 | Potongan Penjualan | revenue | D | ✓ | ✓ | operating |
| 4140 | Retur Penjualan | revenue | D | ✓ | ✓ | operating |
| 4150 | Pendapatan Selisih Kas Lebih | revenue | K | ✓ | | operating |
| 5000 | HARGA POKOK PENJUALAN | expense | D | ✗ | | none |
| 5100 | HPP Barang Dagang | expense | D | ✓ | | operating |
| 5120 | Selisih / Penyusutan Persediaan | expense | D | ✓ | | operating |
| 5130 | Ongkos Angkut Pembelian | expense | D | ✓ | | operating |
| 6000 | BEBAN OPERASIONAL | expense | D | ✗ | | none |
| 6100 | Beban Operasional Umum | expense | D | ✓ | | operating |
| 6110 | Biaya Harian Toko | expense | D | ✓ | | operating |
| 6200 | Beban Listrik, Air & Internet | expense | D | ✓ | | operating |
| 6300 | Beban Gaji & Upah | expense | D | ✓ | | operating |
| 6400 | Beban Sewa | expense | D | ✓ | | operating |
| 6500 | Beban Penyusutan | expense | D | ✓ | | none |
| 6600 | Beban Transportasi & Pengiriman | expense | D | ✓ | | operating |
| 6700 | Beban Administrasi & Bank | expense | D | ✓ | | operating |
| 6810 | Beban Selisih Kas Kurang | expense | D | ✓ | | operating |
| 6820 | Beban Kerugian Piutang | expense | D | ✓ | | operating |
| 6900 | Beban Lain-lain | expense | D | ✓ | | operating |

Catatan: akun header 1000/2000/…/6000 ditambahkan agar hierarki `parent_category_id` lengkap. `code` lama yang masih dipakai kode existing (1110, 1120, 1130, 1210, 2130, 4110, 4130, 5100) **tetap ada dan tetap aktif** untuk MB-PUPUK. **Pengecualian: 1110 dan 1120 sekarang header (`is_postable=false`)**. Flag ini hanya ditegakkan trigger pada `journal_items` baru, jadi posting lama ke `journal_lines` tetap jalan.

## 7. Trigger (Migrasi 12)
- Hanya jalan bila `DB::getDriverName() === 'mysql'`. Di SQLite lewati (`return;`).
- Tulis **satu statement per `DB::unprepared()`** tanpa `DELIMITER`. Body `BEGIN ... END` diperbolehkan karena PDO mengirimnya sebagai satu statement. Awali setiap trigger dengan `DB::unprepared('DROP TRIGGER IF EXISTS <nama>')`.
- Trigger (isi lengkap di spec §4.6):
  1. `trg_ji_bi` BEFORE INSERT `journal_items`: header harus `draft`; akun `is_postable=1`; `NEW.entity_id` = entity header = entity akun = entity cabang (bandingkan dengan `<=>` agar NULL aman: di tahap 1 entity boleh NULL, tapi **harus sama**).
  2. `trg_ji_bu` / `trg_ji_bd`: tolak bila header bukan `draft`.
  3. `trg_je_bi` BEFORE INSERT `journal_entries`: `NEW.entity_id <=> branches.entity_id`.
  4. `trg_je_bu` BEFORE UPDATE `journal_entries`: draft→posted wajib `COUNT ≥ 2`, `SUM(debit)=SUM(credit)>0`, dan periode `(entity_id, branch_id|NULL)` bukan `closed`; set `total_debit/credit` dan `posted_at`. Status posted hanya boleh berubah → `reversed` dengan mengisi `reversed_by_id`, tanpa mengubah tanggal/cabang/total. Status reversed bersifat final.
  5. `trg_je_bd`: tolak delete selain draft.
  6. `trg_coa_biu` (BEFORE INSERT dan BEFORE UPDATE, jadi dua trigger `trg_coa_bi`/`trg_coa_bu`): bila `branch_id` terisi, entity cabang harus = `NEW.entity_id`.
  7. `trg_branches_bu`: tolak perubahan `entity_id` bila cabang sudah punya `journal_entries` posted.
- Pesan error memakai `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '...'` (Bahasa Indonesia, sama dengan spec).
- `down()`: `DROP TRIGGER IF EXISTS` untuk semua trigger di atas.

## 8. Acceptance Criteria & Tests
Lingkungan: MySQL 8 lokal/Docker (`DB_CONNECTION=mysql`, DB `majubersama_test`) **dan** SQLite default.
1. `php artisan migrate:fresh --seed` sukses di MySQL 8 dan SQLite.
2. `php artisan migrate:rollback --step=12` kembali ke skema pra-tahap-1 tanpa error. Lalu `php artisan migrate` lagi sukses (bolak-balik 2×). Bandingkan `SHOW CREATE TABLE` untuk `branches`, `chart_of_accounts`, `sales`, `sale_items`, `sales_return_items`, `customers`, `payments` sebelum dan sesudah siklus: harus identik.
3. Test baru `tests/Feature/Ledger/` (gunakan `@group mysql` dan skip bila driver bukan mysql untuk test trigger):
   - `LedgerTriggerTest`: posting tidak seimbang ditolak; header dengan 1 baris ditolak; edit/delete item pada entry posted ditolak; update `entry_date` pada entry posted ditolak; posted→reversed dengan `reversed_by_id` diterima; item dengan entity berbeda (akun DEKA di entry MB-PUPUK) ditolak; entry yang cabangnya beda entity ditolak; posting ke periode `closed` ditolak; akun header (`is_postable=0`, mis. 1110) ditolak.
   - `PosSessionGuardTest`: dua sesi `open` untuk laci atau kasir yang sama ditolak (unique generated column).
   - `CashDifferenceApprovalConstraintTest`: shortage dengan `other_income_4150` ditolak; resolusi 1135 tanpa `employee_user_id` ditolak.
   - `LedgerSeederIdempotencyTest`: menjalankan `EntitySeeder`, `ChartOfAccountsV2Seeder`, `AccountMappingSeeder` 2× menghasilkan jumlah baris sama; akun laci 1:1 dengan `cash_registers` aktif; COA MB-PUPUK dan DEKA masing-masing berisi 1135, 1199, 4140 (contra, D), 4130 (contra, D), 4150, 6810, 1295, 2295; `PAKAN` ber-entity DEKA, `CBG-ABI` MB-PUPUK.
   - `PaymentTypeTest` (unit): `fromLegacy` memetakan cash/Cash/tunai/Tunai → cash, tempo/piutang/kredit → credit, nilai lain → null.
4. **Seluruh test existing tetap hijau** (`php artisan test`), terutama `AccountingReportServiceTest`, `ReportCenterWebTest`, `Api/ReportApiTest`, dan test POS checkout. Perilaku posting lama tidak boleh berubah.
5. `./vendor/bin/pint --test` bersih untuk file baru.

## 9. Yang DILARANG di Tahap 1
- Menjalankan backfill (`ledger:*` commands) atau mengisi data transaksi ke `journal_entries`.
- Membuat migrasi 13 (NOT NULL) atau 14 (lock tabel jurnal lama).
- Mengubah `SalePostingService`, `SalesReturnService`, `JournalPostingService`, `CashRegisterShiftService`, `ARAPPaymentService`, atau service posting lain; mengaktifkan dual-write.
- Me-rewire laporan `/backoffice/reports` atau `/reports/accounting`.
- Menghapus, rename, atau mengubah `journal_headers`, `journal_lines`, `cash_register_shifts`, `sales.payment_method`, atau enum existing.
- Menghapus akun COA lama (paling jauh `is_active=false` untuk 5110).
- Menyentuh DB produksi, `.env` produksi, deploy, push/merge ke `main`.

## 10. Format Laporan Akhir (wajib)
```
### Laporan Ledger v2 Tahap 1
- Branch: feature/ledger-v2-phase1 (base: main @ <sha>)
- Commits: <sha> <pesan>  (satu per baris, urut)
- Migrasi dibuat: 01..12 (nama file)
- Seeder dibuat/diubah: ...
- Hasil:
  - migrate:fresh --seed (MySQL 8.x.y): OK/FAIL + output ringkas
  - migrate:fresh --seed (SQLite): OK/FAIL
  - rollback --step=12 + migrate ulang (2x): OK/FAIL, diff SHOW CREATE TABLE: kosong/isi
  - php artisan test: <passed>/<total>, durasi; daftar test gagal (bila ada)
  - pint: OK/FAIL
- PR (draft, belum di-merge): <link>
- Penyimpangan dari spec/brief + alasan: ...
- Open questions (mis. orphan payments.customer_id, cabang tanpa entity, kode laci): ...
- Konfirmasi: tidak ada push/merge ke main, tidak ada deploy, tidak menyentuh DB produksi.
```
