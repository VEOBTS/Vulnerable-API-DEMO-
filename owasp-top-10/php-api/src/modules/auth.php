<?php

// Build a signed JWT token for a user

function make_jwt($user_id, $username, $role) {
    $header  = rtrim(strtr(base64_encode(
        json_encode(['alg'=>'HS256','typ'=>'JWT'])
    ),'+/','-_'),'=');
    $payload = rtrim(strtr(base64_encode(json_encode([
        'user_id'  => $user_id,
        'username' => $username,
        'role'     => $role,
        'exp'      => time() + 3600
    ])),'+/','-_'),'=');
    $sig = rtrim(strtr(base64_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    ),'+/','-_'),'=');
    return "$header.$payload.$sig";
}

function handle_request($method, $uri) {
    $db = get_db();

    // ═══ POST /api/auth/login ══════════════════════════════════════
    // FLAW 1: Password hashed with MD5 (not bcrypt)
    // FLAW 2: No rate limiting — unlimited brute-force attempts allowed
    
    if ($uri === '/api/auth/login') {
        $body     = json_decode(file_get_contents('php://input'), true);
        $username = $db->real_escape_string($body['username'] ?? '');
        $password = md5($body['password'] ?? '');
        $result   = $db->query(
            "SELECT * FROM users WHERE username='$username'
             AND password='$password'"
        );
        $user = $result->fetch_assoc();
        if (!$user) json_response(['error' => 'Invalid credentials'], 401);
        $token = make_jwt($user['id'], $user['username'], $user['role']);
        json_response(['token' => $token, 'user_id' => $user['id']]);
    }

    // ═══ VULNERABLE: GET /api/auth/profile ════════════════════════
    // FLAW: Decodes the JWT payload WITHOUT verifying the HMAC signature.
    // Anyone can craft a token with any payload — e.g. role=admin.
    if ($uri === '/api/auth/profile') {
        $token   = get_bearer_token();
        $payload = decode_jwt_insecure($token);
        if (!$payload) json_response(['error' => 'Bad token'], 401);
        json_response(['user' => $payload]);
    }

    // ═══ SECURE: GET /api/auth/profile/secure ═════════════════════
    // FIX: Validates HMAC-SHA256 signature AND checks expiry.
    if ($uri === '/api/auth/profile/secure') {
        $token   = get_bearer_token();
        $payload = decode_jwt_secure($token);
        if (!$payload) json_response(['error' => 'Invalid token'], 401);
        if ($payload['exp'] < time()) {
            json_response(['error' => 'Token expired'], 401);
        }
        json_response(['user' => $payload]);
    }
}