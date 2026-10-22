<?php
// Akun saya: data akun, foto profil, data diri, dan ubah password
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$profileResponse = db_get('users/' . $user['uid']);
$profile = $profileResponse['ok'] && is_array($profileResponse['data']) ? $profileResponse['data'] : [];

$form = [
    'name' => (string) ($profile['name'] ?? $user['name']),
    'phone' => (string) ($profile['phone'] ?? ''),
    'account_type' => (string) ($profile['account_type'] ?? ''),
    'organization' => (string) ($profile['organization'] ?? ''),
];
$infoErr = [];
$photoErr = [];
$pwErr = [];
$pwFailed = [];
$pwSubmitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    // Ubah data diri
    if ($action === 'info') {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        [$accountFields, $accErr] = account_input($_POST);
        $form = ['name' => $name, 'phone' => $phone] + $accountFields;

        if ($name === '') {
            $infoErr['name'][] = 'Nama wajib diisi.';
        } elseif (mb_strlen($name) > 80 || !preg_match('/^\p{L}[\p{L} .\'-]*$/u', $name)) {
            $infoErr['name'][] = 'Nama hanya boleh berisi huruf dan spasi (maksimal 80 karakter).';
        }
        if ($phone !== '' && !preg_match('/^\+?[0-9][0-9 \-]{7,18}$/', $phone)) {
            $infoErr['phone'][] = 'Nomor telepon tidak valid. Contoh: 081234567890';
        }
        $infoErr += $accErr;

        if (!$infoErr) {
            $saveResponse = db_patch('users/' . $user['uid'], ['name' => $name, 'phone' => $phone] + $accountFields);
            if ($saveResponse['ok']) {
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['account_type'] = $accountFields['account_type'];
                $_SESSION['user']['organization'] = $accountFields['organization'];
                flash('success', 'Data diri berhasil diperbarui.');
                redirect('profile.php');
            }
            $infoErr['name'][] = 'Gagal menyimpan: ' . $saveResponse['error'];
        }
    }

    // Ganti foto profil
    if ($action === 'photo') {
        $photoData = (string) ($_POST['photo_data'] ?? '');
        if (!preg_match('#^data:image/jpeg;base64,#', $photoData, $matches)) {
            $photoErr[] = 'Pilih foto terlebih dahulu.';
        } elseif (strlen($photoData) > 90000) {
            $photoErr[] = 'Ukuran foto terlalu besar. Pilih foto lain.';
        } else {
            $raw = base64_decode(substr($photoData, strlen($matches[0])), true);
            if ($raw === false || @getimagesizefromstring($raw) === false) {
                $photoErr[] = 'File yang dipilih bukan gambar yang valid.';
            } else {
                $saveResponse = db_patch('users/' . $user['uid'], ['photo' => $photoData]);
                if ($saveResponse['ok']) {
                    $_SESSION['user']['photo'] = $photoData;
                    flash('success', 'Foto profil berhasil diperbarui.');
                    redirect('profile.php');
                }
                $photoErr[] = 'Gagal menyimpan foto: ' . $saveResponse['error'];
            }
        }
    }

    // Hapus foto profil
    if ($action === 'remove_photo') {
        $saveResponse = db_delete('users/' . $user['uid'] . '/photo');
        if ($saveResponse['ok']) {
            $_SESSION['user']['photo'] = '';
            flash('success', 'Foto profil dihapus.');
        } else {
            flash('error', 'Gagal menghapus foto: ' . $saveResponse['error']);
        }
        redirect('profile.php');
    }

    // Ubah password
    if ($action === 'password') {
        $pwSubmitted = true;
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '') {
            $pwErr['current'][] = 'Password saat ini wajib diisi.';
        }
        $pwFailed = pw_failed($newPassword);
        if ($pwFailed) {
            $pwErr['new'][] = 'Password baru belum memenuhi syarat di bawah.';
        } elseif ($currentPassword !== '' && $newPassword === $currentPassword) {
            $pwErr['new'][] = 'Password baru tidak boleh sama dengan password saat ini.';
        }
        if ($confirmPassword === '') {
            $pwErr['confirm'][] = 'Konfirmasi password wajib diisi.';
        } elseif ($newPassword !== $confirmPassword) {
            $pwErr['confirm'][] = 'Konfirmasi password tidak sama dengan password baru.';
        }

        if (!$pwErr) {
            // Verifikasi password lama dengan login ulang, lalu ganti password
            $reauthResponse = firebase_auth('signInWithPassword', ['email' => $user['email'], 'password' => $currentPassword, 'returnSecureToken' => true]);
            if (!$reauthResponse['ok']) {
                $pwErr['current'][] = str_starts_with((string) $reauthResponse['error'], 'INVALID_')
                    ? 'Password saat ini salah.'
                    : auth_error_text($reauthResponse['error']);
            } else {
                $updateResponse = firebase_auth('update', ['idToken' => $reauthResponse['data']['idToken'], 'password' => $newPassword, 'returnSecureToken' => true]);
                if (!$updateResponse['ok']) {
                    $pwErr['new'][] = auth_error_text($updateResponse['error']);
                } else {
                    // Firebase mencabut token lama saat password berubah, jadi simpan token baru
                    $_SESSION['user']['idToken'] = $updateResponse['data']['idToken'] ?? $reauthResponse['data']['idToken'];
                    $_SESSION['user']['refreshToken'] = $updateResponse['data']['refreshToken'] ?? $reauthResponse['data']['refreshToken'];
                    $_SESSION['user']['expires_at'] = time() + (int) ($updateResponse['data']['expiresIn'] ?? 3600);
                    flash('success', 'Password berhasil diubah.');
                    redirect('profile.php');
                }
            }
        }
    }
}

$displayUser = ['name' => $profile['name'] ?? $user['name'], 'photo' => $profile['photo'] ?? ''];

render_header('Akun Saya', 'profile', 'Kelola foto, data diri, dan keamanan akun Anda.');
?>
<!-- Ringkasan akun -->
<div class="card">
    <div class="profile-head">
        <?php render_avatar($displayUser, 'avatar-lg'); ?>
        <div>
            <h2 style="margin:0"><?= e($displayUser['name']) ?></h2>
            <div><span class="badge r-<?= e($user['role']) ?>"><?= e(label(ROLES, $user['role'])) ?></span></div>
            <div class="muted"><?= e($user['email']) ?></div>
        </div>
    </div>
    <dl>
        <dt>Nomor telepon</dt><dd><?= e($profile['phone'] ?? '') ?: '-' ?></dd>
        <dt>Jenis akun</dt><dd><?= e(label(ACCOUNT_TYPES, $profile['account_type'] ?? '') ?: '-') ?></dd>
        <dt>Institusi</dt><dd><?= e($profile['organization'] ?? '') ?: '-' ?></dd>
        <dt>Terdaftar sejak</dt><dd><?= e($profile['created_at'] ?? '-') ?></dd>
    </dl>
</div>

<!-- Foto profil -->
<form method="post" class="card form" id="photo-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="photo">
    <input type="hidden" name="photo_data" id="photo-data" value="">
    <h2>Foto profil</h2>
    <?php render_errors($photoErr); ?>
    <div class="photo-row">
        <div id="photo-preview"><?php render_avatar($displayUser, 'avatar-xl'); ?></div>
        <div class="photo-actions">
            <input type="file" id="photo-input" accept="image/*">
            <span class="hint">Foto otomatis dipotong persegi dan dikecilkan.</span>
            <div class="field-error" id="photo-error"></div>
            <div><button type="submit" class="btn btn-small" id="photo-save" disabled>Simpan foto</button></div>
        </div>
    </div>
</form>
<?php if (!empty($profile['photo'])): ?>
<form method="post" class="card form" onsubmit="return confirm('Hapus foto profil?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="remove_photo">
    <div><button type="submit" class="btn btn-danger btn-small">Hapus foto profil</button></div>
</form>
<?php endif; ?>

<!-- Data diri -->
<form method="post" class="card form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="info">
    <h2>Data diri</h2>
    <label>Nama
        <input type="text" name="name" value="<?= e($form['name']) ?>" maxlength="80" class="<?= !empty($infoErr['name']) ? 'invalid' : '' ?>">
        <?php field_errors($infoErr, 'name'); ?>
    </label>
    <label>Email
        <input type="email" value="<?= e($user['email']) ?>" disabled>
        <span class="hint">Email tidak dapat diubah.</span>
    </label>
    <label>Nomor telepon (opsional)
        <input type="tel" name="phone" value="<?= e($form['phone']) ?>" maxlength="20" placeholder="081234567890" class="<?= !empty($infoErr['phone']) ? 'invalid' : '' ?>">
        <?php field_errors($infoErr, 'phone'); ?>
    </label>
    <?php render_account_fields($form['account_type'], $form['organization'], $infoErr); ?>
    <div><button type="submit" class="btn">Simpan data diri</button></div>
</form>

<!-- Ubah password -->
<form method="post" class="card form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <h2>Ubah password</h2>
    <label>Password saat ini
        <input type="password" name="current_password" autocomplete="current-password" class="<?= !empty($pwErr['current']) ? 'invalid' : '' ?>">
        <?php field_errors($pwErr, 'current'); ?>
    </label>
    <label>Password baru
        <input type="password" name="new_password" id="new-password" autocomplete="new-password" class="<?= !empty($pwErr['new']) ? 'invalid' : '' ?>">
        <?php field_errors($pwErr, 'new'); ?>
        <ul class="pw-rules" id="pw-rules">
            <?php foreach (pw_rules() as $key => $rule):
                $cssClass = $pwSubmitted ? (in_array($key, $pwFailed, true) ? 'bad' : 'ok') : '';
                $ruleText = $cssClass === 'bad' ? $rule['bad'] : $rule['ok']; ?>
                <li data-rule="<?= e($key) ?>" data-ok="<?= e($rule['ok']) ?>" data-bad="<?= e($rule['bad']) ?>" class="<?= $cssClass ?>"><?= e($ruleText) ?></li>
            <?php endforeach; ?>
        </ul>
    </label>
    <label>Konfirmasi password baru
        <input type="password" name="confirm_password" autocomplete="new-password" class="<?= !empty($pwErr['confirm']) ? 'invalid' : '' ?>">
        <?php field_errors($pwErr, 'confirm'); ?>
    </label>
    <div><button type="submit" class="btn">Ubah password</button></div>
</form>

<script>
// Pratinjau, potong persegi 256x256, dan kompres foto sebelum dikirim
(function () {
    var input = document.getElementById('photo-input');
    var hiddenInput = document.getElementById('photo-data');
    var saveButton = document.getElementById('photo-save');
    var previewBox = document.getElementById('photo-preview');
    var errorBox = document.getElementById('photo-error');

    input.addEventListener('change', function () {
        errorBox.textContent = '';
        var file = input.files[0];
        if (!file) return;
        if (!/^image\//.test(file.type)) { errorBox.textContent = 'Pilih file gambar (JPG, PNG, WebP).'; return; }
        var img = new Image(), url = URL.createObjectURL(file);
        img.onload = function () {
            var SIZE = 256, side = Math.min(img.width, img.height);
            var sx = (img.width - side) / 2, sy = (img.height - side) / 2, quality = 0.85, dataUrl = '';
            var canvas = document.createElement('canvas'); canvas.width = SIZE; canvas.height = SIZE;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, SIZE, SIZE);
            ctx.drawImage(img, sx, sy, side, side, 0, 0, SIZE, SIZE);
            do { dataUrl = canvas.toDataURL('image/jpeg', quality); quality -= 0.1; } while (dataUrl.length > 80000 && quality > 0.3);
            URL.revokeObjectURL(url);
            if (dataUrl.length > 80000) { errorBox.textContent = 'Foto terlalu besar, pilih foto lain.'; return; }
            hiddenInput.value = dataUrl;
            previewBox.innerHTML = '<img class="avatar avatar-xl" src="' + dataUrl + '" alt="Pratinjau foto">';
            saveButton.disabled = false;
        };
        img.onerror = function () { URL.revokeObjectURL(url); errorBox.textContent = 'Foto tidak bisa dibaca.'; };
        img.src = url;
    });
})();

// Checklist syarat password baru
(function () {
    var newPasswordInput = document.getElementById('new-password');
    var tests = {
        len:     function (value) { return value.length >= 8; },
        upper:   function (value) { return /[A-Z]/.test(value); },
        lower:   function (value) { return /[a-z]/.test(value); },
        digit:   function (value) { return /[0-9]/.test(value); },
        special: function (value) { return /[^A-Za-z0-9\s]/.test(value); }
    };
    newPasswordInput.addEventListener('input', function () {
        document.querySelectorAll('#pw-rules li').forEach(function (li) {
            var isValid = tests[li.dataset.rule](newPasswordInput.value);
            li.className = isValid ? 'ok' : 'bad';
            li.textContent = isValid ? li.dataset.ok : li.dataset.bad;
        });
    });
})();
</script>
<?php render_footer();
