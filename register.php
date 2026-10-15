<?php
// Halaman register: buat akun di Firebase Authentication lalu kirim email verifikasi
require __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user'])) {
    redirect('dashboard.php');
}

$errors    = [];   // error per kolom
$formError = '';   // error umum (mis. dari Firebase)
$pwFailed  = [];
$submitted = false;
$old       = ['name' => '', 'email' => '', 'account_type' => '', 'organization' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $submitted = true;
    $name    = trim((string) preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? '')));
    $email   = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass    = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    [$acc, $accErrors] = account_input($_POST);
    $old = ['name' => $name, 'email' => $email] + $acc;

    // Nama (boleh satu kata)
    if ($name === '') {
        $errors['name'][] = 'Nama wajib diisi.';
    } elseif (mb_strlen($name) > 80) {
        $errors['name'][] = 'Nama maksimal 80 karakter.';
    } elseif (!preg_match('/^\p{L}[\p{L} .\'-]*$/u', $name)) {
        $errors['name'][] = 'Nama hanya boleh berisi huruf dan spasi.';
    }

    // Email
    if ($email === '') {
        $errors['email'][] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $email)) {
        $errors['email'][] = 'Format email tidak valid. Contoh: nama@gmail.com';
    }

    // Jenis akun & institusi
    $errors += $accErrors;

    // Password
    $pwFailed = pw_failed($pass);
    if ($pwFailed) {
        $errors['password'][] = 'Password belum memenuhi syarat di bawah.';
    }
    if ($confirm === '') {
        $errors['confirm'][] = 'Konfirmasi password wajib diisi.';
    } elseif ($pass !== $confirm) {
        $errors['confirm'][] = 'Konfirmasi password tidak sama dengan password.';
    }

    if (!$errors) {
        $signUp = firebase_auth('signUp', ['email' => $email, 'password' => $pass, 'returnSecureToken' => true]);
        if (!$signUp['ok']) {
            if (str_starts_with((string) $signUp['error'], 'EMAIL_EXISTS')) {
                $errors['email'][] = 'Email ini sudah terdaftar. Silakan login. Jika belum verifikasi, kirim ulang email verifikasi dari halaman login.';
            } else {
                $formError = auth_error_text($signUp['error']);
            }
        } else {
            $token = $signUp['data']['idToken'];
            $uid   = $signUp['data']['localId'];

            $mail = firebase_auth('sendOobCode', ['requestType' => 'VERIFY_EMAIL', 'idToken' => $token]);

            // Profil disimpan dengan role awal merchant
            $profile = db_put('users/' . $uid, [
                'name'         => $name,
                'email'        => $email,
                'role'         => 'merchant',
                'account_type' => $acc['account_type'],
                'organization' => $acc['organization'],
                'created_at'   => now_string(),
            ], $token);

            if (!$profile['ok']) {
                flash('warning', 'Akun dibuat, tetapi profil belum tersimpan: ' . $profile['error']);
            }
            if ($mail['ok']) {
                flash('success', 'Registrasi berhasil. Email verifikasi sudah dikirim ke ' . $email . '. Verifikasi dulu sebelum login.');
            } else {
                flash('warning', 'Registrasi berhasil, tetapi email verifikasi gagal dikirim. Coba kirim ulang dari halaman login.');
            }
            redirect('login.php');
        }
    }
}

render_header('Register');
if ($formError !== '') {
    render_errors([$formError]);
}
?>
<form method="post" class="card form" id="register-form" novalidate>
    <?= csrf_field() ?>
    <label>Nama
        <input type="text" name="name" value="<?= e($old['name']) ?>" maxlength="80" autocomplete="name"
               placeholder="Nama Anda" class="<?= !empty($errors['name']) ? 'invalid' : '' ?>">
        <?php field_errors($errors, 'name'); ?>
    </label>
    <label>Email
        <input type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email"
               placeholder="nama@gmail.com" class="<?= !empty($errors['email']) ? 'invalid' : '' ?>">
        <?php field_errors($errors, 'email'); ?>
    </label>

    <?php render_account_fields($old['account_type'], $old['organization'], $errors); ?>

    <label>Password
        <input type="password" name="password" autocomplete="new-password" class="<?= !empty($errors['password']) ? 'invalid' : '' ?>">
        <ul class="pw-rules" id="pw-rules">
            <?php foreach (pw_rules() as $key => $r):
                $cls = $submitted ? (in_array($key, $pwFailed, true) ? 'bad' : 'ok') : '';
                $txt = $cls === 'bad' ? $r['bad'] : $r['ok']; ?>
                <li data-rule="<?= e($key) ?>" data-ok="<?= e($r['ok']) ?>" data-bad="<?= e($r['bad']) ?>" class="<?= $cls ?>"><?= e($txt) ?></li>
            <?php endforeach; ?>
        </ul>
    </label>
    <label>Konfirmasi password
        <input type="password" name="confirm" autocomplete="new-password" class="<?= !empty($errors['confirm']) ? 'invalid' : '' ?>">
        <?php field_errors($errors, 'confirm'); ?>
    </label>
    <button type="submit" class="btn" id="register-btn">Daftar</button>
    <p class="muted">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Login</a></p>
</form>

<script>
(function () {
    var form = document.getElementById('register-form');
    var btn  = document.getElementById('register-btn');
    var f = form.elements;
    var tests = {
        len:     function (p) { return p.length >= 8; },
        upper:   function (p) { return /[A-Z]/.test(p); },
        lower:   function (p) { return /[a-z]/.test(p); },
        digit:   function (p) { return /[0-9]/.test(p); },
        special: function (p) { return /[^A-Za-z0-9\s]/.test(p); }
    };

    function setError(name, msg) {
        var box = form.querySelector('.field-error[data-for="' + name + '"]');
        box.textContent = msg;
        f[name].classList.toggle('invalid', msg !== '');
    }

    function checkPassword() {
        var p = f.password.value, allOk = true;
        form.querySelectorAll('#pw-rules li').forEach(function (li) {
            var ok = tests[li.dataset.rule](p);
            li.className = ok ? 'ok' : 'bad';
            li.textContent = ok ? li.dataset.ok : li.dataset.bad;
            if (!ok) allOk = false;
        });
        f.password.classList.toggle('invalid', !allOk && p !== '');
        return allOk;
    }

    f.password.addEventListener('input', checkPassword);

    form.addEventListener('submit', function (ev) {
        var name = f.name.value.trim().replace(/\s+/g, ' ');
        var email = f.email.value.trim();
        var type = f.account_type.value, org = f.organization.value.trim();
        var bad = false, msg;

        msg = '';
        if (!name) msg = 'Nama wajib diisi.';
        else if (!/^\p{L}[\p{L} .'-]*$/u.test(name)) msg = 'Nama hanya boleh berisi huruf dan spasi.';
        setError('name', msg); if (msg) bad = true;

        msg = '';
        if (!email) msg = 'Email wajib diisi.';
        else if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(email)) msg = 'Format email tidak valid. Contoh: nama@gmail.com';
        setError('email', msg); if (msg) bad = true;

        msg = type ? '' : 'Pilih jenis akun.';
        setError('account_type', msg); if (msg) bad = true;

        msg = (type && type !== 'individu' && !org) ? 'Nama sekolah / perusahaan / institusi wajib diisi.' : '';
        setError('organization', msg); if (msg) bad = true;

        if (!checkPassword()) bad = true;

        msg = '';
        if (!f.confirm.value) msg = 'Konfirmasi password wajib diisi.';
        else if (f.confirm.value !== f.password.value) msg = 'Konfirmasi password tidak sama dengan password.';
        setError('confirm', msg); if (msg) bad = true;

        if (bad) {
            ev.preventDefault();
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Memproses...';
    });
})();
</script>
<?php render_footer();