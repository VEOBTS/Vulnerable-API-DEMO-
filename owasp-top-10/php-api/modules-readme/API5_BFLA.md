# API5:2023 — Broken Function Level Authorization

**Module file:** `php-api/src/modules/bfla.php`  
**Vulnerable endpoint:** `GET /api/admin/users`  
**Secure endpoint:** `GET /api/admin/users/secure`

## What This Vulnerability Is

The API authenticates the caller — it checks that a valid token exists — but it does not authorise the caller. It never asks what role that token belongs to. A regular user token and an admin token are treated identically. Any logged-in user can call admin-only functions.

The distinction between authentication and authorisation is the core of this vulnerability:

- **Authentication:** "Is this a valid token?" — answered by checking the signature
- **Authorisation:** "Is this token's owner allowed to call this function?" — never checked in the vulnerable endpoint

## The Exploit — Step by Step

### Step 1 — Login as a regular user

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"alice","password":"password123"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
```

Alice has `role: "user"` in her JWT payload.

### Step 2 — Call the admin endpoint with a regular user token

```bash
curl http://localhost:8080/api/admin/users \
  -H "Authorization: Bearer $TOKEN"

A regular user has retrieved the full user list including password hashes and account balances for every account.

### Step 3 — Confirm the secure endpoint rejects this

```bash
curl http://localhost:8080/api/admin/users/secure \
  -H "Authorization: Bearer $TOKEN"
```

### Burp Suite Approach

1. Capture a login request in Burp Proxy. Get alice's token (role=user).
2. Also capture admin's token — login as admin with the known credentials.
3. In Repeater, send `GET /api/admin/users` first with the admin token (should succeed).
4. Replace the Authorization header with alice's token. The vulnerable endpoint still returns 200.
5. This demonstrates the horizontal privilege escalation — one role's token accessing another role's function.



## The Vulnerable Code
`if (!$token)` checks that the `Authorization` header contains something. If the token string is not empty, execution continues to the database query. The token is never decoded. The role inside the token is never read. Any non-empty Bearer token value — even an expired one, even a malformed one — would pass this check because `!$token` only fails for `null` or an empty string.



## The Fix — Exactly What Changed

**Changed — `if (!$token)` replaced with `decode_jwt_secure($token)`:**  
The secure endpoint no longer checks whether a token string exists — it verifies whether the token is cryptographically valid. `decode_jwt_secure()` validates the HMAC-SHA256 signature and returns the payload only if it passes. An expired, forged, or tampered token returns `null` and the endpoint responds with 401.

**Added — explicit role check:**  
```php
if ($payload['role'] !== 'admin') {
    json_response(['error' => 'Forbidden: admin only'], 403);
}
```
After confirming the token is genuine, the role claim from the verified payload is compared against `'admin'`. Because the token was signed by the server, the `role` field in `$payload` is trustworthy — it reflects what the server set at login time. Alice logged in as a regular user, so her verified token contains `"role":"user"`, and this check returns 403.

The response status is 403 (Forbidden) rather than 401 (Unauthorized) — an important distinction. 401 means "you need to identify yourself". 403 means "I know who you are and you are not allowed". Using the wrong status code leaks information.

**Also changed — `password` removed from the admin response:**  
The vulnerable version includes `password` in `SELECT`. The secure version's column list is `id, username, email, role` — no password hash, even for admin users. Even admin endpoints should return the minimum data necessary.
