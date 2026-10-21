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
$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    social_fail('Permintaan tidak valid.');
}
$csrf = $in['_csrf'] ?? '';
if (!is_string($csrf) || !hash_equals(csrf_token(), $csrf)) {
    social_fail('Token CSRF salah. Muat ulang halaman lalu coba lagi.', 419);
}
$idToken = $in['idToken'] ?? '';
$refresh = $in['refreshToken'] ?? '';
if (!is_string($idToken) || $idToken === '' || strlen($idToken) > 4096 || !is_string($refresh) || strlen($refresh) > 4096) {
    social_fail('Token tidak valid.');
}

// Verifikasi token langsung ke Firebase, bukan memercayai data dari browser
$lk = firebase_auth('lookup', ['idToken' => $idToken]);
$u  = $lk['ok'] ? ($lk['data']['users'][0] ?? null) : null;
if (!is_array($u) || empty($u['localId'])) {
    social_fail('Token tidak dapat diverifikasi. Coba login lagi.', 401);
}
if (!empty($u['disabled'])) {
    social_fail('Akun ini dinonaktifkan.', 403);
}
$providers = array_column((array) ($u['providerUserInfo'] ?? []), 'providerId');
if (!array_intersect($providers, ['google.com', 'github.com'])) {
    social_fail('Metode login tidak didukung.', 403);
}
$uid   = (string) $u['localId'];
$email = strtolower((string) ($u['email'] ?? ''));
if ($email === '' || empty($u['emailVerified'])) {
    social_fail('Email akun belum terverifikasi atau tidak tersedia. Jadikan email Anda publik dan terverifikasi di penyedia login, atau daftar dengan email biasa.', 403);
}

// Ambil profil; buat profil merchant jika belum ada
$prof = db_get('users/' . $uid, $idToken);
if (!$prof['ok']) {
    social_fail('Gagal membaca profil: ' . $prof['error'], 502);
}
$data = is_array($prof['data']) ? $prof['data'] : null;
if ($data === null) {
    $name = trim((string) ($u['displayName'] ?? '')) ?: explode('@', $email)[0];
    $data = [
        'name'         => mb_substr($name, 0, 80),
        'email'        => $email,
        'role'         => 'merchant',
        'account_type' => '',
        'organization' => '',
        'provider'     => $providers[0] ?? '',
        'created_at'   => now_string(),
    ];
    $put = db_put('users/' . $uid, $data, $idToken);
    if (!$put['ok']) {
        social_fail('Akun dibuat tetapi profil gagal disimpan: ' . $put['error'], 502);
    }
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'uid'          => $uid,
    'email'        => $email,
    'name'         => (string) ($data['name'] ?? $email),
    'role'         => (string) ($data['role'] ?? 'merchant'),
    'account_type' => (string) ($data['account_type'] ?? ''),
    'organization' => (string) ($data['organization'] ?? ''),
    'photo'        => (string) ($data['photo'] ?? ''),
    'idToken'      => $idToken,
    'refreshToken' => $refresh,
    'expires_at'   => time() + 3000,
];
flash('success', 'Selamat datang, ' . $_SESSION['user']['name'] . '.');
echo json_encode(['ok' => true, 'redirect' => url('dashboard.php')]);
