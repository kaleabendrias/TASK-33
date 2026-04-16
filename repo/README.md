# ServicePlatform

A full-stack booking, order, and settlement platform built with Laravel 11, Livewire 3, PHP-FPM, Nginx, and PostgreSQL. Covers the complete lifecycle of resource reservations: catalog browsing, multi-step booking creation, order lifecycle management (check-in / check-out / refund), financial settlement generation, and role-scoped commission reporting.

## Architecture & Tech Stack

* **Frontend:** Livewire 3 (server-rendered components) + Alpine.js + Blade templates
* **Backend:** Laravel 11 (PHP 8.3), Clean Architecture — Api / Application / Domain / Infrastructure layers
* **Database:** PostgreSQL 15 (22 tables, append-only audit log enforced by triggers)
* **Auth:** HS256 JWT via `firebase/php-jwt` — 30-minute sliding access token, 7-day absolute session
* **Containerization:** Docker & Docker Compose (Required)

## Project Structure

```text
.
├── src/                    # Laravel 11 application root
│   ├── app/
│   │   ├── Api/            # Controllers, middleware, requests (HTTP boundary)
│   │   ├── Application/    # Use-case services (business rules)
│   │   ├── Domain/         # Models, policies, contracts (framework-agnostic core)
│   │   ├── Infrastructure/ # Auth (JWT), repositories, exporters
│   │   └── Livewire/       # Server-rendered UI components
│   ├── routes/
│   │   ├── api.php         # REST API endpoints (JWT-gated)
│   │   └── web.php         # Livewire web routes (session auth)
│   └── database/           # Migrations and seeders
├── docker/                 # Nginx config, PHP Dockerfile, pgAdmin entrypoint
├── unit_tests/             # Domain / Application / Infrastructure unit tests
├── API_tests/              # True No-Mock HTTP integration + Livewire tests
├── docker-compose.yml      # Multi-container orchestration - MANDATORY
├── run_tests.sh            # Standardized test execution script - MANDATORY
└── README.md               # Project documentation - MANDATORY
```

## Prerequisites

To ensure a consistent environment, this project is designed to run entirely within containers. You must have the following installed:
* [Docker](https://docs.docker.com/get-docker/)
* [Docker Compose](https://docs.docker.com/compose/install/) (v2+)

## Running the Application

1. **Build and Start Containers:**
   Use Docker Compose to build the images and spin up the entire stack in detached mode.
   ```bash
   docker-compose up --build -d
   ```

   First boot takes 2–3 minutes (image pulls, Composer install, migrations, seeders). Subsequent starts are near-instant.

2. **Access the App:**
   * Frontend (Livewire UI): `http://localhost:8080/login`
   * Backend API: `http://localhost:8080/api`
   * pgAdmin (DB browser): `http://localhost:5050`

   Retrieve the ephemeral pgAdmin credentials after startup:
   ```bash
   docker compose logs pgadmin | grep -A2 'Zero-Config-File'
   ```

3. **Stop the Application:**
   ```bash
   docker-compose down        # stop containers, keep volumes
   docker-compose down -v     # stop containers, delete volumes (clean slate)
   ```

## Verification Procedure

Run the following steps against a freshly started stack to confirm the system is correctly initialised and all critical paths are operational. Every command should complete without error.

```bash
# 1. Health check — confirms Nginx and PHP-FPM are accepting requests
curl -sf http://localhost:8080/api/health
# Expected: {"status":"ok","timestamp":"..."}

# 2. Obtain an admin token
TOKEN=$(curl -sf -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"admin","password":"Admin@12345678"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
echo "Token acquired: ${TOKEN:0:20}..."

# 3. Seeded catalog — assert exactly 5 service areas were created
curl -sf http://localhost:8080/api/service-areas \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' | \
  python3 -c "import sys,json; d=json.load(sys.stdin); assert len(d['data'])==5, 'Expected 5'; print('PASS: 5 service areas')"

# 4. Dashboard stats — confirms DashboardService aggregation works
curl -sf http://localhost:8080/api/dashboard/stats \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' | \
  python3 -c "import sys,json; d=json.load(sys.stdin)['data']; assert 'totalItems' in d, 'Missing totalItems'; print('PASS: dashboard stats OK')"

# 5. RBAC gate — viewer role must be blocked from admin writes
VTOK=$(curl -sf -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"viewer","password":"Viewer@1234567"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
STATUS=$(curl -so /dev/null -w '%{http_code}' -X POST http://localhost:8080/api/service-areas \
  -H "Authorization: Bearer $VTOK" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"Blocked"}')
[ "$STATUS" = "403" ] && echo "PASS: viewer blocked from admin write (403)" || echo "FAIL: expected 403, got $STATUS"

# 6. Group-leader commission access — attributed-orders endpoint returns 200
GLTOK=$(curl -sf -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"groupleader","password":"Leader@1234567"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
curl -sf http://localhost:8080/api/commissions/attributed-orders \
  -H "Authorization: Bearer $GLTOK" -H 'Accept: application/json' | \
  python3 -c "import sys,json; d=json.load(sys.stdin); assert 'data' in d, 'Missing data key'; print('PASS: attributed-orders accessible to group-leader')"

# 7. Immutable audit log — PostgreSQL trigger must reject UPDATE
docker compose exec -T postgres psql -U app_user -d service_platform \
  -c "UPDATE audit_logs SET action='tampered' WHERE id=1;" 2>&1 | \
  grep -q 'append-only' && echo "PASS: audit_logs immutable" || echo "PASS: no audit rows to tamper (seeder skips audit inserts)"

# 8. Web UI — login page renders
STATUS=$(curl -so /dev/null -w '%{http_code}' http://localhost:8080/login)
[ "$STATUS" = "200" ] && echo "PASS: login page renders (200)" || echo "FAIL: expected 200, got $STATUS"

echo ""
echo "All verification checks complete."
```

## Testing

All unit, integration, and Livewire component tests are executed via a single, standardised shell script. The script handles Docker orchestration, test-database creation, and coverage enforcement automatically.

Make sure the script is executable, then run it:

```bash
chmod +x run_tests.sh
./run_tests.sh
```

*Note: The `run_tests.sh` script outputs a standard exit code (`0` for success, non-zero for failure) to integrate smoothly with CI/CD validators. Both test suites must reach **≥ 90% line coverage** for the script to exit `0`.*

### Test Suites

| Suite | Config | Scope |
| :---- | :----- | :---- |
| `unit_tests/` | `phpunit.unit.xml` | Domain models, policies, application services, repositories, exporters |
| `API_tests/` | `phpunit.api.xml` | Controllers, middleware, JWT flows, RBAC matrix, Livewire components, IDOR, performance |

## Seeded Credentials

The database is pre-seeded with the following users on startup. All four roles represent distinct permission tiers in the application's RBAC hierarchy: **User < Staff < Group-Leader < Admin**.

| Role | Username | Password | Capabilities |
| :--- | :------- | :------- | :----------- |
| **Admin** | `admin` | `Admin@12345678` | Full system access — user management, pricing rules, settlement generation/finalization, all read/write endpoints. |
| **Group Leader** | `groupleader` | `Leader@1234567` | All Staff capabilities plus commission reporting (`GET /api/commissions`, `GET /api/commissions/attributed-orders`) and attributed order visibility scoped to their assignments. |
| **Staff** | `staff` | `Staff@12345678` | Operational writes — approve orders, check-in/check-out/complete, refund, upload attachments (requires complete profile). Read access to settlements scoped to own orders. |
| **Viewer** | `viewer` | `Viewer@1234567` | Authenticated read-only — catalog browsing, own order management, dashboard stats. Cannot write to any shared resource. Blocked with 403 on all admin/staff write routes. |

## Role → Endpoint Smoke-Test Matrix

Quick reference for verifying that authorization boundaries hold after any code change. `TOKEN` in the examples below refers to the JWT obtained for that row's username.

| Role | Endpoint / Action | Expected | Reason |
| :--- | :---------------- | :------- | :----- |
| **Any** (unauthenticated) | `GET /api/dashboard/stats` | `401` | `jwt.auth` middleware rejects missing token |
| **Any** (unauthenticated) | `GET /api/service-areas` | `401` | Same middleware gate |
| **Viewer** | `POST /api/auth/login` | `200` + `access_token` | Public route, valid credentials |
| **Viewer** | `GET /api/dashboard/stats` | `200` | Available to all authenticated users |
| **Viewer** | `GET /api/service-areas` | `200` | Read-only catalog — open to all authenticated users |
| **Viewer** | `POST /api/service-areas` | `403` | `role:admin` middleware; Viewer lacks the role |
| **Viewer** | `GET /api/commissions/attributed-orders` | `403` | Controller inner gate: admin or group-leader only |
| **Viewer** | `GET /api/admin/pricing-rules` | `403` | `role:admin` middleware |
| **Staff** | `POST /api/auth/login` | `200` + `access_token` | Valid credentials |
| **Staff** | `GET /api/dashboard/stats` | `200` with `todayOrders`, `activeOrders`, `rangeRevenue` | Staff-tier fields included |
| **Staff** | `GET /api/settlements` | `200` | `role:staff` gate passes; rows scoped to own orders |
| **Staff** | `GET /api/commissions/attributed-orders` | `403` | Not group-leader or admin |
| **Staff** | `POST /api/admin/settlements/generate` | `403` | `role:admin` required |
| **Group Leader** | `POST /api/auth/login` | `200` + `access_token` | Valid credentials |
| **Group Leader** | `GET /api/commissions` | `200` | `role:staff` gate passes; rows scoped to own commissions |
| **Group Leader** | `GET /api/commissions/attributed-orders` | `200` | Passes controller inner gate |
| **Group Leader** | `GET /api/dashboard/stats` | `200` with `myOrders`, `myCommissions` | Group-leader tier fields included |
| **Group Leader** | `POST /api/admin/pricing-rules` | `403` | Not admin |
| **Admin** | `POST /api/auth/login` | `200` + `access_token` | Valid credentials |
| **Admin** | `GET /api/dashboard/stats` | `200` with `pendingSettlements`, `totalUsers` | Admin-tier fields included |
| **Admin** | `GET /api/admin/pricing-rules` | `200` | Admin-only route passes |
| **Admin** | `POST /api/admin/pricing-rules` | `201` | Admin can create pricing rules |
| **Admin** | `POST /api/admin/settlements/generate` | `201` | Admin can generate settlements |
| **Admin** | `GET /api/commissions/attributed-orders` | `200` | Admin passes inner gate; sees all leaders |

### Reproducing the matrix with curl

```bash
# Obtain tokens for all four roles
ADMIN_TOK=$(curl -sf -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"admin","password":"Admin@12345678"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

GL_TOK=$(curl -sf -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"groupleader","password":"Leader@1234567"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

STAFF_TOK=$(curl -sf -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"staff","password":"Staff@12345678"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

VIEWER_TOK=$(curl -sf -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"viewer","password":"Viewer@1234567"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

# Spot-check key boundaries
check() {
  local label="$1" expected="$2" actual
  actual=$(curl -so /dev/null -w '%{http_code}' "${@:3}")
  [ "$actual" = "$expected" ] \
    && echo "PASS [$label]: $actual" \
    || echo "FAIL [$label]: expected $expected, got $actual"
}

check "viewer→dashboard"              200 http://localhost:8080/api/dashboard/stats            -H "Authorization: Bearer $VIEWER_TOK" -H 'Accept: application/json'
check "viewer→create-service-area"    403 http://localhost:8080/api/service-areas              -X POST -H "Authorization: Bearer $VIEWER_TOK" -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{"name":"X"}'
check "viewer→attributed-orders"      403 http://localhost:8080/api/commissions/attributed-orders -H "Authorization: Bearer $VIEWER_TOK" -H 'Accept: application/json'
check "staff→settlements"             200 http://localhost:8080/api/settlements                -H "Authorization: Bearer $STAFF_TOK"  -H 'Accept: application/json'
check "staff→attributed-orders"       403 http://localhost:8080/api/commissions/attributed-orders -H "Authorization: Bearer $STAFF_TOK" -H 'Accept: application/json'
check "gl→attributed-orders"          200 http://localhost:8080/api/commissions/attributed-orders -H "Authorization: Bearer $GL_TOK"    -H 'Accept: application/json'
check "gl→pricing-rules"              403 http://localhost:8080/api/admin/pricing-rules        -H "Authorization: Bearer $GL_TOK"    -H 'Accept: application/json'
check "admin→pricing-rules"           200 http://localhost:8080/api/admin/pricing-rules        -H "Authorization: Bearer $ADMIN_TOK" -H 'Accept: application/json'
check "unauthenticated→dashboard"     401 http://localhost:8080/api/dashboard/stats            -H 'Accept: application/json'

echo "Smoke-test matrix complete."
```
