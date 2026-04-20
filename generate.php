<?php
// ─────────────────────────────────────────────────────────────
// GDTF Backend — Génération GDTF depuis un PDF
// POST JSON : { "fixture_name": "", "manufacturer": "", "pdf_base64": "" }
// ─────────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']); exit;
}

// ── Lecture du body JSON ─────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

$fixtureName  = trim($body['fixture_name']  ?? '');
$manufacturer = trim($body['manufacturer']  ?? '');
$pdfBase64    = trim($body['pdf_base64']    ?? '');

if (empty($fixtureName) || empty($manufacturer)) {
    http_response_code(400);
    echo json_encode(['error' => 'fixture_name et manufacturer sont requis']); exit;
}
if (empty($pdfBase64)) {
    http_response_code(400);
    echo json_encode(['error' => 'pdf_base64 est requis']); exit;
}

// ── Décode le PDF et extraction texte ───────────────────────
$pdfBytes = base64_decode($pdfBase64);
if ($pdfBytes === false || strlen($pdfBytes) < 100) {
    http_response_code(400);
    echo json_encode(['error' => 'pdf_base64 invalide']); exit;
}

if (!is_dir(TEMP_DIR)) mkdir(TEMP_DIR, 0755, true);
$pdfPath  = TEMP_DIR . uniqid('pdf_') . '.pdf';
file_put_contents($pdfPath, $pdfBytes);

$textPath = $pdfPath . '.txt';
exec("pdftotext -layout " . escapeshellarg($pdfPath) . " " . escapeshellarg($textPath) . " 2>&1", $out, $rc);

$pdfText = '';
if ($rc === 0 && file_exists($textPath)) {
    $pdfText = file_get_contents($textPath);
    unlink($textPath);
} else {
    $pdfText = shell_exec("strings " . escapeshellarg($pdfPath)) ?? '';
}
unlink($pdfPath);

if (empty(trim($pdfText))) {
    http_response_code(422);
    echo json_encode(['error' => 'Impossible d\'extraire le texte du PDF']); exit;
}

$pdfText = mb_substr($pdfText, 0, 15000);

// ── Prompt Groq ──────────────────────────────────────────────
$prompt = <<<PROMPT
Tu es un expert en éclairage scénique et en format GDTF 1.2.
Voici le texte extrait du manuel technique :
- Fabricant : {$manufacturer}
- Modèle : {$fixtureName}

TEXTE DU MANUEL :
{$pdfText}

Génère un fichier description.xml GDTF 1.2 complet avec :
1. Tous les modes DMX trouvés dans le manuel
2. Tous les canaux de chaque mode avec leur numéro, nom anglais court et fonction
3. Une géométrie de base simple (Body > Yoke > Head)

Règles :
- Noms de canaux en anglais court (Dimmer, Pan, Tilt, Red, Green, Blue, Strobe, etc.)
- Valeurs Default et Highlight entre 0 et 255
- UUID valides format XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX (génère des vrais UUID aléatoires)
- Retourne UNIQUEMENT le XML, sans markdown, sans texte avant ou après

PROMPT;

// ── Appel Groq ───────────────────────────────────────────────
$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'temperature' => 0.1,
        'max_tokens'  => 8192,
        'messages'    => [
            ['role' => 'system', 'content' => 'Tu génères uniquement du XML GDTF 1.2 valide, sans aucun texte avant ou après.'],
            ['role' => 'user',   'content' => $prompt],
        ],
    ]),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_TIMEOUT        => 90,
]);

$groqResponse = curl_exec($ch);
$curlError    = curl_error($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$groqResponse) {
    http_response_code(502);
    echo json_encode(['error' => 'Erreur API Groq', 'code' => $httpCode, 'curl_error' => $curlError]); exit;
}

$groqData   = json_decode($groqResponse, true);
$xmlContent = $groqData['choices'][0]['message']['content'] ?? '';

if (empty($xmlContent)) {
    http_response_code(502);
    echo json_encode(['error' => 'Groq n\'a pas retourné de XML']); exit;
}

// Nettoie les balises markdown si présentes
$xmlContent = preg_replace('/^```xml\s*/m', '', $xmlContent);
$xmlContent = preg_replace('/^```\s*/m',    '', $xmlContent);
$xmlContent = trim($xmlContent);

// ── Création du .gdtf (ZIP contenant description.xml) ───────
$gdtfPath = TEMP_DIR . uniqid('gdtf_') . '.gdtf';
$zip = new ZipArchive();
if ($zip->open($gdtfPath, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de créer le fichier GDTF']); exit;
}
$zip->addFromString('description.xml', $xmlContent);
$zip->close();

// ── Réponse JSON avec le .gdtf en base64 ────────────────────
$safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $manufacturer . '_' . $fixtureName);
$gdtfData = base64_encode(file_get_contents($gdtfPath));
unlink($gdtfPath);

echo json_encode([
    'success'     => true,
    'filename'    => $safeName . '.gdtf',
    'gdtf_base64' => $gdtfData,
]);
