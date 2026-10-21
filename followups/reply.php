<?php
// Merchant membalas atau menambah informasi pada tiketnya sendiri (db_post ke followups/{ticketId}).
// Balasan ditandai jenis "tanggapan_merchant" dan boleh dibaca merchant. Security Rules hanya mengizinkan pemilik tiket.
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

$res = db_get('tickets/' . $ticketId);
$t   = $res['ok'] && is_array($res['data']) ? $res['data'] : null;
if ($t === null || ($t['owner_uid'] ?? '') !== $user['uid']) {
    flash('error', 'Hanya pemilik tiket yang dapat membalas.');
    redirect('tickets/index.php');
}
if (($t['status'] ?? '') === 'selesai') {
    flash('error', 'Tiket sudah selesai. Buat tiket baru jika masih ada kendala.');
    redirect($back);
}

[$clean, $errors] = followup_input(['type' => 'tanggapan_merchant', 'content' => $_POST['content'] ?? ''], ['tanggapan_merchant']);
if ($errors) {
    flash('error', implode(' ', $errors));
    redirect($back);
}
[$clean, $maskedLabels] = mask_fields($clean, ['content']);

$r = db_post('followups/' . $ticketId, $clean + [
    'author_uid'          => $user['uid'],
    'author_name'         => $user['name'],
    'author_role'         => $user['role'],
    'visible_to_merchant' => true,
    'created_at'          => now_string(),
    'updated_at'          => now_string(),
]);
if ($r['ok']) {
    flash('success', 'Balasan Anda sudah dikirim ke petugas.');
    if ($maskedLabels) {
        flash('warning', 'Data sensitif disamarkan demi keamanan: ' . implode(', ', $maskedLabels) . '.');
    }
} else {
    flash('error', 'Gagal mengirim balasan: ' . $r['error']);
}
redirect($back);
