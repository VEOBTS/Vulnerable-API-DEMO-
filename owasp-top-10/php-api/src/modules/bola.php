<?php
// php-api/src/modules/bola.php
// API1:2023 — Broken Object Level Authorization

function handle_request($method, $uri) {
    $db = get_db();

    // ═══ VULNERABLE ENDPOINT: GET /api/bola ═══════════════════════
    // FLAW: Takes user_id from the URL query string and returns that
    //       user's data with NO check that the caller owns this record.
    if ($uri === '/api/bola') {
        $user_id = intval($_GET['user_id'] ?? 0);
        if (!$user_id) {
            json_response(['error' => 'user_id required'], 400);
        }
        $result = $db->query(
            'SELECT id, username, email, balance FROM users' .
            ' WHERE id = ' . $user_id
        );
        $user = $result->fetch_assoc();
        if (!$user) json_response(['error' => 'not found'], 404);
        json_response(['data' => $user]);
    }

    // ═══ SECURE ENDPOINT: GET /api/bola/secure ════════════════════
    // FIX: Ignores the URL parameter entirely. Reads the caller's
    //      user_id from their verified JWT token instead.
    if ($uri === '/api/bola/secure') {
        $token   = get_bearer_token();
        $payload = decode_jwt_secure($token);
        if (!$payload) json_response(['error' => 'Unauthorized'], 401);

        $caller_id = intval($payload['user_id']);
        $stmt = $db->prepare(
            'SELECT id, username, email, balance FROM users WHERE id = ?'
        );
        $stmt->bind_param('i', $caller_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        json_response(['data' => $user]);
    }
}
