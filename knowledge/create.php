<?php

// Create: tambah artikel Knowledge Base (db_post ke node knowledge_base)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/kb_form.php';
$user = require_kb_editor();

$errors = [];
$article = ['title' => '', 'category' => 'lainnya', 'keywords' => '', 'content' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$clean, $errors] = kb_input($_POST);
    $article = $clean;

    if (!$errors) {
        $record = $clean + [
            'author_uid' => $user['uid'],
            'author_email' => $user['email'],
            'created_at' => now_string(),
            'updated_at' => now_string(),
        ];
        $response = db_post('knowledge_base', $record);
        if ($response['ok']) {
            flash('success', 'Artikel berhasil ditambahkan.');
            redirect('knowledge/index.php');
        }
        $errors[] = 'Gagal menyimpan ke Firebase: ' . $response['error'];
    }
}

render_header('Tambah Artikel Panduan', 'knowledge');
render_errors($errors);
render_kb_form($article, 'Simpan artikel');
render_footer();
