<?php
// Configuration via variables d'environnement Railway

define('GDTF_USER',     getenv('GDTF_USER'));
define('GDTF_PASSWORD', getenv('GDTF_PASSWORD'));
define('GROQ_API_KEY',  getenv('GROQ_API_KEY'));
define('API_SECRET',    getenv('API_SECRET'));

// Dossier temporaire
define('TEMP_DIR', __DIR__ . '/tmp/');
