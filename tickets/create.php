<?php
// Create: buat tiket baru (db_post ke node tickets) beserta lampiran bukti (node ticket_files)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

$errors = [];
$files  = [];
$t = ['title' => '', 'description' => '', 'category' => 'lainnya', 'payment_method' => '', 'transaction_id' => '', 'amount' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$clean, $errors] = ticket_input($_POST, false);
    [$files, $fileErrors] = ticket_attachments($_POST);
    $errors = array_merge($errors, $fileErrors);
    $t = $clean;

    if (!$errors) {
        $record = $clean + [
            'priority'    => 'sedang',   // sementara 'sedang', nanti diisi otomatis dari hasil Gemini
            'status'      => 'baru',
            'owner_uid'   => $user['uid'],
            'owner_email' => $user['email'],
            'owner_name'  => $user['name'],
            'owner_type'  => $user['account_type'] ?? '',
            'owner_org'   => $user['organization'] ?? '',
            'created_at'  => now_string(),
            'updated_at'  => now_string(),
        ];
        $r = db_post('tickets', $record);
        if ($r['ok']) {
            $ticketId = (string) ($r['data']['name'] ?? '');
            $failed = 0;
            if ($ticketId !== '') {
                foreach ($files as $f) {
                    $fr = db_post('ticket_files/' . $ticketId, $f + ['owner_uid' => $user['uid'], 'created_at' => now_string()]);
                    if (!$fr['ok']) {
                        $failed++;
                    }
                }
            }
            if ($failed > 0) {
                flash('warning', 'Tiket dibuat, tetapi ' . $failed . ' lampiran gagal diunggah.');
            } else {
                flash('success', 'Tiket berhasil dibuat.');
            }
            redirect('tickets/index.php');
        }
        $errors[] = 'Gagal menyimpan ke Firebase: ' . $r['error'];
    }
}

render_header('Buat Tiket Kendala', 'tickets');
render_errors($errors);
render_ticket_form($t, false, 'Kirim tiket', $files);
render_footer();
