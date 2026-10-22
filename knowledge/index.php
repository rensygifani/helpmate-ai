<?php
// Read: tampilkan daftar artikel Knowledge Base (db_get dari node knowledge_base)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/kb_form.php';
$user = require_login();

$articlesResponse = db_get('knowledge_base');
$articles = $articlesResponse['ok'] ? collection_to_list($articlesResponse['data']) : [];

$searchText = trim((string) ($_GET['q'] ?? ''));
$filterCategory = (string) ($_GET['category'] ?? '');
$articles = array_values(array_filter($articles, function ($article) use ($searchText, $filterCategory) {
    if ($filterCategory !== '' && ($article['category'] ?? '') !== $filterCategory) {
        return false;
    }
    if ($searchText !== '') {
        $searchableText = mb_strtolower(($article['title'] ?? '') . ' ' . ($article['keywords'] ?? '') . ' ' . ($article['content'] ?? ''));
        return str_contains($searchableText, mb_strtolower($searchText));
    }
    return true;
}));

render_header('Knowledge Base', 'knowledge');
if (!$articlesResponse['ok']) {
    render_errors(['Gagal membaca data dari Firebase: ' . $articlesResponse['error']]);
}
?>
<form method="get" class="filters">
    <input type="text" name="q" value="<?= e($searchText) ?>" placeholder="Cari panduan atau kata kunci">
    <select name="category"><?php render_options(['' => 'Semua kategori'] + CATEGORIES, $filterCategory); ?></select>
    <button type="submit" class="btn btn-small">Cari</button>
    <?php if (is_kb_editor($user)): ?>
        <a class="btn btn-small" href="<?= e(url('knowledge/create.php')) ?>">Tambah artikel</a>
    <?php endif; ?>
</form>

<div class="card table-wrap">
<table>
    <thead>
        <tr><th>Judul</th><th>Kategori</th><th>Kata kunci</th><th>Diubah</th><?php if (is_kb_editor($user)): ?><th>Aksi</th><?php endif; ?></tr>
    </thead>
    <tbody>
    <?php if (!$articles): ?>
        <tr><td colspan="5" class="muted">Belum ada artikel panduan.</td></tr>
    <?php endif; ?>
    <?php foreach ($articles as $article): ?>
        <tr>
            <td><a href="<?= e(url('knowledge/view.php?id=' . urlencode($article['id']))) ?>"><?= e($article['title'] ?? '') ?></a></td>
            <td><?= e(label(CATEGORIES, $article['category'] ?? '')) ?></td>
            <td><?= e($article['keywords'] ?? '') ?></td>
            <td><?= e($article['updated_at'] ?? '') ?></td>
            <?php if (is_kb_editor($user)): ?>
            <td class="actions">
                <a class="btn btn-small btn-light" href="<?= e(url('knowledge/edit.php?id=' . urlencode($article['id']))) ?>">Ubah</a>
                <form method="post" action="<?= e(url('knowledge/delete.php')) ?>" onsubmit="return confirm('Hapus artikel ini?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($article['id']) ?>">
                    <button type="submit" class="btn btn-small btn-danger">Hapus</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php render_footer();
