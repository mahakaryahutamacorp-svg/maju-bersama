# Architecture Decisions Record - Maju Bersama POS & ERP

Dokumen ini mencatat keputusan arsitektur penting, bug major, dan perubahan struktur sebagai memori proyek.

---

## ADR-001: Form Request Pattern untuk Validasi [2026-09-13]

**Konteks:** Controller memiliki validasi inline yang sulit di-test dan maintain.

**Keputusan:**
- Semua validasi dipindahkan ke Form Request classes di `app/Http/Requests/`
- Complex validation (after hooks) tetap di Form Request via `withValidator()`
- Controller hanya menerima `$request->validated()`

**Konsekuensi:**
- ✅ Validasi lebih mudah di-test
- ✅ Controller lebih bersih
- ⚠️ Perlu file tambahan per endpoint

---

## ADR-002: Service Layer Pattern [2026-09-13]

**Konteks:** Business logic tercampur di controller.

**Keputusan:**
- Service Layer di `app/Services/`
- Controller: validate → call service → return response
- Service menerima validated data, return domain object

---

## ADR-003: Multi-Tenancy dengan Branch Scoping [2026-09-13]

**Konteks:** Isolasi data per branch untuk multi-cabang.

**Keputusan:**
- `HasBranchScope` trait di semua model dengan `branch_id`
- Global Scope filter berdasarkan `auth()->user()->branch_id`
- Role `master`/`superadmin` bypass scope

**Models dengan HasBranchScope:** Product, Sale, JournalHeader

---

## ADR-004: Double-Entry Accounting [2026-09-13]

**Keputusan:**
- Setiap transaksi POS otomatis create journal entry
- Validasi `debit = credit` di Form Request
- Journal posting dalam database transaction
- CoA codes: `1110` (Kas), `4110` (Pendapatan)

---

## ADR-005: Money Storage Strategy [2026-09-13]

**Keputusan:**
- Products: `DECIMAL(10,2)` di DB, cast `decimal:2`
- Sales: `INTEGER` (cents) di DB
- Sale_items: `INTEGER` (cents)
- Journal_lines: `DECIMAL(15,2)`

---

## ADR-006: Receipt Number Format [2026-09-13]

**Format:** `INV-YYYYMMDD-XXXX` (random 4 digit, unique check)

---

## ADR-007: Products and Categories CRUD Implementation [2026-09-13]

**Konteks:** Need to implement full CRUD operations for Products and Categories with proper multi-tenant isolation.

**Keputusan:**
- **Products**: Branch-scoped resource using `HasBranchScope` trait
  - Users can only access products in their own branch
  - Superadmins can view all products across branches
  - Auto-generate SKU if not provided (format: `PROD-{branch_code}-{random}`)
  - Initial stock defaults to 0 if not specified
  
- **Categories**: Global resource (not branch-scoped)
  - All users can view categories
  - Only superadmins can create/update/delete categories
  - Categories cannot be deleted if they have associated products
  
- **Authorization Strategy:**
  - Products: Implicit via `HasBranchScope` (automatic filtering)
  - Categories: Explicit via `authorize()` in FormRequest classes

**Konsekuensi:**
- ✅ Branch isolation is automatic for Products (no manual checks needed)
- ✅ Clear separation: Products are branch-specific, Categories are shared
- ⚠️ Category deletion requires checking for associated products
- ⚠️ SKU generation uses random suffix to avoid collisions

**Files Created:**
- `app/Http/Controllers/Api/CategoryController.php`
- `app/Http/Requests/StoreProductRequest.php`
- `app/Http/Requests/UpdateProductRequest.php`
- `app/Http/Requests/StoreCategoryRequest.php`
- `app/Http/Requests/UpdateCategoryRequest.php`
- `app/Services/ProductService.php`
- `tests/Feature/ProductApiTest.php` (11 tests)
- `tests/Feature/CategoryApiTest.php` (11 tests)

**Files Modified:**
- `app/Http/Controllers/Api/ProductController.php` (added store, show, update, destroy)
- `routes/api.php` (added CRUD routes for products and categories)

**Test Coverage:**
- Branch isolation tests: Users cannot access products from other branches
- Authorization tests: Regular users cannot modify categories
- Validation tests: Required fields, negative stock prevention, duplicate names
- Business logic tests: SKU generation, category deletion protection

---

## ADR-008: Database Produksi MySQL + Transaksi ACID via Laravel [2026-09-14]

**Konteks:**
Spesifikasi awal menyebut penggunaan PostgreSQL/Supabase RPC untuk membungkus
logika double-entry dalam blok `BEGIN`/`COMMIT`. Kenyataannya ekosistem yang sudah
berjalan adalah Laravel 12 dengan MySQL 8 (produksi) dan SQLite in-memory (testing).
Migrasi ke PostgreSQL berarti mengganti seluruh infrastruktur yang sudah live.

**Keputusan:**
Tetap menggunakan MySQL 8 (InnoDB) dan membungkus seluruh operasi multi-tabel
dengan `DB::transaction()` milik Laravel.

**Alasan:**
- `DB::transaction()` pada InnoDB mengeluarkan `BEGIN`/`COMMIT`/`ROLLBACK` yang
  sesungguhnya, sehingga jaminan atomicity identik dengan PostgreSQL.
- Logika transaksi tetap berada di layer aplikasi (PHP) sehingga dapat diuji otomatis
  lewat PHPUnit tanpa memerlukan database server terpisah.
- Menghindari pemecahan logika bisnis ke dalam stored procedure yang sulit di-version
  control dan sulit di-review.
- Biaya migrasi infrastruktur produksi yang sudah live tidak sebanding dengan
  manfaat yang diperoleh.

**Konsekuensi:**
- Semua operasi yang menyentuh lebih dari satu tabel wajib berada di dalam
  `DB::transaction()`.
- Pembacaan baris yang akan dimutasi wajib memakai `lockForUpdate()` untuk mencegah
  race condition (pola ini sudah diterapkan pada `CheckoutController`).
- Jika suatu saat benar-benar pindah ke PostgreSQL, kode transaksi tidak perlu diubah
  karena abstraksi Laravel bersifat database-agnostic.

---

## ADR-009: Deployment ke VPS aaPanel dengan SSL Let's Encrypt [2026-09-14]

**Konteks:**
Aplikasi perlu diakses publik pada domain `majubersama.online`. VPS Hostinger yang
tersedia sudah ter-install aaPanel beserta stack Nginx 1.30, PHP 8.3, dan MySQL 8.

**Keputusan:**
- Document root diarahkan ke `/www/wwwroot/majubersama.online/public`.
- Sertifikat SSL diterbitkan memakai `acme.sh` (bukan fitur SSL bawaan panel).
- Nginx dikonfigurasi dengan dua server block: port 80 melakukan redirect 301 ke HTTPS,
  port 443 melayani aplikasi dengan TLS 1.2/1.3, HTTP/2, dan header HSTS.
- Perpanjangan sertifikat otomatis lewat cron `acme.sh --cron` dengan
  `--reloadcmd "nginx -s reload"`.

**Alasan:**
- `acme.sh` dapat dioperasikan sepenuhnya lewat SSH sehingga proses deployment dapat
  diotomatisasi dan diulang, tanpa bergantung pada klik manual di antarmuka panel.
- Redirect 301 dan HSTS memastikan cookie sesi Laravel selalu dikirim melalui koneksi
  terenkripsi (atribut `secure` aktif).

**Konsekuensi:**
- Pengelolaan nginx pada server ini memakai `nginx -s reload`, bukan `systemctl`
  (lihat BUG-002 pada `docs/BUG_REGISTRY.md`).
- Konfigurasi vhost diedit langsung di
  `/www/server/panel/vhost/nginx/majubersama.online.conf`. Perubahan lewat UI aaPanel
  berpotensi menimpa konfigurasi ini, sehingga backup selalu dibuat sebelum diedit.

---

## ADR-010: Pemisahan Bug Registry dari Architecture Decisions [2026-09-14]

**Konteks:**
Catatan bug sebelumnya bercampur di dalam dokumen keputusan arsitektur. Keduanya
memiliki umur pakai dan pembaca yang berbeda: keputusan arsitektur dibaca saat
merancang modul baru, sedangkan rekaman bug dibaca saat melakukan diagnosis masalah.

**Keputusan:**
- `architecture_decisions.md` menyimpan keputusan arsitektur beserta alasannya (ADR).
- `docs/BUG_REGISTRY.md` menyimpan rekaman bug beserta akar masalah dan pencegahannya.
- Jika sebuah bug melahirkan keputusan arsitektur, keduanya saling menautkan nomor.

**Konsekuensi:**
- Setiap perbaikan bug yang tidak trivial wajib menghasilkan satu entri di
  `docs/BUG_REGISTRY.md`, termasuk bagian akar masalah dan pencegahan.

---

## ADR-011: Tabel `inventories` dengan Composite Unique `(branch_id, product_id)` [2026-09-15]

**Konteks:**
Modul Distribusi Inventori membutuhkan aliran barang dari entitas pusat ke cabang.
Skema lama menyimpan stok pada kolom `products.stock`, sementara `products` terikat
satu cabang dan `products.sku` bersifat unique global. Konsekuensinya satu artikel
tidak dapat berada di pusat dan cabang secara bersamaan, sehingga transfer stok
mustahil dicatat tanpa memaksa SKU baru.

**Keputusan:**
- Tabel baru `inventories` menjadi sumber kebenaran stok, dengan **composite unique
  key `(branch_id, product_id)`** sehingga mustahil ada dua baris stok untuk pasangan
  cabang/produk yang sama.
- Kolom `products.stock` dipertahankan sebagai cache kompatibilitas dan selalu
  disinkronkan oleh service (`StockTransferService`, `SalePostingService`,
  `ProductService`) di dalam transaksi yang sama.
- SKU produk dilonggarkan dari unique global menjadi **unique per cabang**
  (`products_branch_sku_unique`) agar artikel yang sama dapat didaftarkan di cabang
  tujuan saat transfer pertama kali terjadi.
- Migrasi `create_inventories_table` sekaligus backfill dari `products.stock` agar
  data produksi tidak kehilangan stok awal.

**Konsekuensi:**
- Semua pembacaan stok kritis (transfer, checkout) membaca `inventories` dengan
  `lockForUpdate()`.
- `firstOrCreate` pada pasangan `(branch_id, product_id)` aman dari duplikasi karena
  dijaga constraint database, bukan logika aplikasi.
- Jika katalog produk kelak dinormalisasi menjadi global (tanpa `branch_id`), tabel
  `inventories` sudah berbentuk benar dan tidak perlu dimigrasi ulang.

---

## ADR-012: Jurnal Penjualan POS Empat Baris [2026-09-15]

**Konteks:**
Checkout sebelumnya hanya menulis dua baris jurnal (Dr Kas, Cr Pendapatan). Akibatnya
persediaan di neraca tidak berkurang saat barang terjual dan laba kotor tidak terbaca
dari ledger.

**Keputusan:**
- Setiap penjualan POS menulis empat baris dalam satu header jurnal:
  `Dr 1110 Kas`, `Cr 4110 Pendapatan`, `Dr 5100 Harga Pokok Penjualan`,
  `Cr 1210 Persediaan`, dengan sisi biaya dihitung dari `purchase_price`.
- Akun baru `5100 Harga Pokok Penjualan` ditambahkan ke `ChartOfAccountSeeder`
  (idempoten via `updateOrCreate`, aman dijalankan ulang di produksi).
- Logika dipindah dari controller ke `SalePostingService` sesuai ADR-002; seluruh
  mutasi (stok, sale, jurnal) berada dalam satu `DB::transaction()` sesuai ADR-008.

**Konsekuensi:**
- Baris HPP/Persediaan dilewati ketika total biaya nol agar ledger tidak berisi baris
  kosong; jurnal tetap balance.
- `CheckoutTest` memverifikasi keempat baris, keseimbangan debit/kredit, dan
  decrement pada tabel `inventories`.

---

## ADR-013: Modul Distribusi Inventori (Stock Transfer) [2026-09-15]

**Keputusan:**
- Endpoint `GET/POST /api/stock-transfers` dan `GET /api/stock-transfers/{id}`
  (Sanctum), plus halaman web `/inventory/transfer` (Blade + Alpine).
- `StockTransferService::transfer()` membungkus seluruh operasi dalam satu
  `DB::transaction()`: validasi stok sumber, decrement stok pusat, increment stok
  cabang (membuat baris katalog tujuan berdasarkan SKU bila belum ada), pencatatan
  `stock_transfers` + `stock_transfer_items`, dan dua jurnal mirror
  (`Stock transfer out` di sumber, `Stock transfer in` di tujuan) memakai akun
  `1210 Persediaan`.
- Otorisasi: master boleh transfer dari cabang manapun; non-master hanya boleh
  mengeluarkan stok dari cabangnya sendiri.
- Nomor referensi: `TRF-YYYYMMDD-XXXX`, konsisten dengan format resi ADR-006.

**Konsekuensi:**
- Transfer `majubersamapusat` -> `majubersama 1` berjalan dengan stok berkurang akurat
  di sumber, bertambah di tujuan, tanpa duplikasi baris `inventories`
  (test `test_repeated_transfers_do_not_duplicate_inventory_rows`).

---

## Bug Fixes

Rekaman bug lengkap beserta akar masalah dan langkah pencegahannya dipindahkan ke
[`docs/BUG_REGISTRY.md`](docs/BUG_REGISTRY.md) (lihat ADR-010).

Ringkasan bug yang pernah ditemukan:

| ID | Judul | Status |
| --- | --- | --- |
| BUG-001 | HasBranchScope belum dipasang pada model Sale | RESOLVED |
| BUG-002 | Nginx tidak dapat di-start melalui systemd (aaPanel) | MITIGATED |
| BUG-003 | Aplikasi produksi berjalan dengan konfigurasi lokal | RESOLVED |
| BUG-004 | Sertifikat SSL gagal terbit untuk subdomain `www` | RESOLVED |
| BUG-005 | Test BranchApiTest gagal karena `category_id` tidak diisi | RESOLVED |

---

## API Endpoints

- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/user` - Current user
- `GET /api/products` - List products (branch-scoped)
- `POST /api/checkout` - Create sale + journal
- `POST /api/journals` - Create journal entry
- `GET /api/stock-transfers` - List transfers involving caller's branch
- `POST /api/stock-transfers` - Execute stock transfer pusat -> cabang
- `GET /api/stock-transfers/{stockTransfer}` - Transfer detail

---

## Testing Strategy

- PHPUnit + Laravel TestCase
- `DatabaseTransactions` untuk rollback
- SQLite in-memory untuk speed
- `Sanctum::actingAs()` untuk API auth

---

## Future Considerations

- [ ] Redis caching untuk product list
- [ ] Rate limiting API endpoints
- [ ] Error tracking (Sentry/Bugsnag)
- [ ] Queue workers dengan Supervisor
- [ ] Database backup automation
