<?php
// Saran panduan sebelum tiket dikirim: mencocokkan tulisan merchant dengan artikel Knowledge Base.
// Tidak memakai AI sehingga cepat dan tidak menghabiskan kuota Gemini.

// Kata kunci yang mengarah ke kategori tertentu, supaya "dobel" atau "tertahan" tetap menemukan artikel yang tepat
const KB_CATEGORY_HINTS = [
    'transaksi_ganda' => ['ganda', 'dobel', 'dua kali', 'double', 'terpotong dua'],
    'transaksi_gagal' => ['gagal', 'failed', 'ditolak', 'error'],
    'pending'         => ['pending', 'tertahan', 'menunggu', 'belum masuk status'],
    'saldo_terpotong' => ['terpotong', 'saldo berkurang', 'debit', 'kepotong'],
    'settlement'      => ['settlement', 'pencairan', 'dana belum masuk', 'penyelesaian dana'],
    'refund'          => ['refund', 'pengembalian', 'dikembalikan'],
    'integrasi_api'   => ['api', 'callback', 'webhook', 'integrasi', 'signature'],
    'akun_merchant'   => ['akun', 'login', 'password', 'verifikasi', 'kyc'],
];

function kb_guess_categories(string $q): array
{
    $q   = mb_strtolower($q);
    $out = [];
    foreach (KB_CATEGORY_HINTS as $cat => $words) {
        foreach ($words as $w) {
            if (mb_strpos($q, $w) !== false) {
                $out[$cat] = true;
                break;
            }
        }
    }
    return array_keys($out);
}

function kb_suggest(string $q, array $articles, int $limit = 3): array
{
    $qt   = similar_tokens($q);
    $cats = kb_guess_categories($q);
    if (!$qt && !$cats) {
        return [];
    }
    $res = [];
    foreach ($articles as $a) {
        $score = 0;
        $title = similar_tokens((string) ($a['title'] ?? ''));
        $keys  = similar_tokens((string) ($a['keywords'] ?? ''));
        $body  = similar_tokens(mb_substr((string) ($a['content'] ?? ''), 0, 1500));
        $score += 3 * count(array_intersect($qt, $title));
        $score += 3 * count(array_intersect($qt, $keys));
        $score += count(array_intersect($qt, $body));
        if (in_array($a['category'] ?? '', $cats, true)) {
            $score += 4;
        }
        if ($score >= 4) {
            $snippet = trim(preg_replace('/\s+/', ' ', (string) ($a['content'] ?? '')));
            $res[]   = ['id' => $a['id'], 'title' => (string) ($a['title'] ?? ''), 'category' => (string) ($a['category'] ?? ''),
                        'snippet' => mb_substr($snippet, 0, 140) . (mb_strlen($snippet) > 140 ? '...' : ''), 'score' => $score];
        }
    }
    usort($res, fn($x, $y) => $y['score'] <=> $x['score']);
    return array_slice($res, 0, $limit);
}
