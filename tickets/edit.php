<?php
// Update: ubah tiket (db_patch ke tickets/{id})
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$ticketResponse = $id !== '' ? db_get('tickets/' . $id) : ['ok' => false, 'data' => null];
$existing = $ticketResponse['ok'] && is_array($ticketResponse['data']) ? $ticketResponse['data'] : null;

if ($existing === null || !can_edit_ticket($user, $existing)) {
    http_response_code(403);
    render_header('Akses ditolak', 'tickets');
    echo '<p>Tiket tidak ditemukan atau tidak dapat diubah (merchant hanya dapat mengubah tiket berstatus Baru).</p><p><a class="btn" href="' . e(url('tickets/index.php')) . '">Kembali</a></p>';
    render_footer();
    exit;
}

$errors = [];
$ticket = $existing;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$clean, $errors] = ticket_input($_POST, is_staff($user));
    $ticket = array_merge($existing, $clean);

    if (!$errors) {
        [$clean, $maskedLabels] = mask_fields($clean, ['title', 'description']);
        if ($maskedLabels) {
            flash('warning', 'Data sensitif disamarkan demi keamanan: ' . implode(', ', $maskedLabels) . '.');
        }
        $clean['updated_at'] = now_string();
        $response = db_patch('tickets/' . $id, $clean);
        if ($response['ok']) {
            flash('success', 'Tiket berhasil diperbarui.');
            redirect('tickets/index.php');
        }
        $errors[] = 'Gagal memperbarui di Firebase: ' . $response['error'];
    }
}

render_header('Ubah Tiket', 'tickets');
render_errors($errors);
?>
<?php render_ticket_form($ticket, is_staff($user), 'Simpan perubahan'); ?>
<?php render_footer();
