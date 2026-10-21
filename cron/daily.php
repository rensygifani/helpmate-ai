<?php
// Pembuatan ringkasan harian otomatis. Dipanggil oleh penjadwal (cron-job.org atau Render Cron Job) setiap hari, contoh:
//   https://NAMA-APP.onrender.com/cron/daily.php?token=RAHASIA_CRON
// Syarat di pengaturan server (environment): CRON_TOKEN, CRON_EMAIL, CRON_PASSWORD (akun dengan role admin atau super_admin).
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$expected = (string) cfg('CRON_TOKEN', '');
$given    = (string) ($_GET['token'] ?? '');
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token tidak valid atau CRON_TOKEN belum diatur.']);
    exit;
}
$email = (string) cfg('CRON_EMAIL', '');
$pass  = (string) cfg('CRON_PASSWORD', '');
if ($email === '' || $pass === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CRON_EMAIL dan CRON_PASSWORD belum diatur.']);
    exit;
}

$login = firebase_auth('signInWithPassword', ['email' => $email, 'password' => $pass, 'returnSecureToken' => true]);
if (!$login['ok']) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Login akun cron gagal: ' . auth_error_text($login['error'])]);
    exit;
}
$token = $login['data']['idToken'];
$uid   = $login['data']['localId'];
$prof  = db_get('users/' . $uid, $token);
if (!$prof['ok'] || !in_array($prof['data']['role'] ?? '', ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Akun cron harus berrole admin atau super_admin.']);
    exit;
}

// Bawaan: ringkasan untuk kemarin (dijadwalkan pagi hari). Bisa diubah dengan &date=YYYY-MM-DD
$date = (string) ($_GET['date'] ?? date('Y-m-d', strtotime('-1 day')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d', strtotime('-1 day'));
}
$r = daily_generate($date, $token, 'Penjadwal otomatis');
http_response_code($r['ok'] ? 200 : 500);
echo json_encode(['ok' => $r['ok'], 'date' => $date, 'error' => $r['error'] ?? null]);
