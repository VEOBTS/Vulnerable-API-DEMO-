<?php
// php-api/src/modules/unsafe.php
// API10:2023 — Unsafe Consumption of APIs

function handle_request($method, $uri) {

    // ═══ VULNERABLE: blindly trusts third-party/internal data ════
    if ($uri === '/api/weather') {
        $city = $_GET['city'] ?? 'Lagos';
        // Simulated call to a third-party / internal service
        $data = call_node('/metadata');

        // FLAW 1: Takes 'hostname' from the service response and uses
        //         it directly in an SQL query — no sanitisation
        $hostname = $data['hostname'];
        $db       = get_db();
        $res      = $db->query(
            "SELECT * FROM products WHERE name LIKE '%$hostname%'"
        );
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;

        // FLAW 2: Raw third-party response reflected back to caller
        // Leaks internal fields like 'iam_token'
        json_response([
            'city'          => $city,
            'internal_data' => $data,
            'products'      => $rows
        ]);
    }

    // ═══ SECURE: validate, sanitise, and never expose raw data ══
    if ($uri === '/api/weather/secure') {
        $city = $_GET['city'] ?? 'Lagos';
        $data = call_node('/metadata');

        // FIX 1: Validate that expected fields exist and have correct types
        if (!isset($data['hostname']) || !is_string($data['hostname'])) {
            json_response(['error' => 'Invalid response from upstream'], 502);
        }

        // FIX 2: Sanitise and use parameterised query
        $db   = get_db();
        $like = '%' . $db->real_escape_string($data['hostname']) . '%';
        $stmt = $db->prepare(
            'SELECT id, name, price FROM products WHERE name LIKE ?'
        );
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $rows = [];
        while ($r = $stmt->get_result()->fetch_assoc()) $rows[] = $r;

        // FIX 3: Never expose raw upstream response
        json_response(['city' => $city, 'products' => $rows]);
    }
}