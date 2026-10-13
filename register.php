<?php
// Halaman register: buat akun di Firebase Authentication lalu kirim email verifikasi
require __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user'])) {
    redirect('dashboard.php');
}

$errors = [];
$old    = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name    = trim((string) ($_POST['name'] ?? ''));
    $email   = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass    = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    $old     = ['name' => $name, 'email' => $email];

    if ($name === '' || mb_strlen($name) > 80) {
        $errors[] = 'Nama wajib diisi (maksimal 80 karakter).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (strlen($pass) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }
    if ($pass !== $confirm) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        // Buat akun di Firebase Authentication
        $signUp = firebase_auth('signUp', ['email' => $email, 'password' => $pass, 'returnSecureToken' => true]);
        if (!$signUp['ok']) {
            $errors[] = auth_error_text($signUp['error']);
        } else {
            $token = $signUp['data']['idToken'];
            $uid   = $signUp['data']['localId'];

            // Kirim email verifikasi lewat Firebase
            $mail = firebase_auth('sendOobCode', ['requestType' => 'VERIFY_EMAIL', 'idToken' => $token]);

            // Simpan data profil ke Realtime Database, role awal selalu merchant
            $profile = db_put('users/' . $uid, [
                'name'       => $name,
                'email'      => $email,
                'role'       => 'merchant',
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
render_errors($errors);
?>
<form method="post" class="card form">
    <?= csrf_field() ?>
    <label>Nama lengkap
        <input type="text" name="name" value="<?= e($old['name']) ?>" maxlength="80" required>
    </label>
    <label>Email
        <input type="email" name="email" value="<?= e($old['email']) ?>" required>
    </label>
    <label>Password (minimal 6 karakter)
        <input type="password" name="password" required>
    </label>
    <label>Konfirmasi password
        <input type="password" name="confirm" required>
    </label>
    <button type="submit" class="btn">Daftar</button>
    <p class="muted">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Login</a></p>
</form>
<?php render_footer();
