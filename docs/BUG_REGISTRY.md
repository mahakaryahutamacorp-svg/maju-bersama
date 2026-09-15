# Bug Registry - Maju Bersama POS & ERP

Rekaman permanen setiap bug yang pernah ditemukan dan diselesaikan pada ekosistem ini.
Tujuannya: saat masalah serupa muncul kembali (atau modul bisnis baru ditambahkan),
tim tidak perlu mendiagnosis dari nol.

## Cara Menggunakan Dokumen Ini

Setiap kali sebuah bug selesai diperbaiki, tambahkan entri baru **di bagian paling atas**
daftar (urutan terbaru ke terlama) menggunakan template berikut:

```markdown
### BUG-XXX: <Judul singkat> [RESOLVED | MITIGATED | OPEN]

- **Tanggal ditemukan:** YYYY-MM-DD
- **Area:** <Backend API | Database | Deployment | Frontend | Accounting | Sync>
- **Severity:** <Critical | High | Medium | Low>

**Gejala:**
Apa yang terlihat oleh pengguna/developer.

**Akar masalah:**
Penyebab teknis sebenarnya (bukan sekadar gejala).

**Perbaikan:**
Perubahan konkret yang dilakukan (file/perintah/konfigurasi).

**Pencegahan:**
Test, validasi, atau guard rail yang ditambahkan agar tidak terulang.
```

Aturan penting:

- Satu entri = satu akar masalah. Jangan gabungkan dua bug berbeda.
- Selalu isi bagian **Akar masalah**; entri tanpa akar masalah tidak berguna di kemudian hari.
- Jika bug menghasilkan keputusan arsitektur, catat juga di `architecture_decisions.md`
  dan tautkan nomor ADR-nya.

---

## Daftar Bug

### BUG-005: Test BranchApiTest gagal karena `category_id` tidak diisi [RESOLVED]

- **Tanggal ditemukan:** 2026-09-15
- **Area:** Backend API / Testing
- **Severity:** Medium

**Gejala:**
Dua test pada `tests/Feature/BranchApiTest.php` gagal dengan
`SQLSTATE[23000]: NOT NULL constraint failed: products.category_id`, sehingga
`php artisan test` merah padahal kode aplikasi tidak bermasalah.

**Akar masalah:**
Kolom `products.category_id` didefinisikan `NOT NULL` melalui
`foreignId('category_id')->constrained()`, namun `BranchApiTest` membuat `Product`
tanpa menyertakan `category_id`. Test lain (`CheckoutTest`, `ProductApiTest`) sudah
membuat `Category` lebih dulu, sehingga kesalahan ini hanya muncul di satu file.

**Perbaikan:**
Menambahkan pembuatan `Category` pada `setUp()` `BranchApiTest` dan menyertakan
`category_id` pada setiap pemanggilan `Product::create()` di file tersebut.

**Pencegahan:**
Tidak ada `ProductFactory` di repositori ini, sehingga setiap test yang membuat produk
harus menyediakan relasi wajib secara manual. Selalu jalankan seluruh suite
(`php artisan test`) sebelum menambah fitur, bukan hanya test file yang sedang dikerjakan.

---

### BUG-004: Sertifikat SSL gagal terbit untuk subdomain `www` [RESOLVED]

- **Tanggal ditemukan:** 2026-09-14
- **Area:** Deployment
- **Severity:** High

**Gejala:**
Penerbitan sertifikat Let's Encrypt via `acme.sh` berhasil untuk `majubersama.online`
tetapi gagal untuk `www.majubersama.online` dengan error:
`Invalid response from http://www.majubersama.online/.well-known/acme-challenge/...: 404`.
Anehnya, DNS A record untuk `www` sudah benar mengarah ke IP VPS.

**Akar masalah:**
Nginx virtual host yang dibuat aaPanel hanya mendaftarkan satu hostname:
`server_name majubersama.online;`. Akibatnya request dengan `Host: www.majubersama.online`
tidak cocok dengan server block manapun, lalu jatuh ke *default server block* yang
document root-nya berbeda, sehingga file verifikasi ACME tidak ditemukan (404).
Masalahnya ada di layer nginx, bukan di DNS.

**Perbaikan:**
Menambahkan hostname `www` pada direktif server_name di
`/www/server/panel/vhost/nginx/majubersama.online.conf`:

```nginx
server_name majubersama.online www.majubersama.online;
```

lalu `nginx -t && nginx -s reload`, dan mengulang `acme.sh --issue`.

**Pencegahan:**
Saat menambah domain/subdomain baru, selalu verifikasi dengan
`curl -H "Host: <domain>" http://127.0.0.1/` dari dalam server sebelum meminta sertifikat.
Jika hasilnya 404 padahal domain utama 200, hampir pasti `server_name` belum lengkap.

---

### BUG-003: Aplikasi produksi berjalan dengan konfigurasi environment lokal [RESOLVED]

- **Tanggal ditemukan:** 2026-09-14
- **Area:** Deployment
- **Severity:** Critical

**Gejala:**
Aplikasi yang sudah ter-deploy di VPS berjalan dengan `APP_ENV=local`,
`APP_DEBUG=true`, dan `APP_URL=http://localhost`. Stack trace Laravel berpotensi
tampil ke publik saat terjadi error, dan URL absolut yang dihasilkan framework salah.

**Akar masalah:**
File `.env` di server merupakan hasil salinan `.env.example` yang belum pernah
disesuaikan untuk produksi. Terdapat pula sisa file `.env.save` dan `.env.save.1`
dari percobaan konfigurasi sebelumnya, yang membuat konfigurasi mana yang aktif
menjadi ambigu.

**Perbaikan:**
Menulis ulang `.env` produksi dengan `APP_ENV=production`, `APP_DEBUG=false`,
`APP_URL=https://majubersama.online`, `LOG_LEVEL=error`, kredensial MySQL yang benar,
lalu menghapus file `.env.save*`, mengatur permission `chmod 600`, dan menjalankan
`php artisan config:cache`.

**Pencegahan:**
Jangan pernah menyalin `.env.example` mentah-mentah ke server produksi.
Verifikasi hasil akhir dengan `php artisan about` dan pastikan baris
`Environment: production` serta `Debug Mode: OFF`.

---

### BUG-002: Nginx tidak dapat di-start melalui systemd [MITIGATED]

- **Tanggal ditemukan:** 2026-09-14
- **Area:** Deployment
- **Severity:** Low

**Gejala:**
`systemctl start nginx` mengembalikan
`Job for nginx.service failed because the control process exited with error code`,
dan `systemctl is-active nginx` melaporkan `failed`, padahal website tetap dapat
diakses normal dan merespons HTTP 200.

**Akar masalah:**
Nginx pada server ini dikelola oleh aaPanel melalui LSB init script
(`/etc/init.d/nginx`), bukan sebagai unit systemd native. Proses nginx sebenarnya
sudah berjalan, sehingga init script menolak start kedua dengan pesan
`nginx (pid ...) already running` dan systemd menerjemahkannya sebagai kegagalan.
Status `failed` di systemd adalah *false negative*.

**Perbaikan:**
Tidak ada perubahan yang diperlukan pada layanan. Untuk mengelola nginx di server ini,
gunakan `nginx -s reload` / `nginx -t` atau panel aaPanel, bukan `systemctl`.

**Pencegahan:**
Jangan jadikan `systemctl is-active nginx` sebagai indikator kesehatan pada server
berbasis aaPanel. Gunakan pengecekan fungsional:
`curl -s -o /dev/null -w "%{http_code}" https://majubersama.online/`.

---

### BUG-001: Model Sale tidak menerapkan isolasi cabang [RESOLVED]

- **Tanggal ditemukan:** 2026-09-13
- **Area:** Backend API
- **Severity:** Critical

**Gejala:**
Pengguna dari satu cabang dapat melihat data penjualan milik cabang lain.

**Akar masalah:**
Model `Sale` tidak menggunakan trait `HasBranchScope`, sehingga global scope yang
memfilter berdasarkan `branch_id` milik pengguna terautentikasi tidak pernah aktif
pada query penjualan.

**Perbaikan:**
Menambahkan `use HasBranchScope;` pada `app/Models/Sale.php`.

**Pencegahan:**
Setiap model baru yang memiliki kolom `branch_id` wajib memakai `HasBranchScope`.
Sertakan test isolasi cabang (pengguna cabang A tidak boleh melihat data cabang B)
untuk setiap resource baru, sebagaimana pola pada `tests/Feature/ProductApiTest.php`.

---
