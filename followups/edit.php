<?php

// Update: koreksi catatan tindakan (db_patch ke followups/{ticketId}/{id})
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
require __DIR__ . '/../includes/followup_form.php';
$user = require_login();

$ticketId = (string) ($_GET['ticket'] ?? $_POST['ticket_id'] ?? '');
$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$viewUrl = 'tickets/view.php?id=' . urlencode($ticketId);

$existing = null;
if (valid_id($ticketId) && valid_id($id)) {
    $followupResponse = db_get('followups/' . $ticketId . '/' . $id);
    $existing = $followupResponse['ok'] && is_array($followupResponse['data']) ? $followupResponse['data'] + ['id' => $id] : null;
}
if ($existing === null || !can_edit_followup($user, $existing)) {
    http_response_code(403);
    render_header('Akses ditolak', 'tickets');
    echo '<p>Catatan tidak ditemukan atau Anda tidak berhak mengubahnya.</p><p><a class="btn" href="' . e(url('tickets/index.php')) . '">Kembali</a></p>';
    render_footer();
    exit;
}

$errors = [];
$followup = $existing;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$clean, $errors] = followup_input($_POST);
    $followup = array_merge($existing, $clean);
    if (!$errors) {
        [$clean, $maskedLabels] = mask_fields($clean, ['content']);
        if ($maskedLabels) {
            flash('warning', 'Data sensitif pada catatan disamarkan: ' . implode(', ', $maskedLabels) . '.');
        }
        $clean['visible_to_merchant'] = in_array($clean['type'], FOLLOWUP_MERCHANT_VISIBLE, true);
        $clean['updated_at'] = now_string();
        $clean['edited_at'] = now_string();
        $response = db_patch('followups/' . $ticketId . '/' . $id, $clean);
        if ($response['ok']) {
            flash('success', 'Catatan berhasil diperbarui.');
            redirect($viewUrl);
        }
        $errors[] = 'Gagal memperbarui di Firebase: ' . $response['error'];
    }
}

render_header('Ubah Catatan Tindak Lanjut', 'tickets');
render_errors($errors);
echo '<div class="card">';
render_followup_form($followup, 'followups/edit.php', $ticketId, 'Simpan perubahan', url($viewUrl), 'followup-edit');
echo '</div>';
render_footer();
