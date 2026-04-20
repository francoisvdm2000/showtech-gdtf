<?php

// ─────────────────────────────────────────────────────────────
// GDTF Backend — Recherche de fixtures sur GDTF Share
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// ── Validation ────────────────────────────────────────────────
$query = trim($_GET['q'] ?? '');
if (empty($query)) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre q manquant']);
    exit;
}

// ── Appel GDTF Share ──────────────────────────────────────────
$result = gdtf_curl('https://gdtf-share.com/apis/public/getList.php');

if ($result['code'] !== 200 || !empty($result['error'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Erreur GDTF Share', 'detail' => $result['error'], 'code' => $result['code']]);
    exit;
}

$decoded = json_decode($result['response'], true);
$allFixtures = $decoded['list'] ?? [];

if (!is_array($allFixtures)) {
    http_response_code(502);
    echo json_encode(['error' => 'Réponse invalide', 'raw' => mb_substr($result['response'], 0, 300)]);
    exit;
}

// ── Filtrage ──────────────────────────────────────────────────
$queryLower = mb_strtolower($query);
$filtered = array_values(array_filter($allFixtures, function($f) use ($queryLower) {
    $haystack = mb_strtolower(($f['fixture'] ?? '') . ' ' . ($f['manufacturer'] ?? ''));
    return strpos($haystack, $queryLower) !== false;
}));

// ── Tri ───────────────────────────────────────────────────────
usort($filtered, function($a, $b) use ($queryLower) {
    $aName = strpos(mb_strtolower($a['fixture'] ?? ''), $queryLower) !== false;
    $bName = strpos(mb_strtolower($b['fixture'] ?? ''), $queryLower) !== false;
    if ($aName !== $bName) return $bName ? 1 : -1;
    return ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0);
});

$filtered = array_slice($filtered, 0, 30);

// ── Formatage ─────────────────────────────────────────────────
$formatted = array_map(function($f) {
    return [
        'rid'          => $f['rid'] ?? '',
        'fixture'      => $f['fixture'] ?? '',
        'manufacturer' => $f['manufacturer'] ?? '',
        'revision'     => $f['revision'] ?? '',
        'rating'       => (float)($f['rating'] ?? 0),
        'uploader'     => $f['uploader'] ?? '',
        'version'      => $f['version'] ?? '',
        'filesize'     => (int)($f['filesize'] ?? 0),
        'modes'        => $f['modes'] ?? [],
        'lastModified' => $f['lastModified'] ?? '',
    ];
}, $filtered);

echo json_encode([
    'query'   => $query,
    'count'   => count($formatted),
    'results' => $formatted,
]);
