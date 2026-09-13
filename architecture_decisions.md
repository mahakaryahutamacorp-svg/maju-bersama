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

## Bug Fixes

### BUG-001: HasBranchScope di Sale Model [RESOLVED]

**Issue:** Sale model tidak punya HasBranchScope, user bisa lihat sales branch lain.

**Fix:** Tambah `use HasBranchScope;` di Sale model.

---

## API Endpoints

- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/user` - Current user
- `GET /api/products` - List products (branch-scoped)
- `POST /api/checkout` - Create sale + journal
- `POST /api/journals` - Create journal entry

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
