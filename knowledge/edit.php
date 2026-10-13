<?php
// Update: ubah artikel Knowledge Base (db_patch ke knowledge_base/{id})
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/kb_form.php';
$user = require_kb_editor();

$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$res = $id !== '' ? db_get('knowledge_base/' . $id) : ['ok' => false, 'data' => null];
$existing = $res['ok'] && is_array($res['data']) ? $res['data'] : null;

if ($existing === null) {
    http_response_code(404);
    render_header('Artikel tidak ditemukan', 'knowledge');
    echo '<p>Artikel tidak ditemukan.</p><p><a class="btn" href="' . e(url('knowledge/index.php')) . '">Kembali</a></p>';
    render_footer();
    exit;
}

$errors = [];
$a = $existing;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$clean, $errors] = kb_input($_POST);
    $a = array_merge($existing, $clean);

    if (!$errors) {
        $clean['updated_at'] = now_string();
        $r = db_patch('knowledge_base/' . $id, $clean);
        if ($r['ok']) {
            flash('success', 'Artikel berhasil diperbarui.');
            redirect('knowledge/index.php');
        }
        $errors[] = 'Gagal memperbarui di Firebase: ' . $r['error'];
    }
}

render_header('Ubah Artikel Panduan', 'knowledge');
render_errors($errors);
render_kb_form($a, 'Simpan perubahan');
render_footer();
