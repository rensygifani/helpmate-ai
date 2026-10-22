<?php
// Fungsi-fungsi pendukung untuk modul Knowledge Base (validasi input dan form)

function kb_input(array $input): array
{
    $errors = [];
    $clean = [
        'title' => trim((string) ($input['title'] ?? '')),
        'category' => (string) ($input['category'] ?? 'lainnya'),
        'keywords' => trim((string) ($input['keywords'] ?? '')),
        'content' => trim((string) ($input['content'] ?? '')),
    ];
    if ($clean['title'] === '' || mb_strlen($clean['title']) > 150) {
        $errors[] = 'Judul artikel wajib diisi (maksimal 150 karakter).';
    }
    if (!isset(CATEGORIES[$clean['category']])) {
        $errors[] = 'Kategori tidak valid.';
    }
    if (mb_strlen($clean['keywords']) > 200) {
        $errors[] = 'Kata kunci maksimal 200 karakter.';
    }
    if ($clean['content'] === '' || mb_strlen($clean['content']) > 8000) {
        $errors[] = 'Isi artikel wajib diisi (maksimal 8000 karakter).';
    }
    return [$clean, $errors];
}

function render_kb_form(array $article, string $submitLabel): void
{
    ?>
<form method="post" class="card form">
    <?= csrf_field() ?>
    <label>Judul artikel
        <input type="text" name="title" value="<?= e($article['title'] ?? '') ?>" maxlength="150" required>
    </label>
    <label>Kategori
        <select name="category"><?php render_options(CATEGORIES, $article['category'] ?? 'lainnya'); ?></select>
    </label>
    <label>Kata kunci (pisahkan dengan koma)
        <input type="text" name="keywords" value="<?= e($article['keywords'] ?? '') ?>" maxlength="200" placeholder="contoh: qris, pending, cek status">
    </label>
    <label>Isi panduan / solusi
        <textarea name="content" rows="10" maxlength="8000" required><?= e($article['content'] ?? '') ?></textarea>
    </label>
    <div>
        <button type="submit" class="btn"><?= e($submitLabel) ?></button>
        <a class="btn btn-light" href="<?= e(url('knowledge/index.php')) ?>">Batal</a>
    </div>
</form>
<?php
}
