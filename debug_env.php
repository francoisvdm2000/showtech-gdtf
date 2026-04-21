<?php
$key = getenv('GROQ_API_KEY');
echo json_encode([
    'key_length'  => strlen($key),
    'key_prefix'  => substr($key, 0, 8),
    'key_suffix'  => substr($key, -4),
    'key_defined' => !empty($key),
]);
