<?php
// Fungsi pendukung modul Riwayat Tindak Lanjut (node followups/{ticketId}/{id}):
// validasi input, hak akses, form, dan tampilan daftar riwayat pada detail tiket.

function followup_input(array $in): array
{
    $errors = [];
    $clean  = [
        'type'    => (string) ($in['type'] ?? 'catatan'),
        'content' => trim((string) ($in['content'] ?? '')),
    ];
    if (!isset(FOLLOWUP_TYPES[$clean['type']])) {
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
function can_edit_followup(array $user, array $f): bool
{
    if (in_array($user['role'] ?? '', ['admin', 'super_admin'], true)) {
        return true;
    }
    return is_staff($user) && ($f['author_uid'] ?? '') === $user['uid'];
}

function can_delete_followup(array $user, array $f): bool
{
    return can_edit_followup($user, $f);
}

function render_followup_form(array $f, string $action, string $ticketId, string $submitLabel, string $backUrl, string $formId = 'followup-form'): void
{
    ?>
<form method="post" action="<?= e(url($action)) ?>" class="form followup-form" id="<?= e($formId) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="ticket_id" value="<?= e($ticketId) ?>">
    <?php if (!empty($f['id'])): ?><input type="hidden" name="id" value="<?= e($f['id']) ?>"><?php endif; ?>
    <label>Jenis tindakan
        <select name="type"><?php render_options(FOLLOWUP_TYPES, $f['type'] ?? 'catatan'); ?></select>
    </label>
    <label>Isi tindakan
        <textarea name="content" rows="4" maxlength="2000" required><?= e($f['content'] ?? '') ?></textarea>
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
    $r    = db_get('followups/' . $ticketId);
    $list = $r['ok'] ? array_reverse(collection_to_list($r['data'])) : [];
    $back = url('tickets/view.php?id=' . urlencode($ticketId));
    ?>
    <h3>Riwayat tindak lanjut</h3>
    <?php if (!$r['ok']): ?>
        <div class="alert alert-error">Gagal membaca riwayat: <?= e((string) $r['error']) ?></div>
    <?php elseif (!$list): ?>
        <p class="muted">Belum ada tindakan yang dicatat pada tiket ini.</p>
    <?php endif; ?>
    <ol class="timeline">
    <?php foreach ($list as $f): ?>
        <li>
            <div class="tl-head">
                <span class="badge"><?= e(label(FOLLOWUP_TYPES, $f['type'] ?? '')) ?></span>
                <strong><?= e($f['author_name'] ?? '') ?></strong>
                <span class="muted"><?= e(label(ROLES, $f['author_role'] ?? '')) ?> &middot; <?= e($f['created_at'] ?? '') ?><?= !empty($f['edited_at']) ? ' &middot; diubah ' . e($f['edited_at']) : '' ?></span>
            </div>
            <p><?= nl2br(e($f['content'] ?? '')) ?></p>
            <?php if (can_edit_followup($user, $f)): ?>
            <div class="tl-actions">
                <a class="btn btn-small btn-light" href="<?= e(url('followups/edit.php?ticket=' . urlencode($ticketId) . '&id=' . urlencode($f['id']))) ?>">Ubah</a>
                <form method="post" action="<?= e(url('followups/delete.php')) ?>" onsubmit="return confirm('Hapus catatan ini?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ticket_id" value="<?= e($ticketId) ?>">
                    <input type="hidden" name="id" value="<?= e($f['id']) ?>">
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
    <?php endif;
}
