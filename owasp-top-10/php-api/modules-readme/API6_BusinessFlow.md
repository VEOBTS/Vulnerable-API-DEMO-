# API6:2023 — Unrestricted Access to Sensitive Business Flows

**Module file:** `php-api/src/modules/business.php`  
**Vulnerable endpoint:** `POST /api/order`  
**Secure endpoint:** `POST /api/order/secure`

## What This Vulnerability Is

This vulnerability is about business logic, not a technical implementation bug. The API is technically working correctly — it places orders as designed. The problem is that it places orders with no constraints on quantity, frequency, or stock availability. A bot script can legitimately call the endpoint thousands of times or request thousands of units in a single call, which harms the business and blocks legitimate users.

This is different from the other vulnerabilities in the lab. There is no "hack" here in the traditional sense — the attacker is simply using the API as designed, but at a scale and frequency the business never intended to allow.

## The Exploit — Step by Step

### Prerequisites — Get a Token

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"alice","password":"password123"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
```

### Attack 1 — Order More Than Exists

Widget A has 100 units in stock. Order 10,000:

```bash
curl -X POST http://localhost:8080/api/order \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"product_id":1,"quantity":10000}'
```

Response:
```json
{ "order_placed": true, "total": 99900.0 }
```

The order is placed for 10,000 units against a stock of 100. No stock check occurs. The inventory column in the database is never updated.

### Attack 2 — Unlimited Orders Per Day

A bot places 50 orders in rapid succession:

```bash
for i in $(seq 1 50); do
  curl -s -X POST http://localhost:8080/api/order \
    -H "Authorization: Bearer $TOKEN" \
    -H 'Content-Type: application/json' \
    -d '{"product_id":1,"quantity":1}' &
done
wait
echo "50 orders placed — no daily limit triggered"
```

All 50 succeed. A legitimate user trying to buy the same product finds it "sold out" in the orders table even though the stock column was never decremented (because there is no stock update in the vulnerable version either).

### Confirm the Secure Endpoint Blocks These

```bash
# Quantity too high
curl -X POST http://localhost:8080/api/order/secure \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"product_id":1,"quantity":10000}'
# {"error":"Quantity must be between 1 and 5"}

# After 3 orders today — daily limit
for i in 1 2 3 4; do
  curl -s -X POST http://localhost:8080/api/order/secure \
    -H "Authorization: Bearer $TOKEN" \
    -H 'Content-Type: application/json' \
    -d '{"product_id":1,"quantity":1}'
  echo
done
# 4th request: {"error":"Daily order limit reached (max 3)"}
```
## The Vulnerable Code

Three missing checks: no quantity cap, no per-user daily limit, no stock availability verification before inserting the order.

## The Fix — Exactly What Changed

**Added — quantity cap:**  
`if ($quantity < 1 || $quantity > 5)` rejects any request outside the allowed range before any database work is done. The number 5 is a business rule — it lives in the code where it can be reviewed and changed deliberately.

**Added — daily order limit query:**  
```php
SELECT COUNT(*) AS c FROM orders WHERE user_id=$uid AND DATE(created_at)='$today'
```
This counts how many orders the current user has placed today. If the count is 3 or more, the request is rejected with 429. The limit is enforced per `user_id`, not per IP, so changing your IP address does not bypass it.

**Added — stock availability check:**  
```php
SELECT * FROM products WHERE id = ? AND stock >= ?
```
The `AND stock >= ?` condition means the query returns a result only if there is enough stock to fulfil this order. If `stock` is 2 and the order requests 5, the product is not found and the order is rejected. Without this check, orders can be placed against zero or negative stock.

**Added — stock decrement:**  
```php
UPDATE products SET stock = stock - $quantity WHERE id = $product_id
```
After the order is inserted, the stock column is decremented. The stock check and decrement together form a basic inventory transaction — the check prevents over-selling, and the decrement keeps the stock count accurate. A production system would wrap both in an explicit `BEGIN TRANSACTION ... COMMIT` block to prevent race conditions under concurrent requests.
