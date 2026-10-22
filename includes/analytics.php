<?php

// Perhitungan analitik admin dan peringatan gangguan. Semua fungsi menerima array hasil collection_to_list().

function an_ts(?string $dateString): ?int
{
    $timestamp = $dateString ? strtotime($dateString) : false;
    return $timestamp === false ? null : $timestamp;
}

// Peringatan gangguan: banyak laporan dengan kategori sama dalam waktu singkat
function incident_alerts(array $tickets, int $minutes = 60, int $min = 3): array
{
    $since = time() - $minutes * 60;
    $countByCategory = [];
    foreach ($tickets as $ticket) {
        $createdAt = an_ts($ticket['created_at'] ?? null);
        if ($createdAt !== null && $createdAt >= $since) {
            $category = $ticket['category'] ?? 'lainnya';
            $countByCategory[$category] = ($countByCategory[$category] ?? 0) + 1;
        }
    }
    $alerts = [];
    foreach ($countByCategory as $category => $count) {
        if ($count >= $min && $category !== 'lainnya') {
            $alerts[] = ['category' => $category, 'count' => $count, 'minutes' => $minutes];
        }
    }
    return $alerts;
}

// Waktu respons pertama: tiket dibuat sampai catatan petugas pertama
function an_first_staff_followup(array $followups): ?int
{
    $best = null;
    foreach ($followups as $followup) {
        if (($followup['type'] ?? '') === 'tanggapan_merchant') {
            continue;
        }
        $createdAt = an_ts($followup['created_at'] ?? null);
        if ($createdAt !== null && ($best === null || $createdAt < $best)) {
            $best = $createdAt;
        }
    }
    return $best;
}

function analytics_build(array $tickets, array $followups, array $kbTitles = []): array
{
    $stats = ['total' => count($tickets), 'open' => 0, 'done' => 0, 'by_status' => [], 'by_category' => [], 'by_priority' => [],
          'by_method' => [], 'per_day' => [], 'top_merchants' => [], 'sla' => [], 'top_kb' => [], 'avg_first_response_min' => null,
          'avg_resolution_hours' => null, 'flagged_duplicates' => 0];
    for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
        $stats['per_day'][date('Y-m-d', strtotime("-$daysAgo day"))] = 0;
    }
    $responseMinutes = [];
    $resolutionHours = [];
    $kbCounts = [];
    $merchantCounts = [];
    $now = time();
    foreach ($tickets as $ticket) {
        $status = $ticket['status'] ?? 'baru';
        $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;
        $stats['by_category'][$ticket['category'] ?? 'lainnya'] = ($stats['by_category'][$ticket['category'] ?? 'lainnya'] ?? 0) + 1;
        $stats['by_priority'][$ticket['priority'] ?? 'sedang'] = ($stats['by_priority'][$ticket['priority'] ?? 'sedang'] ?? 0) + 1;
        if (!empty($ticket['payment_method'])) {
            $stats['by_method'][$ticket['payment_method']] = ($stats['by_method'][$ticket['payment_method']] ?? 0) + 1;
        }
        $day = substr((string) ($ticket['created_at'] ?? ''), 0, 10);
        if (isset($stats['per_day'][$day])) {
            $stats['per_day'][$day]++;
        }
        $name = trim((string) ($ticket['owner_org'] ?? '')) !== '' ? $ticket['owner_org'] : ($ticket['owner_name'] ?? 'Tidak diketahui');
        $merchantCounts[$name] = ($merchantCounts[$name] ?? 0) + 1;
        if (!empty($ticket['possible_duplicate_of'])) {
            $stats['flagged_duplicates']++;
        }
        foreach ((array) ($ticket['ai_reply']['related_kb_ids'] ?? []) as $kbId) {
            $kbCounts[$kbId] = ($kbCounts[$kbId] ?? 0) + 1;
        }

        $created = an_ts($ticket['created_at'] ?? null);
        $ticketFollowups = is_array($followups[$ticket['id']] ?? null) ? $followups[$ticket['id']] : [];
        $first = an_first_staff_followup($ticketFollowups);
        if ($created !== null && $first !== null && $first >= $created) {
            $responseMinutes[] = ($first - $created) / 60;
        }
        if ($status === 'selesai') {
            $stats['done']++;
            $end = an_ts($ticket['updated_at'] ?? null);
            if ($created !== null && $end !== null && $end >= $created) {
                $resolutionHours[] = ($end - $created) / 3600;
            }
        } else {
            $stats['open']++;
            // SLA: tiket terbuka tanpa tanggapan petugas yang melewati batas respons
            if ($first === null && $created !== null) {
                $limit = (SLA_HOURS[$ticket['priority'] ?? 'sedang'] ?? 24) * 3600;
                if ($now - $created > $limit) {
                    $stats['sla'][] = [
                        'id' => $ticket['id'],
                        'title' => $ticket['title'] ?? '',
                        'priority' => $ticket['priority'] ?? 'sedang',
                        'overdue_hours' => round(($now - $created - $limit) / 3600, 1),
                    ];
                }
            }
        }
    }
    usort($stats['sla'], fn ($firstItem, $secondItem) => $secondItem['overdue_hours'] <=> $firstItem['overdue_hours']);
    $stats['sla'] = array_slice($stats['sla'], 0, 10);
    arsort($merchantCounts);
    $stats['top_merchants'] = array_slice($merchantCounts, 0, 5, true);
    arsort($kbCounts);
    foreach (array_slice($kbCounts, 0, 5, true) as $id => $count) {
        $stats['top_kb'][] = ['id' => $id, 'title' => $kbTitles[$id] ?? $id, 'count' => $count];
    }
    $stats['avg_first_response_min'] = $responseMinutes ? round(array_sum($responseMinutes) / count($responseMinutes), 1) : null;
    $stats['avg_resolution_hours'] = $resolutionHours ? round(array_sum($resolutionHours) / count($resolutionHours), 1) : null;
    $stats['done_rate'] = $stats['total'] > 0 ? round($stats['done'] / $stats['total'] * 100) : 0;
    $stats['alerts'] = incident_alerts($tickets);
    return $stats;
}

// Statistik satu hari untuk ringkasan harian (tanpa data pribadi merchant)
function daily_stats(string $date, array $tickets): array
{
    $created = array_values(array_filter($tickets, fn ($ticket) => substr((string) ($ticket['created_at'] ?? ''), 0, 10) === $date));
    $closed = array_values(array_filter($tickets, fn ($ticket) => ($ticket['status'] ?? '') === 'selesai' && substr((string) ($ticket['updated_at'] ?? ''), 0, 10) === $date));
    $open = array_values(array_filter($tickets, fn ($ticket) => ($ticket['status'] ?? '') !== 'selesai'));
    $countBy = function (array $rows, string $key, array $labels) {
        $counts = [];
        foreach ($rows as $row) {
            $labelText = label($labels, $row[$key] ?? '');
            $counts[$labelText] = ($counts[$labelText] ?? 0) + 1;
        }
        arsort($counts);
        return $counts;
    };
    $important = array_values(array_filter($created, fn ($ticket) => in_array($ticket['priority'] ?? '', ['kritis', 'tinggi'], true)));
    return [
        'tanggal' => $date,
        'tiket_masuk' => count($created),
        'tiket_selesai' => count($closed),
        'tiket_masih_terbuka' => count($open),
        'per_kategori' => $countBy($created, 'category', CATEGORIES),
        'per_prioritas' => $countBy($created, 'priority', PRIORITIES),
        'per_metode_bayar' => $countBy($created, 'payment_method', PAYMENT_METHODS),
        'tiket_prioritas_tinggi' => array_map(fn ($ticket) => mask_sensitive((string) ($ticket['title'] ?? '')), array_slice($important, 0, 5)),
    ];
}

// Simpan ringkasan harian ke daily_summaries/{tanggal}. Mengembalikan hasil db_put.
function daily_generate(string $date, ?string $token, string $generatedBy): array
{
    $ticketsResponse = db_get('tickets', $token);
    if (!$ticketsResponse['ok']) {
        return ['ok' => false, 'error' => 'Gagal membaca tiket: ' . $ticketsResponse['error']];
    }
    $stats = daily_stats($date, collection_to_list($ticketsResponse['data']));
    $aiResult = ai_daily_summary($date, $stats);
    if (!$aiResult['ok']) {
        return ['ok' => false, 'error' => $aiResult['error'], 'stats' => $stats];
    }
    $record = $aiResult['data'] + ['stats' => $stats, 'date' => $date, 'generated_at' => now_string(), 'generated_by' => $generatedBy];
    $saveResponse = db_put('daily_summaries/' . $date, $record, $token);
    return ['ok' => $saveResponse['ok'], 'error' => $saveResponse['error'], 'record' => $record];
}

// Tampilan sederhana tanpa library: bar HTML dan grafik SVG
function render_bars(array $data, array $labels = []): void
{
    $max = max(1, ...array_values($data ?: [0]));
    echo '<div class="bars">';
    foreach ($data as $barKey => $barCount) {
        $text = $labels[$barKey] ?? (string) $barKey;
        echo '<div class="bar-row"><span>' . e($text) . '</span><div class="bar"><span style="width:' . round($barCount / $max * 100) . '%"></span></div><strong>' . (int) $barCount . '</strong></div>';
    }
    if (!$data) {
        echo '<p class="muted">Belum ada data.</p>';
    }
    echo '</div>';
}

function render_day_chart(array $perDay): void
{
    $max = max(1, ...array_values($perDay ?: [0]));
    $dayTotal = max(1, count($perDay));
    $width = 560;
    $height = 130;
    $barWidth = $width / $dayTotal;
    echo '<svg class="daychart" viewBox="0 0 ' . $width . ' ' . ($height + 28) . '" role="img" aria-label="Jumlah tiket per hari">';
    $index = 0;
    foreach ($perDay as $day => $dayCount) {
        $barHeight = $dayCount / $max * $height;
        $barX = $index * $barWidth + 4;
        echo '<rect x="' . round($barX, 1) . '" y="' . round($height - $barHeight, 1) . '" width="' . round($barWidth - 8, 1) . '" height="' . round($barHeight, 1) . '" rx="3" fill="#3f8e8a"><title>' . e($day) . ': ' . (int) $dayCount . ' tiket</title></rect>';
        if ($dayCount > 0) {
            echo '<text x="' . round($barX + ($barWidth - 8) / 2, 1) . '" y="' . round($height - $barHeight - 4, 1) . '" font-size="10" text-anchor="middle" fill="#16393b">' . (int) $dayCount . '</text>';
        }
        if ($index % 2 === 0) {
            echo '<text x="' . round($barX + ($barWidth - 8) / 2, 1) . '" y="' . ($height + 16) . '" font-size="9" text-anchor="middle" fill="#667">' . e(substr($day, 8, 2) . '/' . substr($day, 5, 2)) . '</text>';
        }
        $index++;
    }
    echo '</svg>';
}
