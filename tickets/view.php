<?php
// Read: tampilkan detail satu tiket beserta lampiran buktinya
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
require __DIR__ . '/../includes/followup_form.php';
require __DIR__ . '/../includes/ai_panel.php';
$user = require_login();

$id = (string) ($_GET['id'] ?? '');
$ticketResponse = $id !== '' ? db_get('tickets/' . $id) : ['ok' => false, 'data' => null];
$ticket = $ticketResponse['ok'] && is_array($ticketResponse['data']) ? $ticketResponse['data'] : null;

if ($ticket === null || !can_view_ticket($user, $ticket)) {
    http_response_code(404);
    render_header('Tiket tidak ditemukan', 'tickets');
    echo '<p>Tiket tidak ditemukan atau Anda tidak memiliki akses.</p><p><a class="btn" href="' . e(url('tickets/index.php')) . '">Kembali</a></p>';
    render_footer();
    exit;
}

// Lampiran bukti (node ticket_files/{id}), urut dari yang paling awal
$filesResponse = db_get('ticket_files/' . $id);
$files = $filesResponse['ok'] ? array_reverse(collection_to_list($filesResponse['data'])) : [];

// Judul artikel Knowledge Base, dipakai untuk menampilkan artikel terkait dari AI
$kbById = [];
if (is_staff($user) && !empty($ticket['ai_reply']['related_kb_ids'])) {
    foreach (collection_to_list(db_get('knowledge_base')['data'] ?? null) as $article) {
        $kbById[$article['id']] = (string) ($article['title'] ?? '');
    }
}

// Tiket serupa dari merchant yang sama (hanya untuk petugas)
$similar = is_staff($user) ? find_similar_tickets($ticket + ['id' => $id], tickets_of_owner((string) ($ticket['owner_uid'] ?? '')), 4, 5) : [];

render_header('Detail Tiket', 'tickets');
?>
<div class="card detail">
    <h2><?= e($ticket['title'] ?? '') ?> <?php if (is_staff($user) && !empty($ticket['escalated']) && ($ticket['status'] ?? '') !== 'selesai'): ?><span class="badge p-kritis">Tereskalasi</span> <?php endif; ?><?php if (is_staff($user) && !empty($ticket['possible_duplicate_of'])): ?><span class="badge p-tinggi" title="Sistem menemukan tiket serupa dari merchant yang sama">Mungkin duplikat</span><?php endif; ?></h2>
    <p>
        <span class="badge p-<?= e($ticket['priority'] ?? '') ?>">Prioritas: <?= e(label(PRIORITIES, $ticket['priority'] ?? '')) ?></span>
        <span class="badge s-<?= e($ticket['status'] ?? '') ?>"><?= e(label(STATUSES, $ticket['status'] ?? '')) ?></span>
    </p>
    <dl>
        <dt>Kategori</dt><dd><?= e(label(CATEGORIES, $ticket['category'] ?? '')) ?></dd>
        <dt>Metode bayar</dt><dd><?= e(label(PAYMENT_METHODS, $ticket['payment_method'] ?? '') ?: '-') ?></dd>
        <dt>ID transaksi</dt><dd><?= e($ticket['transaction_id'] ?? '') ?: '-' ?></dd>
        <dt>Nominal</dt><dd><?= e(rupiah($ticket['amount'] ?? null)) ?></dd>
        <dt>Pelapor</dt><dd><?= e($ticket['owner_name'] ?? '') ?> (<?= e($ticket['owner_email'] ?? '') ?>)</dd>
        <?php if (!empty($ticket['owner_type'])): ?>
        <dt>Jenis akun</dt><dd><?= e(label(ACCOUNT_TYPES, $ticket['owner_type'])) ?><?= !empty($ticket['owner_org']) ? ' - ' . e($ticket['owner_org']) : '' ?></dd>
        <?php endif; ?>
        <dt>Dibuat</dt><dd><?= e($ticket['created_at'] ?? '') ?></dd>
        <dt>Diubah</dt><dd><?= e($ticket['updated_at'] ?? '') ?></dd>
    </dl>
    <h3>Deskripsi</h3>
    <p><?= nl2br(e($ticket['description'] ?? '')) ?></p>

    <?php if ($files): ?>
    <h3>Lampiran bukti</h3>
    <div class="att-gallery">
        <?php foreach ($files as $file):
            if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', (string) ($file['data'] ?? ''))) {
                continue;
            } ?>
            <img src="<?= e($file['data']) ?>" alt="<?= e($file['name'] ?? 'Lampiran') ?>" title="Klik untuk memperbesar">
        <?php endforeach; ?>
    </div>
    <script>
    document.querySelectorAll('.att-gallery img').forEach(function (thumbnail) {
        thumbnail.addEventListener('click', function () {
            var overlay = document.createElement('div'); overlay.className = 'lightbox';
            var largeImage = document.createElement('img'); largeImage.src = thumbnail.src; overlay.appendChild(largeImage);
            overlay.addEventListener('click', function () { overlay.remove(); });
            document.body.appendChild(overlay);
        });
    });
    </script>
    <?php endif; ?>

    <?php render_ai_panel($user, $id, $ticket, $kbById, $similar); ?>
    <?php render_followups($user, $id, $ticket); ?>

    <p>
        <a class="btn btn-light" href="<?= e(url('tickets/index.php')) ?>">Kembali</a>
        <?php if (can_edit_ticket($user, $ticket)): ?>
            <a class="btn" href="<?= e(url('tickets/edit.php?id=' . urlencode($id))) ?>">Ubah</a>
        <?php endif; ?>
    </p>
</div>
<?php render_footer();
