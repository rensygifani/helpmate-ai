<?php
// Endpoint fitur AI. Hanya petugas (CS, tim teknis, admin) yang boleh menjalankan.
// mode=extract -> ekstraksi + klasifikasi + prioritas (disimpan di tickets/{id}/ai)
// mode=summary -> ringkasan + draf balasan + artikel terkait (disimpan di tickets/{id}/ai_reply)
// mode=apply   -> terapkan saran kategori dan prioritas AI ke tiket setelah dicek petugas
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tickets/index.php');
}
csrf_check();

$id   = (string) ($_POST['id'] ?? '');
$mode = (string) ($_POST['mode'] ?? '');
$back = 'tickets/view.php?id=' . urlencode($id);

if (!is_staff($user)) {
    flash('error', 'Fitur AI hanya untuk petugas.');
    redirect('tickets/index.php');
}
$res = valid_id($id) ? db_get('tickets/' . $id) : ['ok' => false, 'data' => null];
$t   = $res['ok'] && is_array($res['data']) ? $res['data'] : null;
if ($t === null) {
    flash('error', 'Tiket tidak ditemukan.');
    redirect('tickets/index.php');
}

if ($mode === 'extract') {
    $r = ai_extract_classify($t);
    if ($r['ok']) {
        $s = db_patch('tickets/' . $id, ['ai' => $r['data']]);
        flash($s['ok'] ? 'success' : 'error', $s['ok'] ? 'Analisis AI selesai. Periksa hasilnya sebelum diterapkan.' : 'Gagal menyimpan hasil AI: ' . $s['error']);
    } else {
        flash('error', $r['error']);
    }
} elseif ($mode === 'summary') {
    $kb = collection_to_list(db_get('knowledge_base')['data'] ?? null);
    $fu = array_reverse(collection_to_list(db_get('followups/' . $id)['data'] ?? null));
    $r  = ai_summarize_reply($t, $kb, $fu);
    if ($r['ok']) {
        $s = db_patch('tickets/' . $id, ['ai_reply' => $r['data']]);
        flash($s['ok'] ? 'success' : 'error', $s['ok'] ? 'Ringkasan dan draf balasan dibuat. Periksa dan edit sebelum dikirim.' : 'Gagal menyimpan hasil AI: ' . $s['error']);
    } else {
        flash('error', $r['error']);
    }
} elseif ($mode === 'similar') {
    $others = tickets_of_owner((string) ($t['owner_uid'] ?? ''));
    $byId   = [];
    foreach ($others as $o) {
        $byId[$o['id']] = $o;
    }
    $cands = find_similar_tickets($t + ['id' => $id], $others, 3, 5);
    foreach ($cands as $k => $c) {
        $cands[$k]['full'] = $byId[$c['id']] ?? null;
    }
    if (!$cands) {
        flash('success', 'Tidak ditemukan tiket yang cukup mirip dari merchant ini.');
    } else {
        $r = ai_confirm_similar($t, $cands);
        if ($r['ok']) {
            $s = db_patch('tickets/' . $id, ['ai_similar' => $r['data']]);
            flash($s['ok'] ? 'success' : 'error', $s['ok'] ? 'Pengecekan tiket serupa selesai.' : 'Gagal menyimpan hasil AI: ' . $s['error']);
        } else {
            flash('error', $r['error']);
        }
    }
} elseif ($mode === 'apply') {
    $ai = $t['ai'] ?? null;
    if (!is_array($ai) || !isset(CATEGORIES[$ai['category'] ?? ''], PRIORITIES[$ai['priority'] ?? ''])) {
        flash('error', 'Belum ada hasil analisis AI untuk diterapkan.');
    } else {
        $patch = ['category' => $ai['category'], 'priority' => $ai['priority'], 'updated_at' => now_string()];
        $x = $ai['extracted'] ?? [];
        // Field form yang masih kosong ikut dilengkapi dari hasil ekstraksi
        if (empty($t['transaction_id']) && !empty($x['transaction_id'])) $patch['transaction_id'] = $x['transaction_id'];
        if (($t['amount'] ?? null) === null && isset($x['amount']))      $patch['amount'] = (int) $x['amount'];
        if (empty($t['payment_method']) && !empty($x['payment_method'])) $patch['payment_method'] = $x['payment_method'];
        $s = db_patch('tickets/' . $id, $patch);
        flash($s['ok'] ? 'success' : 'error', $s['ok'] ? 'Saran AI diterapkan ke tiket.' : 'Gagal menerapkan: ' . $s['error']);
    }
} else {
    flash('error', 'Mode tidak dikenal.');
}
redirect($back);
