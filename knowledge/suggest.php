<?php

// Endpoint JSON: saran artikel panduan saat merchant menulis keluhan (dipanggil dari form tiket)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/kb_suggest.php';
$user = require_login();

header('Content-Type: application/json; charset=utf-8');
$query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 600);
if (mb_strlen($query) < 8) {
    echo json_encode(['items' => []]);
    exit;
}
$articlesResponse = db_get('knowledge_base');
$articles = $articlesResponse['ok'] ? collection_to_list($articlesResponse['data']) : [];
$items = array_map(function ($article) {
    $article['url'] = url('knowledge/view.php?id=' . urlencode($article['id']));
    $article['category_label'] = label(CATEGORIES, $article['category']);
    unset($article['score']);
    return $article;
}, kb_suggest($query, $articles, 3));
echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
