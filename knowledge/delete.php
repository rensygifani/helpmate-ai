<?php

// Delete: hapus artikel Knowledge Base (db_delete pada knowledge_base/{id})
require __DIR__ . '/../includes/bootstrap.php';
$user = require_kb_editor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('knowledge/index.php');
}
csrf_check();

$id = (string) ($_POST['id'] ?? '');
if (!valid_id($id)) {
    flash('error', 'ID artikel tidak valid.');
    redirect('knowledge/index.php');
}

// Pastikan artikelnya masih ada sebelum dihapus
$exists = db_get('knowledge_base/' . $id);
if (!$exists['ok'] || !is_array($exists['data'])) {
    flash('error', 'Artikel tidak ditemukan.');
    redirect('knowledge/index.php');
}

$response = db_delete('knowledge_base/' . $id);
flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Artikel berhasil dihapus.' : 'Gagal menghapus: ' . $response['error']);
redirect('knowledge/index.php');
