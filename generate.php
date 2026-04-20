<?php
// ─────────────────────────────────────────────────────────────
// GDTF Backend — Génération GDTF depuis un PDF de manuel
// Endpoint : POST /generate.php
// Body multipart : pdf (file), fixture_name, manufacturer
// ─────────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/config.php';

// ── Validation ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$fixtureName  = trim($_POST['fixture_name']  ?? '');
$manufacturer = trim($_POST['manufacturer']  ?? '');

if (empty($fixtureName) || empty($manufacturer)) {
    http_response_code(400);
    echo json_encode(['error' => 'fixture_name et manufacturer sont requis']);
    exit;
}

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    $uploadError = $_FILES['pdf']['error'] ?? 'fichier absent';
    http_response_code(400);
    echo json_encode(['error' => 'Fichier PDF manquant ou erreur upload', 'upload_error' => $uploadError]);
    exit;
}

// Vérifie que c'est bien un PDF
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['pdf']['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    http_response_code(400);
    echo json_encode(['error' => 'Le fichier doit être un PDF', 'mime_detected' => $mimeType]);
    exit;
}

// ── Extraction texte du PDF ──────────────────────────────────
if (!is_dir(TEMP_DIR)) mkdir(TEMP_DIR, 0755, true);

$pdfPath  = TEMP_DIR . uniqid('pdf_') . '.pdf';
move_uploaded_file($_FILES['pdf']['tmp_name'], $pdfPath);

$textPath  = $pdfPath . '.txt';
exec("pdftotext -layout " . escapeshellarg($pdfPath) . " " . escapeshellarg($textPath) . " 2>&1", $output, $returnCode);

$pdfText = '';
if ($returnCode === 0 && file_exists($textPath)) {
    $pdfText = file_get_contents($textPath);
    unlink($textPath);
} else {
    // Fallback : lecture binaire basique
    $pdfText = shell_exec("strings " . escapeshellarg($pdfPath));
}

unlink($pdfPath);

if (empty(trim($pdfText))) {
    http_response_code(422);
    echo json_encode(['error' => 'Impossible d\'extraire le texte du PDF (PDF scanné ?)']);
    exit;
}

// Limite le texte à 15000 caractères pour l'API
$pdfText = mb_substr($pdfText, 0, 15000);

// ── Prompt Groq ──────────────────────────────────────────────
$prompt = <<<PROMPT
Tu es un expert en éclairage scénique et en format GDTF (General Device Type Format).
Voici le texte extrait du manuel technique de la fixture suivante :
- Fabricant : {$manufacturer}
- Modèle : {$fixtureName}

TEXTE DU MANUEL :
{$pdfText}

Ta tâche :
1. Identifie TOUS les modes DMX présents dans ce manuel
2. Pour chaque mode, liste tous les canaux DMX avec leur numéro, nom et fonction
3. Génère le XML GDTF complet (description.xml) selon la spec GDTF 1.2

Règles importantes :
- Chaque canal doit avoir un nom court en anglais (ex: Dimmer, Pan, Tilt, Red, Green, Blue, Strobe, etc.)
- Les valeurs Default et Highlight doivent être entre 0 et 255
- Utilise des UUID au format XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
- Ne génère QUE le XML, rien d'autre, sans balises markdown

Structure XML attendue :
<?xml version="1.0" encoding="UTF-8"?>
<GDTF DataVersion="1.2">
  <FixtureType Name="{$fixtureName}" ShortName="" Manufacturer="{$manufacturer}" Description="" Type="MovingHead"
    FixtureTypeID="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX" Thumbnail="" RefFT="">
    <AttributeDefinitions>
      <ActivationGroups/>
      <Features>
        <Feature Name="Control"/>
        <Feature Name="Beam"/>
        <Feature Name="Color"/>
        <Feature Name="Position"/>
      </Features>
      <Attributes>
        <!-- Génère les attributs nécessaires -->
      </Attributes>
    </AttributeDefinitions>
    <Wheels/>
    <PhysicalDescriptions/>
    <Models/>
    <Geometries>
      <Geometry Name="Body" Model="" Position="{1,0,0,0}{0,1,0,0}{0,0,1,0}{0,0,0,1}">
        <Geometry Name="Yoke" Model="" Position="{1,0,0,0}{0,1,0,0}{0,0,1,0}{0,0,0,1}">
          <Geometry Name="Head" Model="" Position="{1,0,0,0}{0,1,0,0}{0,0,1,0}{0,0,0,1}"/>
        </Geometry>
      </Geometry>
    </Geometries>
    <DMXModes>
      <!-- Génère tous les modes DMX trouvés -->
    </DMXModes>
    <Revisions/>
    <FTPresets/>
    <Protocols/>
  </FixtureType>
</GDTF>
PROMPT;

// ── Appel Groq API ───────────────────────────────────────────
$groqUrl  = 'https://api.groq.com/openai/v1/chat/completions';
$groqBody = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'temperature' => 0.1,
    'max_tokens'  => 8192,
    'messages'    => [
        [
            'role'    => 'system',
            'content' => 'Tu es un expert GDTF. Tu génères uniquement du XML GDTF valide, sans aucun texte avant ou après, sans balises markdown.',
        ],
        [
            'role'    => 'user',
            'content' => $prompt,
        ],
    ],
]);

$ch = curl_init($groqUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     $groqBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . GROQ_API_KEY,
]);
curl_setopt($ch, CURLOPT_TIMEOUT,        90);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$groqResponse = curl_exec($ch);
$curlError    = curl_error($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$groqResponse) {
    http_response_code(502);
    echo json_encode([
        'error'      => 'Erreur API Groq',
        'code'       => $httpCode,
        'curl_error' => $curlError,
        'detail'     => $groqResponse,
    ]);
    exit;
}

$groqData   = json_decode($groqResponse, true);
$xmlContent = $groqData['choices'][0]['message']['content'] ?? '';

if (empty($xmlContent)) {
    http_response_code(502);
    echo json_encode(['error' => 'Groq n\'a pas retourné de XML']);
    exit;
}

// Nettoie le XML (enlève les balises markdown si présentes)
$xmlContent = preg_replace('/^```xml\s*/m', '', $xmlContent);
$xmlContent = preg_replace('/^```\s*/m',    '', $xmlContent);
$xmlContent = trim($xmlContent);

// ── Création du fichier .gdtf (ZIP) ─────────────────────────
$gdtfPath = TEMP_DIR . uniqid('gdtf_') . '.gdtf';
$zip      = new ZipArchive();

if ($zip->open($gdtfPath, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de créer le fichier GDTF']);
    exit;
}

$zip->addFromString('description.xml', $xmlContent);
$zip->close();

// ── Retourne le .gdtf encodé en base64 dans du JSON ─────────
$safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $manufacturer . '_' . $fixtureName);
$filename = $safeName . '.gdtf';
$gdtfData = base64_encode(file_get_contents($gdtfPath));
unlink($gdtfPath);

echo json_encode([
    'success'     => true,
    'filename'    => $filename,
    'gdtf_base64' => $gdtfData,
]);
