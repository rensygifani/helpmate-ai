<?php
// Fungsi pendukung modul Riwayat Tindak Lanjut (node followups/{ticketId}/{id}):
// validasi input, hak akses, form, dan tampilan daftar riwayat pada detail tiket.

function followup_input(array $input, array $allowed = FOLLOWUP_STAFF_TYPES): array
{
    $errors = [];
    $clean = [
        'type' => (string) ($input['type'] ?? 'catatan'),
        'content' => trim((string) ($input['content'] ?? '')),
    ];
    if (!in_array($clean['type'], $allowed, true)) {
        $errors[] = 'Jenis tindakan tidak valid.';
    }
    if ($clean['content'] === '' || mb_strlen($clean['content']) > 2000) {
        $errors[] = 'Isi tindakan wajib diisi (maksimal 2000 karakter).';
    }
    return [$clean, $errors];
}

// Hanya petugas (CS, tim teknis, admin) yang boleh mencatat tindakan
function can_add_followup(array $user): bool
{
    return is_staff($user);
}

// Penulis catatan boleh mengubahnya; admin boleh mengubah catatan siapa pun
function can_edit_followup(array $user, array $followup): bool
{
    if (in_array($user['role'] ?? '', ['admin', 'super_admin'], true)) {
        return true;
    }
    return is_staff($user) && ($followup['author_uid'] ?? '') === $user['uid'];
}

function can_delete_followup(array $user, array $followup): bool
{
    return can_edit_followup($user, $followup);
}

function render_followup_form(array $followup, string $action, string $ticketId, string $submitLabel, string $backUrl, string $formId = 'followup-form'): void
{
    ?>
<form method="post" action="<?= e(url($action)) ?>" class="form followup-form" id="<?= e($formId) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="ticket_id" value="<?= e($ticketId) ?>">
    <?php if (!empty($followup['id'])): ?><input type="hidden" name="id" value="<?= e($followup['id']) ?>"><?php endif; ?>
    <label>Jenis tindakan
        <select name="type"><?php render_options(array_intersect_key(FOLLOWUP_TYPES, array_flip(FOLLOWUP_STAFF_TYPES)), $followup['type'] ?? 'catatan'); ?></select>
    </label>
    <label>Isi tindakan
        <textarea name="content" rows="4" maxlength="2000" required><?= e($followup['content'] ?? '') ?></textarea>
    </label>
    <div>
        <button type="submit" class="btn"><?= e($submitLabel) ?></button>
        <a class="btn btn-light" href="<?= e($backUrl) ?>">Batal</a>
    </div>
</form>
<?php
}

// Read: daftar riwayat tindak lanjut satu tiket, dari yang paling awal
function render_followups(array $user, string $ticketId, array $ticket): void
{
    // Merchant hanya menerima catatan yang boleh dibaca merchant (difilter di Firebase)
    $followupsResult = followups_for($user, $ticketId);
    $list = array_reverse($followupsResult['list']);
    $back = url('tickets/view.php?id=' . urlencode($ticketId));
    ?>
    <h3>Riwayat tindak lanjut</h3>
    <?php if (!$followupsResult['ok']): ?>
        <div class="alert alert-error">Gagal membaca riwayat: <?= e((string) $followupsResult['error']) ?></div>
    <?php elseif (!$list): ?>
        <p class="muted">Belum ada tindakan yang dicatat pada tiket ini.</p>
    <?php endif; ?>
    <ol class="timeline">
    <?php foreach ($list as $followup): ?>
        <li>
            <div class="tl-head">
                <span class="badge"><?= e(label(FOLLOWUP_TYPES, $followup['type'] ?? '')) ?></span>
                <strong><?= e($followup['author_name'] ?? '') ?></strong>
                <span class="muted"><?= e(label(ROLES, $followup['author_role'] ?? '')) ?> &middot; <?= e($followup['created_at'] ?? '') ?><?= !empty($followup['edited_at']) ? ' &middot; diubah ' . e($followup['edited_at']) : '' ?></span>
            </div>
            <p><?= nl2br(e($followup['content'] ?? '')) ?></p>
            <?php if (can_edit_followup($user, $followup)): ?>
            <div class="tl-actions">
                <a class="btn btn-small btn-light" href="<?= e(url('followups/edit.php?ticket=' . urlencode($ticketId) . '&id=' . urlencode($followup['id']))) ?>">Ubah</a>
                <form method="post" action="<?= e(url('followups/delete.php')) ?>" onsubmit="return confirm('Hapus catatan ini?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ticket_id" value="<?= e($ticketId) ?>">
                    <input type="hidden" name="id" value="<?= e($followup['id']) ?>">
                    <button type="submit" class="btn btn-small btn-danger">Hapus</button>
                </form>
            </div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ol>
    <?php if (can_add_followup($user)): ?>
        <h4>Catat tindakan baru</h4>
        <?php render_followup_form(['type' => 'catatan', 'content' => ''], 'followups/create.php', $ticketId, 'Simpan tindakan', $back); ?>
    <?php elseif (($ticket['owner_uid'] ?? '') === $user['uid'] && ($ticket['status'] ?? '') !== 'selesai'): ?>
        <h4>Tambah informasi atau balas petugas</h4>
        <form method="post" action="<?= e(url('followups/reply.php')) ?>" class="form followup-form">
            <?= csrf_field() ?>
            <input type="hidden" name="ticket_id" value="<?= e($ticketId) ?>">
            <label>Pesan Anda
                <textarea name="content" rows="4" maxlength="2000" required placeholder="Contoh: ID transaksinya TRX-20261015-001, waktu kejadian pukul 10.05."></textarea>
            </label>
            <p class="muted">Jangan menulis nomor kartu lengkap, PIN, OTP, atau password. Data seperti itu otomatis disamarkan.</p>
            <div><button type="submit" class="btn">Kirim balasan</button></div>
        </form>
    <?php endif;
}
