# Laporan Developer — AGC CRM

> Dokumen ini ditulis **developer untuk developer** sebagai handoff teknis proyek.  
> Untuk panduan instalasi dan penggunaan umum, lihat [README.md](README.md).

**Terakhir diperbarui:** 23 Juli 2026  
**Stack:** Laravel 10 · PHP 8.1+ · MySQL (database EspoCRM `db_crm`) · Blade · Tailwind CDN · Alpine.js · DomPDF · Spatie Media Library

---

## 1. Ringkasan Proyek

AGC CRM adalah aplikasi Laravel yang **membaca dan menulis ke database EspoCRM yang sudah ada**, tanpa migrasi data. Tujuan utamanya: memberikan UX modern untuk tim sales (opportunity, quotation, aktivitas, dashboard) sambil tetap kompatibel dengan data legacy Espo.

**Prinsip desain yang dipegang teguh:**

- Satu database, dua "dunia" data: tabel Espo (`account`, `opportunity`, `user`, …) dan tabel aplikasi (`crm_*`).
- Tidak over-engineer: tanpa Docker, tanpa build step frontend (CDN), tanpa microservice.
- Business logic berat diletakkan di **Service class** (`QuotationService`, `NotificationService`, dll.), bukan di controller.
- Role & permission dikonsolidasikan di model `User`, bukan tersebar di view.

---

## 2. Arsitektur Data

### 2.1 Peta Tabel

| Kategori | Tabel | Model | Catatan |
|----------|-------|-------|---------|
| EspoCRM (legacy) | `user` | `App\Models\User` | Auth + identitas. PK varchar(24). |
| EspoCRM | `account`, `contact`, `lead` | `App\Models\Espo\*` | Pelanggan, kontak, lead. |
| EspoCRM | `opportunity` | `App\Models\Espo\Opportunity` | Deal + kolom custom `crm_*` (pricing, diskon, margin). |
| EspoCRM | `entity_email_address`, `email_address` | — | Email user di-resolve via join manual. |
| Aplikasi | `crm_quotations`, `crm_quotation_items`, `crm_quotation_revisions` | `Quotation`, `QuotationItem`, `QuotationRevision` | Penawaran & riwayat revisi. |
| Aplikasi | `crm_quotation_templates` | `QuotationTemplate` | Template HTML perusahaan (AGC/EPS/PSI). |
| Aplikasi | `crm_activities` | `Activity` | Follow-up, task, event, training. |
| Aplikasi | `crm_user_profiles` | `UserProfile` | Sales code, target, signature, `app_role`. |
| Aplikasi | `crm_settings` | `CrmSetting` | PPN/PPH persisten (override config). |
| Aplikasi | `crm_notifications` | `CrmNotification` | Notifikasi in-app + popup. |
| Aplikasi | `crm_opportunity_notes` | `OpportunityNote` | Catatan internal per opportunity. |
| Aplikasi | `crm_purchase_orders`, `crm_purchase_order_items` | `PurchaseOrder`, `PurchaseOrderItem` | PO per Closed Won; item bebas (qty, deskripsi, note, harga modal). |
| Aplikasi | `media` | Spatie Media Library | Lampiran aktivitas & dokumen opportunity. |

### 2.2 Kolom Custom di `opportunity`

Kolom `crm_*` ditambahkan via migration Laravel ke tabel Espo yang sudah ada:

```
crm_tax_category[]      — wapu / non_wapu per baris item
crm_item_kind[]         — barang / jasa per baris item
crm_sell_exclude[]      — harga jual (exclude PPN)
crm_cost_exclude[]      — harga modal (exclude PPN)
crm_item_discount[]     — diskon per item
crm_won_margin          — margin aktual saat Closed Won
crm_shipping_cost       — ongkir (1 opp = 1 nilai; dikelola di area PO)
crm_has_discount        — flag ada permintaan diskon
crm_discount_amount     — nominal diskon yang diminta
crm_discount_status     — pending | approved | rejected
crm_discount_*          — metadata request/review (by, at, note)
```

Array item (`item`, `quantity`, `price`, `cost`, `vendor`) tetap memakai format JSON array EspoCRM.

### 2.3 Konvensi ID & Ownership

- Semua PK Espo = `varchar(24)` (format Espo).
- `created_by`, `assigned_to`, `assigned_user_id` → selalu `user.id` Espo.
- Tabel `crm_*` tidak pernah menghapus/mengubah struktur tabel Espo asli (hanya `ALTER` tambah kolom).

---

## 3. Autentikasi & Role

### 3.1 Login

```
LoginController → EspoAuth → EspoPassword (hash sha256 + salt)
```

- Salt: `config('crm.espo_password_salt')` atau env `ESPO_PASSWORD_SALT`.
- Login bisa pakai `user_name` atau email (email di-resolve dari `entity_email_address`).
- Remember token **sengaja dinonaktifkan** di model `User`.
- Manajemen user baru: `EspoUserManager` (pakai DB transaction).

### 3.2 Role Aplikasi

Role disimpan di `crm_user_profiles.app_role`, bukan hanya `user.type` Espo:

| Role | Konstanta | Akses utama |
|------|-----------|-------------|
| Superadmin | `superadmin` | Semua modul + admin panel + approve diskon |
| Admin | `admin` | Lihat semua opportunity, buat quotation |
| Sales | `sales` | Data assigned ke dirinya saja |
| Purchasing | `purchasing` | Opportunity Closed Won (edit cost vendor + kelola PO) |
| Finance | `finance` | Opportunity Closed Won (lihat margin/finance) |

Fallback legacy: `user.type === 'admin'` → superadmin, selain itu → sales.

**Permission dicek via method di `User`**, contoh:

```php
$user->canCreateQuotation()
$user->canApproveDiscount()
$user->canViewAllOpportunities()
$user->canEditWonCostVendor()
$user->canManagePurchaseOrders()  // purchasing + superadmin, Closed Won
$user->canEditCustomerContact()  // hanya superadmin
```

Middleware route admin: `->middleware('role:superadmin')` (`EnsureRole`).

### 3.3 Data Scoping

Trait `ScopesToUser` dipakai controller:

- **Sales** → `where assigned_user_id = auth id`
- **Purchasing/Finance** → hanya `stage = 'Closed Won'`
- **Admin/Superadmin** → tanpa filter assignment

---

## 4. Modul & Alur Bisnis

### 4.1 Opportunity (Deal)

**Controller:** `OpportunityController`  
**Model:** `App\Models\Espo\Opportunity`

Fitur utama:
- Pipeline Kanban (`KANBAN_STAGES`) + drag stage via `updateStage`.
- Multi-item pricing dengan `OpportunityProductPricing` (PPN, PPH, margin).
- Prefill quotation dari opportunity (termasuk item, harga, kontak).
- Upload dokumen via Spatie Media Library.
- Catatan internal (`OpportunityNote`).
- **Purchase Order (PO)** — nested di detail Closed Won (`OpportunityPurchaseOrderController`):
  - 1 opportunity : banyak PO; 1 PO : banyak item bebas (tidak mengikat produk opportunity)
  - Kondisi bayar: `payment_term` = `top` | `cash` (cash: modal +1% exclude & include)
  - Ongkir: `opportunity.crm_shipping_cost` (1 opportunity = 1 ongkir), UI di area PO
  - Laporan: preview + PDF per opportunity (`PurchaseOrderReportService`) — nilai jual dari produk opp, modal dari total PO, PPh/invoice dikosongkan
  - Field item: nama produk, qty, deskripsi, note, harga modal; kolom tampilan: 1% Exclude (cash), Jumlah Exclude, Harga Include (modal×PPN), 1% Include (cash), Jumlah Include, Subtotal
  - `total` = Σ(qty × jumlah_exclude); jumlah_exclude = modal (+1% bila cash)
  - CRUD hanya `canManagePurchaseOrders()` + stage Closed Won
- **Alur diskon:**
  1. Sales mengajukan diskon → `crm_discount_status = pending`
  2. Superadmin approve/reject/revert via route terpisah
  3. `NotificationService` kirim notifikasi ke sales & requester

### 4.2 Quotation (Penawaran)

**Controller:** `QuotationController`  
**Service:** `QuotationService` (631 baris — inti bisnis)

#### Penomoran otomatis

Format: `0002/KA/QO/VII/26`

```
{sequence}/{sales_code}/{prefix}/{roman_month}/{year}
```

- Sequence unik per tahun, dengan `lockForUpdate()` untuk hindari race condition.
- Sales code dari `crm_user_profiles.sales_code` — **wajib diisi admin**.
- Prioritas kode: assigned user di opportunity → user login.
- Revisi dokumen setelah `sent`: `0002-R1/KA/QO/VII/26` (`base_number` + `document_revision`).

#### Status & revisi

| Status | Perilaku |
|--------|----------|
| `draft` | Bisa diedit bebas. Snapshot revisi tidak dibuat berulang untuk draft yang belum pernah sent. |
| `sent` | Edit memicu increment `document_revision` + simpan snapshot ke `crm_quotation_revisions`. |
| `accepted` / `rejected` / `expired` | Terminal states |

#### Template rendering

`QuotationService::render()` mengganti placeholder `{{ key }}` di HTML template.

Placeholder utama:
- Data pelanggan: `customer_name`, `company_name`, `contact_person`, dll.
- Finansial: `subtotal`, `discount`, `tax_percent`, `total_price`
- Tabel item: `items_table`, `items_rows`, `items_table_idr`, `items_table_diskon_item`, dll.
- Perusahaan: `company_legal_name`, `company_address`, `company_phone`, `company_email`
- Sales: `sales_name`, `sales_signature`, `sales_job_position`

**Penting:** Template memakai placeholder custom, **bukan Blade engine penuh**. Beberapa `@if` untuk `$notes`/`$terms` diproses manual via regex; directive Blade lain di-strip.

#### Pricing di quotation

- Unit price quotation memakai **`sell_exclude`** (harga exclude PPN), bukan `sell_include`.
- Item punya kolom pricing terpisah di `crm_quotation_items` (cost, margin, discount per item).
- PDF: `prepareHtmlForPdf()` resolve gambar lokal + font custom (`storage/fonts`).

### 4.3 Aktivitas

**Controller:** `ActivityController`, `ActivityMediaController`

- Tipe: follow-up, task, event, training.
- Integrasi Google Calendar: URL template di response JSON (`config('crm.google_calendar_timezone')`).
- Media upload via Spatie (tabel `media`, `model_id` varchar untuk kompatibilitas Espo).

### 4.4 Dashboard & Leaderboard

**Controller:** `DashboardController`

- Statistik pipeline, quotation aktif, aktivitas mendatang.
- Sales leaderboard: sort by `total` | `margin` | `target_percent`.
- Sales target dari `crm_user_profiles` (nominal + periode + deadline).

### 4.5 Notifikasi

**Service:** `NotificationService`  
**Controller:** `NotificationController`

- Tipe: diskon approved/rejected/revised, deadline aktivitas, dll.
- `unique_key` mencegah duplikasi notifikasi.
- Popup flag (`show_popup`) untuk alert di UI.
- Mark read individual / semua / dismiss popups.

### 4.6 Settings (Admin)

**Controller:** `Admin\SettingController`  
**Model:** `CrmSetting`

- PPN & PPH persisten di DB, fallback ke `config/crm.php`.
- Dipakai oleh `OpportunityProductPricing::ppnPercent()` dan `pphPercent()`.

---

## 5. Pricing & Pajak — Konvensi Penting

Semua kalkulasi terpusat di `App\Support\OpportunityProductPricing`:

| Konsep | Implementasi |
|--------|--------------|
| PPN | `ppnPercent()` → DB setting → config → default 11% |
| PPH | `pphPercent()` → default 2% |
| Exclude → Include | `includeFromExclude()` = exclude × 1.11 |
| Include → Exclude | `excludeFromInclude()` = include ÷ 1.11 |
| Margin | Berdasarkan sell exclude, cost exclude, PPH factor |
| Tax category | `wapu` vs `non_wapu` per baris item |

**Perubahan penting (Juli 2026):** Quotation dan tampilan template beralih ke terminologi **exclude PPN** (`sell_exclude`) agar konsisten dengan perhitungan margin di opportunity.

---

## 6. Struktur Kode

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # Template, User, Setting (superadmin only)
│   │   ├── Auth/           # LoginController
│   │   └── Concerns/
│   │       └── ScopesToUser.php
│   └── Middleware/
│       └── EnsureRole.php
├── Models/
│   ├── Espo/               # Read/write Espo entities
│   │   └── Concerns/EspoEntity.php
│   ├── User.php            # Auth model = tabel user Espo
│   ├── UserProfile.php
│   ├── Quotation*.php
│   ├── PurchaseOrder.php / PurchaseOrderItem.php
│   ├── Activity.php
│   ├── CrmNotification.php
│   └── CrmSetting.php
├── Services/
│   ├── QuotationService.php    # Penomoran, template merge, PDF prep
│   ├── PurchaseOrderService.php
│   ├── NotificationService.php
│   ├── EspoAuth.php / EspoPassword.php
│   ├── EspoUserManager.php
│   ├── EspoEntityWriter.php
│   └── ContactService.php
└── Support/
    ├── OpportunityProductPricing.php
    └── helpers.php             # money(), initials()

config/crm.php                  # Salt, perusahaan, format nomor QO, pajak fallback
routes/web.php                  # Semua route web
database/migrations/            # Hanya crm_* + alter opportunity
resources/views/                # Blade templates per modul
```

---

## 7. Route Penting (Cheat Sheet)

| Area | Prefix | Middleware |
|------|--------|------------|
| Dashboard | `/dashboard` | auth |
| Customers | `/customers` | auth |
| Leads | `/leads` | auth |
| Opportunities | `/opportunities` | auth |
| Purchase Orders | `/opportunities/{id}/purchase-orders` | auth (purchasing/superadmin + Closed Won) |
| Quotations | `/quotations` | auth |
| Activities | `/activities` | auth |
| Notifications | `/notifications` | auth |
| Profile | `/profile` | auth |
| Admin (templates, users, settings) | `/templates`, `/users`, `/settings` | auth + role:superadmin |
| Contacts (global) | `/contacts` | auth + role:superadmin |

Utility routes (hati-hati di production):
- `GET /clear-cache`
- `GET /optimize`
- `GET /storage-link`

---

## 8. Changelog Developer (Ringkas)

Commit history dari awal proyek hingga Juli 2026:

| Periode | Perubahan utama |
|---------|-----------------|
| Awal | Scaffold Laravel, integrasi EspoCRM DB, modul dasar (customer, lead, quotation, activity) |
| Jun 2026 | Opportunity module, product pricing, media library, activity media, Google Calendar link |
| Jun 2026 | Sales leaderboard, dashboard enhancement, opportunity notes, contact CRUD |
| Jul 2026 | Sales code & format nomor QO baru, document revision, template per kategori/perusahaan |
| Jul 2026 | Role system diperluas (superadmin, purchasing, finance), discount approval workflow |
| Jul 2026 | Notification system, tax settings (PPN/PPH), sales target period/deadline |
| Jul 2026 | Pricing refactor: `sell_exclude` di quotation, diskon per item, akses kontak pelanggan |
| Jul 2026 | Purchase Order (PO) pada Closed Won: multi-PO per opportunity, item bebas, akses purchasing/superadmin |

**Commit terbaru (8e67418):**
- Search quotation include data opportunity
- `EspoUserManager` pakai DB transaction
- Terminologi pricing di tabel quotation diperjelas
- UX pencarian quotation di index view

---

## 9. Hal yang Perlu Diperhatikan (Gotchas)

### 9.1 EspoCRM Compatibility

- Jangan rename/hapus kolom Espo. Hanya tambah `crm_*`.
- `EspoEntity` trait handle soft delete (`deleted = 0`) dan timestamp Espo.
- Write ke Espo entity lewat `EspoEntityWriter` agar format ID & field konsisten.

### 9.2 User & Email

- Model `User` tidak punya kolom `email` langsung — selalu lewat accessor `getEmailAttribute()` yang query `entity_email_address`.
- `UserProfile` terpisah di `crm_user_profiles` — load via relasi `profile()`.

### 9.3 Quotation

- Sales code **harus** ada sebelum generate nomor — otherwise `RuntimeException`.
- `lockForUpdate()` di `nextYearlySequence()` butuh transaction aktif (pastikan caller wrap dalam `DB::transaction`).
- Template HTML: hindari Blade syntax kompleks; pakai placeholder `{{ key }}`.
- Font PDF disimpan di `storage/fonts` (gitignored) — perlu di-setup manual di server baru.

### 9.4 Media Library

- Migration `alter_media_model_id_for_espo` mengubah `model_id` jadi varchar agar kompatibel PK Espo.
- Opportunity & Activity implement `HasMedia`.

### 9.5 Frontend

- Tailwind + Alpine via CDN — **tidak ada `npm run build`**.
- Untuk production skala besar, pertimbangkan migrasi ke Vite (belum dilakukan).

### 9.6 Security

- Route `/clear-cache`, `/optimize`, `/storage-link` terbuka tanpa auth — **nonaktifkan atau proteksi di production**.
- Salt password Espo ada di config — jangan commit `.env`.

---

## 10. Setup Developer

```bash
# 1. Clone & install
composer install
cp .env.example .env
php artisan key:generate

# 2. Konfigurasi DB (harus pointing ke db_crm EspoCRM)
# DB_DATABASE=db_crm
# ESPO_PASSWORD_SALT=<dari EspoCRM config>

# 3. Migrasi (hanya buat/alter tabel crm_*)
php artisan migrate

# 4. Seed (opsional — template quotation default)
php artisan migrate --seed

# 5. Storage link (untuk signature & media)
php artisan storage:link

# 6. Jalankan
php artisan serve
```

### Env yang relevan

```env
DB_DATABASE=db_crm
ESPO_PASSWORD_SALT=...
CRM_DEFAULT_CURRENCY=IDR
CRM_LEGACY_URL=https://crm.alphagraha.co.id
CRM_PPN_PERCENT=11
CRM_PPH_PERCENT=2
CRM_GOOGLE_CALENDAR_TIMEZONE=Asia/Jakarta
```

---

## 11. Cara Menambah Fitur Baru

Pola yang sudah established:

1. **Migration** → prefix `crm_` untuk tabel baru, atau `crm_*` column untuk alter Espo table.
2. **Model** → `App\Models\` untuk tabel crm, `App\Models\Espo\` untuk tabel Espo.
3. **Service** → business logic yang reusable (hindari fat controller).
4. **Controller** → validasi request, panggil service, return view/redirect.
5. **Permission** → tambah method di `User`, cek di controller **dan** sembunyikan UI di Blade.
6. **Route** → daftarkan di `routes/web.php`, group dengan middleware yang sesuai.
7. **View** → extend `layouts/app.blade.php`, pakai komponen di `resources/views/components/`.

---

## 12. Testing & Quality

- PHPUnit tersedia (`tests/`) — coverage masih minimal, belum ada test suite komprehensif.
- Laravel Pint tersedia untuk code style: `./vendor/bin/pint`
- Belum ada CI/CD pipeline di repo.

**Rekomendasi untuk developer berikutnya:**
- [ ] Tambah test untuk `QuotationService` (penomoran, revisi, placeholder merge)
- [ ] Tambah test untuk `OpportunityProductPricing` (edge case PPN/PPH)
- [ ] Proteksi/hapus utility routes di production
- [ ] Pertimbangkan queue untuk notifikasi jika volume meningkat (`QUEUE_CONNECTION` masih `sync`)

---

## 13. Kontak & Referensi

| Resource | Lokasi |
|----------|--------|
| Config bisnis | `config/crm.php` |
| Helper global | `app/Support/helpers.php` |
| Panduan user | `README.md` |
| EspoCRM legacy | `CRM_LEGACY_URL` (untuk dokumen lama) |

---