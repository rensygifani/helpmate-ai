<?php
// Update: koreksi catatan tindakan (db_patch ke followups/{ticketId}/{id})
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
require __DIR__ . '/../includes/followup_form.php';
$user = require_login();

$ticketId = (string) ($_GET['ticket'] ?? $_POST['ticket_id'] ?? '');
$id       = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$viewUrl  = 'tickets/view.php?id=' . urlencode($ticketId);

$existing = null;
if (valid_id($ticketId) && valid_id($id)) {
    $res      = db_get('followups/' . $ticketId . '/' . $id);
    $existing = $res['ok'] && is_array($res['data']) ? $res['data'] + ['id' => $id] : null;
}
if ($existing === null || !can_edit_followup($user, $existing)) {
    http_response_code(403);
    render_header('Akses ditolak', 'tickets');
    echo '<p>Catatan tidak ditemukan atau Anda tidak berhak mengubahnya.</p><p><a class="btn" href="' . e(url('tickets/index.php')) . '">Kembali</a></p>';
    render_footer();
    exit;
}

$errors = [];
$f = $existing;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$clean, $errors] = followup_input($_POST);
    $f = array_merge($existing, $clean);
    if (!$errors) {
        $clean['updated_at'] = now_string();
        $clean['edited_at']  = now_string();
        $r = db_patch('followups/' . $ticketId . '/' . $id, $clean);
        if ($r['ok']) {
            flash('success', 'Catatan berhasil diperbarui.');
            redirect($viewUrl);
        }
        $errors[] = 'Gagal memperbarui di Firebase: ' . $r['error'];
    }
}

render_header('Ubah Catatan Tindak Lanjut', 'tickets');
render_errors($errors);
echo '<div class="card">';
render_followup_form($f, 'followups/edit.php', $ticketId, 'Simpan perubahan', url($viewUrl), 'followup-edit');
echo '</div>';
render_footer();
