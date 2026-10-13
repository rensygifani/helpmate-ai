<?php
// Delete: hapus artikel Knowledge Base (db_delete pada knowledge_base/{id})
require __DIR__ . '/../includes/bootstrap.php';
$user = require_kb_editor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('knowledge/index.php');
}
csrf_check();

$id = (string) ($_POST['id'] ?? '');
if ($id === '') {
    flash('error', 'ID artikel tidak valid.');
    redirect('knowledge/index.php');
}

$r = db_delete('knowledge_base/' . $id);
flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Artikel berhasil dihapus.' : 'Gagal menghapus: ' . $r['error']);
redirect('knowledge/index.php');
