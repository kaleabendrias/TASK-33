# Previous Audit Fix Check (Current Code)

## Scope
- Static-only re-check of issues from the previous audit report.
- No Docker/test/runtime execution.

## Overall Status
- Fully fixed: 5
- Partially fixed: 1
- Not fixed: 0

## Issue Status Matrix

| Previous Issue | Current Status | Evidence | Notes |
|---|---|---|---|
| High: Role governance exceeded admin authority | Fixed | src/routes/api.php:101, src/routes/api.php:104, src/routes/api.php:105, src/routes/api.php:109, src/routes/api.php:112, src/database/seeders/PermissionSeeder.php:48, src/database/seeders/PermissionSeeder.php:50 | Foundational writes are admin-only; staff/group-leader permissions emptied. |
| Medium: JWT policy not externally configurable | Fixed | src/config/jwt.php:21, src/config/jwt.php:22, src/config/jwt.php:23, docker-compose.yml:72, docker-compose.yml:73, docker-compose.yml:74 | TTL/session caps now env-driven. |
| Medium: Livewire API-decoupling inconsistent | Fixed | src/app/Livewire/Booking/BookingCreate.php:64, src/app/Livewire/Booking/BookingCreate.php:162, src/app/Livewire/Orders/OrderShow.php:86 | Prior flagged components now read via API path. |
| Medium: Logging channels not segmented | Fixed | src/config/logging.php:4, src/config/logging.php:38, src/config/logging.php:51, src/config/logging.php:63 | `security`/`business`/`errors` channels added with stack default. |
| Low: README counts stale | Fixed | README.md:482, README.md:483 | Counts updated to 24 migrations and 10 seeders. |
| Low: pgAdmin weak defaults exposed | Partially Fixed | docker-compose.yml:121, docker-compose.yml:122, docker-compose.yml:123 | Now host-injectable env vars, but fallback remains `admin`; risk reduced, not eliminated. |

## Re-check of Last Follow-up Risk
- Previous follow-up risk: tests likely mismatched after admin-only write changes.
- Current status: Fixed.
- Evidence:
  - Old permissive expectations are gone (no matches for `test_group_leader_can_create_service_area` / `test_staff_can_create_resource`).
  - New restrictive tests present:
    - API_tests/Auth/RbacTest.php:39
    - API_tests/Auth/RbacTest.php:58
    - API_tests/Controllers/ServiceAreaControllerTest.php:60
    - API_tests/Controllers/RoleControllerTest.php:48
    - API_tests/Controllers/PricingControllerTest.php:57
    - API_tests/Controllers/ResourceControllerTest.php:81

## Final Conclusion
- Yes, the previous report has been largely fixed in current code.
- The only remaining item is the pgAdmin credential issue, which is now partially mitigated (env-injectable) but not fully resolved due to weak default fallback.
