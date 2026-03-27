<?php
// php-api/src/modules/rate.php
// API4:2023 — Unrestricted Resource Consumption

function handle_request($method, $uri) {
    $db = get_db();

    // ═══ VULNERABLE: no rate limit, no pagination, no LIMIT clause ═
    if ($uri === '/api/search') {
        $q    = $db->real_escape_string($_GET['q'] ?? '');
        // FLAW: No LIMIT — returns ALL matching rows
        // FLAW: No rate limiting — caller can send unlimited requests
        $res  = $db->query(
            "SELECT id, username, email FROM users
             WHERE username LIKE '%$q%'"
        );
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        json_response(['count' => count($rows), 'results' => $rows]);
    }

    // ═══ SECURE: rate limit + pagination + result cap ═════════════
    if ($uri === '/api/search/secure') {
        // Simple per-IP rate limit using PHP APCu
        // Production systems should use Redis with a sliding window
        $ip  = $_SERVER['REMOTE_ADDR'];
        $key = 'rate_' . md5($ip);
        $hits = apcu_fetch($key);
        if ($hits === false) $hits = 0;
        if ($hits >= 10) {
            json_response([
                'error' => 'Rate limit exceeded. Try again in 60 seconds.'
            ], 429);
        }
        apcu_store($key, $hits + 1, 60);

        $q    = $db->real_escape_string($_GET['q'] ?? '');
        $page = max(1, intval($_GET['page'] ?? 1));
        $size = min(10, intval($_GET['size'] ?? 5));
        $off  = ($page - 1) * $size;

        $res  = $db->query(
            "SELECT id, username FROM users
             WHERE username LIKE '%$q%'
             LIMIT $size OFFSET $off"
        );
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        json_response(['page'=>$page,'size'=>$size,'results'=>$rows]);
    }
}