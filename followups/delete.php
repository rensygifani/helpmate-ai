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
$id = (string) ($_POST['id'] ?? '');
if (!valid_id($ticketId) || !valid_id($id)) {
    flash('error', 'Data tidak valid.');
    redirect('tickets/index.php');
}
$back = 'tickets/view.php?id=' . urlencode($ticketId);

$followupResponse = db_get('followups/' . $ticketId . '/' . $id);
$followup = $followupResponse['ok'] && is_array($followupResponse['data']) ? $followupResponse['data'] : null;
if ($followup === null || !can_delete_followup($user, $followup)) {
    flash('error', 'Catatan tidak ditemukan atau Anda tidak berhak menghapusnya.');
    redirect($back);
}

$response = db_delete('followups/' . $ticketId . '/' . $id);
flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Catatan berhasil dihapus.' : 'Gagal menghapus: ' . $response['error']);
redirect($back);
