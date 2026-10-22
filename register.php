<?php
// Halaman register: buat akun di Firebase Authentication lalu kirim email verifikasi
require __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user'])) {
    redirect('dashboard.php');
}

$errors = [];   // error per kolom
$formError = '';   // error umum (mis. dari Firebase)
$pwFailed = [];
$submitted = false;
$old = ['name' => '', 'email' => '', 'account_type' => '', 'organization' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $submitted = true;
    $name = trim((string) preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? '')));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    [$accountFields, $accErrors] = account_input($_POST);
    $old = ['name' => $name, 'email' => $email] + $accountFields;

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
            $uid = $signUp['data']['localId'];

            $mail = firebase_auth('sendOobCode', ['requestType' => 'VERIFY_EMAIL', 'idToken' => $token]);

            // Profil disimpan dengan role awal merchant
            $profile = db_put('users/' . $uid, [
                'name' => $name,
                'email' => $email,
                'role' => 'merchant',
                'account_type' => $accountFields['account_type'],
                'organization' => $accountFields['organization'],
                'created_at' => now_string(),
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
            <?php foreach (pw_rules() as $key => $rule):
                $cssClass = $submitted ? (in_array($key, $pwFailed, true) ? 'bad' : 'ok') : '';
                $ruleText = $cssClass === 'bad' ? $rule['bad'] : $rule['ok']; ?>
                <li data-rule="<?= e($key) ?>" data-ok="<?= e($rule['ok']) ?>" data-bad="<?= e($rule['bad']) ?>" class="<?= $cssClass ?>"><?= e($ruleText) ?></li>
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

<?php render_social_buttons('Daftar'); ?>

<script>
(function () {
    var form = document.getElementById('register-form');
    var submitButton = document.getElementById('register-btn');
    var fields = form.elements;
    var tests = {
        len:     function (password) { return password.length >= 8; },
        upper:   function (password) { return /[A-Z]/.test(password); },
        lower:   function (password) { return /[a-z]/.test(password); },
        digit:   function (password) { return /[0-9]/.test(password); },
        special: function (password) { return /[^A-Za-z0-9\s]/.test(password); }
    };

    function setError(name, message) {
        var box = form.querySelector('.field-error[data-for="' + name + '"]');
        box.textContent = message;
        fields[name].classList.toggle('invalid', message !== '');
    }

    function checkPassword() {
        var password = fields.password.value, allOk = true;
        form.querySelectorAll('#pw-rules li').forEach(function (li) {
            var isValid = tests[li.dataset.rule](password);
            li.className = isValid ? 'ok' : 'bad';
            li.textContent = isValid ? li.dataset.ok : li.dataset.bad;
            if (!isValid) allOk = false;
        });
        fields.password.classList.toggle('invalid', !allOk && password !== '');
        return allOk;
    }

    fields.password.addEventListener('input', checkPassword);

    form.addEventListener('submit', function (event) {
        var name = fields.name.value.trim().replace(/\s+/g, ' ');
        var email = fields.email.value.trim();
        var type = fields.account_type.value, organization = fields.organization.value.trim();
        var hasError = false, message;

        message = '';
        if (!name) message = 'Nama wajib diisi.';
        else if (!/^\p{L}[\p{L} .'-]*$/u.test(name)) message = 'Nama hanya boleh berisi huruf dan spasi.';
        setError('name', message); if (message) hasError = true;

        message = '';
        if (!email) message = 'Email wajib diisi.';
        else if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(email)) message = 'Format email tidak valid. Contoh: nama@gmail.com';
        setError('email', message); if (message) hasError = true;

        message = type ? '' : 'Pilih jenis akun.';
        setError('account_type', message); if (message) hasError = true;

        message = (type && type !== 'individu' && !organization) ? 'Nama sekolah / perusahaan / institusi wajib diisi.' : '';
        setError('organization', message); if (message) hasError = true;

        if (!checkPassword()) hasError = true;

        message = '';
        if (!fields.confirm.value) message = 'Konfirmasi password wajib diisi.';
        else if (fields.confirm.value !== fields.password.value) message = 'Konfirmasi password tidak sama dengan password.';
        setError('confirm', message); if (message) hasError = true;

        if (hasError) {
            event.preventDefault();
            return;
        }
        submitButton.disabled = true;
        submitButton.textContent = 'Memproses...';
    });
})();
</script>
<?php render_footer();
