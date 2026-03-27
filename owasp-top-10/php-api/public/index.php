<?php
// php-api/public/index.php

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/router.php';

// Permissive CORS headers — intentional for lab testing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

route($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);