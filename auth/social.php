<?php

// Menerima ID token dari login Google atau GitHub, memverifikasinya ke Firebase, lalu membuat sesi pengguna.
// Pengguna baru otomatis berrole merchant (sama seperti register). Role petugas hanya diberikan manual oleh Super Admin.
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function social_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    social_fail('Metode tidak diizinkan.', 405);
}
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    social_fail('Permintaan tidak valid.');
}
$csrf = $payload['_csrf'] ?? '';
if (!is_string($csrf) || !hash_equals(csrf_token(), $csrf)) {
    social_fail('Token CSRF salah. Muat ulang halaman lalu coba lagi.', 419);
}
$idToken = $payload['idToken'] ?? '';
$refresh = $payload['refreshToken'] ?? '';
if (!is_string($idToken) || $idToken === '' || strlen($idToken) > 4096 || !is_string($refresh) || strlen($refresh) > 4096) {
    social_fail('Token tidak valid.');
}

// Verifikasi token langsung ke Firebase, bukan memercayai data dari browser
$lookup = firebase_auth('lookup', ['idToken' => $idToken]);
$firebaseUser = $lookup['ok'] ? ($lookup['data']['users'][0] ?? null) : null;
if (!is_array($firebaseUser) || empty($firebaseUser['localId'])) {
    social_fail('Token tidak dapat diverifikasi. Coba login lagi.', 401);
}
if (!empty($firebaseUser['disabled'])) {
    social_fail('Akun ini dinonaktifkan.', 403);
}
$providers = array_column((array) ($firebaseUser['providerUserInfo'] ?? []), 'providerId');
if (!array_intersect($providers, ['google.com', 'github.com'])) {
    social_fail('Metode login tidak didukung.', 403);
}
$uid = (string) $firebaseUser['localId'];
$email = strtolower((string) ($firebaseUser['email'] ?? ''));
if ($email === '' || empty($firebaseUser['emailVerified'])) {
    social_fail('Email akun belum terverifikasi atau tidak tersedia. Jadikan email Anda publik dan terverifikasi di penyedia login, atau daftar dengan email biasa.', 403);
}

// Ambil profil; buat profil merchant jika belum ada
$profileResponse = db_get('users/' . $uid, $idToken);
if (!$profileResponse['ok']) {
    social_fail('Gagal membaca profil: ' . $profileResponse['error'], 502);
}
$data = is_array($profileResponse['data']) ? $profileResponse['data'] : null;
if ($data === null) {
    $name = trim((string) ($firebaseUser['displayName'] ?? '')) ?: explode('@', $email)[0];
    $data = [
        'name' => mb_substr($name, 0, 80),
        'email' => $email,
        'role' => 'merchant',
        'account_type' => '',
        'organization' => '',
        'provider' => $providers[0] ?? '',
        'created_at' => now_string(),
    ];
    $saveResponse = db_put('users/' . $uid, $data, $idToken);
    if (!$saveResponse['ok']) {
        social_fail('Akun dibuat tetapi profil gagal disimpan: ' . $saveResponse['error'], 502);
    }
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'uid' => $uid,
    'email' => $email,
    'name' => (string) ($data['name'] ?? $email),
    'role' => (string) ($data['role'] ?? 'merchant'),
    'account_type' => (string) ($data['account_type'] ?? ''),
    'organization' => (string) ($data['organization'] ?? ''),
    'photo' => (string) ($data['photo'] ?? ''),
    'idToken' => $idToken,
    'refreshToken' => $refresh,
    'expires_at' => time() + 3000,
];
flash('success', 'Selamat datang, ' . $_SESSION['user']['name'] . '.');
echo json_encode(['ok' => true, 'redirect' => url('dashboard.php')]);
