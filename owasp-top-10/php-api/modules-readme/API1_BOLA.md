# API1:2023 — Broken Object Level Authorization (BOLA)

**Module file:** `php-api/src/modules/bola.php`  
**Vulnerable endpoint:** `GET /api/bola?user_id=N`  
**Secure endpoint:** `GET /api/bola/secure`



## What This Vulnerability Is

BOLA, also called IDOR (Insecure Direct Object Reference), occurs when an API accepts a user-supplied identifier — in this case `user_id` in the query string — and uses it to look up and return a database record without checking whether the requesting user is actually allowed to see that record.

The object here is a user account row in the database. The reference is the integer ID passed in the URL. The flaw is that the API performs the lookup using whatever ID is supplied and returns the result — no ownership check, no authorisation check.

## The Exploit — Step by Step

There is no login required for the vulnerable endpoint. No token, no credentials.

### Step 1 — Request your own data

```bash
curl 'http://localhost:8080/api/bola?user_id=1'
```
This looks normal. You are user 1, you requested user 1.

### Step 2 — Change the ID

```bash
curl 'http://localhost:8080/api/bola?user_id=2'
```

Bob's private account data — email and account balance — is returned to you with no authorisation check whatsoever.

### Step 3 — Access the admin account

```bash
curl 'http://localhost:8080/api/bola?user_id=4'
```

Returns the admin's email and balance. No admin credentials required.

### Step 4 — Enumerate all accounts

```bash
for ID in 1 2 3 4 5 6 7 8 9 10; do
  echo "=== user_id=$ID ==="
  curl -s "http://localhost:8080/api/bola?user_id=$ID"
  echo
done
```

This loops over sequential IDs and dumps every user account that exists. This is how attackers extract entire user databases through BOLA — automated enumeration of integer IDs.

### Burp Suite Approach

1. Send `GET /api/bola?user_id=1` through Burp Proxy
2. Right-click the request in HTTP History → Send to Intruder
3. Highlight the number `1` in `user_id=1` and click Add §
4. Payload type: Numbers → From 1, To 50, Step 1
5. Start Attack
6. Sort by Response Length — longer responses indicate a user record was found



## The Vulnerable Code

The value from `$_GET['user_id']` goes directly into the SQL query after an `intval()` cast. There is no code anywhere in this block that asks: "does the person making this request own user ID `$user_id`?" The only question asked is whether a user with that ID exists in the database.


## The Fix — Exactly What Changed

Two specific things were added and one thing was removed:

**Added — token extraction and verification:**
```php
$token   = get_bearer_token();
$payload = decode_jwt_secure($token);
if (!$payload) json_response(['error' => 'Unauthorized'], 401);
```
The server now reads the caller's identity from their JWT token. The token was issued by the server after login and is signed with `JWT_SECRET`. The caller cannot alter the `user_id` inside it without invalidating the signature.

**Added — identity comes from the token, not the URL:**
```php
$caller_id = intval($payload['user_id']);
```
Instead of reading `$_GET['user_id']`, the query now uses `$payload['user_id']` — the value the server embedded and signed at login time. The URL parameter `user_id` is completely ignored. An attacker can put any number they like in the URL; it has no effect.

**Removed — the URL parameter as a data source:**  
`$_GET['user_id']` does not appear anywhere in the secure endpoint. Removing it as a data source is what closes the vulnerability. You cannot exploit an object reference that the API never reads.

**Also changed — parameterised query:**  
The vulnerable version concatenated `$user_id` directly into the SQL string. The secure version uses `$db->prepare()` with a `?` placeholder and `bind_param()`. This also prevents SQL injection as a secondary improvement.
