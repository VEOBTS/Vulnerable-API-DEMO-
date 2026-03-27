<?php

function handle_request($method, $uri) {
    $db     = get_db();
    $token  = get_bearer_token();
    $caller = decode_jwt_secure($token);
    if (!$caller) json_response(['error' => 'Unauthorized'], 401);
    $uid = intval($caller['user_id']);

    // ═══ VULNERABLE GET: exposes password hash and all columns ════
    if ($uri === '/api/bopla/user' && $method === 'GET') {
        // FLAW: SELECT * returns the password hash to the caller
        $r = $db->query("SELECT * FROM users WHERE id = $uid");
        json_response($r->fetch_assoc());
    }

    // ═══ VULNERABLE PUT: accepts any column including 'role' ══════
    if ($uri === '/api/bopla/user' && $method === 'PUT') {
        $body = json_decode(file_get_contents('php://input'), true);
        // FLAW: Blindly builds SET clause from user-provided fields.
        // An attacker sends {"role":"admin","balance":99999}
        $sets = [];
        foreach ($body as $col => $val) {
            $v = $db->real_escape_string($val);
            $sets[] = "$col = '$v'";
        }
        $sql = 'UPDATE users SET ' . implode(', ', $sets)
             . " WHERE id = $uid";
        $db->query($sql);
        json_response(['updated' => true, 'query_ran' => $sql]);
    }

    // ═══ SECURE GET: whitelist only safe columns ══════════════════
    if ($uri === '/api/bopla/user/secure' && $method === 'GET') {
        // FIX: Explicitly list safe columns. Never use SELECT *.
        $stmt = $db->prepare(
            'SELECT id, username, email, created_at FROM users WHERE id = ?'
        );
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        json_response($stmt->get_result()->fetch_assoc());
    }
}