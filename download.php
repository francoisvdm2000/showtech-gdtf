<?php
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$rid = trim($_GET['rid'] ?? '');
if (empty($rid) || !is_numeric($rid)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètre rid manquant ou invalide']);
    exit;
}

$result = gdtf_curl("https://gdtf-share.com/apis/public/downloadFile.php?rid={$rid}", 30);

if ($result['code'] !== 200 || empty($result['response'])) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Fichier introuvable (rid: {$rid})"]);
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="fixture_' . $rid . '.gdtf"');
header('Content-Length: ' . strlen($result['response']));
echo $result['response'];
