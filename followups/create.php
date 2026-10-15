<?php
// Create: catat tindakan baru pada tiket (db_post ke followups/{ticketId})
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
require __DIR__ . '/../includes/followup_form.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tickets/index.php');
}
csrf_check();

$ticketId = (string) ($_POST['ticket_id'] ?? '');
if (!valid_id($ticketId)) {
    flash('error', 'Tiket tidak valid.');
    redirect('tickets/index.php');
}
$back = 'tickets/view.php?id=' . urlencode($ticketId);

if (!can_add_followup($user)) {
    flash('error', 'Hanya petugas yang dapat mencatat tindakan.');
    redirect($back);
}
$res = db_get('tickets/' . $ticketId);
if (!$res['ok'] || !is_array($res['data'])) {
    flash('error', 'Tiket tidak ditemukan.');
    redirect('tickets/index.php');
}

[$clean, $errors] = followup_input($_POST);
if ($errors) {
    flash('error', implode(' ', $errors));
    redirect($back);
}

$record = $clean + [
    'author_uid'  => $user['uid'],
    'author_name' => $user['name'],
    'author_role' => $user['role'],
    'created_at'  => now_string(),
    'updated_at'  => now_string(),
];
$r = db_post('followups/' . $ticketId, $record);
if ($r['ok']) {
    // Jenis "selesai" sekalian mengubah status tiket
    if ($clean['type'] === 'selesai') {
        db_patch('tickets/' . $ticketId, ['status' => 'selesai', 'updated_at' => now_string()]);
    }
    flash('success', 'Tindakan berhasil dicatat.');
} else {
    flash('error', 'Gagal menyimpan ke Firebase: ' . $r['error']);
}
redirect($back);
