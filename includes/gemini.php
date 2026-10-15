<?php
// Integrasi Gemini API (fitur AI). Kunci API hanya dibaca dari config.local.php di server,
// tidak pernah dikirim ke browser dan tidak diunggah ke GitHub.
// Gemini dipaksa menjawab lewat function calling, sehingga hasilnya selalu berupa JSON terstruktur.

// ---------- Fungsi inti: panggil Gemini dengan function calling ----------
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
        'contents'          => [['role' => 'user', 'parts' => [['text' => $userText]]]],
        'tools'             => [['functionDeclarations' => [$function]]],
        'toolConfig'        => ['functionCallingConfig' => ['mode' => 'ANY', 'allowedFunctionNames' => [$function['name']]]],
    ];
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

    $last    = 'Tidak ada model yang bisa dipakai.';
    $started = microtime(true);
    foreach ($models as $model) {
        if (microtime(true) - $started > 40) {
            break;   // batasi total waktu tunggu supaya halaman tidak menggantung
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        // Model sedang sibuk (429/500/503) dicoba ulang sekali setelah jeda singkat
        for ($try = 1; $try <= 2; $try++) {
            $r = http_request('POST', $url, $payload, ['Content-Type: application/json', 'x-goog-api-key: ' . $key]);
            if ($r['ok'] || !in_array($r['status'], [429, 500, 503], true) || $try === 2) {
                break;
            }
            sleep(2);
        }
        if (!$r['ok']) {
            $last = 'Gemini (' . $model . '): ' . $r['error'];
            if (in_array($r['status'], [404, 429, 500, 503], true)) {
                continue;   // model tidak tersedia atau sedang sibuk, coba model berikutnya
            }
            return ['ok' => false, 'data' => null, 'error' => $last];
        }
        foreach ($r['data']['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['functionCall']['args']) && is_array($part['functionCall']['args'])) {
                return ['ok' => true, 'data' => $part['functionCall']['args'], 'error' => null];
            }
        }
        return ['ok' => false, 'data' => null, 'error' => 'Gemini tidak mengembalikan data terstruktur. Coba lagi.'];
    }
    return ['ok' => false, 'data' => null, 'error' => 'Semua model Gemini sedang sibuk atau tidak tersedia. Tunggu beberapa menit lalu klik tombolnya lagi. Rincian terakhir: ' . $last];
}

// ---------- Penyamaran data sensitif sebelum dikirim ke Gemini ----------
function mask_sensitive(string $text): string
{
    // Nomor kartu (13-19 digit, boleh dipisah spasi atau tanda minus)
    $text = preg_replace_callback('/\b\d(?:[ -]?\d){12,18}\b/', function ($m) {
        $digits = preg_replace('/\D/', '', $m[0]);
        return '[KARTU ****' . substr($digits, -4) . ']';
    }, $text);
    // Email dan nomor telepon
    $text = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[EMAIL]', $text);
    $text = preg_replace('/(?<!\d)(?:\+62|62|0)8\d{8,11}(?!\d)/', '[TELEPON]', $text);
    return $text;
}

function ai_clip($v, int $max): string
{
    return mb_substr(trim((string) $v), 0, $max);
}

// Teks tiket yang dikirim ke AI (sudah disamarkan)
function ai_ticket_text(array $t): string
{
    $lines = [
        'Judul: ' . mask_sensitive((string) ($t['title'] ?? '')),
        'Keluhan merchant: ' . mask_sensitive((string) ($t['description'] ?? '')),
        'Field form - metode bayar: ' . (($t['payment_method'] ?? '') ?: '(kosong)')
            . ', ID transaksi: ' . (($t['transaction_id'] ?? '') ?: '(kosong)')
            . ', nominal: ' . (($t['amount'] ?? '') !== '' && $t['amount'] !== null ? $t['amount'] : '(kosong)'),
        'Kategori pilihan merchant: ' . ($t['category'] ?? 'lainnya'),
        'Waktu tiket dibuat: ' . ($t['created_at'] ?? ''),
    ];
    return implode("\n", $lines);
}

// ---------- AI 1 (Rensy): ekstraksi data, deteksi data kurang, klasifikasi, prioritas ----------
function ai_extract_classify(array $ticket): array
{
    $system = "Kamu asisten helpdesk untuk perusahaan payment gateway di Indonesia. "
        . "Baca keluhan merchant lalu isi fungsi analyze_ticket. Jangan mengarang: jika data tidak disebutkan, kosongkan field-nya "
        . "dan masukkan nama field itu ke missing_fields. Prioritas kritis/tinggi untuk dana pelanggan terpotong, transaksi ganda, "
        . "atau gangguan yang berulang; sedang untuk settlement/refund tertunda; rendah untuk pertanyaan umum.";

    $function = [
        'name'        => 'analyze_ticket',
        'description' => 'Hasil analisis tiket kendala payment gateway.',
        'parameters'  => [
            'type' => 'OBJECT',
            'properties' => [
                'category' => ['type' => 'STRING', 'enum' => array_keys(CATEGORIES)],
                'priority' => ['type' => 'STRING', 'enum' => array_keys(PRIORITIES)],
                'priority_reason' => ['type' => 'STRING', 'description' => 'Alasan singkat pemilihan prioritas.'],
                'extracted' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'transaction_id' => ['type' => 'STRING'],
                        'amount'         => ['type' => 'INTEGER', 'description' => 'Rupiah, angka saja.'],
                        'payment_method' => ['type' => 'STRING', 'enum' => array_keys(PAYMENT_METHODS)],
                        'event_time'     => ['type' => 'STRING', 'description' => 'Waktu kejadian sesuai teks merchant.'],
                        'error_code'     => ['type' => 'STRING'],
                    ],
                ],
                'missing_fields' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'],
                    'description' => 'Data wajib yang belum disebutkan, misalnya ID transaksi.'],
            ],
            'required' => ['category', 'priority', 'extracted', 'missing_fields'],
        ],
    ];

    $r = gemini_call($system, ai_ticket_text($ticket), $function);
    if (!$r['ok']) {
        return $r;
    }
    $d = $r['data'];
    $x = is_array($d['extracted'] ?? null) ? $d['extracted'] : [];

    // Validasi hasil AI sebelum disimpan
    $out = [
        'category'        => isset(CATEGORIES[$d['category'] ?? '']) ? $d['category'] : 'lainnya',
        'priority'        => isset(PRIORITIES[$d['priority'] ?? '']) ? $d['priority'] : 'sedang',
        'priority_reason' => ai_clip($d['priority_reason'] ?? '', 300),
        'extracted' => [
            'transaction_id' => ai_clip($x['transaction_id'] ?? '', 60),
            'amount'         => isset($x['amount']) && is_numeric($x['amount']) ? (int) $x['amount'] : null,
            'payment_method' => isset(PAYMENT_METHODS[$x['payment_method'] ?? '']) ? $x['payment_method'] : '',
            'event_time'     => ai_clip($x['event_time'] ?? '', 80),
            'error_code'     => ai_clip($x['error_code'] ?? '', 40),
        ],
        'missing_fields'  => array_slice(array_map(fn($v) => ai_clip($v, 60), array_values(array_filter((array) ($d['missing_fields'] ?? []), 'is_string'))), 0, 8),
        'analyzed_at'     => now_string(),
    ];
    return ['ok' => true, 'data' => $out, 'error' => null];
}

// ---------- AI 2 (Gherald): ringkasan kronologi, draf balasan, artikel Knowledge Base terkait ----------
function ai_summarize_reply(array $ticket, array $kbArticles, array $followups = []): array
{
    // Knowledge Base dikirim sebagai acuan jawaban (dibatasi supaya permintaan tidak terlalu besar)
    $kbText = '';
    $validIds = [];
    foreach (array_slice($kbArticles, 0, 25) as $a) {
        $validIds[(string) $a['id']] = (string) ($a['title'] ?? '');
        $kbText .= '[ID: ' . $a['id'] . '] ' . ($a['title'] ?? '') . ' (' . ($a['category'] ?? '') . ")\n"
            . mb_substr((string) ($a['content'] ?? ''), 0, 500) . "\n\n";
    }
    $fuText = '';
    foreach (array_slice($followups, -5) as $f) {
        $fuText .= '- ' . label(FOLLOWUP_TYPES, $f['type'] ?? '') . ': ' . mask_sensitive(mb_substr((string) ($f['content'] ?? ''), 0, 300)) . "\n";
    }

    $system = "Kamu asisten CS untuk perusahaan payment gateway di Indonesia. Isi fungsi summarize_ticket. "
        . "summary: kronologi singkat 2-3 kalimat. suggested_reply: balasan sopan berbahasa Indonesia untuk merchant, "
        . "berdasarkan Knowledge Base yang diberikan; jika data seperti ID transaksi belum ada, minta data itu. "
        . "Jangan menjanjikan pengembalian dana atau waktu penyelesaian yang tidak ada di Knowledge Base. "
        . "related_kb_ids hanya boleh berisi ID dari daftar Knowledge Base.";

    $function = [
        'name'        => 'summarize_ticket',
        'description' => 'Ringkasan tiket dan draf balasan untuk merchant.',
        'parameters'  => [
            'type' => 'OBJECT',
            'properties' => [
                'summary'         => ['type' => 'STRING'],
                'suggested_reply' => ['type' => 'STRING'],
                'related_kb_ids'  => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            ],
            'required' => ['summary', 'suggested_reply', 'related_kb_ids'],
        ],
    ];

    $text = ai_ticket_text($ticket)
        . ($fuText !== '' ? "\n\nTindakan yang sudah dicatat:\n" . $fuText : '')
        . "\n\nKnowledge Base:\n" . ($kbText !== '' ? $kbText : '(kosong)');

    $r = gemini_call($system, $text, $function);
    if (!$r['ok']) {
        return $r;
    }
    $d   = $r['data'];
    $ids = array_values(array_filter((array) ($d['related_kb_ids'] ?? []), fn($i) => is_string($i) && isset($validIds[$i])));
    return ['ok' => true, 'data' => [
        'summary'         => ai_clip($d['summary'] ?? '', 800),
        'suggested_reply' => ai_clip($d['suggested_reply'] ?? '', 2000),
        'related_kb_ids'  => array_slice($ids, 0, 3),
        'summarized_at'   => now_string(),
    ], 'error' => null];
}
