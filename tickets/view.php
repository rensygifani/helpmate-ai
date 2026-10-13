<?php
// Read: tampilkan detail satu tiket
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

$id = (string) ($_GET['id'] ?? '');
$res = $id !== '' ? db_get('tickets/' . $id) : ['ok' => false, 'data' => null];
$t = $res['ok'] && is_array($res['data']) ? $res['data'] : null;

if ($t === null || !can_view_ticket($user, $t)) {
    http_response_code(404);
    render_header('Tiket tidak ditemukan', 'tickets');
    echo '<p>Tiket tidak ditemukan atau Anda tidak memiliki akses.</p><p><a class="btn" href="' . e(url('tickets/index.php')) . '">Kembali</a></p>';
    render_footer();
    exit;
}

render_header('Detail Tiket', 'tickets');
?>
<div class="card detail">
    <h2><?= e($t['title'] ?? '') ?></h2>
    <p>
        <span class="badge p-<?= e($t['priority'] ?? '') ?>">Prioritas: <?= e(label(PRIORITIES, $t['priority'] ?? '')) ?></span>
        <span class="badge s-<?= e($t['status'] ?? '') ?>"><?= e(label(STATUSES, $t['status'] ?? '')) ?></span>
    </p>
    <dl>
        <dt>Kategori</dt><dd><?= e(label(CATEGORIES, $t['category'] ?? '')) ?></dd>
        <dt>Metode bayar</dt><dd><?= e(label(PAYMENT_METHODS, $t['payment_method'] ?? '') ?: '-') ?></dd>
        <dt>ID transaksi</dt><dd><?= e($t['transaction_id'] ?? '') ?: '-' ?></dd>
        <dt>Nominal</dt><dd><?= e(rupiah($t['amount'] ?? null)) ?></dd>
        <dt>Pelapor</dt><dd><?= e($t['owner_name'] ?? '') ?> (<?= e($t['owner_email'] ?? '') ?>)</dd>
        <dt>Dibuat</dt><dd><?= e($t['created_at'] ?? '') ?></dd>
        <dt>Diubah</dt><dd><?= e($t['updated_at'] ?? '') ?></dd>
    </dl>
    <h3>Deskripsi</h3>
    <p><?= nl2br(e($t['description'] ?? '')) ?></p>
    <p>
        <a class="btn btn-light" href="<?= e(url('tickets/index.php')) ?>">Kembali</a>
        <?php if (can_edit_ticket($user, $t)): ?>
            <a class="btn" href="<?= e(url('tickets/edit.php?id=' . urlencode($id))) ?>">Ubah</a>
        <?php endif; ?>
    </p>
</div>
<?php render_footer();
