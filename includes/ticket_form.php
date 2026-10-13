<?php
// Fungsi-fungsi pendukung untuk modul Tiket (validasi input, hak akses, dan form)

function ticket_input(array $in, bool $staff): array
{
    $errors = [];
    $clean  = [
        'title'          => trim((string) ($in['title'] ?? '')),
        'description'    => trim((string) ($in['description'] ?? '')),
        'category'       => (string) ($in['category'] ?? 'lainnya'),
        'payment_method' => (string) ($in['payment_method'] ?? ''),
        'transaction_id' => trim((string) ($in['transaction_id'] ?? '')),
        'amount'         => trim((string) ($in['amount'] ?? '')),
    ];

    if ($clean['title'] === '' || mb_strlen($clean['title']) > 120) {
        $errors[] = 'Judul wajib diisi (maksimal 120 karakter).';
    }
    if ($clean['description'] === '' || mb_strlen($clean['description']) > 3000) {
        $errors[] = 'Deskripsi wajib diisi (maksimal 3000 karakter).';
    }
    if (!isset(CATEGORIES[$clean['category']])) {
        $errors[] = 'Kategori tidak valid.';
    }
    if ($clean['payment_method'] !== '' && !isset(PAYMENT_METHODS[$clean['payment_method']])) {
        $errors[] = 'Metode bayar tidak valid.';
    }
    if (mb_strlen($clean['transaction_id']) > 60) {
        $errors[] = 'ID transaksi maksimal 60 karakter.';
    }
    if ($clean['amount'] === '') {
        $clean['amount'] = null;
    } elseif (!ctype_digit($clean['amount'])) {
        $errors[] = 'Nominal harus berupa angka tanpa titik atau koma.';
    } else {
        $clean['amount'] = (int) $clean['amount'];
    }

    if ($staff) {
        $priority = (string) ($in['priority'] ?? 'sedang');
        $status   = (string) ($in['status'] ?? 'baru');
        if (!isset(PRIORITIES[$priority])) {
            $errors[] = 'Prioritas tidak valid.';
        }
        if (!isset(STATUSES[$status])) {
            $errors[] = 'Status tidak valid.';
        }
        $clean['priority'] = $priority;
        $clean['status']   = $status;
    }
    return [$clean, $errors];
}

function can_edit_ticket(array $user, array $ticket): bool
{
    if (is_staff($user)) {
        return true;
    }
    return ($ticket['owner_uid'] ?? '') === $user['uid'] && ($ticket['status'] ?? '') === 'baru';
}

function can_delete_ticket(array $user, array $ticket): bool
{
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    return ($ticket['owner_uid'] ?? '') === $user['uid'] && ($ticket['status'] ?? '') === 'baru';
}

function can_view_ticket(array $user, array $ticket): bool
{
    return is_staff($user) || ($ticket['owner_uid'] ?? '') === $user['uid'];
}

function render_ticket_form(array $t, bool $staff, string $submitLabel): void
{
    ?>
<form method="post" class="card form">
    <?= csrf_field() ?>
    <label>Judul kendala
        <input type="text" name="title" value="<?= e($t['title'] ?? '') ?>" maxlength="120" required>
    </label>
    <label>Kategori
        <select name="category"><?php render_options(CATEGORIES, $t['category'] ?? 'lainnya'); ?></select>
    </label>
    <label>Deskripsi (ceritakan kronologi kejadian)
        <textarea name="description" rows="6" maxlength="3000" required><?= e($t['description'] ?? '') ?></textarea>
    </label>
    <div class="row">
        <label>Metode bayar
            <select name="payment_method"><?php render_options(PAYMENT_METHODS, $t['payment_method'] ?? '', true); ?></select>
        </label>
        <label>ID transaksi
            <input type="text" name="transaction_id" value="<?= e($t['transaction_id'] ?? '') ?>" maxlength="60">
        </label>
        <label>Nominal (rupiah, angka saja)
            <input type="text" name="amount" value="<?= e((string) ($t['amount'] ?? '')) ?>" inputmode="numeric">
        </label>
    </div>
    <?php if ($staff): ?>
    <div class="row">
        <label>Prioritas
            <select name="priority"><?php render_options(PRIORITIES, $t['priority'] ?? 'sedang'); ?></select>
        </label>
        <label>Status
            <select name="status"><?php render_options(STATUSES, $t['status'] ?? 'baru'); ?></select>
        </label>
    </div>
    <?php endif; ?>
    <div>
        <button type="submit" class="btn"><?= e($submitLabel) ?></button>
        <a class="btn btn-light" href="<?= e(url('tickets/index.php')) ?>">Batal</a>
    </div>
</form>
<?php
}
