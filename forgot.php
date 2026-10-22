<?php
// Reset password: Firebase Authentication mengirim email berisi tautan untuk membuat password baru.
// Pesan sukses selalu sama, supaya halaman ini tidak dipakai untuk menebak email yang terdaftar.
require __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user'])) {
    redirect('dashboard.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    } else {
        $response = firebase_auth('sendOobCode', ['requestType' => 'PASSWORD_RESET', 'email' => $email]);
        $notFound = !$response['ok'] && str_starts_with((string) $response['error'], 'EMAIL_NOT_FOUND');
        if ($response['ok'] || $notFound) {
            flash('success', 'Jika email terdaftar, tautan untuk membuat password baru sudah dikirim. Periksa kotak masuk dan folder spam.');
            redirect('login.php');
        }
        $errors[] = auth_error_text($response['error']);
    }
}

render_header('Lupa Password');
render_errors($errors);
?>
<form method="post" class="card form">
    <?= csrf_field() ?>
    <p>Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat password baru.</p>
    <label>Email
        <input type="email" name="email" required>
    </label>
    <button type="submit" class="btn">Kirim tautan reset</button>
    <p class="muted"><a href="<?= e(url('login.php')) ?>">Kembali ke login</a></p>
</form>
<?php render_footer();
