<?php

// Logout: hapus session lalu kembali ke halaman login
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $_SESSION = [];
    session_destroy();
    session_start();
    flash('success', 'Anda sudah logout.');
}
redirect('login.php');
