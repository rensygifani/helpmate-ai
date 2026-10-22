<?php

// Delete: hapus tiket (db_delete pada tickets/{id}) beserta lampirannya
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tickets/index.php');
}
csrf_check();

$id = (string) ($_POST['id'] ?? '');
$ticketResponse = $id !== '' ? db_get('tickets/' . $id) : ['ok' => false, 'data' => null];
$ticket = $ticketResponse['ok'] && is_array($ticketResponse['data']) ? $ticketResponse['data'] : null;

if ($ticket === null || !can_delete_ticket($user, $ticket)) {
    flash('error', 'Tiket tidak ditemukan atau Anda tidak berhak menghapusnya.');
    redirect('tickets/index.php');
}

// Lampiran dan riwayat dihapus lebih dulu selagi tiket masih ada, karena Security Rules memeriksa pemilik tiket
db_delete('ticket_files/' . $id);
db_delete('followups/' . $id);
$response = db_delete('tickets/' . $id);
flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Tiket berhasil dihapus.' : 'Gagal menghapus: ' . $response['error']);
redirect('tickets/index.php');
