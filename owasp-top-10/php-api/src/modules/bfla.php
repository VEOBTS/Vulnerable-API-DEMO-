<?php
function handle_request($method, $uri) {
    $db    = get_db();
    $token = get_bearer_token();

    // ═══ VULNERABLE: checks token EXISTS, but not the role ════════
    if ($uri === '/api/admin/users') {
        // FLAW: Any valid token passes this check, even role=user
        if (!$token) json_response(['error' => 'Token required'], 401);
        $res   = $db->query(
            'SELECT id, username, email, password, role, balance FROM users'
        );
        $users = [];
        while ($r = $res->fetch_assoc()) $users[] = $r;
        json_response(['users' => $users]);
    }

    // ═══ SECURE: verifies token AND checks role = admin ═══════════
    if ($uri === '/api/admin/users/secure') {
        $payload = decode_jwt_secure($token);
        if (!$payload) json_response(['error' => 'Unauthorized'], 401);
        // FIX: Explicitly check the role claim from the verified token
        if ($payload['role'] !== 'admin') {
            json_response(['error' => 'Forbidden: admin only'], 403);
        }
        $res   = $db->query(
            'SELECT id, username, email, role FROM users'
        );
        $users = [];
        while ($r = $res->fetch_assoc()) $users[] = $r;
        json_response(['users' => $users]);
    }
}<?php
// php-api/src/modules/bfla.php
// API5:2023 — Broken Function Level Authorization

function handle_request($method, $uri) {
    $db    = get_db();
    $token = get_bearer_token();

    // ═══ VULNERABLE: checks token EXISTS, but not the role ════════
    if ($uri === '/api/admin/users') {
        // FLAW: Any valid token passes this check, even role=user
        if (!$token) json_response(['error' => 'Token required'], 401);
        $res   = $db->query(
            'SELECT id, username, email, password, role, balance FROM users'
        );
        $users = [];
        while ($r = $res->fetch_assoc()) $users[] = $r;
        json_response(['users' => $users]);
    }

    // ═══ SECURE: verifies token AND checks role = admin ═══════════
    if ($uri === '/api/admin/users/secure') {
        $payload = decode_jwt_secure($token);
        if (!$payload) json_response(['error' => 'Unauthorized'], 401);
        // FIX: Explicitly check the role claim from the verified token
        if ($payload['role'] !== 'admin') {
            json_response(['error' => 'Forbidden: admin only'], 403);
        }
        $res   = $db->query(
            'SELECT id, username, email, role FROM users'
        );
        $users = [];
        while ($r = $res->fetch_assoc()) $users[] = $r;
        json_response(['users' => $users]);
    }
}