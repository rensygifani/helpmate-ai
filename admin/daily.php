<?php
// Ringkasan harian untuk admin. Angka dihitung dari data tiket, lalu Gemini menulis ringkasan dan saran tindak lanjut.
// Hasilnya disimpan di node daily_summaries/{tanggal}. Pembuatan otomatis tersedia lewat cron/daily.php.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();

$date = (string) ($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
    $date = date('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $r = daily_generate($date, null, $user['name']);
    flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Ringkasan harian untuk ' . $date . ' berhasil dibuat.' : (string) $r['error']);
    redirect('admin/daily.php?date=' . urlencode($date));
}

$tr    = db_get('tickets');
$stats = daily_stats($date, $tr['ok'] ? collection_to_list($tr['data']) : []);
$sr    = db_get('daily_summaries/' . $date);
$saved = $sr['ok'] && is_array($sr['data']) ? $sr['data'] : null;

render_header('Ringkasan Harian', 'analytics', 'Ringkasan penanganan tiket per hari untuk atasan.');
?>
<form method="get" class="card form" style="max-width:520px">
    <label>Tanggal <input type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
    <div><button type="submit" class="btn btn-light">Tampilkan</button></div>
</form>

<div class="kpis" style="margin-top:16px">
    <div class="card"><span class="num"><?= (int) $stats['tiket_masuk'] ?></span>Tiket masuk</div>
    <div class="card"><span class="num"><?= (int) $stats['tiket_selesai'] ?></span>Tiket selesai</div>
    <div class="card"><span class="num"><?= (int) $stats['tiket_masih_terbuka'] ?></span>Masih terbuka</div>
</div>
<div class="two-col">
    <div class="card"><h3>Per kategori</h3><?php render_bars($stats['per_kategori']); ?></div>
    <div class="card"><h3>Per prioritas</h3><?php render_bars($stats['per_prioritas']); ?></div>
</div>

<div class="card" style="margin-top:16px">
    <h3>Ringkasan AI</h3>
    <?php if ($saved): ?>
        <p class="muted">Dibuat <?= e($saved['generated_at'] ?? '') ?> oleh <?= e($saved['generated_by'] ?? '') ?></p>
        <p><?= nl2br(e($saved['summary'] ?? '')) ?></p>
        <?php if (!empty($saved['highlights'])): ?><p><strong>Hal penting</strong></p><ul><?php foreach ($saved['highlights'] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <?php if (!empty($saved['recommendations'])): ?><p><strong>Saran tindak lanjut</strong></p><ul><?php foreach ($saved['recommendations'] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php else: ?>
        <p class="muted">Belum ada ringkasan untuk tanggal ini.</p>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="date" value="<?= e($date) ?>">
        <button type="submit" class="btn"><?= $saved ? 'Buat ulang ringkasan AI' : 'Buat ringkasan AI' ?></button>
    </form>
</div>
<p><a class="btn btn-light" href="<?= e(url('admin/analytics.php')) ?>">Kembali ke analitik</a></p>
<?php render_footer();
