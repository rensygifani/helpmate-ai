<?php

// Endpoint fitur AI. Hanya petugas (CS, tim teknis, admin) yang boleh menjalankan.
// mode=similar: konfirmasi tiket serupa dengan Gemini (disimpan di tickets/{id}/ai_similar)
// mode=extract: ekstraksi, klasifikasi, dan prioritas (disimpan di tickets/{id}/ai)
// mode=summary: ringkasan, draf balasan, dan artikel terkait (disimpan di tickets/{id}/ai_reply)
// mode=apply: terapkan saran kategori dan prioritas AI ke tiket setelah dicek petugas
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ticket_form.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tickets/index.php');
}
csrf_check();

$id = (string) ($_POST['id'] ?? '');
$mode = (string) ($_POST['mode'] ?? '');
$back = 'tickets/view.php?id=' . urlencode($id);

if (!is_staff($user)) {
    flash('error', 'Fitur AI hanya untuk petugas.');
    redirect('tickets/index.php');
}
$ticketResponse = valid_id($id) ? db_get('tickets/' . $id) : ['ok' => false, 'data' => null];
$ticket = $ticketResponse['ok'] && is_array($ticketResponse['data']) ? $ticketResponse['data'] : null;
if ($ticket === null) {
    flash('error', 'Tiket tidak ditemukan.');
    redirect('tickets/index.php');
}

if ($mode === 'extract') {
    $aiResult = ai_extract_classify($ticket);
    if ($aiResult['ok']) {
        $saveResult = db_patch('tickets/' . $id, ['ai' => $aiResult['data']]);
        flash($saveResult['ok'] ? 'success' : 'error', $saveResult['ok'] ? 'Analisis AI selesai. Periksa hasilnya sebelum diterapkan.' : 'Gagal menyimpan hasil AI: ' . $saveResult['error']);
    } else {
        flash('error', $aiResult['error']);
    }
} elseif ($mode === 'summary') {
    $kbArticles = collection_to_list(db_get('knowledge_base')['data'] ?? null);
    $followups = array_reverse(collection_to_list(db_get('followups/' . $id)['data'] ?? null));
    $aiResult = ai_summarize_reply($ticket, $kbArticles, $followups);
    if ($aiResult['ok']) {
        $saveResult = db_patch('tickets/' . $id, ['ai_reply' => $aiResult['data']]);
        flash($saveResult['ok'] ? 'success' : 'error', $saveResult['ok'] ? 'Ringkasan dan draf balasan dibuat. Periksa dan edit sebelum dikirim.' : 'Gagal menyimpan hasil AI: ' . $saveResult['error']);
    } else {
        flash('error', $aiResult['error']);
    }
} elseif ($mode === 'similar') {
    $others = tickets_of_owner((string) ($ticket['owner_uid'] ?? ''));
    $byId = [];
    foreach ($others as $otherTicket) {
        $byId[$otherTicket['id']] = $otherTicket;
    }
    $candidates = find_similar_tickets($ticket + ['id' => $id], $others, 3, 5);
    foreach ($candidates as $index => $candidate) {
        $candidates[$index]['full'] = $byId[$candidate['id']] ?? null;
    }
    if (!$candidates) {
        flash('success', 'Tidak ditemukan tiket yang cukup mirip dari merchant ini.');
    } else {
        $aiResult = ai_confirm_similar($ticket, $candidates);
        if ($aiResult['ok']) {
            $saveResult = db_patch('tickets/' . $id, ['ai_similar' => $aiResult['data']]);
            flash($saveResult['ok'] ? 'success' : 'error', $saveResult['ok'] ? 'Pengecekan tiket serupa selesai.' : 'Gagal menyimpan hasil AI: ' . $saveResult['error']);
        } else {
            flash('error', $aiResult['error']);
        }
    }
} elseif ($mode === 'apply') {
    $aiAnalysis = $ticket['ai'] ?? null;
    if (!is_array($aiAnalysis) || !isset(CATEGORIES[$aiAnalysis['category'] ?? ''], PRIORITIES[$aiAnalysis['priority'] ?? ''])) {
        flash('error', 'Belum ada hasil analisis AI untuk diterapkan.');
    } else {
        $patch = ['category' => $aiAnalysis['category'], 'priority' => $aiAnalysis['priority'], 'updated_at' => now_string()];
        $extracted = $aiAnalysis['extracted'] ?? [];
        // Field form yang masih kosong ikut dilengkapi dari hasil ekstraksi
        if (empty($ticket['transaction_id']) && !empty($extracted['transaction_id'])) {
            $patch['transaction_id'] = $extracted['transaction_id'];
        }
        if (($ticket['amount'] ?? null) === null && isset($extracted['amount'])) {
            $patch['amount'] = (int) $extracted['amount'];
        }
        if (empty($ticket['payment_method']) && !empty($extracted['payment_method'])) {
            $patch['payment_method'] = $extracted['payment_method'];
        }
        $saveResult = db_patch('tickets/' . $id, $patch);
        flash($saveResult['ok'] ? 'success' : 'error', $saveResult['ok'] ? 'Saran AI diterapkan ke tiket.' : 'Gagal menerapkan: ' . $saveResult['error']);
    }
} else {
    flash('error', 'Mode tidak dikenal.');
}
redirect($back);
