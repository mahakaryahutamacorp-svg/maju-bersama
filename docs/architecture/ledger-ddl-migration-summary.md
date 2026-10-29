# Brief Eksekusi Developer: Ledger v2 (Laravel 12 + MySQL 8)

**Sumber kebenaran:** `docs/architecture/accounting-ledger-spec.md` v1.1 (keputusan §8 DIPUTUSKAN 30 Okt 2026). DDL lengkap ada di spec §4.0–§4.6; brief ini hanya urutan kerja yang siap dieksekusi.
**Cutover:** 1 Des 2026 00:00 WIB. November = dual-write + automated testing.

## 0. Aturan Wajib
- Patuhi `.cursor/rules/approved-changes-to-main.mdc`: kerja di branch fitur (`feat/ledger-v2`). **Jangan commit ke main, merge, push ke `origin/main`, atau deploy tanpa persetujuan eksplisit pengguna.** Setelah disetujui dan di-push, berhenti; pengguna sendiri yang menjalankan `.\deploy-direct.ps1`. Jangan SSH, jangan `git pull` di server.
- Semua migrasi **additive** dan reversible (`down()`), kecuali trigger yang di-drop di `down()`.
- Trigger ditulis via `DB::unprepared()` dan dibungkus `if (DB::getDriverName() === 'mysql')`. SQLite testing memakai validasi di aplikasi. Test ledger wajib juga jalan di MySQL 8 (CI service container).
- Uang `DECIMAL(18,2)`, biaya satuan `DECIMAL(18,4)`, aritmetika pakai `bcmath`.
- Dilarang `firstOrCreate` akun di service. Akun di-resolve lewat `account_mappings`; bila tidak ada → exception.

## 1. Urutan Migrasi (`database/migrations/`)

| # | File | Isi | Spec |
|---|---|---|---|
| 1 | `2026_11_01_000001_create_entities_table.php` | `entities` (code unique, legal_name, npwp, `cash_shortage_auto_threshold`/`cash_overage_auto_threshold` DECIMAL(18,2) default 10000) | §4.0 |
| 2 | `2026_11_01_000002_add_entity_id_to_branches_table.php` | `branches.entity_id` nullable + FK RESTRICT + index | §4.0 |
| 3 | `2026_11_01_000003_upgrade_chart_of_accounts_for_ledger_v2.php` | +`entity_id`, drop unique `code`, unique `(entity_id, code)`, `parent_id`, `level`, `normal_balance`, `is_postable`, `is_contra`, `is_system`, `branch_id`, `cash_register_id` (unique), `cash_flow_tag`, `type` ENUM | §4.1 |
| 4 | `2026_11_01_000004_create_account_mappings_table.php` | `account_mappings(entity_id, key, branch_id NULL, cash_register_id NULL, account_id)`, unique `(entity_id, key, branch_id, cash_register_id)` | §4.6 |
| 5 | `2026_11_01_000005_create_product_branch_costs_table.php` | `product_branch_costs` (unique branch+product), `inventory_cost_movements` | §4.1 |
| 6 | `2026_11_01_000006_create_accounting_periods_table.php` | `accounting_periods(entity_id, branch_id NULL, period_start, period_end, status)` | §4.2 |
| 7 | `2026_11_01_000007_create_journal_entries_table.php` | `journal_entries` (+entity_id, intercompany_group_id, source_type/id/event unique, reversal FKs, legacy_journal_header_id) | §4.3 |
| 8 | `2026_11_01_000008_create_journal_items_table.php` | `journal_items` (+entity_id, branch_id, subledger FKs, CHECK satu sisi) | §4.4 |
| 9 | `2026_11_01_000009_create_pos_sessions_table.php` | `pos_sessions` (+entity_id, status incl. `pending_approval`, generated guard columns unique, threshold_applied) + FK `journal_entries.pos_session_id` | §4.5 |
| 10 | `2026_11_01_000010_create_cash_difference_approvals_table.php` | `cash_difference_approvals` (status pending/approved/rejected/resolved, CHECK resolusi per arah) | §4.5 |
| 11 | `2026_11_01_000011_add_ledger_links_to_sales_tables.php` | `sales.pos_session_id`, `sales.journal_entry_id`, `sales.payment_type` ENUM; `sale_items.unit_cost` (18,4), `cogs_amount` (18,2); `sales_return_items.unit_cost` (18,4), `sale_item_id` FK; `customers.credit_limit`, `payment_terms_days`, `code`; FK `payments.customer_id` | §4.5 |
| 12 | `2026_11_01_000012_create_ledger_integrity_triggers.php` | triggers `trg_ji_bi/bu/bd`, `trg_je_bi/bu/bd`, `trg_coa_biu`, guard `branches.entity_id` | §4.6 |
| 13 | `2026_11_25_000001_enforce_entity_not_null.php` | **setelah backfill:** `branches/chart_of_accounts.entity_id` NOT NULL, `sale_items.unit_cost/cogs_amount` NOT NULL | §7.2 |
| 14 | `2026_11_30_000001_lock_legacy_journal_tables.php` | **malam cutover, setelah approval:** trigger tolak INSERT/UPDATE/DELETE pada `journal_headers/journal_lines` | §7.5 |

## 2. Seeder (`database/seeders/`)
1. `EntitySeeder`: `MB-PUPUK` ("Maju Bersama – Jaringan Ritel Pupuk") dan `DEKA` ("CV. Deka Agro Mandiri"), ambang 10000/10000. Map cabang: `PAKAN` → DEKA; `OFFICE-PUPUK`, `GUDANG-PUPUK`, `CBG-ABI`, `CBG-MAMA`, `CBG-AROFAH`, `CBG-TULUSAYU`, `CBG-LUBUKHARJO` → MB-PUPUK.
2. `ChartOfAccountV2Seeder` (idempoten, per entitas): seluruh COA spec §3, termasuk **1135** Piutang Karyawan, **1199** Selisih Kas Belum Diselesaikan, **4140** Retur Penjualan (`is_contra=1`, normal D), **6810** Beban Selisih Kas Kurang, plus 4150, 1115, 1125, 1290, 1295, 2295, 2140, 2150, 3900, 6110. Koreksi 4130 (contra, D) dan 1320 (contra, K); akun 5110 dipindah ke 6110 (5110 dinonaktifkan, tidak dihapus). Akun yang sudah ada di-assign ke MB-PUPUK; DEKA mendapat salinan baru.
3. `CashDrawerAccountSeeder`: satu akun `1110xxx` per `cash_registers` aktif (entity dari cabang), `cash_flow_tag='cash'`.
4. `AccountMappingSeeder`: key `sales.cash`, `sales.ar`, `sales.revenue`, `sales.discount`, `sales.return`(=4140), `sales.ppn_out`, `inventory`, `cogs`, `cash.shortage`(=6810), `cash.overage`(=4150), `cash.suspense`(=1199), `employee.receivable`(=1135), `interbranch`(=1290), `interco.due_from`(=1295), `interco.due_to`(=2295).

## 3. Backfill (Artisan commands, `app/Console/Commands/Ledger/`)
Semua command punya `--dry-run` dan `--entity=`, idempoten, dan mencatat ringkasan ke `storage/logs/ledger-backfill.log`.
1. `ledger:assign-entities`: isi `branches.entity_id` dan `chart_of_accounts.entity_id`.
2. `ledger:opening-balances {--date=}`: saldo awal per entitas/cabang/laci/bank/stok/AR/AP dengan lawan 3900.
3. `ledger:migrate-legacy-journals`: `journal_headers/lines` → `journal_entries/items` (`legacy_journal_header_id`, tebak source_type). Jurnal soft-deleted dimigrasi lalu dibuat reversal-nya.
4. `ledger:reclass-cash-drawers`: baris 1110 → `1110xxx` per sesi, lewat jurnal reklasifikasi.
5. `ledger:migrate-shifts`: `cash_register_shifts` → `pos_sessions`, dengan Hybrid ≤10000 → 6810/4150, >10000 → 1199 + approval pending.
6. `ledger:replay-moving-average`: replay kronologis per branch+product → `inventory_cost_movements`, `product_branch_costs`, `sale_items.unit_cost/cogs_amount`, `sales_return_items.unit_cost`, plus jurnal koreksi 5100/1210.
7. `ledger:reclass-returns`: jurnal retur lama Dr 4110 → reklas Dr 4140 / Kr 4110.
8. `ledger:link-ar-subledger`: isi `customer_id/sale_id` di baris 1130, `sales.journal_entry_id`.
9. `ledger:normalize-payment-type`: `payment_method` → `payment_type`, laporkan nilai tak dikenal.
10. `ledger:reconcile {--date=}`: 10 cek spec §7.4. Exit code ≠ 0 bila ada selisih; dijadwalkan harian 23:30 WIB selama November.

## 4. Perubahan Model & Service
- **Trait** `App\Traits\HasEntityScope` (filter `entity_id` = `session('active_entity_id')` atau entitas cabang user). Dipasang bersama `HasBranchScope` di model baru `Entity`, `JournalEntry`, `JournalItem`, `PosSession`, `CashDifferenceApproval`, `ProductBranchCost`, `InventoryCostMovement`, `AccountingPeriod`, `AccountMapping`, serta `ChartOfAccount` (entity saja). `Branch belongsTo Entity`.
- **`LedgerPostingService`** (baru, satu-satunya penulis ledger): `post(LedgerEntryData)` → draft + items → `status='posted'`; `reverse()`; `postIntercompany()` (dua entry, satu `intercompany_group_id`). Validasi di aplikasi: seimbang total & per cabang, satu entitas, akun postable, periode open, **tolak Dr 4110 bila `source_type='sales_return'`**.
- **`InventoryCostService`** (baru): `receive()`, `issue()` (mengembalikan unit_cost beku), `returnIn(saleItem)`, dengan `lockForUpdate` pada `product_branch_costs`.
- **`SalePostingService`**: unit_cost dari `InventoryCostService::issue` (bukan `products.purchase_price`, sekarang baris 107); tulis `sale_items.unit_cost/cogs_amount`; wajib `pos_session_id` untuk tunai; posting via LedgerPostingService. Dual-write ke jurnal lama di balik flag `ledger_v2_dual_write`.
- **`SalesReturnService` / `JournalPostingService::postSalesReturn`**: Dr 4140 (bukan 4110), unit_cost dari `sale_item_id` asal, lalu `InventoryCostService::returnIn`.
- **`GoodsReceiptService`, `StockTransferService`, `StockAdjustmentService`, `PurchaseReturnService`**: panggil InventoryCostService dan posting v2.
- **`CashRegisterShiftService` → `PosSessionService`**: expected_cash dari saldo `journal_items` akun laci per sesi. `close(session, counted, reason)`: snapshot threshold entitas; selisih 0 → `closed`; kurang ≤T → jurnal 6810; lebih ≤T → jurnal 4150; >T → jurnal 1199 + `cash_difference_approvals` pending + sesi `pending_approval`.
- **`CashDifferenceApprovalService`** (baru): `approve(approval, resolution, employeeId?)` yang mem-posting jurnal resolusi (6810/1135/4150 vs 1199) dalam satu transaksi → `resolved`, sesi `reconciled`; `reject()`; `resubmit()`. Gate `approve-cash-difference` (supervisor/master entitas yang sama, bukan kasir/pengaju). UI: antrian di backoffice.
- **`ARAPPaymentService`, `ExpenseService`, `CashTransactionService`, `CashTransferService`, `SupplierPaymentService`, `OpeningBalanceService`, `FixedAssetService`**: posting via LedgerPostingService dengan subledger FK.
- **Laporan**: `CashFlowService` memakai `cash_flow_tag='cash'` (bukan `LIKE '%kas%'`); `AgingReportService` dari subledger 1130; `IncomeStatementService` menampilkan 4110−4130−4140. Semuanya diberi filter entity+branch wajib. Route paralel `/backoffice/reports/v2/*` selama November.
- `closePeriod` menolak bila saldo 1199 ≠ 0 atau masih ada approval pending/rejected.

## 5. Acceptance Tests (`tests/Feature/Ledger/`, juga dijalankan di MySQL 8)
1. POS tunai: jurnal seimbang, Dr 1110xxx, HPP = avg × qty, `sale_items.unit_cost` beku, tidak berubah setelah ada penerimaan baru.
2. POS tempo: Dr 1130 dengan customer_id/sale_id; Aging = saldo 1130.
3. Cicilan & kelebihan bayar (→2140).
4. Moving Average: terima 10@100 + 10@130 → avg 115; jual 5 → HPP 575; retur 1 → masuk @115 (biaya beku asal).
5. Retur tunai & kredit: Dr 4140, **tidak ada Dr 4110**; PPN dibalik; persediaan masuk dengan biaya beku.
6. Tutup shift: kurang 10.000 → 6810 otomatis (batas inklusif); kurang 10.001 → 1199 + approval pending; approve ke 6810 dan ke 1135 (employee wajib); reject → tetap 1199; kasir tidak bisa approve sendiri.
7. Kas lebih ≤/> ambang → 4150 / 1199 + approval.
8. Ambang per entitas: ubah DEKA ke 5000, maka kurang 7.000 di PAKAN → 1199.
9. Entitas: jurnal yang mencampur cabang MB-PUPUK & DEKA ditolak (trigger + service); intercompany menghasilkan 2 entry dengan 1295 = 2295.
10. Immutability: UPDATE/DELETE item atau entry posted → error; reversal menetralkan saldo; posting ke periode closed → error.
11. Isolasi: user cabang ABI tidak melihat jurnal MAMA/DEKA; master tanpa entitas aktif tidak bisa melihat laporan konsolidasi campuran.
12. `ledger:reconcile` pada data backfill staging → exit 0.

## 6. Definition of Done (sebelum minta persetujuan merge)
- Semua test hijau (SQLite + MySQL 8), `ledger:reconcile` 0 selisih 7 hari berturut-turut di staging/dual-write.
- Ringkasan diff + hasil rekonsiliasi diserahkan ke pengguna. **Tidak ada push/merge/deploy ke main tanpa persetujuan** (`approved-changes-to-main.mdc`).

## Lampiran
DDL lengkap: spec §4.0 (`entities`, `branches.entity_id`), §4.1 (`chart_of_accounts`, `product_branch_costs`, `inventory_cost_movements`, aturan Moving Average), §4.2 (`accounting_periods`), §4.3 (`journal_entries`), §4.4 (`journal_items`), §4.5 (`pos_sessions`, `cash_difference_approvals`, kolom sales/sale_items/sales_return_items), §4.6 (trigger). Matriks jurnal: spec §5. Cek rekonsiliasi: spec §7.4.
