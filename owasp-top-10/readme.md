# OWASP API Security Top 10 — Vulnerable Lab

A production-quality, Dockerized vulnerable API lab demonstrating
all 10 vulnerabilities from the OWASP API Security Top 10 (2023).

## Architecture
Client -> PHP API (port 8080) -> Node.js Internal -> MySQL
Only PHP port 8080 is publicly accessible.

## Quick Start
```bash
git clone <https://github.com/VEOBTS/Vulnerable-API-DEMO->
cd owasp-top-10
docker compose up --build
```
API is available at: http://localhost:8080

## Vulnerabilities Demonstrated
| ID           | Name                                     | Endpoint          |
|-------------|------------------------------------------|-------------------|
| API1:2023   | Broken Object Level Authorization        | GET /api/bola     |
| API2:2023   | Broken Authentication                    | POST /api/auth/.. |
| API3:2023   | Broken Object Property Level Auth        | PUT /api/bopla/.. |
| API4:2023   | Unrestricted Resource Consumption        | GET /api/search   |
| API5:2023   | Broken Function Level Authorization      | GET /api/admin/.. |
| API6:2023   | Sensitive Business Flow Abuse            | POST /api/order   |
| API7:2023   | Server Side Request Forgery              | GET /api/ssrf     |
| API8:2023   | Security Misconfiguration                | GET /api/debug    |
| API9:2023   | Improper Inventory Management            | GET /api/v1/users |
| API10:2023  | Unsafe Consumption of APIs               | GET /api/weather  |

## Legal Warning
FOR EDUCATIONAL USE ONLY.
Run only in an isolated local/private environment.
Never deploy to a public server.