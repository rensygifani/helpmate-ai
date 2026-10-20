<?php
// Read: tampilkan detail satu tiket beserta lampiran buktinya
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
require __DIR__ . '/../includes/followup_form.php';
require __DIR__ . '/../includes/ai_panel.php';
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

// Lampiran bukti (node ticket_files/{id}), urut dari yang paling awal
$fr    = db_get('ticket_files/' . $id);
$files = $fr['ok'] ? array_reverse(collection_to_list($fr['data'])) : [];

// Judul artikel Knowledge Base, dipakai untuk menampilkan artikel terkait dari AI
$kbById = [];
if (is_staff($user) && !empty($t['ai_reply']['related_kb_ids'])) {
    foreach (collection_to_list(db_get('knowledge_base')['data'] ?? null) as $a) {
        $kbById[$a['id']] = (string) ($a['title'] ?? '');
    }
}

// Tiket serupa dari merchant yang sama (hanya untuk petugas)
$similar = is_staff($user) ? find_similar_tickets($t + ['id' => $id], tickets_of_owner((string) ($t['owner_uid'] ?? '')), 4, 5) : [];

render_header('Detail Tiket', 'tickets');
?>
<div class="card detail">
    <h2><?= e($t['title'] ?? '') ?> <?php if (is_staff($user) && !empty($t['escalated']) && ($t['status'] ?? '') !== 'selesai'): ?><span class="badge p-kritis">Tereskalasi</span> <?php endif; ?><?php if (is_staff($user) && !empty($t['possible_duplicate_of'])): ?><span class="badge p-tinggi" title="Sistem menemukan tiket serupa dari merchant yang sama">Mungkin duplikat</span><?php endif; ?></h2>
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
        <?php if (!empty($t['owner_type'])): ?>
        <dt>Jenis akun</dt><dd><?= e(label(ACCOUNT_TYPES, $t['owner_type'])) ?><?= !empty($t['owner_org']) ? ' - ' . e($t['owner_org']) : '' ?></dd>
        <?php endif; ?>
        <dt>Dibuat</dt><dd><?= e($t['created_at'] ?? '') ?></dd>
        <dt>Diubah</dt><dd><?= e($t['updated_at'] ?? '') ?></dd>
    </dl>
    <h3>Deskripsi</h3>
    <p><?= nl2br(e($t['description'] ?? '')) ?></p>

    <?php if ($files): ?>
    <h3>Lampiran bukti</h3>
    <div class="att-gallery">
        <?php foreach ($files as $f):
            if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', (string) ($f['data'] ?? ''))) continue; ?>
            <img src="<?= e($f['data']) ?>" alt="<?= e($f['name'] ?? 'Lampiran') ?>" title="Klik untuk memperbesar">
        <?php endforeach; ?>
    </div>
    <script>
    document.querySelectorAll('.att-gallery img').forEach(function (im) {
        im.addEventListener('click', function () {
            var o = document.createElement('div'); o.className = 'lightbox';
            var b = document.createElement('img'); b.src = im.src; o.appendChild(b);
            o.addEventListener('click', function () { o.remove(); });
            document.body.appendChild(o);
        });
    });
    </script>
    <?php endif; ?>

    <?php render_ai_panel($user, $id, $t, $kbById, $similar); ?>
    <?php render_followups($user, $id, $t); ?>

    <p>
        <a class="btn btn-light" href="<?= e(url('tickets/index.php')) ?>">Kembali</a>
        <?php if (can_edit_ticket($user, $t)): ?>
            <a class="btn" href="<?= e(url('tickets/edit.php?id=' . urlencode($id))) ?>">Ubah</a>
        <?php endif; ?>
    </p>
</div>
<?php render_footer();