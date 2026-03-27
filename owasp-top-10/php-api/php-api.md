# php-api

## Request Lifecycle

Every HTTP request to the lab follows this exact path:

```
Browser / curl / Postman
        |
        | HTTP request to localhost:8080
        |
  Apache (inside php-api container)
        |
        | .htaccess: RewriteRule ^ index.php [QSA,L]
        | All paths rewritten to index.php
        |
  public/index.php
        |
        | require_once config.php   (constants loaded)
        | require_once helpers.php  (functions loaded)
        | require_once router.php   (route() function loaded)
        |
        | route($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'])
        |
  src/router.php — route()
        |
        | Extracts clean $uri from REQUEST_URI
        | Builds key: "GET /api/bola"
        | Finds module in $routes array
        | require_once modules/bola.php
        | handle_request($method, $uri)
        |
  src/modules/bola.php — handle_request()
        |
        | Uses $uri to decide which endpoint was called
        | Calls get_db(), get_bearer_token(), json_response()
        | Returns JSON response to client
```

---

## File Responsibilities

| File | Single Responsibility |
|---|---|
| `public/index.php` | HTTP bootstrap — load files, set CORS headers, call router |
| `public/.htaccess` | Apache URL rewriting — send all requests to index.php |
| `src/config.php` | Constants — DB credentials, JWT secret, Node URL |
| `src/helpers.php` | Shared PHP functions — DB connection, JWT, HTTP utilities |
| `src/router.php` | URL-to-module mapping — one lookup table |
| `src/modules/*.php` | Vulnerability implementation — one file per OWASP item |

No file crosses into another's responsibility. A module file never reads `$_SERVER` directly. It never sets HTTP headers. It receives `$method` and `$uri` as arguments and calls `json_response()` when done. Configuration never appears inside a module file — it is always accessed through the constants defined in `config.php`.

---

## public/.htaccess

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

The two `RewriteCond` lines mean: only redirect if the requested path is not an actual file (`!-f`) and not an actual directory (`!-d`). This prevents Apache from trying to route requests for real static files through index.php. The `QSA` flag (Query String Append) preserves query parameters like `?user_id=2` so they are still available via `$_GET` inside PHP.

---

### Intentional Weaknesses

**`JWT_SECRET = 'secret123'`**

A JWT signature is only as strong as its secret. This 9-character secret can be brute-forced offline against any stolen token using tools like `hashcat` or `jwt_tool`. Once an attacker recovers the secret they can sign arbitrary payloads — any user ID, any role — and the server will accept them as legitimate. This sets up the forgery attack demonstrated in `auth.php`.

OWASP connection: **API2:2023 (Broken Authentication)**.

**`ADMIN_KEY = 'supersecretadminkey'`**

Stored in plain text in source code. This value is identical across every clone of the repository and is permanently in the git history. This sets up **API8:2023 (Security Misconfiguration)**.

**Credentials read from environment variables with fallback hardcoding:**

```php
define('DB_PASS', getenv('DB_PASS') ?: 'labpass');
```

The `?: 'labpass'` fallback means that even if the environment variable is unset, the application continues to run with a known default password. In a real deployment, the application should refuse to start if required secrets are missing, rather than falling back to insecure defaults.

---

## src/helpers.php

### `json_response($data, $status)`

Sets the HTTP status code, writes `Content-Type: application/json`, encodes the data, and calls `exit`. Every endpoint in every module calls this function. Using `exit` after sending the response prevents any code after the call from accidentally writing additional output.

### `get_db()`


### `get_bearer_token()`
Reads the `Authorization` header and extracts the token after the word `Bearer`. Returns `null` if the header is absent or malformed. Every secure endpoint calls this before calling either JWT decoder.

### `decode_jwt_insecure($token)` vs `decode_jwt_secure($token)`

| | Insecure | Secure |
|---|---|---|
| Checks signature? | **No** | Yes — recomputes HMAC-SHA256 |
| Checks expiry? | **No** | Checked by the calling endpoint |
| Used by | `auth.php` vulnerable endpoint | All secure endpoints |
| Exploitable? | **Yes — accept forged tokens** | No |

The insecure version skips `$parts[2]` (the signature) entirely. The secure version recomputes the expected signature from the header and payload, then compares with `hash_equals()`. `hash_equals()` is used instead of `===` to prevent timing attacks — an attacker measuring response times could otherwise determine how many characters of their forged signature matched.

## src/modules/

Each file in this directory:

- Defines exactly one function: `handle_request($method, $uri)`
- Contains one or two `if` blocks keyed on `$uri` — one for the vulnerable endpoint, one for the secure version
- Uses only functions defined in `helpers.php` and constants from `config.php`
- Does not import or require any other file (everything it needs is already loaded globally by `index.php`)
- Never reads `$_SERVER` or sets headers directly

See the individual module READMEs for exploit details and code-level fix explanations.

---

## The Vulnerable vs Secure Pattern

Every vulnerability in this lab follows the same structural pattern:

```
GET  /api/<name>         →  vulnerable endpoint (always first in the if-chain)
GET  /api/<name>/secure  →  secure endpoint     (always second)
```

Both endpoints live in the same module file, side by side. This is intentional — you can read the vulnerable version, understand the flaw, then immediately read the secure version and see exactly what line or lines were changed to fix it.

The vulnerable endpoint is always simpler — it has fewer lines because it skips checks. The secure endpoint adds the minimum code necessary to close the specific flaw, without rewriting the entire function. This makes the delta between them as small and clear as possible.
