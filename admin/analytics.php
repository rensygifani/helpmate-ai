<?php
// Dashboard admin dan analitik: ringkasan beban kerja, waktu respons, SLA, dan artikel panduan yang paling sering terpakai.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();

$ticketsResponse = db_get('tickets');
$tickets = $ticketsResponse['ok'] ? collection_to_list($ticketsResponse['data']) : [];
$followupsResponse = db_get('followups');                       // {ticketId: {fid: {...}}}
$followups = [];
if ($followupsResponse['ok'] && is_array($followupsResponse['data'])) {
    foreach ($followupsResponse['data'] as $tid => $rows) {
        $followups[$tid] = is_array($rows) ? array_values($rows) : [];
    }
}
$kbTitles = [];
foreach (collection_to_list(db_get('knowledge_base')['data'] ?? null) as $article) {
    $kbTitles[$article['id']] = (string) ($article['title'] ?? '');
}
$analytics = analytics_build($tickets, $followups, $kbTitles);

render_header('Dashboard Admin dan Analitik', 'analytics', 'Gambaran beban kerja dan kualitas penanganan tiket.');
if (!$ticketsResponse['ok']) {
    echo '<div class="alert alert-error">Gagal membaca tiket: ' . e((string) $ticketsResponse['error']) . '</div>';
}
foreach ($analytics['alerts'] as $alert) {
    echo '<div class="alert alert-warning"><strong>Peringatan gangguan:</strong> ' . (int) $alert['count'] . ' laporan "' . e(label(CATEGORIES, $alert['category'])) . '" dalam ' . (int) $alert['minutes'] . ' menit terakhir.</div>';
}
?>
<div class="kpis">
    <div class="card"><span class="num"><?= (int) $analytics['total'] ?></span>Total tiket</div>
    <div class="card"><span class="num"><?= (int) $analytics['open'] ?></span>Masih terbuka</div>
    <div class="card"><span class="num"><?= (int) $analytics['done_rate'] ?>%</span>Tiket selesai</div>
    <div class="card"><span class="num"><?= $analytics['avg_first_response_min'] === null ? '-' : e((string) $analytics['avg_first_response_min']) . ' mnt' ?></span>Rata-rata respons pertama</div>
    <div class="card"><span class="num"><?= $analytics['avg_resolution_hours'] === null ? '-' : e((string) $analytics['avg_resolution_hours']) . ' jam' ?></span>Rata-rata penyelesaian</div>
    <div class="card"><span class="num"><?= (int) $analytics['flagged_duplicates'] ?></span>Tiket mungkin duplikat</div>
</div>

<div class="card"><h3>Tiket masuk 14 hari terakhir</h3><?php render_day_chart($analytics['per_day']); ?></div>

<div class="two-col" style="margin-top:16px">
    <div class="card"><h3>Per kategori</h3><?php arsort($analytics['by_category']);
render_bars($analytics['by_category'], CATEGORIES); ?></div>
    <div class="card"><h3>Per prioritas</h3><?php render_bars($analytics['by_priority'], PRIORITIES); ?></div>
    <div class="card"><h3>Per status</h3><?php render_bars($analytics['by_status'], STATUSES); ?></div>
    <div class="card"><h3>Per metode bayar</h3><?php render_bars($analytics['by_method'], PAYMENT_METHODS); ?></div>
    <div class="card"><h3>Merchant dengan tiket terbanyak</h3><?php render_bars($analytics['top_merchants']); ?></div>
    <div class="card"><h3>Artikel panduan paling sering terkait</h3>
        <?php if (!$analytics['top_kb']): ?><p class="muted">Belum ada data. Jalankan "Ringkasan dan draf balasan" pada beberapa tiket.</p><?php endif; ?>
        <?php foreach ($analytics['top_kb'] as $kbEntry): ?>
            <p><a href="<?= e(url('knowledge/view.php?id=' . urlencode($kbEntry['id']))) ?>"><?= e($kbEntry['title']) ?></a> <span class="muted">(<?= (int) $kbEntry['count'] ?> tiket)</span></p>
        <?php endforeach; ?>
    </div>
</div>

<div class="card table-wrap" style="margin-top:16px">
    <h3>Tiket melewati batas respons (SLA)</h3>
    <p class="hint">Batas respons pertama: kritis <?= SLA_HOURS['kritis'] ?> jam, tinggi <?= SLA_HOURS['tinggi'] ?> jam, sedang <?= SLA_HOURS['sedang'] ?> jam, rendah <?= SLA_HOURS['rendah'] ?> jam.</p>
    <table>
        <thead><tr><th>Judul</th><th>Prioritas</th><th>Terlambat</th></tr></thead>
        <tbody>
        <?php if (!$analytics['sla']): ?><tr><td colspan="3" class="muted">Tidak ada tiket yang melewati batas respons.</td></tr><?php endif; ?>
        <?php foreach ($analytics['sla'] as $overdueTicket): ?>
            <tr>
                <td><a href="<?= e(url('tickets/view.php?id=' . urlencode($overdueTicket['id']))) ?>"><?= e($overdueTicket['title']) ?></a></td>
                <td><span class="badge p-<?= e($overdueTicket['priority']) ?>"><?= e(label(PRIORITIES, $overdueTicket['priority'])) ?></span></td>
                <td><?= e((string) $overdueTicket['overdue_hours']) ?> jam</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p><a class="btn btn-light" href="<?= e(url('admin/daily.php')) ?>">Ringkasan harian</a></p>
<?php render_footer();
