<?php
// Portal Merchant: merchant memantau status dan perkembangan semua tiketnya tanpa perlu bertanya lewat WhatsApp.
// Hanya catatan yang boleh dibaca merchant yang ditampilkan (catatan internal petugas tidak ikut).
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
if (is_staff($user)) {
    redirect('dashboard.php');
}

$tickets = tickets_for_user($user);
usort($tickets, fn ($firstTicket, $secondTicket) => strcmp((string) ($secondTicket['updated_at'] ?? ''), (string) ($firstTicket['updated_at'] ?? '')));
$tickets = array_slice($tickets, 0, 10);

$order = array_keys(STATUSES);
$needs = array_values(array_filter($tickets, fn ($ticket) => ($ticket['status'] ?? '') === 'menunggu_merchant'));
$latest = [];
foreach ($tickets as $ticket) {
    $followupResult = followups_for($user, $ticket['id']);
    $latest[$ticket['id']] = $followupResult['list'] ? $followupResult['list'][0] : null;   // collection_to_list mengurutkan dari yang terbaru
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
<?php foreach ($tickets as $ticket):
    $status = $ticket['status'] ?? 'baru';
    $currentStep = array_search($status, $order, true);
    $latestFollowup = $latest[$ticket['id']] ?? null;
    ?>
<div class="card ticket-card <?= $status === 'menunggu_merchant' ? 'need-action' : '' ?>">
    <h3><a href="<?= e(url('tickets/view.php?id=' . urlencode($ticket['id']))) ?>"><?= e($ticket['title'] ?? '') ?></a></h3>
    <div class="steps" aria-label="Tahapan penanganan">
        <?php foreach ($order as $stepIndex => $key): ?>
            <span class="step <?= $stepIndex < $currentStep ? 'done' : ($stepIndex === $currentStep ? 'now' : '') ?>"><?= e(STATUSES[$key]) ?></span>
        <?php endforeach; ?>
    </div>
    <p class="muted">
        <span class="badge p-<?= e($ticket['priority'] ?? '') ?>"><?= e(label(PRIORITIES, $ticket['priority'] ?? '')) ?></span>
        <?= e(label(CATEGORIES, $ticket['category'] ?? '')) ?> &middot; dibuat <?= e($ticket['created_at'] ?? '') ?>
    </p>
    <?php if ($latestFollowup): ?>
        <p><strong>Perkembangan terakhir</strong> <span class="muted">(<?= e(label(FOLLOWUP_TYPES, $latestFollowup['type'] ?? '')) ?>, <?= e($latestFollowup['created_at'] ?? '') ?>)</span><br><?= nl2br(e(mb_substr((string) ($latestFollowup['content'] ?? ''), 0, 280))) ?></p>
    <?php else: ?>
        <p class="muted">Belum ada tanggapan dari petugas. Tiket Anda sudah masuk antrean.</p>
    <?php endif; ?>
</div>
<?php endforeach;
render_footer();
