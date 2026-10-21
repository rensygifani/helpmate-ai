<?php
// Endpoint JSON: saran artikel panduan saat merchant menulis keluhan (dipanggil dari form tiket)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/kb_suggest.php';
$user = require_login();

header('Content-Type: application/json; charset=utf-8');
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 600);
if (mb_strlen($q) < 8) {
    echo json_encode(['items' => []]);
    exit;
}
$res      = db_get('knowledge_base');
$articles = $res['ok'] ? collection_to_list($res['data']) : [];
$items    = array_map(function ($a) {
    $a['url'] = url('knowledge/view.php?id=' . urlencode($a['id']));
    $a['category_label'] = label(CATEGORIES, $a['category']);
    unset($a['score']);
    return $a;
}, kb_suggest($q, $articles, 3));
echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
