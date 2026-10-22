<?php
// Read: tampilkan daftar tiket (db_get dari node tickets)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

// Merchant hanya menerima tiket miliknya (difilter di Firebase), CS dan admin menerima semua
$tickets = tickets_for_user($user);

// Fitur cari dan filter status
$searchText = trim((string) ($_GET['q'] ?? ''));
$filterStatus = (string) ($_GET['status'] ?? '');
$onlyEscalated = is_staff($user) && !empty($_GET['esc']);   // hanya tiket yang dieskalasi ke tim teknis
$tickets = array_values(array_filter($tickets, function ($ticket) use ($searchText, $filterStatus, $onlyEscalated) {
    if ($filterStatus !== '' && ($ticket['status'] ?? '') !== $filterStatus) {
        return false;
    }
    if ($onlyEscalated && (empty($ticket['escalated']) || ($ticket['status'] ?? '') === 'selesai')) {
        return false;
    }
    if ($searchText !== '') {
        $searchableText = mb_strtolower(($ticket['title'] ?? '') . ' ' . ($ticket['description'] ?? '') . ' ' . ($ticket['transaction_id'] ?? ''));
        return str_contains($searchableText, mb_strtolower($searchText));
    }
    return true;
}));

render_header('Tiket Kendala', 'tickets');
?>
<form method="get" class="filters">
    <input type="text" name="q" value="<?= e($searchText) ?>" placeholder="Cari judul, deskripsi, atau ID transaksi">
    <select name="status"><?php render_options(['' => 'Semua status'] + STATUSES, $filterStatus); ?></select>
    <?php if (is_staff($user)): ?>
    <label class="check"><input type="checkbox" name="esc" value="1" <?= $onlyEscalated ? 'checked' : '' ?>> Tereskalasi ke tim teknis</label>
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
    <?php foreach ($tickets as $ticket): ?>
        <tr>
            <td><a href="<?= e(url('tickets/view.php?id=' . urlencode($ticket['id']))) ?>"><?= e($ticket['title'] ?? '') ?></a></td>
            <td><?= e(label(CATEGORIES, $ticket['category'] ?? '')) ?></td>
            <td><span class="badge p-<?= e($ticket['priority'] ?? '') ?>"><?= e(label(PRIORITIES, $ticket['priority'] ?? '')) ?></span></td>
            <td><span class="badge s-<?= e($ticket['status'] ?? '') ?>"><?= e(label(STATUSES, $ticket['status'] ?? '')) ?></span></td>
            <td><?= e($ticket['owner_name'] ?? '') ?></td>
            <td><?= e($ticket['created_at'] ?? '') ?></td>
            <td class="actions">
                <?php if (can_edit_ticket($user, $ticket)): ?>
                    <a class="btn btn-small btn-light" href="<?= e(url('tickets/edit.php?id=' . urlencode($ticket['id']))) ?>">Ubah</a>
                <?php endif; ?>
                <?php if (can_delete_ticket($user, $ticket)): ?>
                    <form method="post" action="<?= e(url('tickets/delete.php')) ?>" onsubmit="return confirm('Hapus tiket ini?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($ticket['id']) ?>">
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
