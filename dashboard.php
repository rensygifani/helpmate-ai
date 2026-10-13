<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$tickets = collection_to_list(db_get('tickets')['data'] ?? null);
if (!is_staff($user)) {
    $tickets = array_values(array_filter($tickets, fn($t) => ($t['owner_uid'] ?? '') === $user['uid']));
}
$kb = collection_to_list(db_get('knowledge_base')['data'] ?? null);

$byStatus = [];
foreach ($tickets as $t) {
    $s = $t['status'] ?? 'baru';
    $byStatus[$s] = ($byStatus[$s] ?? 0) + 1;
}

render_header('Dashboard', 'dashboard');
?>
<div class="grid">
    <div class="card stat"><span class="num"><?= count($tickets) ?></span><span>Total tiket<?= is_staff($user) ? '' : ' Anda' ?></span></div>
    <?php foreach (STATUSES as $key => $text): ?>
        <div class="card stat"><span class="num"><?= (int) ($byStatus[$key] ?? 0) ?></span><span><?= e($text) ?></span></div>
    <?php endforeach; ?>
    <div class="card stat"><span class="num"><?= count($kb) ?></span><span>Artikel panduan</span></div>
</div>
<p>
    <a class="btn" href="<?= e(url('tickets/create.php')) ?>">Buat tiket baru</a>
    <a class="btn btn-light" href="<?= e(url('knowledge/index.php')) ?>">Lihat Knowledge Base</a>
</p>
<?php render_footer();
