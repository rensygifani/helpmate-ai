# Mengaktifkan Login dengan Google dan GitHub

Fitur ini memakai Firebase Authentication (gratis di paket Spark). Popup login berjalan di browser lewat Firebase JS SDK,
lalu server PHP memverifikasi token ke Firebase sebelum membuat sesi.

## 1. Google
1. Firebase Console, **Authentication**, tab **Sign-in method**, **Add new provider**, pilih **Google**.
2. Aktifkan (Enable), isi **Project support email**, lalu **Save**.

## 2. GitHub
1. Di Firebase Console, **Sign-in method**, **Add new provider**, pilih **GitHub**, aktifkan. Salin **Authorization callback URL**
   (bentuknya `https://NAMA-PROJECT.firebaseapp.com/__/auth/handler`). Jangan ditutup dulu.
2. Buka https://github.com/settings/developers, **OAuth Apps**, **New OAuth App**.
   - Application name: HelpMate AI
   - Homepage URL: `http://localhost:8000` (ganti dengan alamat Render setelah deploy)
   - Authorization callback URL: tempel URL dari langkah 1
3. Setelah dibuat, salin **Client ID**, lalu **Generate a new client secret** dan salin nilainya.
4. Kembali ke Firebase, tempel **Client ID** dan **Client secret**, lalu **Save**.

## 3. Authorized domains
Firebase Console, **Authentication**, **Settings**, **Authorized domains**. `localhost` sudah ada. Setelah deploy,
tambahkan domain hosting (contoh: `helpmate-ai.onrender.com`).

## 4. Catatan
- Pengguna baru dari Google atau GitHub otomatis berrole **merchant**. Role petugas diberikan Super Admin lewat menu Pengguna.
- Hanya email yang **terverifikasi** yang diterima. Akun GitHub tanpa email publik terverifikasi akan ditolak.
- Jika email yang sama sudah terdaftar dengan metode lain, Firebase menolak dan pengguna diminta memakai metode sebelumnya.
- `FIREBASE_AUTH_DOMAIN` (opsional) di `config.local.php` hanya diisi bila domain Auth berbeda dari `NAMA-PROJECT.firebaseapp.com`.
- Versi SDK diatur lewat `FIREBASE_JS_VERSION` (bawaan 10.14.1). Ubah jika versi itu tidak dapat dimuat.
