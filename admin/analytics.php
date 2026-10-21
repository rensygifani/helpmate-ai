<?php
// Dashboard admin dan analitik: ringkasan beban kerja, waktu respons, SLA, dan artikel panduan yang paling sering terpakai.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();

$tr = db_get('tickets');
$tickets = $tr['ok'] ? collection_to_list($tr['data']) : [];
$fr = db_get('followups');                       // {ticketId: {fid: {...}}}
$followups = [];
if ($fr['ok'] && is_array($fr['data'])) {
    foreach ($fr['data'] as $tid => $rows) {
        $followups[$tid] = is_array($rows) ? array_values($rows) : [];
    }
}
$kbTitles = [];
foreach (collection_to_list(db_get('knowledge_base')['data'] ?? null) as $a) {
    $kbTitles[$a['id']] = (string) ($a['title'] ?? '');
}
$a = analytics_build($tickets, $followups, $kbTitles);

render_header('Dashboard Admin dan Analitik', 'analytics', 'Gambaran beban kerja dan kualitas penanganan tiket.');
if (!$tr['ok']) {
    echo '<div class="alert alert-error">Gagal membaca tiket: ' . e((string) $tr['error']) . '</div>';
}
foreach ($a['alerts'] as $al) {
    echo '<div class="alert alert-warning"><strong>Peringatan gangguan:</strong> ' . (int) $al['count'] . ' laporan "' . e(label(CATEGORIES, $al['category'])) . '" dalam ' . (int) $al['minutes'] . ' menit terakhir.</div>';
}
?>
<div class="kpis">
    <div class="card"><span class="num"><?= (int) $a['total'] ?></span>Total tiket</div>
    <div class="card"><span class="num"><?= (int) $a['open'] ?></span>Masih terbuka</div>
    <div class="card"><span class="num"><?= (int) $a['done_rate'] ?>%</span>Tiket selesai</div>
    <div class="card"><span class="num"><?= $a['avg_first_response_min'] === null ? '-' : e((string) $a['avg_first_response_min']) . ' mnt' ?></span>Rata-rata respons pertama</div>
    <div class="card"><span class="num"><?= $a['avg_resolution_hours'] === null ? '-' : e((string) $a['avg_resolution_hours']) . ' jam' ?></span>Rata-rata penyelesaian</div>
    <div class="card"><span class="num"><?= (int) $a['flagged_duplicates'] ?></span>Tiket mungkin duplikat</div>
</div>

<div class="card"><h3>Tiket masuk 14 hari terakhir</h3><?php render_day_chart($a['per_day']); ?></div>

<div class="two-col" style="margin-top:16px">
    <div class="card"><h3>Per kategori</h3><?php arsort($a['by_category']); render_bars($a['by_category'], CATEGORIES); ?></div>
    <div class="card"><h3>Per prioritas</h3><?php render_bars($a['by_priority'], PRIORITIES); ?></div>
    <div class="card"><h3>Per status</h3><?php render_bars($a['by_status'], STATUSES); ?></div>
    <div class="card"><h3>Per metode bayar</h3><?php render_bars($a['by_method'], PAYMENT_METHODS); ?></div>
    <div class="card"><h3>Merchant dengan tiket terbanyak</h3><?php render_bars($a['top_merchants']); ?></div>
    <div class="card"><h3>Artikel panduan paling sering terkait</h3>
        <?php if (!$a['top_kb']): ?><p class="muted">Belum ada data. Jalankan "Ringkasan dan draf balasan" pada beberapa tiket.</p><?php endif; ?>
        <?php foreach ($a['top_kb'] as $k): ?>
            <p><a href="<?= e(url('knowledge/view.php?id=' . urlencode($k['id']))) ?>"><?= e($k['title']) ?></a> <span class="muted">(<?= (int) $k['count'] ?> tiket)</span></p>
        <?php endforeach; ?>
    </div>
</div>

<div class="card table-wrap" style="margin-top:16px">
    <h3>Tiket melewati batas respons (SLA)</h3>
    <p class="hint">Batas respons pertama: kritis <?= SLA_HOURS['kritis'] ?> jam, tinggi <?= SLA_HOURS['tinggi'] ?> jam, sedang <?= SLA_HOURS['sedang'] ?> jam, rendah <?= SLA_HOURS['rendah'] ?> jam.</p>
    <table>
        <thead><tr><th>Judul</th><th>Prioritas</th><th>Terlambat</th></tr></thead>
        <tbody>
        <?php if (!$a['sla']): ?><tr><td colspan="3" class="muted">Tidak ada tiket yang melewati batas respons.</td></tr><?php endif; ?>
        <?php foreach ($a['sla'] as $s): ?>
            <tr>
                <td><a href="<?= e(url('tickets/view.php?id=' . urlencode($s['id']))) ?>"><?= e($s['title']) ?></a></td>
                <td><span class="badge p-<?= e($s['priority']) ?>"><?= e(label(PRIORITIES, $s['priority'])) ?></span></td>
                <td><?= e((string) $s['overdue_hours']) ?> jam</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p><a class="btn btn-light" href="<?= e(url('admin/daily.php')) ?>">Ringkasan harian</a></p>
<?php render_footer();
