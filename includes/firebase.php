<?php
// File ini berisi fungsi untuk menghubungi Firebase lewat REST API memakai cURL,
// jadi tidak perlu install library tambahan.
// Authentication memakai Identity Toolkit, Realtime Database memakai token milik user yang login.

function http_request(string $method, string $url, ?string $payload = null, array $headers = []): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
    ];
    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = $payload;
    }
    curl_setopt_array($ch, $opts);
    $raw       = curl_exec($ch);
    $status    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => $curlError ?: 'Koneksi ke Firebase gagal'];
    }

    $data  = json_decode($raw, true);
    $error = null;
    if (is_array($data) && isset($data['error'])) {
        $error = is_array($data['error']) ? (string) ($data['error']['message'] ?? 'Error') : (string) $data['error'];
    }
    $ok = $status >= 200 && $status < 300;
    if (!$ok && $error === null) {
        $error = 'HTTP ' . $status;
    }
    return ['ok' => $ok, 'status' => $status, 'data' => $data, 'error' => $error];
}

function http_json(string $method, string $url, ?array $body = null): array
{
    $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE);
    return http_request($method, $url, $payload, $payload === null ? [] : ['Content-Type: application/json']);
}

// Firebase Authentication

function firebase_auth(string $endpoint, array $body): array
{
    $base = rtrim((string) (cfg('FIREBASE_AUTH_BASE') ?: 'https://identitytoolkit.googleapis.com/v1'), '/');
    $url = $base . '/accounts:' . $endpoint . '?key=' . urlencode((string) cfg('FIREBASE_API_KEY'));
    return http_json('POST', $url, $body);
}

function auth_error_text(?string $code): string
{
    $code = (string) $code;
    $map = [
        'EMAIL_EXISTS'                => 'Email sudah terdaftar.',
        'INVALID_LOGIN_CREDENTIALS'   => 'Email atau password salah.',
        'INVALID_PASSWORD'            => 'Email atau password salah.',
        'EMAIL_NOT_FOUND'             => 'Email atau password salah.',
        'USER_DISABLED'               => 'Akun ini dinonaktifkan.',
        'WEAK_PASSWORD'               => 'Password terlalu lemah. Gunakan minimal 8 karakter dengan huruf kapital, huruf kecil, angka, dan karakter khusus.',
        'TOO_MANY_ATTEMPTS_TRY_LATER' => 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.',
        'INVALID_EMAIL'               => 'Format email tidak valid.',
    ];
    foreach ($map as $key => $text) {
        if (str_starts_with($code, $key)) {
            return $text;
        }
    }
    return 'Terjadi kesalahan: ' . ($code !== '' ? $code : 'tidak diketahui');
}

// Ambil token user yang sedang login. Kalau sudah hampir kedaluwarsa, token diperbarui dulu.
function current_token(): ?string
{
    if (empty($_SESSION['user'])) {
        return null;
    }
    if (time() >= $_SESSION['user']['expires_at'] - 60) {
        $payload = http_build_query([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $_SESSION['user']['refreshToken'],
        ]);
        $r = http_request(
            'POST',
            'https://securetoken.googleapis.com/v1/token?key=' . urlencode((string) cfg('FIREBASE_API_KEY')),
            $payload,
            ['Content-Type: application/x-www-form-urlencoded']
        );
        if (!$r['ok'] || empty($r['data']['id_token'])) {
            unset($_SESSION['user']);
            return null;
        }
        $_SESSION['user']['idToken']      = $r['data']['id_token'];
        $_SESSION['user']['refreshToken'] = $r['data']['refresh_token'] ?? $_SESSION['user']['refreshToken'];
        $_SESSION['user']['expires_at']   = time() + (int) ($r['data']['expires_in'] ?? 3600);
    }
    return $_SESSION['user']['idToken'];
}

// Realtime Database

function db_url(string $path, ?string $token, array $query = []): string
{
    $base = rtrim((string) cfg('FIREBASE_DB_URL'), '/');
    $qs   = ['auth' => (string) $token];
    foreach ($query as $key => $value) {
        $qs[$key] = json_encode($value);   // contoh: orderBy="owner_uid", equalTo="abc"
    }
    return $base . '/' . trim($path, '/') . '.json?' . http_build_query($qs);
}

// Read: ambil data dari sebuah path. $query dipakai untuk filter di sisi Firebase,
// contoh: ['orderBy' => 'owner_uid', 'equalTo' => $uid]
function db_get(string $path, ?string $token = null, array $query = []): array
{
    return http_json('GET', db_url($path, $token ?? current_token(), $query));
}

// Create: tambah data baru, ID dibuat otomatis oleh Firebase (ID-nya ada di data['name'])
function db_post(string $path, array $data, ?string $token = null): array
{
    return http_json('POST', db_url($path, $token ?? current_token()), $data);
}

// Tulis data ke path tertentu, isi lama diganti semua
function db_put(string $path, array $data, ?string $token = null): array
{
    return http_json('PUT', db_url($path, $token ?? current_token()), $data);
}

// Update: ubah sebagian field saja, field lain tidak hilang
function db_patch(string $path, array $data, ?string $token = null): array
{
    return http_json('PATCH', db_url($path, $token ?? current_token()), $data);
}

// Delete: hapus data di path tersebut
function db_delete(string $path, ?string $token = null): array
{
    return http_json('DELETE', db_url($path, $token ?? current_token()));
}
