# Panduan Deploy HelpMate AI

Firebase Hosting tidak dapat menjalankan PHP, jadi website dijalankan di hosting yang mendukung Docker (contoh: Render).
Firebase tetap dipakai untuk Authentication dan Realtime Database.

## 1. Siapkan Firebase
1. Realtime Database: tab **Rules**, tempel isi `database.rules.json`, lalu **Publish**.
2. Pastikan ada akun Super Admin di node `users` (role `super_admin`).

## 2. Deploy ke Render
1. Push repository ke GitHub.
2. Buka https://render.com, **New +**, **Web Service**, pilih repository, **Runtime: Docker**.
3. Isi **Environment Variables**: `FIREBASE_API_KEY`, `FIREBASE_DB_URL`, `GEMINI_API_KEY`, `GEMINI_MODEL` (gemini-3.8-flash).
4. **Create Web Service**. Tunggu build selesai lalu buka alamat `https://NAMA.onrender.com`.
5. Paket gratis Render tidur setelah beberapa menit tidak dipakai; akses pertama bisa lambat. Buka situs sekali sebelum demo.

## 3. Login Google dan GitHub
Tambahkan domain Render ke **Authorized domains** di Firebase Authentication dan ubah Homepage URL pada GitHub OAuth App. Langkah lengkap ada di `SOCIAL_LOGIN.md`.

## 4. Ringkasan harian otomatis (opsional)
1. Buat akun Firebase dengan role `admin` (lewat menu Pengguna), lalu isi `CRON_EMAIL`, `CRON_PASSWORD`, dan `CRON_TOKEN` (kata acak panjang) di Environment Variables.
2. Buka https://cron-job.org (gratis), buat cron baru dengan alamat `https://NAMA.onrender.com/cron/daily.php?token=ISI_CRON_TOKEN`, jadwal setiap hari pukul 07.00.
3. Hasilnya muncul di menu Analitik, lalu Ringkasan harian.

## 5. Uji lokal dengan Docker (opsional)
```
docker build -t helpmate-ai .
docker run -p 8080:10000 -e FIREBASE_API_KEY=... -e FIREBASE_DB_URL=... -e GEMINI_API_KEY=... helpmate-ai
```
Buka http://localhost:8080.
