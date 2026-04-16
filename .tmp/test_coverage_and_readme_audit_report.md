# Unified Audit Report: Test Coverage + README Compliance (Strict Static Inspection)

Date: 2026-04-16  
Scope: static inspection only (no test execution, no containers, no builds)

---

## 1) Test Coverage Audit

### Project Type Detection

- README top description says "full-stack" (non-canonical token; canonical required token would be "fullstack").
- Light code inspection confirms both backend and frontend layers:
	- API routes in repo/src/routes/api.php
	- Web/Livewire routes in repo/src/routes/web.php
	- Frontend component tests in repo/frontend_tests
- Inferred project type: fullstack

### Backend Endpoint Inventory

Resolved as METHOD + fully resolved PATH under Laravel API prefix /api from repo/src/routes/api.php.

1. GET /api/health
2. POST /api/auth/login
3. POST /api/auth/refresh
4. POST /api/auth/logout
5. GET /api/auth/me
6. GET /api/profile
7. PUT /api/profile
8. GET /api/dashboard/stats
9. GET /api/service-areas
10. GET /api/service-areas/{service_area}
11. GET /api/roles
12. GET /api/roles/{role}
13. GET /api/resources
14. GET /api/resources/{resource}
15. GET /api/pricing-baselines
16. GET /api/pricing-baselines/{pricing_baseline}
17. GET /api/bookings/items
18. POST /api/bookings/check-availability
19. POST /api/bookings/calculate-totals
20. POST /api/bookings/validate-coupon
21. GET /api/orders
22. GET /api/orders/{id}
23. POST /api/orders
24. POST /api/orders/{id}/transition
25. POST /api/orders/{id}/refund
26. POST /api/orders/{id}/mark-unavailable
27. GET /api/attachments/{id}/download
28. POST /api/exports
29. POST /api/attachments
30. GET /api/settlements
31. GET /api/settlements/{id}
32. GET /api/commissions
33. GET /api/commissions/attributed-orders
34. POST /api/service-areas
35. PUT /api/service-areas/{service_area}
36. POST /api/roles
37. PUT /api/roles/{role}
38. POST /api/resources
39. PUT /api/resources/{resource}
40. POST /api/resources/{resource}/transition
41. POST /api/pricing-baselines
42. PUT /api/pricing-baselines/{pricing_baseline}
43. GET /api/admin/users
44. GET /api/admin/users/{id}
45. POST /api/admin/users
46. POST /api/admin/users/{id}/revoke-tokens
47. POST /api/admin/users/{id}/reset-password
48. GET /api/admin/audit-logs
49. POST /api/admin/settlements/generate
50. POST /api/admin/settlements/{id}/finalize
51. GET /api/admin/pricing-rules
52. GET /api/admin/pricing-rules/{id}
53. POST /api/admin/pricing-rules
54. PUT /api/admin/pricing-rules/{id}
55. DELETE /api/admin/pricing-rules/{id}

Total backend API endpoints: 55

### API Test Mapping Table

Coverage criterion applied strictly: test must issue HTTP request to exact METHOD + PATH.

| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| GET /api/health | yes | true no-mock HTTP | API_tests/Booking/BookingApiTest.php; API_tests/Performance/PageLoadBenchmarkTest.php | test_health_endpoint_public; test_health_endpoint_under_budget |
| POST /api/auth/login | yes | true no-mock HTTP | API_tests/Auth/AuthenticationTest.php | test_login_success |
| POST /api/auth/refresh | yes | true no-mock HTTP | API_tests/Auth/AuthenticationTest.php; API_tests/Auth/JwtRefreshTest.php | test_refresh_token; test_refresh_via_api_endpoint_with_expired_token |
| POST /api/auth/logout | yes | true no-mock HTTP | API_tests/Auth/AuthenticationTest.php | test_logout |
| GET /api/auth/me | yes | true no-mock HTTP | API_tests/Auth/AuthenticationTest.php; API_tests/Middleware/MiddlewareTest.php | test_me_endpoint; test_invalid_bearer_format |
| GET /api/profile | yes | true no-mock HTTP | API_tests/Security/SecurityHardeningTest.php; API_tests/Integration/FullStackFlowTest.php | test_profile_show_and_update; test_profile_saved_via_livewire_readable_via_api |
| PUT /api/profile | yes | true no-mock HTTP | API_tests/Security/SecurityHardeningTest.php; API_tests/Integration/FullStackFlowTest.php | test_profile_show_and_update; test_profile_upserted_via_api_reflected_in_livewire_mount |
| GET /api/dashboard/stats | yes | true no-mock HTTP | API_tests/Dashboard/DashboardStatsApiTest.php | test_requires_authentication |
| GET /api/service-areas | yes | true no-mock HTTP | API_tests/Controllers/ServiceAreaControllerTest.php; API_tests/Auth/RbacTest.php | test_index; test_user_can_read_service_areas |
| GET /api/service-areas/{service_area} | yes | true no-mock HTTP | API_tests/Controllers/ServiceAreaControllerTest.php | test_show |
| GET /api/roles | yes | true no-mock HTTP | API_tests/Controllers/RoleControllerTest.php | test_index |
| GET /api/roles/{role} | yes | true no-mock HTTP | API_tests/Controllers/RoleControllerTest.php | test_show |
| GET /api/resources | yes | true no-mock HTTP | API_tests/Controllers/ResourceControllerTest.php; API_tests/Booking/BookingApiTest.php | test_index; test_list_resources_authenticated |
| GET /api/resources/{resource} | yes | true no-mock HTTP | API_tests/Controllers/ResourceControllerTest.php | test_show |
| GET /api/pricing-baselines | yes | true no-mock HTTP | API_tests/Controllers/PricingControllerTest.php | test_index |
| GET /api/pricing-baselines/{pricing_baseline} | yes | true no-mock HTTP | API_tests/Controllers/PricingControllerTest.php | test_show |
| GET /api/bookings/items | yes | true no-mock HTTP | API_tests/Booking/BookingEndpointTest.php | test_list_bookable_items |
| POST /api/bookings/check-availability | yes | true no-mock HTTP | API_tests/Booking/BookingEndpointTest.php | test_check_availability |
| POST /api/bookings/calculate-totals | yes | true no-mock HTTP | API_tests/Booking/BookingEndpointTest.php | test_calculate_totals |
| POST /api/bookings/validate-coupon | yes | true no-mock HTTP | API_tests/Booking/BookingEndpointTest.php | test_validate_coupon |
| GET /api/orders | yes | true no-mock HTTP | API_tests/Orders/OrderApiTest.php; API_tests/Performance/PageLoadBenchmarkTest.php | test_staff_with_profile_sees_all_pending_orders_in_index; test_order_list_under_budget |
| GET /api/orders/{id} | yes | true no-mock HTTP | API_tests/Orders/OrderApiTest.php; API_tests/Integration/FullStackFlowTest.php | test_owner_can_view_order; test_order_cancelled_via_livewire_reflected_in_api |
| POST /api/orders | yes | true no-mock HTTP | API_tests/Orders/OrderApiTest.php | test_create_order_via_api |
| POST /api/orders/{id}/transition | yes | true no-mock HTTP | API_tests/Orders/OrderApiTest.php | test_owner_can_transition_own_order |
| POST /api/orders/{id}/refund | yes | true no-mock HTTP | API_tests/Orders/OrderApiTest.php | test_owner_can_refund_own_order |
| POST /api/orders/{id}/mark-unavailable | yes | true no-mock HTTP | API_tests/Orders/OrderApiTest.php | test_mark_unavailable_requires_authorization |
| GET /api/attachments/{id}/download | yes | true no-mock HTTP | API_tests/Attachments/AttachmentApiTest.php; API_tests/Security/IdorAndIsolationTest.php | test_download_admin_can_get_any_attachment; test_attachment_idor_non_owner_forbidden |
| POST /api/exports | yes | true no-mock HTTP | API_tests/Exports/ExportApiTest.php | test_export_orders_csv |
| POST /api/attachments | yes | true no-mock HTTP | API_tests/Attachments/AttachmentApiTest.php | test_upload_valid_file |
| GET /api/settlements | yes | true no-mock HTTP | API_tests/Settlement/SettlementApiTest.php; API_tests/Integration/SettlementAccessIntegrationTest.php | test_settlement_index_admin_sees_all; test_staff_with_profile_can_list_settlements_via_real_jwt |
| GET /api/settlements/{id} | yes | true no-mock HTTP | API_tests/Integration/SettlementAccessIntegrationTest.php | test_staff_show_returns_data_for_own_settlement |
| GET /api/commissions | yes | true no-mock HTTP | API_tests/Settlement/SettlementApiTest.php; API_tests/Security/IdorAndIsolationTest.php | test_commissions_endpoint_filters; test_group_leader_sees_only_own_commissions |
| GET /api/commissions/attributed-orders | yes | true no-mock HTTP | API_tests/Commissions/AttributedOrdersApiTest.php | test_group_leader_receives_200 |
| POST /api/service-areas | yes | true no-mock HTTP | API_tests/Controllers/ServiceAreaControllerTest.php; API_tests/Auth/RbacTest.php | test_admin_can_store; test_admin_can_access_everything |
| PUT /api/service-areas/{service_area} | yes | true no-mock HTTP | API_tests/Controllers/ServiceAreaControllerTest.php | test_admin_can_update |
| POST /api/roles | yes | true no-mock HTTP | API_tests/Controllers/RoleControllerTest.php | test_admin_can_store |
| PUT /api/roles/{role} | yes | true no-mock HTTP | API_tests/Controllers/RoleControllerTest.php | test_admin_can_update |
| POST /api/resources | yes | true no-mock HTTP | API_tests/Controllers/ResourceControllerTest.php; API_tests/Auth/RbacTest.php | test_admin_can_store; test_admin_can_create_resource |
| PUT /api/resources/{resource} | yes | true no-mock HTTP | API_tests/Controllers/ResourceControllerTest.php | test_admin_can_update |
| POST /api/resources/{resource}/transition | yes | true no-mock HTTP | API_tests/Controllers/ResourceControllerTest.php | test_admin_transition_valid |
| POST /api/pricing-baselines | yes | true no-mock HTTP | API_tests/Controllers/PricingControllerTest.php | test_admin_can_store |
| PUT /api/pricing-baselines/{pricing_baseline} | yes | true no-mock HTTP | API_tests/Controllers/PricingControllerTest.php | test_admin_can_update |
| GET /api/admin/users | yes | true no-mock HTTP | API_tests/Admin/AdminApiTest.php; API_tests/Auth/RbacTest.php | test_list_users; test_admin_can_access_everything |
| GET /api/admin/users/{id} | yes | true no-mock HTTP | API_tests/Admin/AdminApiTest.php | test_show_user |
| POST /api/admin/users | yes | true no-mock HTTP | API_tests/Admin/AdminApiTest.php | test_create_user |
| POST /api/admin/users/{id}/revoke-tokens | yes | true no-mock HTTP | API_tests/Auth/RbacTest.php | test_admin_can_revoke_user_tokens |
| POST /api/admin/users/{id}/reset-password | yes | true no-mock HTTP | API_tests/Auth/RbacTest.php | test_admin_can_reset_password |
| GET /api/admin/audit-logs | yes | true no-mock HTTP | API_tests/Admin/AdminApiTest.php; API_tests/Auth/RbacTest.php | test_audit_logs; test_admin_can_access_everything |
| POST /api/admin/settlements/generate | yes | true no-mock HTTP | API_tests/Settlement/SettlementApiTest.php; API_tests/Integration/CycleTypeIntegrationTest.php | test_admin_can_generate_settlement; test_admin_generates_weekly_settlement |
| POST /api/admin/settlements/{id}/finalize | yes | true no-mock HTTP | API_tests/Settlement/SettlementApiTest.php | test_admin_can_finalize |
| GET /api/admin/pricing-rules | yes | true no-mock HTTP | API_tests/Pricing/PricingRuleApiTest.php | test_admin_can_list_filter_show |
| GET /api/admin/pricing-rules/{id} | yes | true no-mock HTTP | API_tests/Pricing/PricingRuleApiTest.php | test_admin_can_list_filter_show |
| POST /api/admin/pricing-rules | yes | true no-mock HTTP | API_tests/Pricing/PricingRuleApiTest.php | test_admin_can_create_pricing_rule |
| PUT /api/admin/pricing-rules/{id} | yes | true no-mock HTTP | API_tests/Pricing/PricingRuleApiTest.php | test_admin_can_update_and_delete_rule |
| DELETE /api/admin/pricing-rules/{id} | yes | true no-mock HTTP | API_tests/Pricing/PricingRuleApiTest.php | test_admin_can_update_and_delete_rule |

### API Test Classification

Classification basis:
- True no-mock HTTP: request issued via Laravel HTTP test layer (getJson/postJson/putJson/deleteJson/get/post) and no test-level mocks/stubs/DI overrides found.
- HTTP with mocking: none found.
- Non-HTTP: direct service/middleware/component invocation without route call.

1. True no-mock HTTP
- API_tests/Admin/AdminApiTest.php
- API_tests/Attachments/AttachmentApiTest.php
- API_tests/Auth/AuthenticationTest.php
- API_tests/Auth/RbacTest.php
- API_tests/Booking/BookingApiTest.php
- API_tests/Booking/BookingEndpointTest.php
- API_tests/Commissions/AttributedOrdersApiTest.php
- API_tests/Controllers/PricingControllerTest.php
- API_tests/Controllers/ResourceControllerTest.php
- API_tests/Controllers/RoleControllerTest.php
- API_tests/Controllers/ServiceAreaControllerTest.php
- API_tests/Dashboard/DashboardStatsApiTest.php
- API_tests/Exports/ExportApiTest.php
- API_tests/Integration/CycleTypeIntegrationTest.php
- API_tests/Integration/DirectApiAuthFlowTest.php
- API_tests/Integration/SettlementAccessIntegrationTest.php
- API_tests/Middleware/MiddlewareTest.php
- API_tests/Middleware/WebSessionTest.php
- API_tests/Orders/OrderApiTest.php
- API_tests/Performance/PageLoadBenchmarkTest.php
- API_tests/Pricing/PricingRuleApiTest.php
- API_tests/Security/IdorAndIsolationTest.php
- API_tests/Security/SecurityHardeningTest.php
- API_tests/Settlement/SettlementApiTest.php

2. HTTP with mocking
- None detected by static scan.

3. Non-HTTP (unit/integration without HTTP) or mixed
- API_tests/Auth/JwtRefreshTest.php (contains direct JwtService calls; also one HTTP refresh test)
- API_tests/Integration/FullStackFlowTest.php (mixed HTTP and Livewire::test component invocation)
- API_tests/Livewire/LivewireAuthorizationTest.php (component-level)
- API_tests/Livewire/LivewireComponentTest.php (mixed HTTP and Livewire::test)
- API_tests/Livewire/PricingRuleManagerTest.php (component-level)
- API_tests/Security/CorrelationIdTest.php (mixed HTTP and direct middleware handle)

### Mock Detection Rules Outcome

Patterns searched: jest.mock, vi.mock, sinon.stub, mock(), partialMock, spy(), shouldReceive, expects, instance/swap/bind overrides, withoutMiddleware.

Findings:
- No mock/stub calls detected in test files under API_tests, frontend_tests, unit_tests.
- No explicit middleware bypass calls detected in those test files.
- One app-level container state toggle exists in repo/src/app/Livewire/Concerns/UsesApiClient.php (app()->instance('middleware.disable', ...)); this is implementation behavior, not a test mock declaration.

Conclusion:
- No evidence of HTTP transport/controller/service mocking in API tests.

### Coverage Summary

- Total endpoints: 55
- Endpoints with HTTP tests: 55
- Endpoints with true no-mock HTTP tests: 55

Computed metrics:
- HTTP coverage = 55 / 55 = 100.00%
- True API coverage = 55 / 55 = 100.00%

### Unit Test Summary

Backend unit tests

- Test files: 37 files under repo/unit_tests
- Modules covered (evidence from path taxonomy):
	- Services: unit_tests/Application/Services/*.php (AuthServiceTest, BookingServiceTest, DashboardServiceTest, SettlementServiceTest, PricingResolverTest, PricingRuleServiceTest, AttachmentServiceTest, UserServiceTest)
	- Repositories: unit_tests/Infrastructure/Repositories/*.php
	- Middleware/guards: unit_tests/Infrastructure/Middleware/JwtAuthenticateTest.php, RequireRoleTest.php
	- Domain policies/models/traits: unit_tests/Domain/Policies/*.php, unit_tests/Domain/Models/*.php, unit_tests/Domain/Traits/*.php
- Important backend modules not directly unit-tested (controller-level unit scope):
	- Api controllers under src/app/Api/Controllers (e.g., AdminController, OrderApiController, SettlementApiController, ExportApiController, AttachmentController)
	- profile.complete middleware unit coverage not evident (only integration/API behavior evidence)

Frontend unit tests (strict requirement)

- Frontend test files detected: yes, 11 files under repo/frontend_tests
- Framework/tool evidence:
	- PHPUnit style class tests (*.php in frontend_tests)
	- Livewire testing API via Livewire::test imports and calls
	- Component imports from App/Livewire modules
- Evidence examples:
	- frontend_tests/Auth/LoginTest.php imports App\Livewire\Auth\Login and calls Livewire::test(Login::class)
	- frontend_tests/Booking/BookingCreateTest.php imports App\Livewire\Booking\BookingCreate and asserts component state transitions
- Components/modules covered:
	- Login, DashboardPage, BookingIndex, BookingCreate, OrderIndex, OrderShow, SettlementIndex, CommissionReport, StaffProfilePage, ExportPage, PricingRuleManager
- Important frontend modules not directly unit-tested:
	- src/app/Livewire/Concerns/UsesApiClient.php has no direct standalone test file (covered indirectly through component tests)

Mandatory verdict:
- Frontend unit tests: PRESENT

Strict failure rule (fullstack/web + missing frontend tests):
- Not triggered (frontend tests are present with direct component evidence).

Cross-layer observation:
- Coverage is relatively balanced: strong backend API coverage plus dedicated frontend Livewire component tests.
- True browser E2E coverage is limited; current frontend tests are component-level rather than full browser workflow automation.

### Tests Check

API observability check

- Strong in many tests: explicit method/path, payload, status, and JSON/content assertions.
	- Example: API_tests/Booking/BookingEndpointTest.php::test_check_availability includes request body and asserts available=true.
	- Example: API_tests/Auth/AuthenticationTest.php::test_login_success validates response token structure.
- Weak spots (endpoint request visible, response content shallow):
	- Several tests assert only status code without strong body contracts (e.g., some role-forbidden checks in API_tests/Auth/RbacTest.php and API_tests/Commissions/AttributedOrdersApiTest.php).

Test quality and sufficiency

- Success paths: well covered across auth, booking, orders, settlements, pricing rules, exports.
- Failure and validation cases: present (422/403/401 scenarios across controllers and auth flows).
- Auth/permissions: strong RBAC and IDOR-focused tests exist (Auth/RbacTest, Security/IdorAndIsolationTest, SettlementAccessIntegrationTest).
- Edge cases: present for token expiry/session windows, oversell, idempotent refund, malformed correlation ID.
- Assertion depth: generally meaningful; some tests remain shallow status-only checks.

run_tests.sh check

- Docker-based execution: yes (docker compose orchestration inside run_tests.sh).
- Local dependency concern: low; script relies on Docker/Compose on host, no npm/pip/apt/manual DB setup instructions inside script.

### Test Coverage Score (0-100)

Score: 93/100

### Score Rationale

- +40: endpoint coverage (100%)
- +25: true no-mock HTTP breadth (all endpoints have direct HTTP evidence)
- +15: depth across auth/RBAC/security/failure paths
- +8: backend + frontend unit/component balance
- -5: part of suite is mixed non-HTTP/component-level in API_tests directory (classification noise)
- -2: observability is uneven in status-only tests
- -3: no clear full browser E2E workflow evidence beyond component/API integration

### Key Gaps

1. Classification hygiene gap: API_tests folder contains mixed non-HTTP/component tests (Livewire + direct service/middleware), reducing audit clarity.
2. Observability gap: several tests assert status only without strict response schema/value assertions.
3. Controller unit-test gap: controller classes rely primarily on API tests; direct unit tests are sparse.

### Confidence & Assumptions

- Confidence: high for route inventory and method/path mapping; medium-high for qualitative sufficiency scoring.
- Assumptions:
	- Laravel /api prefix is active for routes in src/routes/api.php.
	- Static evidence only; no runtime behavior or hidden route registration outside inspected files was assumed.

Test Coverage Audit Verdict: PASS (with medium-priority quality gaps)

---

## 2) README Audit

Audited file: repo/README.md

### Hard Gate Evaluation

1. README exists at required path
- PASS

2. Formatting/readability
- PASS (structured headings, tables, command blocks, clear sections)

3. Startup instructions (backend/fullstack requires docker-compose up)
- PASS
- Evidence: Running section includes docker-compose up --build -d

4. Access method
- PASS
- Evidence: explicit URLs and ports for frontend, API, pgAdmin

5. Verification method
- PASS
- Evidence: explicit curl-based checks with expected outcomes

6. Environment rules (no runtime installs/manual DB setup; Docker-contained)
- PARTIAL FAIL (strict)
- Evidence: verification examples depend on host-side python3 command usage multiple times.
- Strict interpretation risk: this introduces non-Docker runtime dependency for verification flow.

7. Demo credentials (if auth exists: all roles + credentials)
- PASS
- Evidence: Seeded Credentials table includes Admin, Group Leader, Staff, Viewer with usernames and passwords.

### High Priority Issues

1. Canonical project-type declaration token missing at top.
- Required tokens: backend/fullstack/web/android/ios/desktop
- README says full-stack (hyphenated natural language) but not explicit canonical token fullstack.

### Medium Priority Issues

1. Verification flow depends on host python3 commands for JSON parsing.
- This weakens strict Docker-contained reproducibility.

### Low Priority Issues

1. README is long and dense; operational quick-start and deep architecture sections could be split for maintainability.

### Hard Gate Failures

1. Strict environment rule risk: host python3 dependency in verification procedure (interpretable as non-containerized runtime dependency).

### README Verdict

PARTIAL PASS

Rationale:
- Core mandatory items (startup, access, verification, credentials, structure) are strong.
- One strict compliance risk remains around fully Docker-contained verification assumptions.

---

Final Combined Verdicts

- Test Coverage Audit: PASS (score 93/100; quality gaps remain)
- README Audit: PARTIAL PASS (strict-environment compliance risk)

