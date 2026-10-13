<?php
// Create: buat tiket baru (db_post ke node tickets)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

$errors = [];
$t = ['title' => '', 'description' => '', 'category' => 'lainnya', 'payment_method' => '', 'transaction_id' => '', 'amount' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$clean, $errors] = ticket_input($_POST, false);
    $t = $clean;

    if (!$errors) {
        $record = $clean + [
            'priority'    => 'sedang',   // sementara 'sedang', nanti diisi otomatis dari hasil Gemini
            'status'      => 'baru',
            'owner_uid'   => $user['uid'],
            'owner_email' => $user['email'],
            'owner_name'  => $user['name'],
            'created_at'  => now_string(),
            'updated_at'  => now_string(),
        ];
        $r = db_post('tickets', $record);
        if ($r['ok']) {
            flash('success', 'Tiket berhasil dibuat.');
            redirect('tickets/index.php');
        }
        $errors[] = 'Gagal menyimpan ke Firebase: ' . $r['error'];
    }
}

render_header('Buat Tiket Kendala', 'tickets');
render_errors($errors);
render_ticket_form($t, false, 'Kirim tiket');
render_footer();
