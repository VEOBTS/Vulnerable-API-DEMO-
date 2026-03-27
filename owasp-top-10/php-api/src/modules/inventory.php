<?php
// php-api/src/modules/inventory.php
// API9:2023 — Improper Inventory Management

function handle_request($method, $uri) {
    $db = get_db();

    // ═══ VULNERABLE: old v1 endpoint — no auth, returns everything ═
    if ($uri === '/api/v1/users') {
        // FLAW: This was 'deprecated' but never removed.
        // It has no authentication and returns SELECT * data.
        $res   = $db->query('SELECT * FROM users');
        $users = [];
        while ($r = $res->fetch_assoc()) $users[] = $r;
        json_response([
            'api_version' => 'v1 -- DEPRECATED, should be removed!',
            'users'       => $users
        ]);
    }

    // ═══ CURRENT: v2 endpoint — requires admin token ═════════════
    if ($uri === '/api/v2/users') {
        $token   = get_bearer_token();
        $payload = decode_jwt_secure($token);
        if (!$payload || $payload['role'] !== 'admin') {
            json_response(['error' => 'Forbidden'], 403);
        }
        $res   = $db->query(
            'SELECT id, username, email, role FROM users'
        );
        $users = [];
        while ($r = $res->fetch_assoc()) $users[] = $r;
        json_response(['api_version' => 'v2', 'users' => $users]);
    }
}