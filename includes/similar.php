<?php
// Deteksi tiket serupa dari merchant yang sama (mencegah kerja ganda).
// Tahap 1 (tanpa AI): penilaian cepat berdasarkan ID transaksi, nominal, metode bayar, kategori, kemiripan teks, dan waktu.
// Tahap 2 (opsional, tombol di panel AI): Gemini memastikan apakah kandidat benar-benar kasus yang sama.

const SIMILAR_STOPWORDS = ['yang', 'dan', 'di', 'ke', 'dari', 'saya', 'kak', 'tolong', 'dicek', 'ini', 'itu', 'sudah', 'untuk',
    'pada', 'dengan', 'tapi', 'masih', 'atau', 'ada', 'tidak', 'belum', 'kali', 'hari', 'tadi', 'sekitar', 'jam', 'mohon', 'dong',
    'ya', 'nya', 'bisa', 'sama', 'lagi', 'juga', 'agar', 'karena', 'sistem'];

function similar_tokens(string $s): array
{
    $s      = mb_strtolower($s);
    $tokens = preg_split('/[^\p{L}\p{N}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out    = [];
    foreach ($tokens as $t) {
        if (mb_strlen($t) >= 3 && !in_array($t, SIMILAR_STOPWORDS, true)) {
            $out[$t] = true;
        }
    }
    return array_keys($out);
}

function similar_ts(?string $s): ?int
{
    $t = $s ? strtotime($s) : false;
    return $t === false ? null : $t;
}

// Nilai kemiripan dua tiket. Mengembalikan [skor, daftar alasan]
function similar_score(array $a, array $b): array
{
    $score   = 0;
    $reasons = [];

    $ta = strtolower(trim((string) ($a['transaction_id'] ?? '')));
    $tb = strtolower(trim((string) ($b['transaction_id'] ?? '')));
    if ($ta !== '' && $ta === $tb) {
        $score += 6;
        $reasons[] = 'ID transaksi sama';
    }
    if (($a['amount'] ?? null) !== null && ($a['amount'] ?? null) === ($b['amount'] ?? null)) {
        $score += 2;
        $reasons[] = 'nominal sama';
        if (!empty($a['payment_method']) && ($a['payment_method'] ?? '') === ($b['payment_method'] ?? '')) {
            $score += 1;
            $reasons[] = 'metode bayar sama';
        }
    }
    if (($a['category'] ?? '') !== '' && ($a['category'] ?? '') !== 'lainnya' && ($a['category'] ?? '') === ($b['category'] ?? '')) {
        $score += 1;
        $reasons[] = 'kategori sama';
    }
    $wa = similar_tokens(($a['title'] ?? '') . ' ' . ($a['description'] ?? ''));
    $wb = similar_tokens(($b['title'] ?? '') . ' ' . ($b['description'] ?? ''));
    if ($wa && $wb) {
        $inter = count(array_intersect($wa, $wb));
        $union = count(array_unique(array_merge($wa, $wb)));
        $j     = $union > 0 ? $inter / $union : 0;
        if ($j >= 0.4) {
            $score += 3;
            $reasons[] = 'isi keluhan sangat mirip (' . round($j * 100) . '%)';
        } elseif ($j >= 0.25) {
            $score += 1;
            $reasons[] = 'isi keluhan agak mirip (' . round($j * 100) . '%)';
        }
    }
    $sa = similar_ts($a['created_at'] ?? null);
    $sb = similar_ts($b['created_at'] ?? null);
    if ($sa !== null && $sb !== null && abs($sa - $sb) <= 86400) {
        $score += 1;
        $reasons[] = 'dibuat berdekatan (kurang dari 24 jam)';
    }
    return [$score, $reasons];
}

// Cari tiket lain dari merchant yang sama yang mirip dengan $ticket.
// $others biasanya hasil tickets_of_owner(). Tiket yang sudah selesai dikurangi skornya.
function find_similar_tickets(array $ticket, array $others, int $threshold = 4, int $limit = 3): array
{
    $now    = time();
    $result = [];
    foreach ($others as $o) {
        if (($o['id'] ?? '') === ($ticket['id'] ?? '__baru__')) {
            continue;
        }
        if (($o['owner_uid'] ?? '') !== ($ticket['owner_uid'] ?? '')) {
            continue;
        }
        $ts = similar_ts($o['created_at'] ?? null);
        if ($ts !== null && $now - $ts > 30 * 86400) {
            continue;   // hanya 30 hari terakhir
        }
        [$score, $reasons] = similar_score($ticket, $o);
        if (($o['status'] ?? '') === 'selesai') {
            $score -= 1;
        }
        if ($score >= $threshold) {
            $result[] = ['id' => $o['id'], 'title' => $o['title'] ?? '', 'status' => $o['status'] ?? '', 'score' => $score,
                         'reasons' => $reasons, 'created_at' => $o['created_at'] ?? ''];
        }
    }
    usort($result, fn($x, $y) => $y['score'] <=> $x['score']);
    return array_slice($result, 0, $limit);
}
