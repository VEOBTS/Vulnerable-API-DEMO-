# API8:2023 — Security Misconfiguration

**Module file:** `php-api/src/modules/misconfig.php`  
**Vulnerable endpoints:** `GET /api/debug`, `GET /api/config`  
**No secure counterpart** — the fix is to delete these endpoints entirely

## What This Vulnerability Is

Security misconfiguration covers a wide range of deployment mistakes:

- Debug endpoints left active in production
- Error messages that leak internal details (stack traces, file paths, credentials)
- Missing HTTP security headers
- Overly permissive CORS policies (see `index.php`)
- Default or weak credentials (see `config.php` and `database/init.sql`)

This module demonstrates the most damaging form: a debug endpoint that exposes the complete server configuration including database credentials, and an error handler that includes credentials in its exception message.

## The Exploit — Step by Step

### Attack 1 — Full Configuration Dump

No authentication required.

```bash
curl http://localhost:8080/api/debug
```

In a single unauthenticated request the attacker now has:

- MySQL host, username, and password
- Internal Node.js service URL (confirming SSRF target)
- Full file system paths for every loaded PHP file
- Internal Docker IP addresses
- PHP version (useful for known-CVE targeting)
- Apache version (useful for known-CVE targeting)

### Attack 2 — Verbose Error with Credential Leak

```bash
curl http://localhost:8080/api/config

The exception message was constructed to include the database credentials. The stack trace reveals internal file paths. This demonstrates what happens when developers build error messages for debugging and forget to remove them before deployment.

### Attack 3 — Check for Missing Security Headers

```bash
curl -sI http://localhost:8080/api/debug
```

Output shows the response headers. Note what is absent:

```
# Present:
Content-Type: application/json
Access-Control-Allow-Origin: *

# Missing — should be present in production:
# X-Content-Type-Options: nosniff
# X-Frame-Options: DENY
# Content-Security-Policy: default-src 'none'
# Strict-Transport-Security: max-age=31536000
# X-XSS-Protection: 1; mode=block
```

`X-Content-Type-Options: nosniff` prevents browsers from MIME-sniffing responses and executing content as a different type than declared. Its absence here supports **API8:2023**.

## The Vulnerable Code

### /api/debug

Every piece of sensitive runtime information is packaged into a JSON response and returned without any authentication check. `DB_PASS` is the constant from `config.php` — this single endpoint exposes what config.php tried to protect behind constants.

### /api/config

The credentials are embedded directly in the exception message string (`' as ' . DB_USER . ' with pass ' . DB_PASS`). The catch block then sends the entire exception — message, file path, line number, and stack trace — to the HTTP response.

## The Fix — What Should Replace This

The fix for this module is not a code change — it is deletion and replacement with safe patterns.

**For `/api/debug`:** Delete the endpoint entirely. It should not exist in any deployed environment, not even behind an admin check (a compromised admin account would then expose all credentials). If runtime diagnostics are genuinely needed, implement a separate out-of-band system (cloud metrics, logging agents) that never exposes credentials over HTTP.

**For error handling — replace the catch block with:**

```php
} catch (Exception $e) {
    // Log the real error server-side only
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    // Return a generic message to the client
    json_response(['error' => 'Internal server error'], 500);
}
```

`error_log()` writes to the Apache error log (visible via `docker compose logs php-api`) where only operators can see it. The client receives only `"Internal server error"` — no credentials, no paths, no version numbers.

**For security headers — add to `index.php` before `route()` is called:**

```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Security-Policy: default-src \'none\'');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
```

**For CORS — replace the wildcard in `index.php`:**

```php
// Instead of:
header('Access-Control-Allow-Origin: *');

// Use a specific trusted origin:
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['https://your-frontend.com'];
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
```
