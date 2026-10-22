<?php

// Pemanggilan Gemini API. Kunci API dibaca dari konfigurasi server (config.local.php atau environment),
// tidak dikirim ke browser dan tidak diunggah ke GitHub.
// Jawaban Gemini diminta lewat function calling supaya selalu berupa JSON terstruktur.

// Fungsi inti: memanggil Gemini dengan function calling
function gemini_call(string $system, string $userText, array $function): array
{
    $key = cfg('GEMINI_API_KEY');
    if (!$key) {
        return ['ok' => false, 'data' => null, 'error' => 'GEMINI_API_KEY belum diisi di config.local.php.'];
    }
    // Nama model sering diganti Google dan model bisa sedang sibuk. Model dari config dicoba dulu;
    // jika dijawab 404 (tidak ditemukan) atau 429/500/503 (sibuk), model cadangan dicoba berurutan.
    $models = array_values(array_unique(array_filter([cfg('GEMINI_MODEL'), 'gemini-3.8-flash', 'gemini-3.6-flash', 'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite'])));

    $body = [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $userText]]]],
        'tools' => [['functionDeclarations' => [$function]]],
        'toolConfig' => ['functionCallingConfig' => ['mode' => 'ANY', 'allowedFunctionNames' => [$function['name']]]],
    ];
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

    $last = 'Tidak ada model yang bisa dipakai.';
    $started = microtime(true);
    foreach ($models as $model) {
        if (microtime(true) - $started > 40) {
            break;   // batasi total waktu tunggu supaya halaman tidak menggantung
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        // Model sedang sibuk (429/500/503) dicoba ulang sekali setelah jeda singkat
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = http_request('POST', $url, $payload, ['Content-Type: application/json', 'x-goog-api-key: ' . $key]);
            if ($response['ok'] || !in_array($response['status'], [429, 500, 503], true) || $attempt === 2) {
                break;
            }
            sleep(2);
        }
        if (!$response['ok']) {
            $last = 'Gemini (' . $model . '): ' . $response['error'];
            if (in_array($response['status'], [404, 429, 500, 503], true)) {
                continue;   // model tidak tersedia atau sedang sibuk, coba model berikutnya
            }
            return ['ok' => false, 'data' => null, 'error' => $last];
        }
        foreach ($response['data']['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['functionCall']['args']) && is_array($part['functionCall']['args'])) {
                return ['ok' => true, 'data' => $part['functionCall']['args'], 'error' => null];
            }
        }
        return ['ok' => false, 'data' => null, 'error' => 'Gemini tidak mengembalikan data terstruktur. Coba lagi.'];
    }
    return ['ok' => false, 'data' => null, 'error' => 'Semua model Gemini sedang sibuk atau tidak tersedia. Tunggu beberapa menit lalu klik tombolnya lagi. Rincian terakhir: ' . $last];
}

function ai_clip($value, int $max): string
{
    return mb_substr(trim((string) $value), 0, $max);
}

// Teks tiket yang dikirim ke AI (sudah disamarkan)
function ai_ticket_text(array $ticket): string
{
    $lines = [
        'Judul: ' . mask_sensitive((string) ($ticket['title'] ?? '')),
        'Keluhan merchant: ' . mask_sensitive((string) ($ticket['description'] ?? '')),
        'Field form - metode bayar: ' . (($ticket['payment_method'] ?? '') ?: '(kosong)')
            . ', ID transaksi: ' . (($ticket['transaction_id'] ?? '') ?: '(kosong)')
            . ', nominal: ' . (($ticket['amount'] ?? '') !== '' && $ticket['amount'] !== null ? $ticket['amount'] : '(kosong)'),
        'Kategori pilihan merchant: ' . ($ticket['category'] ?? 'lainnya'),
        'Waktu tiket dibuat: ' . ($ticket['created_at'] ?? ''),
    ];
    return implode("\n", $lines);
}

// Ekstraksi data transaksi, deteksi data yang kurang, klasifikasi kategori, dan saran prioritas
function ai_extract_classify(array $ticket): array
{
    $system = "Kamu asisten helpdesk untuk perusahaan payment gateway di Indonesia. "
        . "Baca keluhan merchant lalu isi fungsi analyze_ticket. Jangan mengarang: jika data tidak disebutkan, kosongkan field-nya "
        . "dan masukkan nama field itu ke missing_fields. Prioritas kritis/tinggi untuk dana pelanggan terpotong, transaksi ganda, "
        . "atau gangguan yang berulang; sedang untuk settlement/refund tertunda; rendah untuk pertanyaan umum.";

    $function = [
        'name' => 'analyze_ticket',
        'description' => 'Hasil analisis tiket kendala payment gateway.',
        'parameters' => [
            'type' => 'OBJECT',
            'properties' => [
                'category' => ['type' => 'STRING', 'enum' => array_keys(CATEGORIES)],
                'priority' => ['type' => 'STRING', 'enum' => array_keys(PRIORITIES)],
                'priority_reason' => ['type' => 'STRING', 'description' => 'Alasan singkat pemilihan prioritas.'],
                'extracted' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'transaction_id' => ['type' => 'STRING'],
                        'amount' => ['type' => 'INTEGER', 'description' => 'Rupiah, angka saja.'],
                        'payment_method' => ['type' => 'STRING', 'enum' => array_keys(PAYMENT_METHODS)],
                        'event_time' => ['type' => 'STRING', 'description' => 'Waktu kejadian sesuai teks merchant.'],
                        'error_code' => ['type' => 'STRING'],
                    ],
                ],
                'missing_fields' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'],
                    'description' => 'Data wajib yang belum disebutkan, misalnya ID transaksi.'],
            ],
            'required' => ['category', 'priority', 'extracted', 'missing_fields'],
        ],
    ];

    $response = gemini_call($system, ai_ticket_text($ticket), $function);
    if (!$response['ok']) {
        return $response;
    }
    $data = $response['data'];
    $extracted = is_array($data['extracted'] ?? null) ? $data['extracted'] : [];

    // Validasi hasil AI sebelum disimpan
    $out = [
        'category' => isset(CATEGORIES[$data['category'] ?? '']) ? $data['category'] : 'lainnya',
        'priority' => isset(PRIORITIES[$data['priority'] ?? '']) ? $data['priority'] : 'sedang',
        'priority_reason' => ai_clip($data['priority_reason'] ?? '', 300),
        'extracted' => [
            'transaction_id' => ai_clip($extracted['transaction_id'] ?? '', 60),
            'amount' => isset($extracted['amount']) && is_numeric($extracted['amount']) ? (int) $extracted['amount'] : null,
            'payment_method' => isset(PAYMENT_METHODS[$extracted['payment_method'] ?? '']) ? $extracted['payment_method'] : '',
            'event_time' => ai_clip($extracted['event_time'] ?? '', 80),
            'error_code' => ai_clip($extracted['error_code'] ?? '', 40),
        ],
        'missing_fields' => array_slice(array_map(fn ($item) => ai_clip($item, 60), array_values(array_filter((array) ($data['missing_fields'] ?? []), 'is_string'))), 0, 8),
        'analyzed_at' => now_string(),
    ];
    return ['ok' => true, 'data' => $out, 'error' => null];
}

// Ringkasan kronologi, draf balasan, dan artikel Knowledge Base yang terkait
function ai_summarize_reply(array $ticket, array $kbArticles, array $followups = []): array
{
    // Knowledge Base dikirim sebagai acuan jawaban (dibatasi supaya permintaan tidak terlalu besar)
    $kbText = '';
    $validIds = [];
    foreach (array_slice($kbArticles, 0, 25) as $article) {
        $validIds[(string) $article['id']] = (string) ($article['title'] ?? '');
        $kbText .= '[ID: ' . $article['id'] . '] ' . ($article['title'] ?? '') . ' (' . ($article['category'] ?? '') . ")\n"
            . mb_substr((string) ($article['content'] ?? ''), 0, 500) . "\n\n";
    }
    $followupText = '';
    foreach (array_slice($followups, -5) as $followup) {
        $followupText .= '- ' . label(FOLLOWUP_TYPES, $followup['type'] ?? '') . ': ' . mask_sensitive(mb_substr((string) ($followup['content'] ?? ''), 0, 300)) . "\n";
    }

    $system = "Kamu asisten CS untuk perusahaan payment gateway di Indonesia. Isi fungsi summarize_ticket. "
        . "summary: kronologi singkat 2-3 kalimat. suggested_reply: balasan sopan berbahasa Indonesia untuk merchant, "
        . "berdasarkan Knowledge Base yang diberikan; jika data seperti ID transaksi belum ada, minta data itu. "
        . "Jangan menjanjikan pengembalian dana atau waktu penyelesaian yang tidak ada di Knowledge Base. "
        . "related_kb_ids hanya boleh berisi ID dari daftar Knowledge Base.";

    $function = [
        'name' => 'summarize_ticket',
        'description' => 'Ringkasan tiket dan draf balasan untuk merchant.',
        'parameters' => [
            'type' => 'OBJECT',
            'properties' => [
                'summary' => ['type' => 'STRING'],
                'suggested_reply' => ['type' => 'STRING'],
                'related_kb_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            ],
            'required' => ['summary', 'suggested_reply', 'related_kb_ids'],
        ],
    ];

    $text = ai_ticket_text($ticket)
        . ($followupText !== '' ? "\n\nTindakan yang sudah dicatat:\n" . $followupText : '')
        . "\n\nKnowledge Base:\n" . ($kbText !== '' ? $kbText : '(kosong)');

    $response = gemini_call($system, $text, $function);
    if (!$response['ok']) {
        return $response;
    }
    $data = $response['data'];
    $ids = array_values(array_filter((array) ($data['related_kb_ids'] ?? []), fn ($candidateId) => is_string($candidateId) && isset($validIds[$candidateId])));
    return ['ok' => true, 'data' => [
        'summary' => ai_clip($data['summary'] ?? '', 800),
        'suggested_reply' => ai_clip($data['suggested_reply'] ?? '', 2000),
        'related_kb_ids' => array_slice($ids, 0, 3),
        'summarized_at' => now_string(),
    ], 'error' => null];
}

// Konfirmasi tiket serupa
// $candidates berasal dari find_similar_tickets(). Gemini menilai apakah kandidat benar-benar kasus yang sama.
function ai_confirm_similar(array $ticket, array $candidates): array
{
    $ids = [];
    $prompt = "Tiket baru:\n" . ai_ticket_text($ticket) . "\n\nTiket sebelumnya dari merchant yang sama:\n";
    foreach (array_slice($candidates, 0, 5) as $candidate) {
        $ids[(string) $candidate['id']] = true;
        $prompt .= '[ID: ' . $candidate['id'] . '] ' . mask_sensitive((string) ($candidate['title'] ?? '')) . ' (status ' . ($candidate['status'] ?? '') . ', dibuat ' . ($candidate['created_at'] ?? '') . ")\n";
        $full = $candidate['full'] ?? null;
        if (is_array($full)) {
            $prompt .= '   Isi: ' . mask_sensitive(mb_substr((string) ($full['description'] ?? ''), 0, 400)) . "\n";
        }
    }
    $system = "Kamu asisten helpdesk payment gateway. Bandingkan tiket baru dengan tiket sebelumnya dari merchant yang sama. "
        . "Isi fungsi check_similar: untuk setiap tiket sebelumnya, tentukan apakah melaporkan kejadian yang sama (same_issue true) "
        . "atau hanya mirip tetapi kejadian berbeda (false), disertai alasan singkat. Jangan mengarang ID.";
    $function = [
        'name' => 'check_similar',
        'description' => 'Penilaian kemiripan tiket.',
        'parameters' => [
            'type' => 'OBJECT',
            'properties' => [
                'results' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => [
                    'id' => ['type' => 'STRING'],
                    'same_issue' => ['type' => 'BOOLEAN'],
                    'reason' => ['type' => 'STRING'],
                ], 'required' => ['id', 'same_issue', 'reason']]],
            ],
            'required' => ['results'],
        ],
    ];
    $response = gemini_call($system, $prompt, $function);
    if (!$response['ok']) {
        return $response;
    }
    $out = [];
    foreach ((array) ($response['data']['results'] ?? []) as $row) {
        if (is_array($row) && isset($row['id']) && isset($ids[(string) $row['id']])) {
            $out[] = ['id' => (string) $row['id'], 'same_issue' => (bool) ($row['same_issue'] ?? false), 'reason' => ai_clip($row['reason'] ?? '', 250)];
        }
    }
    return ['ok' => true, 'data' => ['results' => $out, 'checked_at' => now_string()], 'error' => null];
}

// Ringkasan harian untuk admin
// $stats berisi angka agregat dan judul tiket penting (sudah disamarkan), bukan data pribadi merchant.
function ai_daily_summary(string $date, array $stats): array
{
    $system = "Kamu analis helpdesk payment gateway. Dari statistik harian yang diberikan, tulis ringkasan untuk atasan dalam bahasa Indonesia. "
        . "Isi fungsi daily_summary. Hanya gunakan angka yang ada pada data; jangan menambah angka baru.";
    $function = [
        'name' => 'daily_summary',
        'description' => 'Ringkasan harian penanganan tiket.',
        'parameters' => [
            'type' => 'OBJECT',
            'properties' => [
                'summary' => ['type' => 'STRING', 'description' => 'Ringkasan 3-4 kalimat.'],
                'highlights' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Hal penting hari itu.'],
                'recommendations' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Saran tindak lanjut.'],
            ],
            'required' => ['summary', 'highlights', 'recommendations'],
        ],
    ];
    $response = gemini_call($system, "Tanggal: $date\nStatistik (JSON):\n" . json_encode($stats, JSON_UNESCAPED_UNICODE), $function);
    if (!$response['ok']) {
        return $response;
    }
    $data = $response['data'];
    $list = fn ($items) => array_slice(array_map(fn ($item) => ai_clip($item, 250), array_values(array_filter((array) $items, 'is_string'))), 0, 5);
    return ['ok' => true, 'data' => [
        'summary' => ai_clip($data['summary'] ?? '', 900),
        'highlights' => $list($data['highlights'] ?? []),
        'recommendations' => $list($data['recommendations'] ?? []),
    ], 'error' => null];
}
