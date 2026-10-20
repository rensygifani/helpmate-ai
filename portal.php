<?php
// Portal Merchant: merchant memantau status dan perkembangan semua tiketnya tanpa perlu bertanya lewat WhatsApp.
// Hanya catatan yang boleh dibaca merchant yang ditampilkan (catatan internal petugas tidak ikut).
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
if (is_staff($user)) {
    redirect('dashboard.php');
}

$tickets = tickets_for_user($user);
usort($tickets, fn($a, $b) => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));
$tickets = array_slice($tickets, 0, 10);

$order  = array_keys(STATUSES);
$needs  = array_values(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'menunggu_merchant'));
$latest = [];
foreach ($tickets as $t) {
    $f = followups_for($user, $t['id']);
    $latest[$t['id']] = $f['list'] ? $f['list'][0] : null;   // collection_to_list mengurutkan dari yang terbaru
}

render_header('Portal Merchant', 'portal', 'Pantau status tiket Anda di satu tempat.');
?>
<?php if ($needs): ?>
<div class="alert alert-warning"><strong>Perlu tindakan Anda:</strong> <?= count($needs) ?> tiket menunggu informasi dari Anda. Buka tiketnya lalu kirim balasan.</div>
<?php endif; ?>

<div class="section-title"><h2>Tiket Anda</h2><a href="<?= e(url('tickets/create.php')) ?>">Buat tiket baru</a></div>
<?php if (!$tickets): ?>
    <div class="card"><p class="muted">Belum ada tiket. Buat tiket pertama untuk melaporkan kendala transaksi.</p></div>
<?php endif; ?>
<?php foreach ($tickets as $t):
    $st  = $t['status'] ?? 'baru';
    $idx = array_search($st, $order, true);
    $lf  = $latest[$t['id']] ?? null;
?>
<div class="card ticket-card <?= $st === 'menunggu_merchant' ? 'need-action' : '' ?>">
    <h3><a href="<?= e(url('tickets/view.php?id=' . urlencode($t['id']))) ?>"><?= e($t['title'] ?? '') ?></a></h3>
    <div class="steps" aria-label="Tahapan penanganan">
        <?php foreach ($order as $i => $key): ?>
            <span class="step <?= $i < $idx ? 'done' : ($i === $idx ? 'now' : '') ?>"><?= e(STATUSES[$key]) ?></span>
        <?php endforeach; ?>
    </div>
    <p class="muted">
        <span class="badge p-<?= e($t['priority'] ?? '') ?>"><?= e(label(PRIORITIES, $t['priority'] ?? '')) ?></span>
        <?= e(label(CATEGORIES, $t['category'] ?? '')) ?> &middot; dibuat <?= e($t['created_at'] ?? '') ?>
    </p>
    <?php if ($lf): ?>
        <p><strong>Perkembangan terakhir</strong> <span class="muted">(<?= e(label(FOLLOWUP_TYPES, $lf['type'] ?? '')) ?>, <?= e($lf['created_at'] ?? '') ?>)</span><br><?= nl2br(e(mb_substr((string) ($lf['content'] ?? ''), 0, 280))) ?></p>
    <?php else: ?>
        <p class="muted">Belum ada tanggapan dari petugas. Tiket Anda sudah masuk antrean.</p>
    <?php endif; ?>
</div>
<?php endforeach;
render_footer();
