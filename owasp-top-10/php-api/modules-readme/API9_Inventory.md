# API9:2023 — Improper Inventory Management

**Module file:** `php-api/src/modules/inventory.php`  
**Vulnerable endpoint:** `GET /api/v1/users`  
**Current endpoint:** `GET /api/v2/users`

## What This Vulnerability Is

APIs accumulate versions over time. When a new version is released with better security controls, the old version is often left running because removing it might break existing clients. Over time, these old versions are forgotten — they are not in the current documentation, not monitored, and not held to the same security standards as the current version.

An attacker who knows that versioned APIs exist (which is most APIs) will systematically try common version patterns. Finding a version that predates the security improvements gives them unprotected access to the same underlying data.

In this lab, `/api/v2/users` requires an admin JWT token. `/api/v1/users` was the original endpoint built before authentication was added. It was never decommissioned.


## The Exploit — Step by Step

### Step 1 — Version Enumeration

No credentials needed for this step.

```bash
for version in v0 v1 v2 v3 v4 beta dev staging internal legacy old; do
  STATUS=$(curl -s -o /dev/null -w '%{http_code}' \
    "http://localhost:8080/api/$version/users")
  echo "/api/$version/users -> HTTP $STATUS"
done
```

`/api/v1/users` returns 200. `/api/v2/users` returns 403 — confirming that v2 has access control but v1 does not.

### Step 2 — Extract All User Data Through v1

```bash
curl http://localhost:8080/api/v1/users


`SELECT *` on the users table — every column including password hashes, roles, and balances — with no authentication required.

### Burp Suite Approach

1. Use Intruder with Sniper mode on the version segment of the URL
2. Set the payload position: `GET /api/§v1§/users`
3. Use a simple word list: `v0, v1, v2, v3, v4, v5, v10, beta, dev, staging, internal, old, legacy, api, test`
4. Filter results by status code 200 — any 200 response is an unprotected endpoint

## The Vulnerable Code

The endpoint is aware it is deprecated — the `api_version` string says so. But awareness in a comment or a response label is not a security control. The code still executes, still runs `SELECT *`, and still returns everything to any caller with no authentication check.

## The Current (v2) Code

v2 verifies the token signature (`decode_jwt_secure`) and checks `role === 'admin'`. It also uses an explicit column list instead of `SELECT *`.
## The Fix — What Should Happen to v1

The fix is not a code change to `v1` — the fix is removing the `v1` route entirely from `router.php` and replacing the endpoint with a permanent redirect or `410 Gone`.

**In `router.php` — remove this line:**
```php
'GET /api/v1/users' => 'inventory',
```

**Add a dedicated handler that returns the correct HTTP status:**

If any clients might legitimately still be calling v1 (e.g. old mobile app versions), return `301 Moved Permanently` pointing to v2:

```php
'GET /api/v1/users' => 'legacy_redirect',
```

```php
// modules/legacy_redirect.php
function handle_request($method, $uri) {
    http_response_code(301);
    header('Location: /api/v2/users');
    header('Deprecation: true');
    header('Sunset: Sat, 01 Jan 2024 00:00:00 GMT');
    exit;
}
```

If there are no legitimate clients left, return `410 Gone` — which tells clients this resource is permanently removed and they should not try again:

```php
function handle_request($method, $uri) {
    json_response([
        'error'      => 'This API version has been decommissioned.',
        'use_instead' => '/api/v2/users'
    ], 410);
}
```

`410 Gone` is more informative than `404 Not Found` because it tells the client explicitly that the resource existed and was intentionally removed — not that it never existed. This prevents security scanners from flagging the endpoint as a valid target indefinitely.

**Operational process to prevent this in future:**

1. Maintain an API inventory document listing every version, its status (active/deprecated/decommissioned), and its sunset date
2. When a new version is deployed, immediately add a deprecation warning header to the old version: `Deprecation: true` and `Sunset: <date>`
3. After the sunset date, remove the route from the router — do not leave the handler code in place
4. Monitor access logs for requests to removed version paths — continued traffic means a client has not migrated yet
