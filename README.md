# HelpMate AI

HelpMate AI adalah website helpdesk untuk layanan payment gateway. Merchant bisa melaporkan kendala transaksi sebagai tiket, lalu petugas (CS, tim teknis, dan admin) menanganinya dengan bantuan AI (Gemini). Proyek ini dibuat untuk tugas ALP mata kuliah Cloud Computing.

Dibuat oleh:
- Rensy Indra Gifani
- Gherald Giovanny Marcello Sumual

Semua data yang dipakai di website ini hanya data contoh (dummy).

## Latar belakang singkat

Penanganan kendala payment gateway sering masih lewat WhatsApp, email, dan spreadsheet. Akibatnya laporan tercecer, data laporan sering tidak lengkap, dan satu masalah bisa dilaporkan oleh beberapa orang sekaligus. HelpMate AI dibuat supaya laporan terkumpul di satu tempat, statusnya bisa dipantau, dan AI membantu petugas membaca keluhan dengan lebih cepat.

## Fitur yang sudah ada

Checkpoint 1:
- Register dan login memakai Firebase Authentication
- Email verifikasi setelah register
- CRUD Tiket Kendala (tambah, lihat, ubah, hapus)
- CRUD Knowledge Base atau panduan solusi (tambah, lihat, ubah, hapus)
- Pembagian role: merchant, cs, tim_teknis, admin, dan super_admin
- Dashboard ringkasan jumlah tiket dan artikel
- Lampiran bukti pada tiket (maksimal 3 gambar, dikecilkan di browser dan divalidasi di server)

Checkpoint 2:
- CRUD Riwayat Tindak Lanjut pada setiap tiket (tambah, lihat, ubah, hapus). Hanya petugas yang bisa menambah, dan hanya penulis catatan atau admin yang bisa mengubah dan menghapus. Jenis tindakan "selesai" sekaligus mengubah status tiket menjadi Selesai.
- Integrasi Gemini API dengan function calling, dipanggil dari server PHP:
  - Ekstraksi data transaksi dari keluhan bebas (ID transaksi, nominal, metode bayar, waktu kejadian, kode error)
  - Deteksi data yang masih kurang, penentuan kategori, dan prioritas
  - Ringkasan kronologi, draf balasan untuk merchant, dan artikel Knowledge Base yang terkait
- Penyamaran data sensitif (versi dasar: nomor kartu, email, dan telepon) sebelum teks dikirim ke Gemini
- Hasil AI hanya berupa saran. Petugas memeriksanya dan menekan tombol Terapkan sebelum kategori dan prioritas tiket berubah.
- Aturan keamanan (Security Rules) untuk node `followups`

## Rencana fitur berikutnya (Checkpoint 3 dan akhir)

- Deteksi tiket serupa, saran panduan sebelum tiket dikirim, dan ringkasan harian
- Penyamaran data sensitif yang lebih lengkap
- Portal merchant, dashboard admin, dan analitik
- Pengetatan Security Rules untuk node `tickets` dan `ticket_files`
- Deploy ke hosting yang mendukung PHP

## Pembagian pekerjaan

| Bagian | Rensy | Gherald |
|---|---|---|
| Tiket Kendala | CRUD Tiket, lampiran | - |
| Knowledge Base | - | CRUD Knowledge Base |
| Riwayat Tindak Lanjut | Tambah dan lihat | Ubah dan hapus |
| AI | Ekstraksi, deteksi data kurang, kategori, prioritas, penyamaran data | Ringkasan, draf balasan, artikel terkait |
| Keamanan | - | Security Rules `followups` |

## Teknologi

- PHP 8 atau lebih baru (ekstensi cURL harus aktif), tanpa framework dan tanpa Composer
- Firebase Authentication
- Firebase Realtime Database
- Gemini API (REST, function calling)
- HTML dan CSS biasa

Koneksi ke Firebase memakai REST API lewat cURL. Semua fungsinya ada di `includes/firebase.php`. Pemanggilan Gemini ada di `includes/gemini.php` dan dilakukan dari server PHP, sehingga API key tidak pernah sampai ke browser dan tidak disimpan di GitHub.

## Cara menjalankan

1. Pastikan PHP 8 sudah terpasang dan ekstensi cURL aktif (kalau pakai XAMPP, cek `extension=curl` di `php.ini`).
2. Buat project di Firebase Console, lalu:
   - Buka Authentication, aktifkan metode sign-in Email/Password.
   - Buka Realtime Database, buat database dan pilih locked mode.
   - Di tab Rules pada Realtime Database, tempel isi file `database.rules.json`, lalu klik Publish.
3. Ambil data dari Firebase dan Google AI Studio:
   - Web API Key Firebase: Project settings, tab General.
   - Alamat database: bagian atas halaman Realtime Database.
   - API key Gemini: https://aistudio.google.com
4. Salin `config.example.php` menjadi `config.local.php`, lalu isi `FIREBASE_API_KEY`, `FIREBASE_DB_URL`, dan `GEMINI_API_KEY`. `GEMINI_MODEL` bersifat opsional (nama model dapat diganti kalau Google mengubah daftar model).
5. Jalankan perintah ini di folder proyek:
   ```
   php -S localhost:8000
   ```
6. Buka `http://localhost:8000` di browser.

File `config.local.php` sudah ada di `.gitignore`. Jangan pernah mengunggahnya ke GitHub.

## Model Gemini dan model cadangan

Nama model Gemini sering diganti oleh Google. Karena itu model utama dibaca dari `GEMINI_MODEL` di konfigurasi. Kalau model utama tidak tersedia (404) atau sedang sibuk (503), fungsi `gemini_call` mencoba ulang sekali, lalu otomatis memakai model cadangan.

## Membuat akun CS atau admin

Semua akun yang baru register otomatis menjadi merchant, supaya pengguna tidak bisa mendaftar langsung sebagai petugas. Cara mengubah role:

1. Register dan verifikasi email seperti biasa.
2. Buka Firebase Console, masuk ke Realtime Database, lalu cari node `users` dan pilih ID akunnya.
3. Ubah nilai `role` menjadi `cs`, `tim_teknis`, `admin`, atau `super_admin`.
4. Logout, lalu login lagi.

Akun `super_admin` juga dapat melihat halaman Pengguna (`admin/users.php`).

Hak akses:
- Petugas (`cs`, `tim_teknis`, `admin`, `super_admin`): melihat semua tiket, menambah tindak lanjut, dan memakai fitur AI.
- `cs`, `admin`, dan `super_admin`: mengelola artikel Knowledge Base.
- Merchant: hanya melihat tiketnya sendiri beserta riwayat tindak lanjutnya. Merchant tidak melihat panel AI.

## Struktur folder

```
index.php              pengarah ke login atau dashboard
register.php           halaman register
login.php              halaman login
logout.php             proses logout
profile.php            halaman profil pengguna
dashboard.php          halaman ringkasan
tickets/               CRUD tiket (create, index, view, edit, delete)
knowledge/             CRUD Knowledge Base (create, index, view, edit, delete)
followups/             CRUD Riwayat Tindak Lanjut (create, edit, delete)
ai/                    endpoint AI (analyze.php: mode extract, summary, apply)
admin/                 halaman pengguna (khusus super_admin)
includes/              fungsi Firebase, Gemini, helper, layout, dan form
assets/style.css       tampilan
database.rules.json    aturan Realtime Database
config.example.php     contoh file konfigurasi (tanpa kunci)
```

Berkas penting di `includes/`:
- `firebase.php`: fungsi `db_get`, `db_post`, `db_put`, `db_patch`, dan `db_delete`
- `gemini.php`: `gemini_call`, `ai_extract_classify`, `ai_summarize_reply`, dan `mask_sensitive`
- `followup_form.php`: validasi input, aturan hak akses, dan tampilan Riwayat Tindak Lanjut
- `ai_panel.php`: tampilan panel AI pada detail tiket
- `kb_form.php` dan `ticket_form.php`: validasi form Knowledge Base dan tiket

## Letak fungsi CRUD

Tiket (node `tickets`):
- Create: `tickets/create.php`
- Read: `tickets/index.php` dan `tickets/view.php`
- Update: `tickets/edit.php`
- Delete: `tickets/delete.php`

Knowledge Base (node `knowledge_base`):
- Create: `knowledge/create.php`
- Read: `knowledge/index.php` dan `knowledge/view.php`
- Update: `knowledge/edit.php`
- Delete: `knowledge/delete.php`

Riwayat Tindak Lanjut (node `followups`):
- Create: `followups/create.php`
- Read: `includes/followup_form.php` (fungsi `render_followups`) yang ditampilkan di `tickets/view.php`
- Update: `followups/edit.php`
- Delete: `followups/delete.php`

Kalau sebuah tiket dihapus, lampiran dan riwayat tindak lanjutnya ikut dihapus lewat `tickets/delete.php`.

## Fitur AI

Alur singkat: petugas membuka detail tiket, menekan tombol Analisis keluhan atau Ringkasan dan draf balasan, lalu `ai/analyze.php` memeriksa CSRF dan role petugas. Endpoint ini memanggil fungsi AI, dan hasilnya disimpan ke Realtime Database lalu ditampilkan di halaman tiket.

- Mode `extract`: menjalankan `ai_extract_classify`, hasilnya disimpan di `tickets/{id}/ai`.
- Mode `summary`: menjalankan `ai_summarize_reply`, hasilnya disimpan di `tickets/{id}/ai_reply`.
- Mode `apply`: menerapkan kategori dan prioritas saran AI ke tiket, setelah petugas menekan tombol Terapkan.

Pengamanan pada fitur AI:
- Gemini dipaksa menjawab lewat function calling, sehingga hasilnya selalu berupa JSON terstruktur.
- Hasil divalidasi di server sebelum disimpan. Kategori dan prioritas harus ada dalam daftar yang valid, dan ID artikel Knowledge Base harus benar-benar ada.
- Nomor kartu, email, dan nomor telepon disamarkan sebelum teks dikirim ke Gemini.
- Draf balasan tidak terkirim otomatis ke merchant. Petugas memeriksa dan menyuntingnya terlebih dahulu.
- Saat ini AI hanya membaca teks keluhan. Gambar lampiran tidak dibaca AI.

## Security Rules

Aturan ada di `database.rules.json`. Untuk node `followups`:
- Hanya pemilik tiket dan petugas yang boleh membaca riwayat sebuah tiket.
- Hanya petugas yang boleh menulis catatan.
- Isi catatan dibatasi 2000 karakter, dan `author_uid` harus sama dengan pengguna yang menulis (tidak bisa dipalsukan).

Aturan untuk `tickets` dan `ticket_files` masih sederhana (pengguna yang login dapat menulis, pembatasan per pemilik baru dilakukan di aplikasi) dan dijadwalkan diperketat pada Checkpoint 3.

Setiap kali `database.rules.json` berubah, salin isinya ke tab Rules di Firebase Console lalu klik Publish. Perubahan di GitHub saja belum mengaktifkan aturan tersebut.

## Struktur data di Realtime Database

```
users/{uid}                  name, email, role, account_type, organization, created_at
tickets/{id}                 title, description, category, payment_method, transaction_id, amount,
                             priority, status, owner_uid, owner_email, owner_name, owner_type,
                             owner_org, created_at, updated_at
tickets/{id}/ai              category, priority, priority_reason,
                             extracted (transaction_id, amount, payment_method, event_time, error_code),
                             missing_fields, analyzed_at
tickets/{id}/ai_reply        summary, suggested_reply, related_kb_ids, summarized_at
ticket_files/{ticketId}/{id} name, data, owner_uid, created_at
followups/{ticketId}/{id}    type, content, author_uid, author_name, author_role,
                             created_at, updated_at, edited_at
knowledge_base/{id}          title, category, keywords, content, author_uid, author_email,
                             created_at, updated_at
```

## Keamanan kunci

- `config.local.php` berisi kunci Firebase dan Gemini, dan tidak diunggah ke GitHub karena ada di `.gitignore`.
- Kunci Gemini hanya dibaca di server PHP dan tidak dikirim ke browser.
