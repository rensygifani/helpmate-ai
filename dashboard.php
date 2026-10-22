<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$tickets = tickets_for_user($user);
$alerts = is_staff($user) ? incident_alerts($tickets) : [];
$kbArticles = collection_to_list(db_get('knowledge_base')['data'] ?? null);

$byStatus = [];
foreach ($tickets as $ticket) {
    $status = $ticket['status'] ?? 'baru';
    $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
}

usort($tickets, fn ($firstTicket, $secondTicket) => strcmp($secondTicket['created_at'] ?? '', $firstTicket['created_at'] ?? ''));
$recent = array_slice($tickets, 0, 5);

render_header('Dashboard', 'dashboard');
?>
<section class="hero">
    <div>
        <h2>Halo, <?= e($user['name']) ?></h2>
        <p><?= is_staff($user) ? 'Tinjau tiket yang masuk dan bantu merchant menyelesaikan kendalanya.' : 'Ada kendala transaksi? Buat tiket dan pantau penanganannya di sini.' ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('tickets/create.php')) ?>">Buat tiket baru</a>
        <a class="btn btn-light" href="<?= e(url('knowledge/index.php')) ?>">Lihat Knowledge Base</a>
    </div>
</section>

<?php
$escalated = is_staff($user) ? count(array_filter($tickets, fn ($ticket) => !empty($ticket['escalated']) && ($ticket['status'] ?? '') !== 'selesai')) : 0;
if ($escalated > 0 && in_array($user['role'] ?? '', ['tim_teknis', 'admin', 'super_admin'], true)): ?>
    <div class="alert alert-warning"><strong>Tim Teknis:</strong> <?= (int) $escalated ?> tiket tereskalasi menunggu penanganan. <a href="<?= e(url('tickets/index.php?esc=1')) ?>">Lihat tiketnya</a></div>
<?php endif; ?>
<?php foreach ($alerts as $alert): ?>
    <div class="alert alert-warning"><strong>Peringatan gangguan:</strong> <?= (int) $alert['count'] ?> laporan "<?= e(label(CATEGORIES, $alert['category'])) ?>" masuk dalam <?= (int) $alert['minutes'] ?> menit terakhir. Periksa kemungkinan gangguan sistem.</div>
<?php endforeach; ?>

<div class="grid">
    <div class="card stat st-total"><span class="num"><?= count($tickets) ?></span><span>Total tiket<?= is_staff($user) ? '' : ' Anda' ?></span></div>
    <?php foreach (STATUSES as $key => $text): ?>
        <div class="card stat st-<?= e($key) ?>"><span class="num"><?= (int) ($byStatus[$key] ?? 0) ?></span><span><?= e($text) ?></span></div>
    <?php endforeach; ?>
    <div class="card stat st-kb"><span class="num"><?= count($kbArticles) ?></span><span>Artikel panduan</span></div>
</div>

<div class="section-title">
    <h2>Tiket terbaru</h2>
    <a href="<?= e(url('tickets/index.php')) ?>">Lihat semua</a>
</div>
<div class="card table-wrap">
<table>
    <thead><tr><th>Judul</th><th>Prioritas</th><th>Status</th><th>Dibuat</th></tr></thead>
    <tbody>
    <?php if (!$recent): ?>
        <tr><td colspan="4" class="muted">Belum ada tiket. Buat tiket pertama Anda untuk melaporkan kendala.</td></tr>
    <?php endif; ?>
    <?php foreach ($recent as $ticket): ?>
        <tr>
            <td><a href="<?= e(url('tickets/view.php?id=' . urlencode($ticket['id']))) ?>"><?= e($ticket['title'] ?? '') ?></a></td>
            <td><span class="badge p-<?= e($ticket['priority'] ?? '') ?>"><?= e(label(PRIORITIES, $ticket['priority'] ?? '')) ?></span></td>
            <td><span class="badge s-<?= e($ticket['status'] ?? '') ?>"><?= e(label(STATUSES, $ticket['status'] ?? '')) ?></span></td>
            <td class="muted"><?= e($ticket['created_at'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php render_footer();
