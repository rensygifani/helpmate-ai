<?php
// Halaman login: pakai Firebase Authentication, email harus sudah diverifikasi
require __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user'])) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'login');

    if ($action === 'resend') {
        // Kirim ulang email verifikasi, pakai token dari percobaan login sebelumnya
        $pending = $_SESSION['pending_token'] ?? null;
        if ($pending) {
            $r = firebase_auth('sendOobCode', ['requestType' => 'VERIFY_EMAIL', 'idToken' => $pending]);
            flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Email verifikasi dikirim ulang. Cek kotak masuk atau spam.' : 'Gagal mengirim ulang: ' . $r['error']);
        } else {
            flash('error', 'Silakan login dulu, lalu kirim ulang email verifikasi.');
        }
        redirect('login.php');
    }

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass  = (string) ($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        $errors[] = 'Email dan password wajib diisi.';
    } else {
        $login = firebase_auth('signInWithPassword', ['email' => $email, 'password' => $pass, 'returnSecureToken' => true]);
        if (!$login['ok']) {
            $errors[] = auth_error_text($login['error']);
        } else {
            $token = $login['data']['idToken'];
            $uid   = $login['data']['localId'];

            // Cek apakah email sudah diverifikasi
            $lookup   = firebase_auth('lookup', ['idToken' => $token]);
            $verified = (bool) ($lookup['data']['users'][0]['emailVerified'] ?? false);

            if (!$verified) {
                $_SESSION['pending_token'] = $token;
                $errors[] = 'Email belum diverifikasi. Buka email dari Firebase lalu klik tautan verifikasi.';
            } else {
                unset($_SESSION['pending_token']);

                // Ambil nama, role, dan jenis akun pengguna dari Realtime Database
                $prof = db_get('users/' . $uid, $token);
                if (!$prof['ok'] || !is_array($prof['data'])) {
                    $newProfile = ['name' => explode('@', $email)[0], 'email' => $email, 'role' => 'merchant', 'created_at' => now_string()];
                    db_put('users/' . $uid, $newProfile, $token);
                    $prof['data'] = $newProfile;
                }

                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'uid'          => $uid,
                    'email'        => $email,
                    'name'         => (string) ($prof['data']['name'] ?? $email),
                    'role'         => (string) ($prof['data']['role'] ?? 'merchant'),
                    'account_type' => (string) ($prof['data']['account_type'] ?? ''),
                    'organization' => (string) ($prof['data']['organization'] ?? ''),
                    'photo'        => (string) ($prof['data']['photo'] ?? ''),
                    'idToken'      => $token,
                    'refreshToken' => $login['data']['refreshToken'],
                    'expires_at'   => time() + (int) ($login['data']['expiresIn'] ?? 3600),
                ];
                flash('success', 'Selamat datang, ' . $_SESSION['user']['name'] . '.');
                redirect('dashboard.php');
            }
        }
    }
}

render_header('Login');
render_errors($errors);
?>
<form method="post" class="card form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="login">
    <label>Email
        <input type="email" name="email" required>
    </label>
    <label>Password
        <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn">Login</button>
    <p class="muted">Belum punya akun? <a href="<?= e(url('register.php')) ?>">Register</a></p>
</form>

<?php if (!empty($_SESSION['pending_token'])): ?>
<form method="post" class="card form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="resend">
    <p>Belum menerima email verifikasi?</p>
    <button type="submit" class="btn btn-light">Kirim ulang email verifikasi</button>
</form>
<?php endif; ?>
<?php render_footer();