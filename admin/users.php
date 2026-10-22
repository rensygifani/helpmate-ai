<?php
// Khusus Super Admin: lihat pengguna, ubah role, dan tambah pengguna baru
require __DIR__ . '/../includes/bootstrap.php';
$user = require_super_admin();

$errors = [];
$fieldErrors = [];
$newAccount = ['name' => '', 'email' => '', 'role' => 'cs', 'account_type' => '', 'organization' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    // Ubah role pengguna yang sudah ada
    if ($action === 'role') {
        $uid = (string) ($_POST['uid'] ?? '');
        $role = (string) ($_POST['role'] ?? '');
        if (!valid_id($uid) || !isset(ROLES[$role])) {
            flash('error', 'Data tidak valid.');
        } elseif ($uid === $user['uid']) {
            flash('error', 'Anda tidak dapat mengubah role akun sendiri.');
        } else {
            $patchResponse = db_patch('users/' . $uid, ['role' => $role]);
            flash(
                $patchResponse['ok'] ? 'success' : 'error',
                $patchResponse['ok'] ? 'Role berhasil diubah. Pengguna perlu login ulang agar role baru aktif.' : 'Gagal mengubah role: ' . $patchResponse['error']
            );
        }
        redirect('admin/users.php');
    }

    // Tambah pengguna baru
    if ($action === 'add') {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? '')));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $pass = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? '');
        [$accountFields, $accErrors] = account_input($_POST);
        $newAccount = ['name' => $name, 'email' => $email, 'role' => $role] + $accountFields;
        $fieldErrors = $accErrors;

        if ($name === '') {
            $fieldErrors['name'][] = 'Nama wajib diisi.';
        } elseif (mb_strlen($name) > 80 || !preg_match('/^\p{L}[\p{L} .\'-]*$/u', $name)) {
            $fieldErrors['name'][] = 'Nama hanya boleh berisi huruf dan spasi (maksimal 80 karakter).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $email)) {
            $fieldErrors['email'][] = 'Format email tidak valid. Contoh: nama@gmail.com';
        }
        if (pw_failed($pass)) {
            $fieldErrors['password'][] = 'Password minimal 8 karakter dan harus ada huruf kapital, huruf kecil, angka, dan karakter khusus.';
        }
        if (!isset(ROLES[$role])) {
            $fieldErrors['role'][] = 'Pilih role.';
        }

        if (!$fieldErrors) {
            $signUp = firebase_auth('signUp', ['email' => $email, 'password' => $pass, 'returnSecureToken' => true]);
            if (!$signUp['ok']) {
                if (str_starts_with((string) $signUp['error'], 'EMAIL_EXISTS')) {
                    $fieldErrors['email'][] = 'Email ini sudah terdaftar.';
                } else {
                    $errors[] = auth_error_text($signUp['error']);
                }
            } else {
                $newUid = $signUp['data']['localId'];
                $newToken = $signUp['data']['idToken'];
                $mail = firebase_auth('sendOobCode', ['requestType' => 'VERIFY_EMAIL', 'idToken' => $newToken]);

                // Profil ditulis memakai token Super Admin (diizinkan oleh Security Rules)
                $profile = db_put('users/' . $newUid, [
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'account_type' => $accountFields['account_type'],
                    'organization' => $accountFields['organization'],
                    'created_at' => now_string(),
                    'created_by' => $user['uid'],
                ]);
                if (!$profile['ok']) {
                    flash('error', 'Akun Auth dibuat, tetapi profil gagal disimpan: ' . $profile['error']);
                } elseif (!$mail['ok']) {
                    flash('warning', 'Pengguna ditambahkan, tetapi email verifikasi gagal dikirim. Pengguna bisa kirim ulang dari halaman login.');
                } else {
                    flash('success', 'Pengguna ' . $name . ' ditambahkan sebagai ' . label(ROLES, $role) . '. Email verifikasi sudah dikirim ke ' . $email . '.');
                }
                redirect('admin/users.php');
            }
        }
    }
}

$usersResponse = db_get('users');
$loadError = !$usersResponse['ok'];
$users = $usersResponse['ok'] ? collection_to_list($usersResponse['data']) : [];

render_header('Kelola Pengguna', 'users', 'Tambah pengguna dan atur peran (role) akun.');
render_errors($errors);
if ($loadError) {
    echo '<div class="alert alert-error">Gagal membaca daftar pengguna. Pastikan Security Rules sudah diperbarui dan role akun Anda adalah super_admin.</div>';
}
?>
<div class="card table-wrap">
<table>
    <thead><tr><th>Pengguna</th><th>Jenis akun</th><th>Role</th></tr></thead>
    <tbody>
    <?php if (!$users): ?>
        <tr><td colspan="3" class="muted">Belum ada pengguna.</td></tr>
    <?php endif; ?>
    <?php foreach ($users as $account): $self = $account['id'] === $user['uid']; ?>
        <tr>
            <td><strong><?= e($account['name'] ?? '') ?></strong><?= $self ? ' <span class="muted">(Anda)</span>' : '' ?><br><span class="muted"><?= e($account['email'] ?? '') ?></span></td>
            <td><?= e(label(ACCOUNT_TYPES, $account['account_type'] ?? '') ?: '-') ?><?php if (!empty($account['organization'])): ?><br><span class="muted"><?= e($account['organization']) ?></span><?php endif; ?></td>
            <td>
                <?php if ($self): ?>
                    <span class="badge r-<?= e($account['role'] ?? '') ?>"><?= e(label(ROLES, $account['role'] ?? '')) ?></span>
                <?php else: ?>
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="role">
                    <input type="hidden" name="uid" value="<?= e($account['id']) ?>">
                    <select name="role">
                        <?php foreach (ROLES as $roleKey => $roleLabel): ?>
                            <option value="<?= e($roleKey) ?>"<?= ($account['role'] ?? '') === $roleKey ? ' selected' : '' ?>><?= e($roleLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-small">Simpan</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<form method="post" class="card form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <h2>Tambah pengguna baru</h2>
    <label>Nama
        <input type="text" name="name" value="<?= e($newAccount['name']) ?>" maxlength="80">
        <?php field_errors($fieldErrors, 'name'); ?>
    </label>
    <label>Email
        <input type="email" name="email" value="<?= e($newAccount['email']) ?>" placeholder="nama@gmail.com">
        <?php field_errors($fieldErrors, 'email'); ?>
    </label>
    <label>Password awal
        <input type="password" name="password" autocomplete="new-password">
        <?php field_errors($fieldErrors, 'password'); ?>
    </label>
    <label>Role
        <select name="role">
            <?php foreach (ROLES as $roleKey => $roleLabel): ?>
                <option value="<?= e($roleKey) ?>"<?= $newAccount['role'] === $roleKey ? ' selected' : '' ?>><?= e($roleLabel) ?></option>
            <?php endforeach; ?>
        </select>
        <?php field_errors($fieldErrors, 'role'); ?>
    </label>
    <?php render_account_fields($newAccount['account_type'], $newAccount['organization'], $fieldErrors); ?>
    <p class="muted">Pengguna baru akan menerima email verifikasi dan harus memverifikasinya sebelum bisa login.</p>
    <div><button type="submit" class="btn">Tambah pengguna</button></div>
</form>
<?php render_footer();
