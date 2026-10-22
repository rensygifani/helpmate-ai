<?php

// Create: buat tiket baru (db_post ke node tickets) beserta lampiran bukti (node ticket_files)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

$errors = [];
$files = [];
$formValues = ['title' => '', 'description' => '', 'category' => 'lainnya', 'payment_method' => '', 'transaction_id' => '', 'amount' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$clean, $errors] = ticket_input($_POST, false);
    [$files, $fileErrors] = ticket_attachments($_POST);
    $errors = array_merge($errors, $fileErrors);
    $formValues = $clean;

    if (!$errors) {
        // Samarkan data sensitif (kartu, OTP, rekening, dan lain-lain) sebelum disimpan
        [$clean, $maskedLabels] = mask_fields($clean, ['title', 'description']);
        // Deteksi tiket serupa dari merchant yang sama (mencegah kerja ganda)
        $similar = find_similar_tickets($clean + ['owner_uid' => $user['uid'], 'created_at' => now_string()], tickets_of_owner($user['uid']), 5, 3);
        $record = $clean + [
            'priority' => 'sedang', // prioritas awal, dapat diganti dengan saran dari Gemini
            'status' => 'baru',
            'owner_uid' => $user['uid'],
            'owner_email' => $user['email'],
            'owner_name' => $user['name'],
            'owner_type' => $user['account_type'] ?? '',
            'owner_org' => $user['organization'] ?? '',
            'created_at' => now_string(),
            'updated_at' => now_string(),
        ];
        if ($similar) {
            $record['possible_duplicate_of'] = array_column($similar, 'id');
        }
        $response = db_post('tickets', $record);
        if ($response['ok']) {
            $ticketId = (string) ($response['data']['name'] ?? '');
            $failed = 0;
            if ($ticketId !== '') {
                foreach ($files as $file) {
                    $fileResponse = db_post('ticket_files/' . $ticketId, $file + ['owner_uid' => $user['uid'], 'created_at' => now_string()]);
                    if (!$fileResponse['ok']) {
                        $failed++;
                    }
                }
            }
            if ($failed > 0) {
                flash('warning', 'Tiket dibuat, tetapi ' . $failed . ' lampiran gagal diunggah.');
            } else {
                flash('success', 'Tiket berhasil dibuat.');
            }
            if ($maskedLabels) {
                flash('warning', 'Data sensitif disamarkan demi keamanan: ' . implode(', ', $maskedLabels) . '. Jangan kirim data tersebut lewat tiket.');
            }
            if ($similar) {
                flash('warning', 'Anda sudah memiliki tiket yang mirip: "' . $similar[0]['title'] . '". Tim kami akan menggabungkannya bila kasusnya sama.');
            }
            redirect('tickets/index.php');
        }
        $errors[] = 'Gagal menyimpan ke Firebase: ' . $response['error'];
    }
}

render_header('Buat Tiket Kendala', 'tickets');
render_errors($errors);
render_ticket_form($formValues, false, 'Kirim tiket', $files);
render_footer();
