<?php
// ─────────────────────────────────────────────────────────────
// GDTF Backend — Helper d'authentification GDTF Share
// ─────────────────────────────────────────────────────────────

function gdtf_get_session() {
    $ch = curl_init('https://gdtf-share.com/apis/public/login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'user'     => GDTF_USER,
        'password' => GDTF_PASSWORD,
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $fullResponse = curl_exec($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    // curl_close($ch); // deprecated PHP 8.5

    if ($httpCode !== 200) return false;

    $headers = substr($fullResponse, 0, $headerSize);
    $body    = substr($fullResponse, $headerSize);
    $data    = json_decode($body, true);

    if (empty($data['result'])) return false;

    preg_match('/PHPSESSID=([a-zA-Z0-9]+)/', $headers, $m);
    return isset($m[1]) ? $m[1] : false;
}

function gdtf_curl($url, $timeout = 20) {
    $sessid = gdtf_get_session();
    if (!$sessid) {
        return ['code' => 0, 'response' => '', 'error' => 'Login GDTF Share échoué'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cookie: PHPSESSID=' . $sessid]);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    // curl_close($ch); // deprecated PHP 8.5

    return [
        'code'     => $httpCode,
        'response' => $response,
        'error'    => $error,
    ];
}
