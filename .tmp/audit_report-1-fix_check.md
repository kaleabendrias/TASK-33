# Audit Report 1 - Fix Check

Date: 2026-04-08
Mode: Static re-audit (code and config inspection only; no runtime execution)
Source baseline: .tmp/audit_report-1.md

## 1. Overall Result

Original actionable issues reviewed: 6
- Solved: 6
- Partially solved: 0
- Not solved: 0

Overall fix status: Fully fixed (all originally reported issues are now closed)

## 2. Issue-by-Issue Verification

### Issue 1 (High)
Title: Role governance exceeds prompt authority boundaries
Previous status: Fail
Fix-check status: Solved

What changed:
- Foundational write routes are now explicitly admin-only.
- Permission seeding no longer grants foundational write permissions to group-leader/staff.

Evidence:
- src/routes/api.php:102
- src/routes/api.php:113
- src/routes/api.php:115
- src/routes/api.php:119
- src/routes/api.php:123
- src/routes/api.php:129
- src/database/seeders/PermissionSeeder.php:46
- src/database/seeders/PermissionSeeder.php:52
- src/database/seeders/PermissionSeeder.php:54

Assessment:
- Requirement-fit governance gap appears resolved in routing and seeded permission matrix.

---

### Issue 2 (Medium)
Title: JWT session policy is not externally configurable as required
Previous status: Partial Fail
Fix-check status: Solved

What changed:
- JWT session policy values are env-driven with defaults.
- Compose wiring exposes override variables for operational tuning.

Evidence:
- src/config/jwt.php:21
- src/config/jwt.php:22
- src/config/jwt.php:23
- docker-compose.yml:75
- docker-compose.yml:76
- docker-compose.yml:77

Assessment:
- Operational configurability requirement is now met.

---

### Issue 3 (Medium)
Title: API-decoupled Livewire boundary is inconsistently enforced
Previous status: Partial Fail
Fix-check status: Solved

What changed:
- Targeted Livewire components now use API client reads instead of direct model/service access.
- Static scan did not find remaining direct model/service coupling in Livewire code paths reviewed.

Evidence:
- src/app/Livewire/Booking/BookingCreate.php:5
- src/app/Livewire/Booking/BookingCreate.php:72
- src/app/Livewire/Booking/BookingCreate.php:167
- src/app/Livewire/Orders/OrderShow.php:5
- src/app/Livewire/Orders/OrderShow.php:27
- src/app/Livewire/Orders/OrderShow.php:86

Assessment:
- The cited architecture-discipline gap appears resolved for the originally flagged components.

---

### Issue 4 (Medium)
Title: Logging channels are minimally segmented for operations/security observability
Previous status: Partial Fail
Fix-check status: Solved

What changed:
- Dedicated channels are defined (`security`, `business`, `errors`).
- Application code now logs to segmented channels.
- Correlation ID middleware pushes `correlation_id` into shared log context, with tests covering behavior.

Evidence:
- src/config/logging.php:38
- src/config/logging.php:51
- src/config/logging.php:63
- src/app/Api/Middleware/CorrelationId.php:71
- src/app/Application/Services/SettlementService.php:67
- src/app/Application/Services/BookingService.php:277
- src/app/Api/Controllers/AttachmentController.php:32
- API_tests/Security/CorrelationIdTest.php:96

Assessment:
- Observability segmentation and correlation traceability are now materially improved and aligned with prior recommendation.

---

### Issue 5 (Low)
Title: README repository shape stats are stale/inconsistent
Previous status: Fail
Fix-check status: Solved

What changed:
- README migration count now matches repository state.
- Repository currently contains 25 migration files, and README states 25.

Evidence:
- README.md:508
- src/database/migrations/0001_01_01_000025_add_refunded_at_to_orders.php:1

Assessment:
- Documentation count mismatch is resolved.

---

### Issue 6 (Low)
Title: Default pgAdmin credentials exposed in compose defaults
Previous status: Suspected Risk
Fix-check status: Solved

What changed:
- Compose no longer sets static pgAdmin default email/password env vars.
- Documented flow indicates credentials are generated at startup by custom pgAdmin entrypoint/image process.
- Historical weak defaults are explicitly marked as not reachable.

Evidence:
- docker-compose.yml:115
- docker-compose.yml:121
- docker-compose.yml:122
- docker-compose.yml:126
- docker-compose.yml:140

Assessment:
- The specific risk of hardcoded default pgAdmin credentials in compose defaults appears resolved.

## 3. Final Conclusion

All originally reported issues are now fixed under static verification.

Current closure state:
- 6 of 6 issues solved.
