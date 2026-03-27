# API4:2023 — Unrestricted Resource Consumption

**Module file:** `php-api/src/modules/rate.php`  
**Vulnerable endpoint:** `GET /api/search`  
**Secure endpoint:** `GET /api/search/secure`


## What This Vulnerability Is

The API accepts search queries with no limit on how many requests a single client can make, no cap on how many rows are returned, and no pagination. This enables two attacks:

- **Data harvesting** — a wildcard query (`q=%`) returns every user in the database in a single response with no authentication required.
- **Denial of Service** — flooding the endpoint with concurrent requests exhausts PHP worker processes and MySQL connections, making the API unresponsive for legitimate users.

In real systems this category also covers paid API operations (SMS, email, biometric checks) where no limit means unlimited cost to the operator.



## The Exploit — Step by Step

### Attack 1 — Wildcard Data Dump

```bash
# The % character is the SQL LIKE wildcard — matches everything
curl 'http://localhost:8080/api/search?q=%'


All users returned in one request, no authentication required. In a production database with 100,000 users, this single request would dump all of them.

### Attack 2 — Denial of Service Flood

```bash
echo "Sending 200 concurrent requests..."
for i in $(seq 1 200); do
  curl -s 'http://localhost:8080/api/search?q=a' > /dev/null &
done
wait
echo "Done — all 200 requests accepted, no blocking"
```

The server accepts all 200 simultaneously. Each request runs a database query. Apache and MySQL have finite thread/connection pools; sustained flooding exhausts them and legitimate requests begin timing out.

### Attack 3 — Demonstrate Rate Limit on Secure Endpoint

```bash
for i in $(seq 1 13); do
  echo -n "Request $i: "
  curl -s 'http://localhost:8080/api/search/secure?q=a' \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('error', f'{len(d[\"results\"])} results'))"
done
```

Requests 1–10 return results. Requests 11–13 return:
```
Rate limit exceeded. Try again in 60 seconds.
```
## The Vulnerable Code

The SQL query has no `LIMIT` clause. The `while` loop fetches every matching row into `$rows`. If 100,000 users match the query, 100,000 rows are loaded into PHP memory and serialised into the response. There is no check on how many times this has been called from the same IP address.

## The Fix — Exactly What Changed

**Added — APCu rate limiter:**
```php
$ip  = $_SERVER['REMOTE_ADDR'];
$key = 'rate_' . md5($ip);
$hits = apcu_fetch($key);
if ($hits === false) $hits = 0;
if ($hits >= 10) {
    json_response(['error' => 'Rate limit exceeded...'], 429);
}
apcu_store($key, $hits + 1, 60);
```
APCu is PHP's in-memory key-value cache. This block reads a counter keyed by the caller's IP address, increments it, and returns 429 if it exceeds 10. The `60` in `apcu_store` is the TTL in seconds — the counter resets after 60 seconds. The HTTP 429 status code (`Too Many Requests`) is the correct status for rate limit responses.

**Added — `LIMIT` and `OFFSET` in the SQL query:**
```php
$page = max(1, intval($_GET['page'] ?? 1));
$size = min(10, intval($_GET['size'] ?? 5));
$off  = ($page - 1) * $size;
```
`min(10, ...)` caps the page size at 10 regardless of what the caller sends. `max(1, ...)` prevents negative or zero page numbers. `LIMIT $size OFFSET $off` is appended to the query — MySQL now returns at most 10 rows per request.

**Changed — `email` column removed from the secure response:**  
The secure version returns only `id` and `username`. Email is not needed for a search results list and removing it reduces data exposure as a secondary improvement.

**Production note:** APCu is per-process and does not persist across restarts. A production rate limiter should use Redis with a sliding window algorithm so limits are shared across all PHP worker processes and survive restarts.
