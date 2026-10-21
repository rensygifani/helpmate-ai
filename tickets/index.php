<?php
// Read: tampilkan daftar tiket (db_get dari node tickets)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

// Merchant hanya menerima tiket miliknya (difilter di Firebase), CS dan admin menerima semua
$tickets = tickets_for_user($user);

// Fitur cari dan filter status
$q = trim((string) ($_GET['q'] ?? ''));
$fs = (string) ($_GET['status'] ?? '');
$fe = is_staff($user) && !empty($_GET['esc']);   // hanya tiket yang dieskalasi ke tim teknis
$tickets = array_values(array_filter($tickets, function ($t) use ($q, $fs, $fe) {
    if ($fs !== '' && ($t['status'] ?? '') !== $fs) {
        return false;
    }
    if ($fe && (empty($t['escalated']) || ($t['status'] ?? '') === 'selesai')) {
        return false;
    }
    if ($q !== '') {
        $hay = mb_strtolower(($t['title'] ?? '') . ' ' . ($t['description'] ?? '') . ' ' . ($t['transaction_id'] ?? ''));
        return str_contains($hay, mb_strtolower($q));
    }
    return true;
}));

render_header('Tiket Kendala', 'tickets');
?>
<form method="get" class="filters">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul, deskripsi, atau ID transaksi">
    <select name="status"><?php render_options(['' => 'Semua status'] + STATUSES, $fs); ?></select>
    <?php if (is_staff($user)): ?>
    <label class="check"><input type="checkbox" name="esc" value="1" <?= $fe ? 'checked' : '' ?>> Tereskalasi ke tim teknis</label>
    <?php endif; ?>
    <button type="submit" class="btn btn-small">Cari</button>
    <a class="btn btn-small" href="<?= e(url('tickets/create.php')) ?>">Buat tiket</a>
</form>

<div class="card table-wrap">
<table>
    <thead>
        <tr><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Pelapor</th><th>Dibuat</th><th>Aksi</th></tr>
    </thead>
    <tbody>
    <?php if (!$tickets): ?>
        <tr><td colspan="7" class="muted">Belum ada tiket.</td></tr>
    <?php endif; ?>
    <?php foreach ($tickets as $t): ?>
        <tr>
            <td><a href="<?= e(url('tickets/view.php?id=' . urlencode($t['id']))) ?>"><?= e($t['title'] ?? '') ?></a></td>
            <td><?= e(label(CATEGORIES, $t['category'] ?? '')) ?></td>
            <td><span class="badge p-<?= e($t['priority'] ?? '') ?>"><?= e(label(PRIORITIES, $t['priority'] ?? '')) ?></span></td>
            <td><span class="badge s-<?= e($t['status'] ?? '') ?>"><?= e(label(STATUSES, $t['status'] ?? '')) ?></span></td>
            <td><?= e($t['owner_name'] ?? '') ?></td>
            <td><?= e($t['created_at'] ?? '') ?></td>
            <td class="actions">
                <?php if (can_edit_ticket($user, $t)): ?>
                    <a class="btn btn-small btn-light" href="<?= e(url('tickets/edit.php?id=' . urlencode($t['id']))) ?>">Ubah</a>
                <?php endif; ?>
                <?php if (can_delete_ticket($user, $t)): ?>
                    <form method="post" action="<?= e(url('tickets/delete.php')) ?>" onsubmit="return confirm('Hapus tiket ini?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                        <button type="submit" class="btn btn-small btn-danger">Hapus</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php render_footer();
