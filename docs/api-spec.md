# API Specification

## Base
- Base URL: `/api`
- Content Type: `application/json`
- Auth Header: `Authorization: Bearer <jwt>`

## Authentication
- `POST /auth/login`
  - Input: `username`, `password`
  - Output: access token + user payload
- `POST /auth/refresh`
  - Input: refresh context from valid session token
  - Output: refreshed access token
- `POST /auth/logout`
  - Effect: revoke/expire current session token

## Dashboard
- `GET /dashboard/stats`
  - Query: `from`, `to` (optional, ISO date)
  - Output: role-scoped KPIs (orders, revenue, commissions, settlement counters)

## Booking and Orders
- `GET /bookings/items`
  - Query: availability and filter fields
  - Output: active/visible bookable items
- `POST /orders`
  - Creates draft order with pricing breakdown
- `GET /orders`
  - Query: status/date filters, role-scoped visibility
- `GET /orders/{id}`
  - Output: order details with policy checks
- `POST /orders/{id}/transition`
  - Input: target status transition (policy + workflow constrained)
- `POST /orders/{id}/cancel`
  - Applies deterministic cancellation/refund rules

## Admin Configuration
- `GET/POST/PUT/DELETE /service-areas`
- `GET/POST/PUT/DELETE /resources`
- `GET/POST/PUT/DELETE /pricing-rules`
- `GET/POST/PUT/DELETE /roles`

## Settlements and Commissions
- `GET /settlements`
- `GET /settlements/{id}`
- `POST /settlements/generate`
- `POST /settlements/{id}/finalize`
- `GET /commissions`
- `GET /commissions/attributed-orders`

## Exports
- `POST /exports/csv`
- `POST /exports/pdf`
  - Input: date range + report type parameters
  - Output: locally generated file stream

## Attachments
- `POST /attachments`
  - Enforces file-type/size constraints and checksum/fingerprinting metadata
- `GET /attachments/{id}`
  - Role and object-level access controlled

## Error Contract
- 400/422 for invalid inputs and validation failures.
- 401 for unauthenticated or expired sessions.
- 403 for authenticated but unauthorized actions.
- 404 for missing resources.
- 409 for state-transition and conflict violations.
