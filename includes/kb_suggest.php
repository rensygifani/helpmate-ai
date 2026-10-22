<?php

// Saran panduan sebelum tiket dikirim: mencocokkan tulisan merchant dengan artikel Knowledge Base.
// Tidak memakai AI sehingga cepat dan tidak menghabiskan kuota Gemini.

// Kata kunci yang mengarah ke kategori tertentu, supaya "dobel" atau "tertahan" tetap menemukan artikel yang tepat
const KB_CATEGORY_HINTS = [
    'transaksi_ganda' => ['ganda', 'dobel', 'dua kali', 'double', 'terpotong dua'],
    'transaksi_gagal' => ['gagal', 'failed', 'ditolak', 'error'],
    'pending' => ['pending', 'tertahan', 'menunggu', 'belum masuk status'],
    'saldo_terpotong' => ['terpotong', 'saldo berkurang', 'debit', 'kepotong'],
    'settlement' => ['settlement', 'pencairan', 'dana belum masuk', 'penyelesaian dana'],
    'refund' => ['refund', 'pengembalian', 'dikembalikan'],
    'integrasi_api' => ['api', 'callback', 'webhook', 'integrasi', 'signature'],
    'akun_merchant' => ['akun', 'login', 'password', 'verifikasi', 'kyc'],
];

function kb_guess_categories(string $query): array
{
    $query = mb_strtolower($query);
    $out = [];
    foreach (KB_CATEGORY_HINTS as $category => $words) {
        foreach ($words as $word) {
            if (mb_strpos($query, $word) !== false) {
                $out[$category] = true;
                break;
            }
        }
    }
    return array_keys($out);
}

function kb_suggest(string $query, array $articles, int $limit = 3): array
{
    $queryTokens = similar_tokens($query);
    $categories = kb_guess_categories($query);
    if (!$queryTokens && !$categories) {
        return [];
    }
    $matches = [];
    foreach ($articles as $article) {
        $score = 0;
        $title = similar_tokens((string) ($article['title'] ?? ''));
        $keys = similar_tokens((string) ($article['keywords'] ?? ''));
        $body = similar_tokens(mb_substr((string) ($article['content'] ?? ''), 0, 1500));
        $score += 3 * count(array_intersect($queryTokens, $title));
        $score += 3 * count(array_intersect($queryTokens, $keys));
        $score += count(array_intersect($queryTokens, $body));
        if (in_array($article['category'] ?? '', $categories, true)) {
            $score += 4;
        }
        if ($score >= 4) {
            $snippet = trim(preg_replace('/\s+/', ' ', (string) ($article['content'] ?? '')));
            $matches[] = ['id' => $article['id'], 'title' => (string) ($article['title'] ?? ''), 'category' => (string) ($article['category'] ?? ''),
                        'snippet' => mb_substr($snippet, 0, 140) . (mb_strlen($snippet) > 140 ? '...' : ''), 'score' => $score];
        }
    }
    usort($matches, fn ($firstMatch, $secondMatch) => $secondMatch['score'] <=> $firstMatch['score']);
    return array_slice($matches, 0, $limit);
}
