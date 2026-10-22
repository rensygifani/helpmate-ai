<?php

// Deteksi tiket serupa dari merchant yang sama (mencegah kerja ganda).
// Tahap 1 (tanpa AI): penilaian cepat berdasarkan ID transaksi, nominal, metode bayar, kategori, kemiripan teks, dan waktu.
// Tahap 2 (opsional, tombol di panel AI): Gemini memastikan apakah kandidat benar-benar kasus yang sama.

const SIMILAR_STOPWORDS = ['yang', 'dan', 'di', 'ke', 'dari', 'saya', 'kak', 'tolong', 'dicek', 'ini', 'itu', 'sudah', 'untuk',
    'pada', 'dengan', 'tapi', 'masih', 'atau', 'ada', 'tidak', 'belum', 'kali', 'hari', 'tadi', 'sekitar', 'jam', 'mohon', 'dong',
    'ya', 'nya', 'bisa', 'sama', 'lagi', 'juga', 'agar', 'karena', 'sistem'];

function similar_tokens(string $text): array
{
    $text = mb_strtolower($text);
    $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = [];
    foreach ($tokens as $token) {
        if (mb_strlen($token) >= 3 && !in_array($token, SIMILAR_STOPWORDS, true)) {
            $out[$token] = true;
        }
    }
    return array_keys($out);
}

function similar_ts(?string $dateString): ?int
{
    $timestamp = $dateString ? strtotime($dateString) : false;
    return $timestamp === false ? null : $timestamp;
}

// Nilai kemiripan dua tiket. Mengembalikan [skor, daftar alasan]
function similar_score(array $ticketA, array $ticketB): array
{
    $score = 0;
    $reasons = [];

    $transactionA = strtolower(trim((string) ($ticketA['transaction_id'] ?? '')));
    $transactionB = strtolower(trim((string) ($ticketB['transaction_id'] ?? '')));
    if ($transactionA !== '' && $transactionA === $transactionB) {
        $score += 6;
        $reasons[] = 'ID transaksi sama';
    }
    if (($ticketA['amount'] ?? null) !== null && ($ticketA['amount'] ?? null) === ($ticketB['amount'] ?? null)) {
        $score += 2;
        $reasons[] = 'nominal sama';
        if (!empty($ticketA['payment_method']) && ($ticketA['payment_method'] ?? '') === ($ticketB['payment_method'] ?? '')) {
            $score += 1;
            $reasons[] = 'metode bayar sama';
        }
    }
    if (($ticketA['category'] ?? '') !== '' && ($ticketA['category'] ?? '') !== 'lainnya' && ($ticketA['category'] ?? '') === ($ticketB['category'] ?? '')) {
        $score += 1;
        $reasons[] = 'kategori sama';
    }
    $wordsA = similar_tokens(($ticketA['title'] ?? '') . ' ' . ($ticketA['description'] ?? ''));
    $wordsB = similar_tokens(($ticketB['title'] ?? '') . ' ' . ($ticketB['description'] ?? ''));
    if ($wordsA && $wordsB) {
        $inter = count(array_intersect($wordsA, $wordsB));
        $union = count(array_unique(array_merge($wordsA, $wordsB)));
        $jaccard = $union > 0 ? $inter / $union : 0;
        if ($jaccard >= 0.4) {
            $score += 3;
            $reasons[] = 'isi keluhan sangat mirip (' . round($jaccard * 100) . '%)';
        } elseif ($jaccard >= 0.25) {
            $score += 1;
            $reasons[] = 'isi keluhan agak mirip (' . round($jaccard * 100) . '%)';
        }
    }
    $timeA = similar_ts($ticketA['created_at'] ?? null);
    $timeB = similar_ts($ticketB['created_at'] ?? null);
    if ($timeA !== null && $timeB !== null && abs($timeA - $timeB) <= 86400) {
        $score += 1;
        $reasons[] = 'dibuat berdekatan (kurang dari 24 jam)';
    }
    return [$score, $reasons];
}

// Cari tiket lain dari merchant yang sama yang mirip dengan $ticket.
// $others biasanya hasil tickets_of_owner(). Tiket yang sudah selesai dikurangi skornya.
function find_similar_tickets(array $ticket, array $others, int $threshold = 4, int $limit = 3): array
{
    $now = time();
    $result = [];
    foreach ($others as $other) {
        if (($other['id'] ?? '') === ($ticket['id'] ?? '__baru__')) {
            continue;
        }
        if (($other['owner_uid'] ?? '') !== ($ticket['owner_uid'] ?? '')) {
            continue;
        }
        $createdAt = similar_ts($other['created_at'] ?? null);
        if ($createdAt !== null && $now - $createdAt > 30 * 86400) {
            continue;   // hanya 30 hari terakhir
        }
        [$score, $reasons] = similar_score($ticket, $other);
        if (($other['status'] ?? '') === 'selesai') {
            $score -= 1;
        }
        if ($score >= $threshold) {
            $result[] = ['id' => $other['id'], 'title' => $other['title'] ?? '', 'status' => $other['status'] ?? '', 'score' => $score,
                         'reasons' => $reasons, 'created_at' => $other['created_at'] ?? ''];
        }
    }
    usort($result, fn ($firstMatch, $secondMatch) => $secondMatch['score'] <=> $firstMatch['score']);
    return array_slice($result, 0, $limit);
}
