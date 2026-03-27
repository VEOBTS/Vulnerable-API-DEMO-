<?php
// php-api/src/helpers.php

// Send a JSON HTTP response and stop all further execution
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Return a shared MySQL connection (created once, reused)
function get_db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            json_response(['error' => 'DB connection failed'], 500);
        }
    }
    return $conn;
}

// Extract the Bearer token from the Authorization header
function get_bearer_token() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
        return $m[1];
    }
    return null;
}

// Decode JWT WITHOUT verifying the signature — used for API2 vuln demo
function decode_jwt_insecure($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $payload = base64_decode(str_pad(
        strtr($parts[1], '-_', '+/'),
        strlen($parts[1]) % 4, '='
    ));
    return json_decode($payload, true);
}

// Decode JWT WITH signature verification — used by secure endpoints
function decode_jwt_secure($token) {
    if (!$token) return null;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $sig_input = $parts[0] . '.' . $parts[1];
    $expected  = rtrim(strtr(base64_encode(
        hash_hmac('sha256', $sig_input, JWT_SECRET, true)
    ), '+/', '-_'), '=');
    if (!hash_equals($expected, $parts[2])) return null;
    $payload = base64_decode(str_pad(
        strtr($parts[1], '-_', '+/'),
        strlen($parts[1]) % 4, '='
    ));
    return json_decode($payload, true);
}

// Make an HTTP request to the Node.js internal service
function call_node($path, $method = 'GET', $body = null) {
    $url = NODE_URL . $path;
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type: application/json']);
    }
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}