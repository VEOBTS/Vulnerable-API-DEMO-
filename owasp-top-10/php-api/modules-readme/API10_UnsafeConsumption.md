# API10:2023 — Unsafe Consumption of APIs

**Module file:** `php-api/src/modules/unsafe.php`  
**Vulnerable endpoint:** `GET /api/weather`  
**Secure endpoint:** `GET /api/weather/secure`

## What This Vulnerability Is

Developers tend to trust data coming from other APIs — especially internal ones — more than they trust data from users. This trust is misplaced. Data from any external or internal service must be treated as untrusted input and subjected to the same validation and sanitisation as user input.

This module demonstrates two specific problems:

1. **SQL injection via upstream data** — a field from the internal Node.js service response is used directly in an SQL query without sanitisation. If the internal service were compromised or returned unexpected data, this becomes a SQL injection vector.
2. **Sensitive data reflection** — the raw response from the internal service is included in the API's outward response, exposing internal fields (`iam_token`, `role`, `hostname`) that were never meant to be seen by API callers.


## The Exploit — Step by Step

### Attack 1 — View Reflected Internal Data

```bash
curl 'http://localhost:8080/api/weather?city=Lagos'

The `internal_data` object exposes three fields from the Node.js service that the caller was never meant to see:

- `hostname` — reveals the internal Docker service name, useful for further SSRF or lateral movement
- `role` — reveals internal service classification
- `iam_token` — in a cloud environment, this would be a real IAM credential granting access to cloud infrastructure

### Attack 2 — Demonstrate Injection Risk via Upstream Data

The `hostname` field from the Node.js response is used directly in an SQL query:

```php
$hostname = $data['hostname'];  // "node-internal"
$res = $db->query(
    "SELECT * FROM products WHERE name LIKE '%$hostname%'"
);
```

If a compromised or malicious internal service returned a crafted `hostname` value like:
```
' UNION SELECT id,username,password,role,balance,created_at FROM users-- 
```

The resulting SQL query would become a UNION injection that extracts the users table through the products endpoint. This demonstrates supply-chain injection — attacking through a trusted upstream, not through direct user input.

In this lab the Node.js service returns a benign value, but the structural vulnerability — unsanitised upstream data in a raw SQL query — is present and real.

### Compare with the Secure Endpoint

```bash
curl 'http://localhost:8080/api/weather/secure?city=Lagos'

## The Vulnerable Code

Two lines are responsible for the two vulnerabilities:

- `$hostname = $data['hostname'];` followed by direct interpolation into the SQL string — no sanitisation
- `'internal_data' => $data` — the complete raw upstream response included in the outward JSON

## The Fix — Exactly What Changed

Before touching any field from `$data`, the code checks that `hostname` exists and is a string. If the upstream service returned something unexpected — `null`, an array, a number, or if the field is missing entirely — the endpoint returns 502 Bad Gateway instead of proceeding with malformed data. This is the point where upstream data transitions from "trusted enough to process" to "validated and typed". Only after this check does the code use the value.

**Changed — parameterised query instead of string interpolation:**
```php
$like = '%' . $db->real_escape_string($data['hostname']) . '%';
$stmt = $db->prepare('SELECT id, name, price FROM products WHERE name LIKE ?');
$stmt->bind_param('s', $like);
```
Even after validation, the value is passed through `real_escape_string()` and then through a prepared statement with `bind_param`. This double-sanitisation ensures that even a validated string cannot break out of the SQL context. The `?` placeholder means MySQL treats the entire value as a data literal — no SQL keyword inside it can be interpreted as a command.

**Changed — explicit column list instead of `SELECT *`:**  
`SELECT id, name, price` replaces `SELECT *`. Internal product fields that should not be in the response (e.g. supplier information if it existed) are excluded.

**Removed — `'internal_data' => $data` from the response:**  
This single deletion closes the data reflection vulnerability. `$data` is now used internally for the query and then discarded. The caller receives only `city` and `products` — nothing from the upstream response is forwarded.

The rule this enforces: your API's output contract should be defined by your requirements, not by whatever your upstream services happen to return.
