<?php
// Read: tampilkan isi satu artikel
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/kb_form.php';
$user = require_login();

$id = (string) ($_GET['id'] ?? '');
$articleResponse = valid_id($id) ? db_get('knowledge_base/' . $id) : ['ok' => false, 'data' => null];
$article = $articleResponse['ok'] && is_array($articleResponse['data']) ? $articleResponse['data'] : null;

if ($article === null) {
    http_response_code(404);
    render_header('Artikel tidak ditemukan', 'knowledge');
    echo '<p>Artikel tidak ditemukan.</p><p><a class="btn" href="' . e(url('knowledge/index.php')) . '">Kembali</a></p>';
    render_footer();
    exit;
}

render_header('Artikel Panduan', 'knowledge');
?>
<div class="card detail">
    <h2><?= e($article['title'] ?? '') ?></h2>
    <p><span class="badge"><?= e(label(CATEGORIES, $article['category'] ?? '')) ?></span></p>
    <p class="muted">Kata kunci: <?= e($article['keywords'] ?? '') ?: '-' ?> | Penulis: <?= e($article['author_email'] ?? '') ?> | Diubah: <?= e($article['updated_at'] ?? '') ?></p>
    <div class="article"><?= nl2br(e($article['content'] ?? '')) ?></div>
    <p>
        <a class="btn btn-light" href="<?= e(url('knowledge/index.php')) ?>">Kembali</a>
        <?php if (is_kb_editor($user)): ?>
            <a class="btn" href="<?= e(url('knowledge/edit.php?id=' . urlencode($id))) ?>">Ubah</a>
        <?php endif; ?>
    </p>
</div>
<?php render_footer();
