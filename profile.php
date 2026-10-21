<?php
// Akun saya: data akun, foto profil, data diri, dan ubah password
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$res = db_get('users/' . $user['uid']);
$p   = $res['ok'] && is_array($res['data']) ? $res['data'] : [];

$form = [
    'name'         => (string) ($p['name'] ?? $user['name']),
    'phone'        => (string) ($p['phone'] ?? ''),
    'account_type' => (string) ($p['account_type'] ?? ''),
    'organization' => (string) ($p['organization'] ?? ''),
];
$infoErr = [];
$photoErr = [];
$pwErr = [];
$pwFailed = [];
$pwSubmitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    // ---- Ubah data diri ----
    if ($action === 'info') {
        $name  = trim((string) preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        [$acc, $accErr] = account_input($_POST);
        $form = ['name' => $name, 'phone' => $phone] + $acc;

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
            $r = db_patch('users/' . $user['uid'], ['name' => $name, 'phone' => $phone] + $acc);
            if ($r['ok']) {
                $_SESSION['user']['name']         = $name;
                $_SESSION['user']['account_type'] = $acc['account_type'];
                $_SESSION['user']['organization'] = $acc['organization'];
                flash('success', 'Data diri berhasil diperbarui.');
                redirect('profile.php');
            }
            $infoErr['name'][] = 'Gagal menyimpan: ' . $r['error'];
        }
    }

    // ---- Ganti foto profil ----
    if ($action === 'photo') {
        $d = (string) ($_POST['photo_data'] ?? '');
        if (!preg_match('#^data:image/jpeg;base64,#', $d, $m)) {
            $photoErr[] = 'Pilih foto terlebih dahulu.';
        } elseif (strlen($d) > 90000) {
            $photoErr[] = 'Ukuran foto terlalu besar. Pilih foto lain.';
        } else {
            $raw = base64_decode(substr($d, strlen($m[0])), true);
            if ($raw === false || @getimagesizefromstring($raw) === false) {
                $photoErr[] = 'File yang dipilih bukan gambar yang valid.';
            } else {
                $r = db_patch('users/' . $user['uid'], ['photo' => $d]);
                if ($r['ok']) {
                    $_SESSION['user']['photo'] = $d;
                    flash('success', 'Foto profil berhasil diperbarui.');
                    redirect('profile.php');
                }
                $photoErr[] = 'Gagal menyimpan foto: ' . $r['error'];
            }
        }
    }

    // ---- Hapus foto profil ----
    if ($action === 'remove_photo') {
        $r = db_delete('users/' . $user['uid'] . '/photo');
        if ($r['ok']) {
            $_SESSION['user']['photo'] = '';
            flash('success', 'Foto profil dihapus.');
        } else {
            flash('error', 'Gagal menghapus foto: ' . $r['error']);
        }
        redirect('profile.php');
    }

    // ---- Ubah password ----
    if ($action === 'password') {
        $pwSubmitted = true;
        $cur  = (string) ($_POST['current_password'] ?? '');
        $new  = (string) ($_POST['new_password'] ?? '');
        $conf = (string) ($_POST['confirm_password'] ?? '');

        if ($cur === '') {
            $pwErr['current'][] = 'Password saat ini wajib diisi.';
        }
        $pwFailed = pw_failed($new);
        if ($pwFailed) {
            $pwErr['new'][] = 'Password baru belum memenuhi syarat di bawah.';
        } elseif ($cur !== '' && $new === $cur) {
            $pwErr['new'][] = 'Password baru tidak boleh sama dengan password saat ini.';
        }
        if ($conf === '') {
            $pwErr['confirm'][] = 'Konfirmasi password wajib diisi.';
        } elseif ($new !== $conf) {
            $pwErr['confirm'][] = 'Konfirmasi password tidak sama dengan password baru.';
        }

        if (!$pwErr) {
            // Verifikasi password lama dengan login ulang, lalu ganti password
            $re = firebase_auth('signInWithPassword', ['email' => $user['email'], 'password' => $cur, 'returnSecureToken' => true]);
            if (!$re['ok']) {
                $pwErr['current'][] = str_starts_with((string) $re['error'], 'INVALID_')
                    ? 'Password saat ini salah.'
                    : auth_error_text($re['error']);
            } else {
                $up = firebase_auth('update', ['idToken' => $re['data']['idToken'], 'password' => $new, 'returnSecureToken' => true]);
                if (!$up['ok']) {
                    $pwErr['new'][] = auth_error_text($up['error']);
                } else {
                    // Firebase mencabut token lama saat password berubah, jadi simpan token baru
                    $_SESSION['user']['idToken']      = $up['data']['idToken'] ?? $re['data']['idToken'];
                    $_SESSION['user']['refreshToken'] = $up['data']['refreshToken'] ?? $re['data']['refreshToken'];
                    $_SESSION['user']['expires_at']   = time() + (int) ($up['data']['expiresIn'] ?? 3600);
                    flash('success', 'Password berhasil diubah.');
                    redirect('profile.php');
                }
            }
        }
    }
}

$disp = ['name' => $p['name'] ?? $user['name'], 'photo' => $p['photo'] ?? ''];

render_header('Akun Saya', 'profile', 'Kelola foto, data diri, dan keamanan akun Anda.');
?>
<!-- Ringkasan akun -->
<div class="card">
    <div class="profile-head">
        <?php render_avatar($disp, 'avatar-lg'); ?>
        <div>
            <h2 style="margin:0"><?= e($disp['name']) ?></h2>
            <div><span class="badge r-<?= e($user['role']) ?>"><?= e(label(ROLES, $user['role'])) ?></span></div>
            <div class="muted"><?= e($user['email']) ?></div>
        </div>
    </div>
    <dl>
        <dt>Nomor telepon</dt><dd><?= e($p['phone'] ?? '') ?: '-' ?></dd>
        <dt>Jenis akun</dt><dd><?= e(label(ACCOUNT_TYPES, $p['account_type'] ?? '') ?: '-') ?></dd>
        <dt>Institusi</dt><dd><?= e($p['organization'] ?? '') ?: '-' ?></dd>
        <dt>Terdaftar sejak</dt><dd><?= e($p['created_at'] ?? '-') ?></dd>
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
        <div id="photo-preview"><?php render_avatar($disp, 'avatar-xl'); ?></div>
        <div class="photo-actions">
            <input type="file" id="photo-input" accept="image/*">
            <span class="hint">Foto otomatis dipotong persegi dan dikecilkan.</span>
            <div class="field-error" id="photo-error"></div>
            <div><button type="submit" class="btn btn-small" id="photo-save" disabled>Simpan foto</button></div>
        </div>
    </div>
</form>
<?php if (!empty($p['photo'])): ?>
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
            <?php foreach (pw_rules() as $key => $r):
                $cls = $pwSubmitted ? (in_array($key, $pwFailed, true) ? 'bad' : 'ok') : '';
                $txt = $cls === 'bad' ? $r['bad'] : $r['ok']; ?>
                <li data-rule="<?= e($key) ?>" data-ok="<?= e($r['ok']) ?>" data-bad="<?= e($r['bad']) ?>" class="<?= $cls ?>"><?= e($txt) ?></li>
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
    var hid = document.getElementById('photo-data');
    var btn = document.getElementById('photo-save');
    var prev = document.getElementById('photo-preview');
    var err = document.getElementById('photo-error');

    input.addEventListener('change', function () {
        err.textContent = '';
        var f = input.files[0];
        if (!f) return;
        if (!/^image\//.test(f.type)) { err.textContent = 'Pilih file gambar (JPG, PNG, WebP).'; return; }
        var img = new Image(), url = URL.createObjectURL(f);
        img.onload = function () {
            var S = 256, side = Math.min(img.width, img.height);
            var sx = (img.width - side) / 2, sy = (img.height - side) / 2, q = 0.85, data = '';
            var c = document.createElement('canvas'); c.width = S; c.height = S;
            var ctx = c.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, S, S);
            ctx.drawImage(img, sx, sy, side, side, 0, 0, S, S);
            do { data = c.toDataURL('image/jpeg', q); q -= 0.1; } while (data.length > 80000 && q > 0.3);
            URL.revokeObjectURL(url);
            if (data.length > 80000) { err.textContent = 'Foto terlalu besar, pilih foto lain.'; return; }
            hid.value = data;
            prev.innerHTML = '<img class="avatar avatar-xl" src="' + data + '" alt="Pratinjau foto">';
            btn.disabled = false;
        };
        img.onerror = function () { URL.revokeObjectURL(url); err.textContent = 'Foto tidak bisa dibaca.'; };
        img.src = url;
    });
})();

// Checklist syarat password baru
(function () {
    var np = document.getElementById('new-password');
    var tests = {
        len:     function (p) { return p.length >= 8; },
        upper:   function (p) { return /[A-Z]/.test(p); },
        lower:   function (p) { return /[a-z]/.test(p); },
        digit:   function (p) { return /[0-9]/.test(p); },
        special: function (p) { return /[^A-Za-z0-9\s]/.test(p); }
    };
    np.addEventListener('input', function () {
        document.querySelectorAll('#pw-rules li').forEach(function (li) {
            var ok = tests[li.dataset.rule](np.value);
            li.className = ok ? 'ok' : 'bad';
            li.textContent = ok ? li.dataset.ok : li.dataset.bad;
        });
    });
})();
</script>
<?php render_footer();