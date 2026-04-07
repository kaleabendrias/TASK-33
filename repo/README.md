# ServicePlatform

Offline-first booking, order, and settlement platform built with Laravel 11, Livewire 3, PHP-FPM, Nginx, and PostgreSQL. Runs entirely via Docker with a single command.

---

## Quick Start

**Prerequisites:** Docker and Docker Compose (v2+) installed. Nothing else.

```bash
git clone <repo-url> && cd repo
docker compose up -d
```

First boot takes 2-3 minutes (pulls images, installs Composer dependencies, runs migrations and seeders). Subsequent starts are near-instant.

### Exposed Ports

| Service        | Host Port | URL / Connection                      |
| -------------- | --------- | ------------------------------------- |
| Web (Nginx)    | **8080**  | http://localhost:8080                 |
| PostgreSQL     | **5433**  | `psql -h localhost -p 5433 -U app_user -d service_platform` |
| pgAdmin        | **5050**  | http://localhost:5050                 |

### Default Credentials

| Account        | Username       | Password          | Role          |
| -------------- | -------------- | ----------------- | ------------- |
| Administrator  | `admin`        | `Admin@12345678`  | admin         |
| Group Leader   | `groupleader`  | `Leader@1234567`  | group-leader  |
| Staff          | `staff`        | `Staff@12345678`  | staff         |
| Viewer         | `viewer`       | `Viewer@1234567`  | user          |
| pgAdmin        | `admin@local.dev` | `admin`        | (DB browser)  |

### Verify It Works

```bash
# Health check
curl -s http://localhost:8080/api/health
# {"status":"ok","timestamp":"..."}

# Login and get a token
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"Admin@12345678"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

# Fetch service areas
curl -s http://localhost:8080/api/service-areas \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# Open the Livewire UI
open http://localhost:8080/login   # or xdg-open on Linux
```

### Shut Down

```bash
docker compose down       # stop containers, keep data
docker compose down -v    # stop containers, delete volumes (clean slate)
```

---

## Service Architecture

```
┌──────────┐     ┌──────────────┐     ┌───────────────┐     ┌──────────┐
│  Browser  │────▶│  Nginx :8080 │────▶│  PHP-FPM :9000│────▶│ Postgres │
│           │     │  (reverse    │     │  (Laravel 11 + │     │ :5432    │
│  Livewire │◀────│   proxy)     │◀────│   Livewire 3)  │◀────│          │
└──────────┘     └──────────────┘     └───────────────┘     └──────────┘
                                             │
                                      ┌──────┴──────┐
                                      │  pgAdmin    │
                                      │  :5050      │
                                      └─────────────┘
```

All services communicate over an internal Docker bridge network (`app-network`). No external API calls are made.

---

## Layered Architecture

```
src/app/
├── Api/                    # Transport layer (HTTP boundary)
│   ├── Controllers/        #   REST endpoints consumed by Livewire
│   ├── Middleware/          #   JWT auth, RBAC role gate, permission gate, profile gate
│   ├── Requests/           #   FormRequest validation classes
│   └── Resources/          #   JSON API resource transformers
│
├── Application/            # Use-case orchestration (business rules)
│   └── Services/           #   AuthService, BookingService, SettlementService, etc.
│
├── Domain/                 # Core domain (framework-agnostic)
│   ├── Models/             #   Eloquent entities with traits
│   ├── Contracts/          #   Repository interfaces (dependency inversion)
│   ├── Policies/           #   PasswordPolicy, PricingPolicy, AttachmentPolicy, ResourcePolicy
│   └── Traits/             #   EncryptsSensitiveFields, TracksChanges, HasStatusLifecycle, etc.
│
├── Infrastructure/         # Concrete implementations
│   ├── Auth/               #   JwtService (HS256 token issue/validate/refresh/revoke)
│   ├── Repositories/       #   Eloquent implementations of domain contracts
│   └── Export/             #   CsvExporter, PdfExporter, ImageCompressor (all pure PHP)
│
└── Livewire/               # UI components (server-rendered with Alpine.js)
    ├── Auth/               #   Login page
    ├── Dashboard/          #   Role-specific KPI dashboard
    ├── Booking/            #   Browse items, create bookings (3-step wizard)
    ├── Orders/             #   List, detail, check-in/out, cancel, refund
    ├── Settlement/         #   Generate/reconcile settlements, commission reports
    ├── Profile/            #   Staff profile completion gate
    └── Export/             #   CSV/PDF export (no third-party dependencies)
```

### Boundary Rules

| From | May call | Must not call |
| ---- | -------- | ------------- |
| Api (Controllers/Middleware) | Application Services | Domain Models directly for writes |
| Application Services | Domain Contracts (interfaces), Domain Policies | Infrastructure classes directly |
| Domain (Models/Policies) | Other Domain classes | Application, Api, or Infrastructure |
| Infrastructure (Repositories) | Domain Models | Application or Api |
| Livewire Components | **REST API endpoints over HTTP** (default) | Infrastructure, Domain models, or Application Services directly |

### API-Decoupled Livewire (and documented exceptions)

The mandated default is that Livewire components interact with the backend
**only via standardised REST API endpoints**, exactly the way an external SPA
client would. Three components are explicitly verified to follow this rule
end-to-end:

| Component | Endpoint(s) it calls |
| --------- | -------------------- |
| `Auth\Login` | `POST /api/auth/login` |
| `Booking\BookingIndex` | `GET /api/bookings/items` |
| `Orders\OrderIndex` | `GET /api/orders` |

The following components contain **documented exceptions** where the read path
goes through an Application Service instead of the API. Each exception exists
because the read is purely server-rendered (no JSON crosses the wire) and
routing it through the loopback HTTP layer would add latency without security
benefit. Writes still go through the API.

| Component | Exception | Why |
| --------- | --------- | --- |
| `Dashboard\DashboardPage` | Reads stats via `DashboardService` | Aggregates 6+ counts; an HTTP round-trip per render would blow the FMI budget. |
| `Orders\OrderShow` | Reads order via `OrderQueryService` | Already authorised by `Gate::allows('view', $order)` in mount/render; mutations go through `/api/orders/*`. |
| `Settlement\SettlementIndex` | Reads via `SettlementService::listSettlementsForUser` | Uses identical scoping rule as the API; mutations go through `/api/admin/settlements/*`. |
| `Settlement\CommissionReport` | Reads via `SettlementService::listCommissionsForUser` | Same justification as above. |
| `Profile\StaffProfilePage` | Reads via `StaffProfileService` | Self-service read; updates go through `PUT /api/profile`. |
| `Booking\BookingCreate` | Reads catalog via `BookingService::listActiveItems` | Read-only catalog snapshot at mount; bookings are created via `POST /api/orders`. |
| `Pricing\PricingRuleManager` | Reads via `PricingRuleService::list` | Admin-only screen; writes go through `POST/PUT/DELETE /api/admin/pricing-rules`. |
| `Export\ExportPage` | (none — already API-only) | Always calls `POST /api/exports`. |

---

## Authentication and Session Policy

| Property | Value |
| -------- | ----- |
| Mechanism | HS256 JWT via `firebase/php-jwt` |
| Access token TTL | 30 minutes (sliding inactivity window) |
| Absolute session lifetime | 7 days from login |
| Concurrent sessions per account | 2 (oldest evicted automatically) |
| Password minimum length | 12 characters |
| Password complexity | Uppercase + lowercase + digit + special character |
| Password hashing | bcrypt (Laravel `Hash::make`) |
| Token refresh | `POST /api/auth/refresh` within the 7-day window |
| Admin token revocation | `POST /api/admin/users/{id}/revoke-tokens` |
| Offline password reset | `POST /api/admin/users/{id}/reset-password` (revokes all sessions) |
| Sensitive field encryption | AES-256-CBC (email, phone) with SHA-256 hash indexes |
| Audit logging | Immutable append-only table (PostgreSQL trigger blocks UPDATE/DELETE) |

---

## Role Permissions Matrix

Four hierarchical roles: **User < Staff < Group-Leader < Admin**

The matrix below is the source of truth for what each role can actually do
in the running code. Every row corresponds to a route, policy method, or
middleware gate that is exercised by the test suite.

| Capability | User | Staff | Group-Leader | Admin |
| ---------- | ---- | ----- | ------------ | ----- |
| **Read** data endpoints (`/service-areas`, `/roles`, `/resources`, `/pricing-baselines`, `/bookings/items`) | ✓ | ✓ | ✓ | ✓ |
| Browse the booking catalog (Livewire `BookingIndex`) | ✓ | ✓ | ✓ | ✓ |
| **Orders** — create own (status starts as `draft`) | ✓ | ✓ | ✓ | ✓ |
| Orders — view own / cancel own / submit own draft for approval (`pending`) | ✓ | ✓ | ✓ | ✓ |
| Orders — view another user's order | | (only when `pending` and profile is complete) | (when attributed as group-leader) | ✓ |
| Orders — **approve** (`pending → confirmed`), any pending order, queue model | | ✓ (profile complete) | | ✓ |
| Orders — operational `checked_in / checked_out / completed` (requires ownership/attribution) | | ✓ (profile complete + own/attributed) | ✓ (profile complete + attributed) | ✓ |
| Orders — refund | | ✓ (profile complete + own/attributed) | ✓ (profile complete + attributed) | ✓ |
| Orders — `markUnavailable` (fulfilment-floor override) | | ✓ (own/attributed) | | ✓ |
| **Settlements** — read summaries (`GET /api/settlements`, web `/settlements`, exports) | | ✓ row-level scoped to your own orders | ✓ row-level scoped to your commissions | ✓ all |
| Settlements — `show` by ID | | ✓ (404 if outside scope) | ✓ (404 if outside scope) | ✓ |
| Settlements — `generate` / `finalize` | | | | ✓ |
| **Commissions** — list (`GET /api/commissions`) | | ✓ scoped to settlements you touched | ✓ scoped to your own commissions | ✓ all |
| **Pricing rules** — admin CRUD (`/api/admin/pricing-rules`, web admin page) | | | | ✓ |
| **Resources** — create/update/transition (requires `permission:resources.*`) | | ✓ | ✓ | ✓ |
| **Service areas** — create/update | | | ✓ | ✓ |
| **Roles** — create/update | | | ✓ | ✓ |
| **Pricing baselines** — create/update | | ✓ | ✓ | ✓ |
| Admin user management, token revocation, password reset, audit logs | | | | ✓ |
| **Attachments** — upload (requires staff role + complete profile + `canAttachTo` policy) | | ✓ | ✓ | ✓ |
| Attachments — download (uploader OR admin OR `canAttachTo`) | varies | ✓ scoped | ✓ scoped | ✓ |

### Profile-completion gate

The `profile.complete` middleware gates **operational write actions**, not read
access. It is applied to:
- All staff-administrative writes (`/api/service-areas`, `/api/resources`, `/api/pricing-baselines`, `/api/attachments`)
- The order approval/check-in/check-out/complete branches via `OrderPolicy`

It is **not** applied to:
- Settlement read routes (`/api/settlements`, `/api/commissions`, `/settlements`)
  — staff with an incomplete profile must still be able to inspect their
  financial summaries.
- Order self-service actions (cancel own, submit own draft for approval).
- Any read endpoint.

### Approval queue model

`OrderPolicy::transition($user, $order, 'confirmed')` follows a **queue
model** rather than an ownership predicate: any staff member with a complete
profile may approve any pending order. This matches the operational reality
of a fulfilment desk where pending tickets land in a shared inbox and any
staff member on shift can clear them. Other operational transitions
(check-in/out/complete) still require ownership or attribution.

---

## Settlement Logic

| Rule | Behavior |
| ---- | -------- |
| Full refund window | Within 15 minutes of order confirmation |
| Cancellation fee (after 15 min) | 20% of order total |
| Staff-unavailable override | Waives the 20% fee entirely |
| Commission rate | 10% of attributed order revenue |
| Commission cycles | Configurable weekly or biweekly |
| Dispute hold | 3 business days after cycle end |
| Reconciliation | Verifies line-item totals match order totals; flags discrepancies |
| CSV export | Pure PHP `fputcsv` streamed response |
| PDF export | Pure PHP minimal PDF 1.4 generator (no third-party libraries) |

---

## Database Schema (22 tables)

| Table | Purpose |
| ----- | ------- |
| `users` | Accounts with encrypted email/phone, SHA-256 hash indexes |
| `user_sessions` | JWT session tracking with JTI, device fingerprint, inactivity |
| `permissions` | Feature-level permission definitions |
| `role_permissions` | Role-to-permission assignments |
| `staff_profiles` | Employee ID, department, title (completion gate) |
| `group_leader_assignments` | Group leader to service area/location mapping |
| `service_areas` | Business service categories |
| `roles` | Resource role levels (Junior through Principal) |
| `resources` | Hierarchical resources with status lifecycle |
| `pricing_baselines` | Rate cards per service area and role |
| `bookable_items` | Labs, rooms, workstations, equipment, consumables |
| `coupons` | Percentage and fixed discount codes |
| `orders` | Full order lifecycle with attribution |
| `order_line_items` | Per-item booking details with tax |
| `refunds` | Refund records with fee calculations |
| `settlements` | Period-based financial reconciliation |
| `commissions` | Group leader commission cycles |
| `attachments` | Polymorphic file metadata with SHA-256 fingerprints |
| `status_transitions` | Lifecycle state change audit trail |
| `change_history` | Append-only field-level diff tracking |
| `audit_logs` | Immutable system-wide audit trail |
| `migrations` | Laravel migration tracking |

Append-only protection on `audit_logs` and `change_history` is enforced by PostgreSQL triggers that raise exceptions on UPDATE or DELETE.

---

## Running Tests

```bash
# Full automated run (boots Docker, creates test DB, runs both suites):
./run_tests.sh

# Or manually inside a running stack:
docker compose exec app php vendor/bin/phpunit -c phpunit.unit.xml    # 210 tests
docker compose exec app php vendor/bin/phpunit -c phpunit.api.xml     # 86 tests
```

### Test Suites

| Suite | Config | Scope | Coverage Target |
| ----- | ------ | ----- | --------------- |
| `unit_tests/` | `phpunit.unit.xml` | Domain models, policies, traits, application services, repositories, exporters | >= 90% |
| `API_tests/` | `phpunit.api.xml` | Controllers, middleware, JWT service, **Livewire components**, HTTP auth flows, RBAC matrix, IDOR, performance benchmarks | >= 90% |

`run_tests.sh` exits nonzero if either suite drops below 90% line coverage.
Coverage tooling (`pcov`) is **pre-baked** into the `development` Docker
target so the script never installs anything at runtime — it just orchestrates
phpunit invocations against the already-built image.

### Coverage Scope

The coverage scope statements below match the actual `phpunit.unit.xml` and
`phpunit.api.xml` `<source><include>` paths exactly:

- **Unit tests** (`phpunit.unit.xml`) measure:
  - `app/Domain`
  - `app/Application`
  - `app/Infrastructure/Export`
  - `app/Infrastructure/Repositories`
- **API tests** (`phpunit.api.xml`) measure:
  - `app/Api`
  - `app/Infrastructure/Auth`
  - `app/Livewire` *(components are exercised end-to-end via the Livewire test harness in `API_tests/Livewire/`)*
- `app/Providers` is excluded from coverage (framework glue with no branching logic).

### Performance Benchmark — First Meaningful Interaction (FMI) KPI

The product KPI for First Meaningful Interaction is **&lt; 2.5 seconds** at the
P50 (median). The repo ships a reproducible benchmark script that runs against
a live stack and emits a JSON artifact suitable for archiving as a CI build
output.

```bash
# Default: 10 samples per route, asserts median &lt; 2500ms
./benchmark_fmi.sh

# More samples for tighter statistics
./benchmark_fmi.sh 30

# Hit a remote / non-default URL
APP_URL=http://staging.internal:8080 ./benchmark_fmi.sh
```

The script:

1. Logs in with the seeded admin credentials over `/api/auth/login`
2. Warms up each measured route (3 hits) so OPcache and DB caches are primed
3. Hits each route N times measuring `time_total` from curl
4. Computes median, p95, and max in milliseconds
5. Writes a JSON artifact under `/tmp/fmi_benchmark/fmi_*.json`
6. Exits non-zero if any route's median exceeds the 2500ms budget — wire this
   into CI as a release gate.

Routes measured (representative server-render KPIs):
`GET /api/health`, `GET /api/service-areas`, `GET /api/bookings/items`,
`GET /api/orders`, `GET /api/auth/me`.

For an in-process equivalent that runs as part of the API test suite, see
`API_tests/Performance/PageLoadBenchmarkTest.php` — those assertions fail the
build immediately when an N+1 regression slips through.

---

## End-to-End Verification Procedure

Run these commands against a freshly started stack to prove all critical user journeys work:

```bash
# 0. Start clean
docker compose down -v && docker compose up -d
# Wait for "Application ready" in logs:
docker compose logs app --tail 5 -f   # Ctrl+C when you see the message

# 1. Health check
curl -sf http://localhost:8080/api/health | grep '"ok"'

# 2. Authenticate as admin
TOKEN=$(curl -sf -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"admin","password":"Admin@12345678"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
echo "Token obtained: ${TOKEN:0:20}..."

# 3. Verify seeded data
curl -sf http://localhost:8080/api/service-areas \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' | \
  python3 -c "import sys,json; d=json.load(sys.stdin); assert len(d['data'])==5, 'Expected 5 service areas'"
echo "PASS: 5 service areas seeded"

# 4. RBAC enforcement — viewer cannot create
VTOK=$(curl -sf -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"viewer","password":"Viewer@1234567"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
STATUS=$(curl -so /dev/null -w '%{http_code}' -X POST http://localhost:8080/api/service-areas \
  -H "Authorization: Bearer $VTOK" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"Blocked"}')
[ "$STATUS" = "403" ] && echo "PASS: viewer blocked (403)" || echo "FAIL: expected 403, got $STATUS"

# 5. Booking + order flow (via artisan tinker for deterministic proof)
docker compose exec -T app php artisan tinker --execute="
use App\Application\Services\BookingService;
use App\Application\Services\SettlementService;
\$bs = app(BookingService::class);
\$ss = app(SettlementService::class);

// Book Engineering Lab A for 5 hours + 2 consumables with WELCOME10 coupon
\$order = \$bs->createOrder(1, [
    ['bookable_item_id' => 1, 'booking_date' => '2026-05-01', 'start_time' => '09:00', 'end_time' => '14:00', 'quantity' => 1],
    ['bookable_item_id' => 10, 'booking_date' => '2026-05-01', 'quantity' => 2],
], 2, 1, 'WELCOME10');
echo 'Order: '.\$order->order_number.' Total: \$'.\$order->total.' Status: '.\$order->status.PHP_EOL;

// Full lifecycle
\$order = \$bs->transitionOrder(\$order, 'checked_in');
\$order = \$bs->transitionOrder(\$order->refresh(), 'checked_out');
\$order = \$bs->transitionOrder(\$order->refresh(), 'completed');
echo 'Completed: '.\$order->status.PHP_EOL;

// Late refund (simulate confirmed 20 min ago)
\$order2 = \$bs->createOrder(1, [
    ['bookable_item_id' => 5, 'booking_date' => '2026-05-02', 'quantity' => 1],
]);
\$order2->update(['confirmed_at' => now()->subMinutes(20)]);
\$refund = \$ss->processRefund(\$order2->refresh(), 'Late cancel');
echo 'Refund: \$'.\$refund->refund_amount.' Fee: \$'.\$refund->cancellation_fee.' Full: '.(\$refund->is_full_refund?'yes':'no').PHP_EOL;

// Staff-unavailable override
\$order3 = \$bs->createOrder(1, [
    ['bookable_item_id' => 5, 'booking_date' => '2026-05-03', 'quantity' => 1],
]);
\$order3->update(['confirmed_at' => now()->subHour(), 'staff_marked_unavailable' => true]);
\$refund2 = \$ss->processRefund(\$order3->refresh(), 'Staff unavailable');
echo 'Override refund fee: \$'.\$refund2->cancellation_fee.' (should be 0)'.PHP_EOL;

// Settlement + commissions
\$stl = \$ss->generateSettlement('2026-01-01', '2026-12-31');
echo 'Settlement: '.\$stl->reference.' Gross: \$'.\$stl->gross_amount.' Net: \$'.\$stl->net_amount.PHP_EOL;
\$comms = \$ss->calculateCommissions('2026-01-01', '2026-12-31');
echo 'Commissions: '.count(\$comms).' records'.PHP_EOL;
"

# 6. Verify immutable audit log
docker compose exec -T postgres psql -U app_user -d service_platform \
  -c "UPDATE audit_logs SET action='tampered' WHERE id=1;" 2>&1 | \
  grep -q 'append-only' && echo "PASS: audit_logs immutable" || echo "FAIL"

# 7. Web UI reachable
STATUS=$(curl -so /dev/null -w '%{http_code}' http://localhost:8080/login)
[ "$STATUS" = "200" ] && echo "PASS: login page renders" || echo "FAIL"

echo ""
echo "All verification checks complete."
```

---

## Project Structure

```
repo/
├── README.md                        # This file
├── docker-compose.yml               # All services, all config (no .env)
├── run_tests.sh                     # Automated test runner
├── .gitignore
├── .dockerignore
├── docker/
│   ├── nginx/default.conf           # Nginx → PHP-FPM reverse proxy
│   └── php/
│       ├── Dockerfile               # PHP 8.3-FPM Alpine + extensions + GD
│       └── entrypoint.sh            # Composer install, migrate, seed, cache, start
├── src/                             # Laravel 11 application root
│   ├── composer.json
│   ├── artisan
│   ├── phpunit.xml                  # Combined test config
│   ├── phpunit.unit.xml             # Unit-scoped coverage config
│   ├── phpunit.api.xml              # API-scoped coverage config
│   ├── bootstrap/app.php            # Route + middleware registration
│   ├── config/                      # app, database, jwt, session, cache, etc.
│   ├── routes/
│   │   ├── api.php                  # REST API endpoints (JWT auth)
│   │   └── web.php                  # Livewire web routes (session auth)
│   ├── app/                         # Application code (see architecture above)
│   ├── database/
│   │   ├── migrations/              # 19 migration files
│   │   └── seeders/                 # 9 seeder files
│   ├── resources/views/             # Blade templates + Livewire views
│   ├── public/index.php             # Web entry point
│   └── storage/                     # Logs, cache, sessions (runtime)
├── unit_tests/                      # 210 unit tests
│   ├── TestCase.php
│   ├── Domain/
│   ├── Application/
│   └── Infrastructure/
└── API_tests/                       # 86 API integration tests
    ├── TestCase.php
    ├── Auth/
    ├── Controllers/
    ├── Middleware/
    ├── Booking/
    └── Admin/
```

---

## Seeded Data

| Entity | Count | Examples |
| ------ | ----- | ------- |
| Service Areas | 5 | Software Engineering, Data Analytics, Cloud Infrastructure, Cybersecurity, UX Design |
| Roles | 5 | Junior (1), Mid-Level (2), Senior (3), Lead (4), Principal (5) |
| Resources | 5 | Alice Chen (Senior/SW Eng), Bob Martinez, Carol Nguyen, David Okafor, Eve Thompson |
| Pricing Baselines | 25 | $75-$375/hr across all area-role combinations |
| Users | 4 | admin, groupleader, staff, viewer |
| Permissions | 9 | CRUD + transition for resources, service areas, roles, pricing |
| Bookable Items | 10 | 2 labs, 2 rooms, 2 workstations, 2 equipment, 2 consumables |
| Coupons | 3 | WELCOME10 (10%), FLAT25OFF ($25), LABWEEK20 (20%) |
| Group Leader Assignments | 2 | groupleader assigned to Software Engineering + Data Analytics |
