<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
session_start();

$localConfig = __DIR__ . '/../config.local.php';
$GLOBALS['CONFIG'] = file_exists($localConfig) ? (require $localConfig) : [];

function cfg(string $key, ?string $default = null): ?string
{
    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $GLOBALS['CONFIG'][$key] ?? $default;
}

require __DIR__ . '/constants.php';
require __DIR__ . '/firebase.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/social_login.php';
require __DIR__ . '/mask.php';
require __DIR__ . '/gemini.php';
require __DIR__ . '/similar.php';
require __DIR__ . '/analytics.php';

if (!cfg('FIREBASE_API_KEY') || !cfg('FIREBASE_DB_URL')) {
    http_response_code(500);
    exit('Konfigurasi Firebase belum diisi. Salin config.example.php menjadi config.local.php lalu isi FIREBASE_API_KEY dan FIREBASE_DB_URL.');
}
