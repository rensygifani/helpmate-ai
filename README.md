# HelpMate AI

HelpMate AI adalah website helpdesk untuk layanan payment gateway. Merchant bisa melaporkan kendala transaksi sebagai tiket, lalu petugas (CS dan admin) menanganinya. Proyek ini dibuat untuk tugas ALP Modul 4 mata kuliah Cloud Computing.

Dibuat oleh:
- Rensy Indra Gifani
- Gherald Giovanny Marcello Sumual

Semua data yang dipakai di website ini hanya data contoh (dummy).

## Latar belakang singkat

Penanganan kendala payment gateway sering masih lewat WhatsApp, email, dan spreadsheet. Akibatnya laporan tercecer, data laporan sering tidak lengkap, dan satu masalah bisa dilaporkan oleh beberapa orang sekaligus. HelpMate AI dibuat supaya laporan terkumpul di satu tempat dan statusnya bisa dipantau.

## Fitur yang sudah ada (Checkpoint 1 dan 2)

- Register dan login memakai Firebase Authentication
- Email verifikasi setelah register
- CRUD Tiket Kendala (tambah, lihat, ubah, hapus) ke Firebase Realtime Database
- CRUD Knowledge Base atau panduan solusi (tambah, lihat, ubah, hapus) ke Firebase Realtime Database
- Pembagian role: merchant, cs, tim_teknis, dan admin
- Dashboard ringkasan jumlah tiket dan artikel
- CRUD Riwayat Tindak Lanjut per tiket (tambah, lihat, ubah, hapus) ke Firebase Realtime Database (Checkpoint 2)
- Fitur AI dengan Gemini API (Checkpoint 2): ekstraksi data transaksi dari keluhan, deteksi data yang kurang, klasifikasi kategori, saran prioritas, ringkasan kronologi, draf balasan, dan artikel Knowledge Base terkait. Hasil AI hanya saran, petugas yang menerapkannya
- Penyamaran nomor kartu, email, dan telepon sebelum teks dikirim ke Gemini

## Rencana fitur berikutnya

- Upload lampiran, deteksi tiket serupa, ringkasan harian, dan pengetatan Security Rules (Checkpoint 3)

## Teknologi

- PHP 8 atau lebih baru (ekstensi cURL harus aktif), tanpa framework dan tanpa Composer
- Firebase Authentication
- Firebase Realtime Database
- Gemini API (function calling, dipanggil dari server PHP)
- HTML dan CSS biasa

Koneksi ke Firebase memakai REST API lewat cURL. Semua fungsinya ada di `includes/firebase.php`.

## Cara menjalankan

1. Pastikan PHP 8 sudah terpasang dan ekstensi cURL aktif (kalau pakai XAMPP, cek `extension=curl` di `php.ini`).
2. Buat project di Firebase Console, lalu:
   - Buka Authentication, aktifkan metode sign-in Email/Password.
   - Buka Realtime Database, buat database dan pilih locked mode.
   - Di tab Rules pada Realtime Database, tempel isi file `database.rules.json`, lalu klik Publish.
3. Ambil dua data dari Firebase:
   - Web API Key: Project settings, tab General.
   - Alamat database: bagian atas halaman Realtime Database.
4. Salin `config.example.php` menjadi `config.local.php`, lalu isi `FIREBASE_API_KEY` dan `FIREBASE_DB_URL`. Untuk fitur AI, buat API key di Google AI Studio lalu isi `GEMINI_API_KEY`.
5. Jalankan perintah ini di folder proyek:
   ```
   php -S localhost:8000
   ```
6. Buka `http://localhost:8000` di browser.

## Membuat akun CS atau admin

Semua akun yang baru register otomatis menjadi merchant. Role tidak bisa diubah dari website supaya tidak disalahgunakan. Cara mengubahnya:

1. Register dan verifikasi email seperti biasa.
2. Buka Firebase Console, masuk ke Realtime Database, lalu cari node `users` dan pilih ID akunnya.
3. Ubah nilai `role` menjadi `cs` atau `admin`.
4. Logout, lalu login lagi.

Hanya role `cs` dan `admin` yang bisa menambah, mengubah, dan menghapus artikel Knowledge Base.

## Struktur folder

```
index.php              pengarah ke login atau dashboard
register.php           halaman register
login.php              halaman login
logout.php             proses logout
dashboard.php          halaman ringkasan
tickets/               CRUD tiket (create, index, view, edit, delete)
knowledge/             CRUD Knowledge Base (create, index, view, edit, delete)
includes/              fungsi Firebase, helper, layout, dan form
assets/style.css       tampilan
database.rules.json    aturan Realtime Database
config.example.php     contoh file konfigurasi
```

## Letak fungsi CRUD

Tiket (node `tickets`):
- Create: `tickets/create.php`
- Read: `tickets/index.php` dan `tickets/view.php`
- Update: `tickets/edit.php`
- Delete: `tickets/delete.php`

Riwayat Tindak Lanjut (node `followups/{ticketId}`):
- Create: `followups/create.php` (Rensy)
- Read: daftar riwayat pada `tickets/view.php`, fungsi `render_followups` di `includes/followup_form.php` (Rensy)
- Update: `followups/edit.php` (Gherald)
- Delete: `followups/delete.php` (Gherald)

Fitur AI:
- Ekstraksi, klasifikasi, dan prioritas: `ai_extract_classify` di `includes/gemini.php` (Rensy)
- Ringkasan, draf balasan, dan artikel terkait: `ai_summarize_reply` di `includes/gemini.php` (Gherald)
- Endpoint dan tombol: `ai/analyze.php`, `includes/ai_panel.php`

Knowledge Base (node `knowledge_base`):
- Create: `knowledge/create.php`
- Read: `knowledge/index.php` dan `knowledge/view.php`
- Update: `knowledge/edit.php`
- Delete: `knowledge/delete.php`

## Struktur data di Realtime Database

```
users/{uid}            name, email, role, created_at
tickets/{id}           title, description, category, payment_method, transaction_id, amount,
                       priority, status, owner_uid, owner_email, owner_name, created_at, updated_at
knowledge_base/{id}    title, category, keywords, content, author_uid, author_email, created_at, updated_at
followups/{ticketId}/{id}  type, content, author_uid, author_name, author_role, created_at, updated_at, edited_at
tickets/{id}/ai        category, priority, priority_reason, extracted, missing_fields, analyzed_at
tickets/{id}/ai_reply  summary, suggested_reply, related_kb_ids, summarized_at
```

## Catatan

- File `config.local.php` berisi kunci Firebase dan sudah dimasukkan ke `.gitignore`, jadi jangan di-upload ke GitHub.
- Aturan database saat ini masih sederhana. Semua user yang sudah login bisa membaca data tiket, tetapi di website tiket merchant sudah difilter supaya hanya miliknya yang tampil. Aturannya akan diperketat di checkpoint berikutnya.
- Panggilan Gemini dilakukan dari server PHP (bukan Cloud Function) supaya tetap satu aplikasi dan kunci tetap di config.local.php. Kunci tidak pernah dikirim ke browser.
- Nama model Gemini sering berganti. Atur `GEMINI_MODEL` di `config.local.php`; jika model dijawab 404, kode otomatis mencoba model cadangan. Cek daftar model terbaru di https://ai.google.dev/gemini-api/docs/models.
- Firebase Hosting tidak bisa menjalankan PHP, jadi untuk deploy akhir dipakai hosting lain yang mendukung PHP.
