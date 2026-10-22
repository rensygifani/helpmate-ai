# HelpMate AI

HelpMate AI adalah website helpdesk untuk layanan payment gateway. Merchant melaporkan kendala transaksi sebagai tiket, lalu petugas (CS, Tim Teknis, dan Admin) menanganinya. Website ini dibuat untuk tugas ALP Modul 4 mata kuliah Cloud Computing, Universitas Ciputra.

Dibuat oleh:

- Rensy Indra Gifani (0706012414002)
- Gherald Giovanny Marcello Sumual (1076012514014)

Semua data di website ini adalah data contoh (dummy).

## Latar belakang

Penanganan kendala payment gateway masih sering lewat WhatsApp, email, dan spreadsheet. Laporan tercecer, data laporan sering tidak lengkap, dan satu masalah bisa dilaporkan berkali-kali. HelpMate AI mengumpulkan laporan di satu tempat, membantu petugas membaca laporan lebih cepat dengan AI, dan memberi merchant tempat untuk memantau status tiketnya.

## Fitur

Autentikasi
- Register dan login dengan Firebase Authentication.
- Email verifikasi dikirim setelah register, dan akun yang belum terverifikasi tidak dapat masuk.
- Login dengan Google dan GitHub.
- Reset password lewat email.
- Role: merchant, cs, tim_teknis, admin, dan super_admin.

Data (Firebase Realtime Database)
- CRUD Tiket Kendala.
- CRUD Knowledge Base (panduan solusi).
- CRUD Riwayat Tindak Lanjut per tiket.
- Lampiran bukti berupa gambar, dikecilkan di browser lalu disimpan sebagai data teks.
- Manajemen pengguna oleh Super Admin (tambah pengguna dan ubah role).

Fitur AI (Gemini API)
- Ekstraksi data transaksi dari keluhan, deteksi data yang kurang, klasifikasi kategori, dan saran prioritas.
- Ringkasan kronologi, draf balasan, dan artikel Knowledge Base yang terkait.
- Konfirmasi tiket serupa dari merchant yang sama.
- Ringkasan harian untuk admin.
- Hasil AI hanya saran. Petugas yang memutuskan dan menerapkannya.

Fitur lain
- Penyamaran data sensitif (kartu, rekening, NIK, OTP/PIN, password, kunci API, email, telepon) sebelum disimpan dan sebelum dikirim ke Gemini.
- Deteksi tiket serupa dengan skor otomatis, dilanjutkan konfirmasi Gemini.
- Saran artikel panduan saat merchant mengetik keluhan.
- Portal Merchant untuk memantau tahapan tiket dan membalas petugas.
- Eskalasi ke Tim Teknis.
- Dashboard analitik admin: KPI, grafik 14 hari, ringkasan per kategori, prioritas, status, dan metode bayar, SLA respons, dan peringatan gangguan.
- Security Rules Realtime Database yang membatasi akses menurut role.

## Layanan cloud dan teknologi

| Layanan | Kegunaan |
| --- | --- |
| Firebase Authentication | Register, login, verifikasi email, reset password, login Google dan GitHub |
| Firebase Realtime Database | Penyimpanan seluruh data dan aturan akses (Security Rules) |
| Gemini API | Fitur AI, dipanggil dari server PHP dengan function calling |
| Render (Docker) | Hosting website |
| cron-job.org (opsional) | Penjadwal ringkasan harian otomatis |

Aplikasi memakai PHP 8 tanpa framework dan tanpa Composer. Koneksi ke Firebase memakai REST API lewat cURL, dan semua fungsinya ada di `includes/firebase.php`.

## Cara menjalankan di komputer sendiri

1. Pasang PHP 8 atau lebih baru dan aktifkan ekstensi cURL dan mbstring (di XAMPP, cek `extension=curl` di `php.ini`).
2. Buat project di Firebase Console.
   - Authentication: aktifkan metode sign-in Email/Password.
   - Realtime Database: buat database dengan locked mode, buka tab Rules, tempel isi `database.rules.json`, lalu Publish.
3. Ambil Web API Key (Project settings, tab General) dan alamat database (bagian atas halaman Realtime Database).
4. Salin `config.example.php` menjadi `config.local.php`, lalu isi `FIREBASE_API_KEY` dan `FIREBASE_DB_URL`. Untuk fitur AI, buat API key di Google AI Studio dan isi `GEMINI_API_KEY`.
5. Jalankan di folder proyek:
   ```
   php -S localhost:8000
   ```
6. Buka `http://localhost:8000`.

Panduan deploy ke Render ada di `DEPLOY.md`, dan cara mengaktifkan login Google dan GitHub ada di `SOCIAL_LOGIN.md`.

## Membuat akun petugas

Akun yang baru register otomatis berrole merchant. Role tidak dapat diubah sendiri oleh pengguna.

1. Register dan verifikasi email untuk akun pertama.
2. Di Firebase Console, buka Realtime Database, node `users`, pilih ID akun tersebut, lalu ubah `role` menjadi `super_admin`.
3. Logout lalu login lagi.
4. Setelah itu, Super Admin dapat menambah pengguna dan mengubah role lewat menu Pengguna di website.

Hak akses:

| Role | Hak akses |
| --- | --- |
| merchant | Membuat tiket, melihat tiket sendiri, membalas lewat Portal Merchant |
| cs | Semua tiket, riwayat tindak lanjut, fitur AI, dan Knowledge Base |
| tim_teknis | Seperti cs, dengan fokus pada tiket yang dieskalasi |
| admin | Seperti cs, ditambah dashboard analitik dan ringkasan harian |
| super_admin | Seperti admin, ditambah manajemen pengguna |

## Letak fungsi CRUD

Tiket (node `tickets`)
- Create: `tickets/create.php`
- Read: `tickets/index.php` dan `tickets/view.php`
- Update: `tickets/edit.php`
- Delete: `tickets/delete.php`

Knowledge Base (node `knowledge_base`)
- Create: `knowledge/create.php`
- Read: `knowledge/index.php` dan `knowledge/view.php`
- Update: `knowledge/edit.php`
- Delete: `knowledge/delete.php`

Riwayat Tindak Lanjut (node `followups/{ticketId}`)
- Create: `followups/create.php` dan `followups/reply.php`
- Read: `render_followups` di `includes/followup_form.php` (tampil di `tickets/view.php`)
- Update: `followups/edit.php`
- Delete: `followups/delete.php`

## Struktur folder

```
index.php               pengarah ke login atau dashboard
register.php            register dan kirim email verifikasi
login.php               login email dan password
forgot.php              reset password
logout.php              logout
dashboard.php           ringkasan dan peringatan gangguan
portal.php              Portal Merchant
profile.php             profil, foto, dan ganti password
tickets/                CRUD tiket
knowledge/              CRUD Knowledge Base dan endpoint saran panduan
followups/              CRUD riwayat tindak lanjut dan balasan merchant
ai/analyze.php          endpoint fitur AI
auth/social.php         verifikasi login Google dan GitHub
admin/                  analitik, ringkasan harian, manajemen pengguna
cron/daily.php          endpoint ringkasan harian otomatis
includes/               fungsi Firebase, Gemini, helper, layout, dan form
assets/style.css        tampilan
database.rules.json     Security Rules Realtime Database
config.example.php      contoh konfigurasi
Dockerfile, render.yaml konfigurasi deploy
```

Isi folder `includes/`:

| File | Isi |
| --- | --- |
| `bootstrap.php` | Memuat konfigurasi dan memulai session |
| `firebase.php` | Fungsi REST untuk Authentication dan Realtime Database |
| `gemini.php` | Pemanggilan Gemini dan semua fungsi AI |
| `helpers.php` | Role, validasi password dan akun, serta pembacaan data sesuai role |
| `mask.php` | Penyamaran data sensitif |
| `similar.php` | Penilaian kemiripan dan pencarian tiket serupa |
| `kb_suggest.php` | Pencocokan keluhan dengan artikel Knowledge Base |
| `analytics.php` | Perhitungan analitik, SLA, dan ringkasan harian |
| `ticket_form.php`, `followup_form.php`, `kb_form.php` | Form dan validasi input |
| `ai_panel.php` | Panel hasil AI pada halaman tiket |
| `social_login.php` | Tombol dan skrip login Google dan GitHub |
| `layout.php` | Kerangka halaman dan menu |
| `constants.php` | Daftar kategori, prioritas, status, role, dan batas SLA |

## Struktur data

```
users/{uid}                          name, email, role, account_type, organization, photo, created_at
tickets/{id}                         title, description, category, payment_method, transaction_id, amount,
                                     priority, status, owner_uid, owner_email, owner_name, owner_type,
                                     owner_org, created_at, updated_at
tickets/{id}/ai                      category, priority, priority_reason, extracted, missing_fields, analyzed_at
tickets/{id}/ai_reply                summary, suggested_reply, related_kb_ids, summarized_at
tickets/{id}/ai_similar              results, checked_at
tickets/{id}/possible_duplicate_of   daftar ID tiket serupa
tickets/{id}/escalated               true jika dieskalasi (dengan escalated_at)
ticket_files/{ticketId}/{id}         name, data, owner_uid, created_at
knowledge_base/{id}                  title, category, keywords, content, author_uid, author_email, created_at, updated_at
followups/{ticketId}/{id}            type, content, author_uid, author_name, author_role, visible_to_merchant,
                                     created_at, updated_at, edited_at
daily_summaries/{YYYY-MM-DD}         summary, highlights, recommendations, stats, generated_at, generated_by
```

## Keamanan

- Kunci API hanya ada di `config.local.php` atau Environment Variables di server dan tidak dikirim ke browser.
- Semua form memakai token CSRF, dan semua keluaran di-escape.
- Data sensitif disamarkan sebelum disimpan dan sebelum dikirim ke Gemini.
- Security Rules: merchant hanya dapat membaca tiket dan lampiran miliknya lewat query `owner_uid`, catatan internal tidak dapat dibaca merchant, dan ringkasan harian hanya dapat dibaca admin.
- Login Google dan GitHub: token dari browser diverifikasi ulang ke Firebase di server, dan hanya email terverifikasi yang diterima.

## Catatan

- Firebase Hosting tidak dapat menjalankan PHP, sehingga website di-deploy dengan Docker di Render.
- Lampiran disimpan di Realtime Database, bukan Firebase Storage, karena Cloud Storage for Firebase mewajibkan paket Blaze berbayar.
- Nama model Gemini sering berganti. Atur `GEMINI_MODEL` jika perlu. Jika model tidak ditemukan, kode mencoba model cadangan.
- Pembacaan gambar bukti oleh AI belum dikerjakan. Saat ini AI hanya membaca teks keluhan.
