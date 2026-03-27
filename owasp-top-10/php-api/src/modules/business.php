<?php

function handle_request($method, $uri) {
    $db     = get_db();
    $token  = get_bearer_token();
    $caller = decode_jwt_secure($token);
    if (!$caller) json_response(['error' => 'Unauthorized'], 401);
    $uid    = intval($caller['user_id']);

    // ═══ VULNERABLE: no limits whatsoever ════════════════════════
    if ($uri === '/api/order') {
        $body       = json_decode(file_get_contents('php://input'), true);
        $product_id = intval($body['product_id'] ?? 0);
        $quantity   = intval($body['quantity']   ?? 1);
        // FLAW: quantity can be 10,000 — no cap
        // FLAW: no per-user daily limit
        // FLAW: no stock availability check
        $prod = $db->query(
            "SELECT * FROM products WHERE id = $product_id"
        )->fetch_assoc();
        if (!$prod) json_response(['error' => 'Product not found'], 404);
        $total = $prod['price'] * $quantity;
        $db->query(
            "INSERT INTO orders (user_id, product, quantity, total)" .
            " VALUES ($uid, '{$prod['name']}', $quantity, $total)"
        );
        json_response(['order_placed' => true, 'total' => $total]);
    }

    // ═══ SECURE: enforces limits and stock check ═════════════════
    if ($uri === '/api/order/secure') {
        $body       = json_decode(file_get_contents('php://input'), true);
        $product_id = intval($body['product_id'] ?? 0);
        $quantity   = intval($body['quantity']   ?? 1);

        // FIX 1: Max 5 items per order
        if ($quantity < 1 || $quantity > 5) {
            json_response(['error' => 'Quantity must be between 1 and 5'], 400);
        }

        // FIX 2: Max 3 orders per user per calendar day
        $today = date('Y-m-d');
        $cnt   = $db->query(
            "SELECT COUNT(*) AS c FROM orders" .
            " WHERE user_id=$uid AND DATE(created_at)='$today'"
        )->fetch_assoc()['c'];
        if ($cnt >= 3) {
            json_response(['error' => 'Daily order limit reached (max 3)'], 429);
        }

        // FIX 3: Check stock before placing order
        $stmt = $db->prepare(
            'SELECT * FROM products WHERE id = ? AND stock >= ?'
        );
        $stmt->bind_param('ii', $product_id, $quantity);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        if (!$prod) {
            json_response(['error' => 'Insufficient stock or invalid product'], 400);
        }

        $total = $prod['price'] * $quantity;
        $db->query(
            "INSERT INTO orders (user_id, product, quantity, total)" .
            " VALUES ($uid, '{$prod['name']}', $quantity, $total)"
        );
        $db->query(
            "UPDATE products SET stock = stock - $quantity WHERE id = $product_id"
        );
        json_response(['order_placed' => true, 'total' => $total]);
    }
}