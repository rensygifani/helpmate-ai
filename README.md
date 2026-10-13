# HelpMate AI

HelpMate AI adalah website helpdesk untuk layanan payment gateway. Merchant bisa melaporkan kendala transaksi sebagai tiket, lalu petugas (CS dan admin) menanganinya. Proyek ini dibuat untuk tugas ALP Modul 4 mata kuliah Cloud Computing.

Dibuat oleh:
- Rensy Indra Gifani
- Gherald Giovanny Marcello Sumual

Semua data yang dipakai di website ini hanya data contoh (dummy).

## Latar belakang singkat

Penanganan kendala payment gateway sering masih lewat WhatsApp, email, dan spreadsheet. Akibatnya laporan tercecer, data laporan sering tidak lengkap, dan satu masalah bisa dilaporkan oleh beberapa orang sekaligus. HelpMate AI dibuat supaya laporan terkumpul di satu tempat dan statusnya bisa dipantau.

## Fitur yang sudah ada (Checkpoint 1)

- Register dan login memakai Firebase Authentication
- Email verifikasi setelah register
- CRUD Tiket Kendala (tambah, lihat, ubah, hapus) ke Firebase Realtime Database
- CRUD Knowledge Base atau panduan solusi (tambah, lihat, ubah, hapus) ke Firebase Realtime Database
- Pembagian role: merchant, cs, tim_teknis, dan admin
- Dashboard ringkasan jumlah tiket dan artikel

## Rencana fitur berikutnya

- CRUD Riwayat Tindak Lanjut (Checkpoint 2)
- Integrasi Gemini API untuk membaca keluhan, menentukan kategori dan prioritas, serta membuat draf balasan (Checkpoint 2)
- Upload lampiran, deteksi tiket serupa, ringkasan harian, dan pengetatan Security Rules (Checkpoint 3)

## Teknologi

- PHP 8 atau lebih baru (ekstensi cURL harus aktif), tanpa framework dan tanpa Composer
- Firebase Authentication
- Firebase Realtime Database
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
4. Salin `config.example.php` menjadi `config.local.php`, lalu isi `FIREBASE_API_KEY` dan `FIREBASE_DB_URL`.
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
```

## Catatan

- File `config.local.php` berisi kunci Firebase dan sudah dimasukkan ke `.gitignore`, jadi jangan di-upload ke GitHub.
- Aturan database saat ini masih sederhana. Semua user yang sudah login bisa membaca data tiket, tetapi di website tiket merchant sudah difilter supaya hanya miliknya yang tampil. Aturannya akan diperketat di checkpoint berikutnya.
- Firebase Hosting tidak bisa menjalankan PHP, jadi untuk deploy akhir dipakai hosting lain yang mendukung PHP.
