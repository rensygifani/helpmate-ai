<?php
// Delete: hapus catatan tindakan (db_delete pada followups/{ticketId}/{id})
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
require __DIR__ . '/../includes/followup_form.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tickets/index.php');
}
csrf_check();

$ticketId = (string) ($_POST['ticket_id'] ?? '');
$id       = (string) ($_POST['id'] ?? '');
if (!valid_id($ticketId) || !valid_id($id)) {
    flash('error', 'Data tidak valid.');
    redirect('tickets/index.php');
}
$back = 'tickets/view.php?id=' . urlencode($ticketId);

$res = db_get('followups/' . $ticketId . '/' . $id);
$f   = $res['ok'] && is_array($res['data']) ? $res['data'] : null;
if ($f === null || !can_delete_followup($user, $f)) {
    flash('error', 'Catatan tidak ditemukan atau Anda tidak berhak menghapusnya.');
    redirect($back);
}

$r = db_delete('followups/' . $ticketId . '/' . $id);
flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Catatan berhasil dihapus.' : 'Gagal menghapus: ' . $r['error']);
redirect($back);
