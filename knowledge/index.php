<?php
// Read: tampilkan daftar artikel Knowledge Base (db_get dari node knowledge_base)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/kb_form.php';
$user = require_login();

$res = db_get('knowledge_base');
$articles = $res['ok'] ? collection_to_list($res['data']) : [];

$q  = trim((string) ($_GET['q'] ?? ''));
$fc = (string) ($_GET['category'] ?? '');
$articles = array_values(array_filter($articles, function ($a) use ($q, $fc) {
    if ($fc !== '' && ($a['category'] ?? '') !== $fc) {
        return false;
    }
    if ($q !== '') {
        $hay = mb_strtolower(($a['title'] ?? '') . ' ' . ($a['keywords'] ?? '') . ' ' . ($a['content'] ?? ''));
        return str_contains($hay, mb_strtolower($q));
    }
    return true;
}));

render_header('Knowledge Base', 'knowledge');
if (!$res['ok']) {
    render_errors(['Gagal membaca data dari Firebase: ' . $res['error']]);
}
?>
<form method="get" class="filters">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari panduan atau kata kunci">
    <select name="category"><?php render_options(['' => 'Semua kategori'] + CATEGORIES, $fc); ?></select>
    <button type="submit" class="btn btn-small">Cari</button>
    <?php if (is_kb_editor($user)): ?>
        <a class="btn btn-small" href="<?= e(url('knowledge/create.php')) ?>">Tambah artikel</a>
    <?php endif; ?>
</form>

<div class="card">
<table>
    <thead>
        <tr><th>Judul</th><th>Kategori</th><th>Kata kunci</th><th>Diubah</th><?php if (is_kb_editor($user)): ?><th>Aksi</th><?php endif; ?></tr>
    </thead>
    <tbody>
    <?php if (!$articles): ?>
        <tr><td colspan="5" class="muted">Belum ada artikel panduan.</td></tr>
    <?php endif; ?>
    <?php foreach ($articles as $a): ?>
        <tr>
            <td><a href="<?= e(url('knowledge/view.php?id=' . urlencode($a['id']))) ?>"><?= e($a['title'] ?? '') ?></a></td>
            <td><?= e(label(CATEGORIES, $a['category'] ?? '')) ?></td>
            <td><?= e($a['keywords'] ?? '') ?></td>
            <td><?= e($a['updated_at'] ?? '') ?></td>
            <?php if (is_kb_editor($user)): ?>
            <td class="actions">
                <a class="btn btn-small btn-light" href="<?= e(url('knowledge/edit.php?id=' . urlencode($a['id']))) ?>">Ubah</a>
                <form method="post" action="<?= e(url('knowledge/delete.php')) ?>" onsubmit="return confirm('Hapus artikel ini?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($a['id']) ?>">
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
