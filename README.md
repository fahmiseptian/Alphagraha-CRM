# AGC CRM

CRM modern berbasis **Laravel 10** yang tetap menggunakan **database EspoCRM** yang sudah ada sebagai sumber data utama (tanpa migrasi data). Dibangun sederhana, stabil, dan mudah dikembangkan untuk tim sales.

> **Developer?** Lihat [README_DEVELOPER.md](README_DEVELOPER.md) untuk laporan teknis, arsitektur, dan handoff developer-to-developer.

## Fitur Utama

- **Dashboard** — ringkasan pelanggan, prospek (lead), penawaran, pipeline penjualan, dan aktivitas mendatang.
- **Data Pelanggan** — daftar, detail, kontak, riwayat deal, penawaran, dan aktivitas. Dilengkapi pencarian & filter. (membaca tabel `account` EspoCRM)
- **Lead Management** — daftar lead, ringkasan per status, ubah status, assignment ke sales, dan catatan follow-up. (tabel `lead` EspoCRM)
- **Aktivitas & Task** — jadwal follow-up, reminder, prioritas, dan catatan sales. (tabel `crm_activities`)
- **Penawaran (Quotation)** — fitur inti:
  - Pilih pelanggan → isi data → sistem **merge ke template HTML** perusahaan.
  - **Preview** sebelum simpan, **unduh PDF**, **riwayat revisi**, dan status penawaran.
  - Item dinamis dengan **kalkulasi total real-time** (subtotal, diskon, pajak).
- **Template Penawaran** (admin) — template HTML standar yang dipakai seluruh sales.
- **Manajemen Pengguna** (admin) — kelola akun, role, dan pemetaan ke user EspoCRM.
- **Hak akses**: `admin` (Administrator) dan `sales`.

## Arsitektur Data

Aplikasi memakai **satu database** (`db_crm`, milik EspoCRM):

| Sumber | Tabel | Akses |
|--------|-------|-------|
| EspoCRM (data lama) | `account`, `contact`, `lead`, `opportunity`, `user` | Dibaca; lead dapat diperbarui status/assignment. Tabel `user` juga menjadi sumber identitas & login. |
| Aplikasi CRM (baru) | `crm_quotations`, `crm_quotation_items`, `crm_quotation_revisions`, `crm_quotation_templates`, `crm_activities` | Dikelola penuh oleh Laravel |

Tabel baru diberi prefix `crm_` agar **tidak mengganggu** struktur & data EspoCRM. Kolom kepemilikan (`created_by`, `assigned_to`) menyimpan `user.id` (varchar 24) milik EspoCRM.

> **Autentikasi:** Pengguna aplikasi = tabel `user` EspoCRM (model `App\Models\User`). Login diverifikasi langsung terhadap tabel `user` memakai algoritma hashing EspoCRM (`app/Services/EspoPassword.php`, salt dari `config/crm.php`), lalu sesi dibuat via `Auth::login()`. **Role** diturunkan dari kolom `type` EspoCRM (`admin` → Administrator, selain itu → Sales), sehingga sales hanya melihat pelanggan/lead yang ditugaskan kepadanya (`assigned_user_id` = id user). Tidak ada lagi tabel `crm_users`.
>
> Login dapat memakai **username (`user_name` EspoCRM) atau email**. Salt EspoCRM diatur di `config/crm.php` (`espo_password_salt`) atau env `ESPO_PASSWORD_SALT`. Penambahan/penyuntingan akun dilakukan di aplikasi EspoCRM; menu **Pengguna** di CRM ini hanya menampilkan daftar (read-only), dan profil hanya mengizinkan ganti kata sandi.

## Teknologi

- Laravel 10 + Blade
- Tailwind CSS (via CDN) + Bootstrap Icons + Alpine.js — tanpa build step (sederhana)
- `barryvdh/laravel-dompdf` untuk export PDF
- MySQL (database EspoCRM `db_crm`)

## Instalasi

1. **Konfigurasi `.env`** (sudah diatur):
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=db_crm
   DB_USERNAME=root
   DB_PASSWORD=1234
   ```

2. **Install dependency & migrasi + seed:**
   ```bash
   composer install
   php artisan migrate --seed
   ```
   Migrasi hanya membuat tabel `crm_*` baru, tidak menyentuh data EspoCRM.

3. **Jalankan:**
   ```bash
   php artisan serve
   ```
   Buka http://127.0.0.1:8000

## Akun Default

| Role | Email | Kata Sandi |
|------|-------|------------|
| Administrator | `admin@agc.local` | `admin123` |
| Sales (impor dari EspoCRM) | email user EspoCRM | `sales123` |

> **Penting:** Ganti kata sandi default setelah login pertama (menu **Profil & Kata Sandi**).

## Template Penawaran & Placeholder

Template ditulis dalam HTML. Saat penawaran dibuat, sistem mengganti placeholder berikut:

```
{{ customer_name }}   {{ company_name }}   {{ customer_email }}   {{ customer_phone }}
{{ customer_address }} {{ quotation_number }} {{ quotation_date }} {{ valid_until }}
{{ currency }} {{ subtotal }} {{ discount }} {{ tax_percent }} {{ tax_amount }}
{{ total_price }} {{ notes }} {{ terms }} {{ sales_name }} {{ revision }}
{{ items_table }}   {{ items_rows }}
```

- `{{ items_table }}` — tabel item lengkap (HTML).
- `{{ items_rows }}` — hanya baris `<tr>` item (untuk template tabel kustom).

Logika merge ada di `app/Services/QuotationService.php`.

## Struktur Folder Penting

```
app/
  Http/Controllers/        # Dashboard, Customer, Lead, Activity, Quotation, Admin\*, Profile
  Http/Middleware/EnsureRole.php
  Models/
    Espo/                  # Model read untuk tabel EspoCRM (Account, Contact, Lead, ...)
    User, Quotation, QuotationItem, QuotationTemplate, QuotationRevision, Activity
  Services/QuotationService.php   # penomoran + merge template
  Support/helpers.php             # money(), initials()
resources/views/
  layouts/app.blade.php    # layout utama (sidebar + topbar)
  components/              # stat-card, card, badge, empty-state
  dashboard, customers, leads, activities, quotations, admin/*, profile, auth/login
database/
  migrations/              # hanya tabel crm_*
  seeders/                 # UserSeeder, QuotationTemplateSeeder
```

## Catatan Pengembangan

- Mengikuti standar Laravel, tanpa over-engineering (tanpa Docker/microservice).
- Tailwind & Alpine via CDN agar tidak butuh `npm`/build. Untuk produksi, dapat dipindah ke build Vite bila diperlukan.
- Mudah ditambah modul baru: buat controller + view, daftarkan route di `routes/web.php`.
