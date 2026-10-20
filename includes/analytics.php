<?php
// Perhitungan analitik admin dan peringatan gangguan. Semua fungsi menerima array hasil collection_to_list().

function an_ts(?string $s): ?int
{
    $t = $s ? strtotime($s) : false;
    return $t === false ? null : $t;
}

// Peringatan gangguan: banyak laporan dengan kategori sama dalam waktu singkat
function incident_alerts(array $tickets, int $minutes = 60, int $min = 3): array
{
    $since = time() - $minutes * 60;
    $by    = [];
    foreach ($tickets as $t) {
        $ts = an_ts($t['created_at'] ?? null);
        if ($ts !== null && $ts >= $since) {
            $cat = $t['category'] ?? 'lainnya';
            $by[$cat] = ($by[$cat] ?? 0) + 1;
        }
    }
    $alerts = [];
    foreach ($by as $cat => $n) {
        if ($n >= $min && $cat !== 'lainnya') {
            $alerts[] = ['category' => $cat, 'count' => $n, 'minutes' => $minutes];
        }
    }
    return $alerts;
}

// Waktu respons pertama: tiket dibuat sampai catatan petugas pertama
function an_first_staff_followup(array $fups): ?int
{
    $best = null;
    foreach ($fups as $f) {
        if (($f['type'] ?? '') === 'tanggapan_merchant') {
            continue;
        }
        $ts = an_ts($f['created_at'] ?? null);
        if ($ts !== null && ($best === null || $ts < $best)) {
            $best = $ts;
        }
    }
    return $best;
}

function analytics_build(array $tickets, array $followups, array $kbTitles = []): array
{
    $a = ['total' => count($tickets), 'open' => 0, 'done' => 0, 'by_status' => [], 'by_category' => [], 'by_priority' => [],
          'by_method' => [], 'per_day' => [], 'top_merchants' => [], 'sla' => [], 'top_kb' => [], 'avg_first_response_min' => null,
          'avg_resolution_hours' => null, 'flagged_duplicates' => 0];
    for ($i = 13; $i >= 0; $i--) {
        $a['per_day'][date('Y-m-d', strtotime("-$i day"))] = 0;
    }
    $resp = [];
    $reso = [];
    $kb   = [];
    $mer  = [];
    $now  = time();
    foreach ($tickets as $t) {
        $st = $t['status'] ?? 'baru';
        $a['by_status'][$st]                         = ($a['by_status'][$st] ?? 0) + 1;
        $a['by_category'][$t['category'] ?? 'lainnya'] = ($a['by_category'][$t['category'] ?? 'lainnya'] ?? 0) + 1;
        $a['by_priority'][$t['priority'] ?? 'sedang']  = ($a['by_priority'][$t['priority'] ?? 'sedang'] ?? 0) + 1;
        if (!empty($t['payment_method'])) {
            $a['by_method'][$t['payment_method']] = ($a['by_method'][$t['payment_method']] ?? 0) + 1;
        }
        $day = substr((string) ($t['created_at'] ?? ''), 0, 10);
        if (isset($a['per_day'][$day])) {
            $a['per_day'][$day]++;
        }
        $name = trim((string) ($t['owner_org'] ?? '')) !== '' ? $t['owner_org'] : ($t['owner_name'] ?? 'Tidak diketahui');
        $mer[$name] = ($mer[$name] ?? 0) + 1;
        if (!empty($t['possible_duplicate_of'])) {
            $a['flagged_duplicates']++;
        }
        foreach ((array) ($t['ai_reply']['related_kb_ids'] ?? []) as $kid) {
            $kb[$kid] = ($kb[$kid] ?? 0) + 1;
        }

        $created = an_ts($t['created_at'] ?? null);
        $fups    = is_array($followups[$t['id']] ?? null) ? $followups[$t['id']] : [];
        $first   = an_first_staff_followup($fups);
        if ($created !== null && $first !== null && $first >= $created) {
            $resp[] = ($first - $created) / 60;
        }
        if ($st === 'selesai') {
            $a['done']++;
            $end = an_ts($t['updated_at'] ?? null);
            if ($created !== null && $end !== null && $end >= $created) {
                $reso[] = ($end - $created) / 3600;
            }
        } else {
            $a['open']++;
            // SLA: tiket terbuka tanpa tanggapan petugas yang melewati batas respons
            if ($first === null && $created !== null) {
                $limit = (SLA_HOURS[$t['priority'] ?? 'sedang'] ?? 24) * 3600;
                if ($now - $created > $limit) {
                    $a['sla'][] = ['id' => $t['id'], 'title' => $t['title'] ?? '', 'priority' => $t['priority'] ?? 'sedang',
                                   'overdue_hours' => round(($now - $created - $limit) / 3600, 1)];
                }
            }
        }
    }
    usort($a['sla'], fn($x, $y) => $y['overdue_hours'] <=> $x['overdue_hours']);
    $a['sla'] = array_slice($a['sla'], 0, 10);
    arsort($mer);
    $a['top_merchants'] = array_slice($mer, 0, 5, true);
    arsort($kb);
    foreach (array_slice($kb, 0, 5, true) as $id => $n) {
        $a['top_kb'][] = ['id' => $id, 'title' => $kbTitles[$id] ?? $id, 'count' => $n];
    }
    $a['avg_first_response_min'] = $resp ? round(array_sum($resp) / count($resp), 1) : null;
    $a['avg_resolution_hours']   = $reso ? round(array_sum($reso) / count($reso), 1) : null;
    $a['done_rate']              = $a['total'] > 0 ? round($a['done'] / $a['total'] * 100) : 0;
    $a['alerts']                 = incident_alerts($tickets);
    return $a;
}

// Statistik satu hari untuk ringkasan harian (tanpa data pribadi merchant)
function daily_stats(string $date, array $tickets): array
{
    $created = array_values(array_filter($tickets, fn($t) => substr((string) ($t['created_at'] ?? ''), 0, 10) === $date));
    $closed  = array_values(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'selesai' && substr((string) ($t['updated_at'] ?? ''), 0, 10) === $date));
    $open    = array_values(array_filter($tickets, fn($t) => ($t['status'] ?? '') !== 'selesai'));
    $cnt = function (array $rows, string $key, array $labels) {
        $o = [];
        foreach ($rows as $r) {
            $k = label($labels, $r[$key] ?? '');
            $o[$k] = ($o[$k] ?? 0) + 1;
        }
        arsort($o);
        return $o;
    };
    $important = array_values(array_filter($created, fn($t) => in_array($t['priority'] ?? '', ['kritis', 'tinggi'], true)));
    return [
        'tanggal'              => $date,
        'tiket_masuk'          => count($created),
        'tiket_selesai'        => count($closed),
        'tiket_masih_terbuka'  => count($open),
        'per_kategori'         => $cnt($created, 'category', CATEGORIES),
        'per_prioritas'        => $cnt($created, 'priority', PRIORITIES),
        'per_metode_bayar'     => $cnt($created, 'payment_method', PAYMENT_METHODS),
        'tiket_prioritas_tinggi' => array_map(fn($t) => mask_sensitive((string) ($t['title'] ?? '')), array_slice($important, 0, 5)),
    ];
}

// Simpan ringkasan harian ke daily_summaries/{tanggal}. Mengembalikan hasil db_put.
function daily_generate(string $date, ?string $token, string $by): array
{
    $tr = db_get('tickets', $token);
    if (!$tr['ok']) {
        return ['ok' => false, 'error' => 'Gagal membaca tiket: ' . $tr['error']];
    }
    $stats = daily_stats($date, collection_to_list($tr['data']));
    $ai    = ai_daily_summary($date, $stats);
    if (!$ai['ok']) {
        return ['ok' => false, 'error' => $ai['error'], 'stats' => $stats];
    }
    $record = $ai['data'] + ['stats' => $stats, 'date' => $date, 'generated_at' => now_string(), 'generated_by' => $by];
    $put    = db_put('daily_summaries/' . $date, $record, $token);
    return ['ok' => $put['ok'], 'error' => $put['error'], 'record' => $record];
}


// ---------- Tampilan sederhana tanpa library (bar HTML dan grafik SVG) ----------
function render_bars(array $data, array $labels = []): void
{
    $max = max(1, ...array_values($data ?: [0]));
    echo '<div class="bars">';
    foreach ($data as $k => $n) {
        $text = $labels[$k] ?? (string) $k;
        echo '<div class="bar-row"><span>' . e($text) . '</span><div class="bar"><span style="width:' . round($n / $max * 100) . '%"></span></div><strong>' . (int) $n . '</strong></div>';
    }
    if (!$data) {
        echo '<p class="muted">Belum ada data.</p>';
    }
    echo '</div>';
}

function render_day_chart(array $perDay): void
{
    $max = max(1, ...array_values($perDay ?: [0]));
    $n   = max(1, count($perDay));
    $w   = 560;
    $h   = 130;
    $bw  = $w / $n;
    echo '<svg class="daychart" viewBox="0 0 ' . $w . ' ' . ($h + 28) . '" role="img" aria-label="Jumlah tiket per hari">';
    $i = 0;
    foreach ($perDay as $day => $c) {
        $bh = $c / $max * $h;
        $x  = $i * $bw + 4;
        echo '<rect x="' . round($x, 1) . '" y="' . round($h - $bh, 1) . '" width="' . round($bw - 8, 1) . '" height="' . round($bh, 1) . '" rx="3" fill="#3f8e8a"><title>' . e($day) . ': ' . (int) $c . ' tiket</title></rect>';
        if ($c > 0) {
            echo '<text x="' . round($x + ($bw - 8) / 2, 1) . '" y="' . round($h - $bh - 4, 1) . '" font-size="10" text-anchor="middle" fill="#16393b">' . (int) $c . '</text>';
        }
        if ($i % 2 === 0) {
            echo '<text x="' . round($x + ($bw - 8) / 2, 1) . '" y="' . ($h + 16) . '" font-size="9" text-anchor="middle" fill="#667">' . e(substr($day, 8, 2) . '/' . substr($day, 5, 2)) . '</text>';
        }
        $i++;
    }
    echo '</svg>';
}
