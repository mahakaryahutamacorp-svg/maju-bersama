# TestSprite AI Testing Report (MCP)

---

## 1. Document Metadata

- **Project Name:** MAJUBERSAMA_CURSOR
- **Date:** 2026-10-13
- **Prepared by:** TestSprite AI Team
- **Target:** `http://127.0.0.1:8000`
- **Scope:** Backend API, kontrak `tests/Feature/Api/` plus matriks RBAC dan isolasi Pakan/Pupuk
- **Execution:** 6 kasus, 3 lulus, 3 gagal (50%)
- **Tokens:** Setiap peran login sendiri lewat `POST /api/login`. Token tidak dipakai silang. Akun: `master@majubersama.test`, `asep@mb.pkn`, `gudang@pakan.178`, `gudang@pupuk.178`, `admin@mb.abi`, `kasir@pakan.mb`, `pipin@kasir.pupuk`.

---

## 2. Requirement Validation Summary

### Requirement: Login Sanctum

#### Test TC001 sanctum_login_authentication_flow

- **Test Code:** [TC001_sanctum_login_authentication_flow.py](./TC001_sanctum_login_authentication_flow.py)
- **Test Error:** Password salah mengembalikan 422 dengan pesan `Invalid credentials.`, sementara skrip mengharapkan 401. Kasus berhenti di assert pertama, jadi profil, logout, dan pencabutan token tidak dijalankan pada putaran ini.
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/bd783b55-cfb9-5f19-9aaf-9c090c7da439/test/ffc955a0-158d-4419-8666-9e0d5a7882b2
- **Status:** Failed
- **Analysis / Findings:** Ini selisih kontrak status, bukan lubang login. `AuthController` dan `tests/Feature/Api/AuthApiTest.php` memang menetapkan 422 untuk kredensial salah, dan 401 hanya bila token tidak ada. Kredensial salah tetap ditolak.

### Requirement: Jurnal hanya master

#### Test TC002 journals_rbac_access_control

- **Test Code:** [TC002_journals_rbac_access_control.py](./TC002_journals_rbac_access_control.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/bd783b55-cfb9-5f19-9aaf-9c090c7da439/test/70d98254-3d8e-49c6-b4ad-533468bd1201
- **Status:** Passed
- **Analysis / Findings:** Tanpa token, `POST /api/journals` mengembalikan 401. `kasir@pakan.mb` dan `pipin@kasir.pupuk` menerima 403 pada GET dan POST. Empat admin cabang (`asep@mb.pkn`, `gudang@pakan.178`, `gudang@pupuk.178`, `admin@mb.abi`) juga menerima 403 pada GET dan POST. Master membuat jurnal seimbang (201) dan jurnal timpang ditolak 422 tanpa header. Tiap peran memakai token hasil loginnya sendiri.

### Requirement: Neraca konsolidasi dan laporan cabang

#### Test TC003 consolidated_financial_reports_access

- **Test Code:** [TC003_consolidated_financial_reports_access.py](./TC003_consolidated_financial_reports_access.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/bd783b55-cfb9-5f19-9aaf-9c090c7da439/test/4b81ac77-b64e-49a9-a975-05947b5a2ace
- **Status:** Passed
- **Analysis / Findings:** Master mendapat 200 pada `GET /api/reports/balance-sheet` dengan `difference` 0.00, dan 200 pada `GET /api/reports/income-statement`. Laporan penjualan admin cabang yang memuat `branch_id` tetap di cabang pemilik token. Kasir tidak menerima neraca cabang lain: responsnya 403, atau 200 yang `branch_id`-nya sama dengan cabang kasir. Endpoint laporan berada di luar middleware `can:view-accounting`, jadi lulus di sini bukan berarti kasir dilarang membaca neraca cabangnya sendiri.

### Requirement: Master data

#### Test TC004 master_data_access_authorization

- **Test Code:** [TC004_master_data_access_authorization.py](./TC004_master_data_access_authorization.py)
- **Test Error:** `kasir@pakan.mb` membuat produk dan menerima 201. Produk tersimpan: id 173, nama `cashier-product-1fc132`, SKU `BR17-L0KLFW`, `branch_id` 17 (`CBG-PKN` / TOKO PAKAN), kategori Pestisida.
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/bd783b55-cfb9-5f19-9aaf-9c090c7da439/test/195f9b0b-39b9-429c-a646-20aa648b69a3
- **Status:** Failed
- **Analysis / Findings:** Sebelum assert produk, kasir yang sama sudah ditolak 403 pada `GET /api/branches`, `POST /api/branches`, dan `POST /api/categories`. `GET /api/categories` tetap 200 karena kategori bersifat global. Celahnya ada di `POST /api/products`: `StoreProductRequest::authorize()` mengembalikan true untuk setiap pengguna yang sudah login. Pemeriksaan master tidak sempat jalan karena kasus berhenti di kasir.

### Requirement: Isolasi Pakan dan Pupuk

#### Test TC005 branch_isolation_product_visibility

- **Test Code:** [TC005_branch_isolation_product_visibility.py](./TC005_branch_isolation_product_visibility.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/bd783b55-cfb9-5f19-9aaf-9c090c7da439/test/ec5cd045-bc74-454f-bd68-6331370e62fa
- **Status:** Passed
- **Analysis / Findings:** Token terpisah untuk `asep@mb.pkn`, `gudang@pakan.178`, `gudang@pupuk.178`, dan `admin@mb.abi`. Daftar `GET /api/products`, termasuk `other_branch_stock`, tidak memuat nama cabang divisi lawan. Lookup barcode lintas divisi tidak mengembalikan 200. Batas pengujian: `branch_id` yang dikirim adalah `999999`, bukan id cabang Pupuk yang nyata, dan tidak ada SKU yang sengaja dibuat sama dengan stok di divisi lain. `ProductController::index` tetap mengisi `other_branch_stock` lewat `Product::withoutGlobalScopes()` bila SKU sama dan stok cabang lain lebih dari nol. Jalur itu tidak terpicu pada katalog yang ada hari ini.

### Requirement: Checkout dan penerimaan barang

#### Test TC006 checkout_and_goods_receipt_workflow

- **Test Code:** [TC006_checkout_and_goods_receipt_workflow.py](./TC006_checkout_and_goods_receipt_workflow.py)
- **Test Error:** Checkout yang sah mengembalikan 201 `Checkout completed successfully.` untuk `INV-20261013-3936` (sale id 145, produk id 175, total 30.00). Skrip mengharapkan 200, lalu berhenti.
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/bd783b55-cfb9-5f19-9aaf-9c090c7da439/test/6ac33c3f-9099-4678-8c76-34f2ae6ad0a7
- **Status:** Failed
- **Analysis / Findings:** Penjualan tercatat dan stok produk uji berkurang dari 5 menjadi 3. Kegagalan ada pada kode status yang diharapkan skrip, bukan pada penolakan transaksi. Sisa data uji: produk 175, penjualan 145, dan penerimaan barang yang dibuat kasus ini. Pemeriksaan jurnal seimbang dan diskon tidak sempat dijalankan.

---

## 3. Coverage & Matching Metrics

- **50.00%** of tests passed (3/6)

| Requirement | Total Tests | Passed | Failed |
| --- | ---: | ---: | ---: |
| Login Sanctum | 1 | 0 | 1 |
| Jurnal hanya master | 1 | 1 | 0 |
| Neraca dan laporan cabang | 1 | 1 | 0 |
| Master data | 1 | 0 | 1 |
| Isolasi Pakan dan Pupuk | 1 | 1 | 0 |
| Checkout dan penerimaan | 1 | 0 | 1 |

Kontrak PHPUnit di `tests/Feature/Api/` yang terverifikasi di server hidup: TC002 jurnal seimbang/timpang, TC007 penolakan jurnal untuk kasir dan admin cabang, serta TC009 neraca master dengan selisih 0. TC001 di server hidup menolak password salah dengan 422, sesuai `AuthApiTest`. Checkout TC003 tercatat 201; assert 200 pada skrip TestSprite tidak cocok dengan respons itu.

---

## 4. Key Gaps / Risks

**Kasir tidak menembus jurnal API.** `POST` dan `GET /api/journals` mengembalikan 403 untuk `kasir@pakan.mb` dan `pipin@kasir.pupuk`. Tanpa token, hasilnya 401. Cabang dan pembuatan kategori juga 403 untuk kasir Pakan.

**Kasir bisa membuat master produk.** `POST /api/products` sebagai `kasir@pakan.mb` mengembalikan 201 dan menulis produk id 173 di TOKO PAKAN. Penyebabnya `StoreProductRequest::authorize()` yang mengizinkan setiap pengguna terautentikasi. Produk uji itu masih ada di database.

**Admin Pakan tidak melihat data Pupuk pada katalog yang diuji.** `GET /api/products` untuk `asep@mb.pkn` dan `gudang@pakan.178` tidak memuat nama atau stok cabang Pupuk di `data` maupun `other_branch_stock`. Arah sebaliknya sama. Celah laten tetap ada: bila SKU yang sama punya stok di divisi lain, `other_branch_stock` mengambil stok itu tanpa scope cabang. Putaran ini tidak menanam SKU bersama, jadi jalur itu belum terbukti bocor pada data hari ini.

**RBAC jurnal belum selesai di web.** `POST /backoffice/finance/journals` berada di grup `can:manage-branch-operations`, yang juga dimiliki admin cabang. TestSprite tidak memanggil rute web itu. Catatan di `JournalApiTest` tetap berlaku: API 403 untuk admin cabang, sementara jurnal manual lewat backoffice belum dikunci ke master.

**Sisa data uji di server lokal:** produk kasir id 173 (`cashier-product-1fc132`), produk checkout id 175, penjualan id 145 (`INV-20261013-3936`). Tidak ada perubahan kode aplikasi.
---
