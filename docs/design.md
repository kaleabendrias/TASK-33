# Design Document

## Objective
Build an offline-first LabOps Resource & Settlement Portal for internal fulfillment, accountable billing, and role-based operations across User, Staff, Group-Leader, and Admin.

## Architecture Summary
- Frontend: Laravel Livewire pages for booking, dashboard, orders, settlements, exports, auth/profile.
- API: Laravel REST-style endpoints consumed by Livewire actions (API-decoupled UI reads/writes).
- Auth: Local username/password with JWT-backed session model and inactivity/session-window policy.
- Database: PostgreSQL for users, resources, pricing, orders, refunds, settlements, commissions, audit/change history.

## Core Domain Modules
- Resource Catalog: service areas, sellable resources, statuses, attachment metadata.
- Booking & Orders: draft to approval and lifecycle transitions with availability/conflict checks.
- Pricing Engine: slot/headcount/member-tier/package/coupon computation plus sales tax.
- Settlement Engine: deterministic refund and commission rules, weekly/biweekly cycles, hold windows.
- Export: local CSV/PDF generation for accounting and reporting.

## Role Model
- User: request and manage own bookings/orders, view history and refund outcomes.
- Staff: profile completion required before approvals/check-in/check-out operations.
- Group-Leader: location-bound attribution and commission visibility over selected date windows.
- Admin: foundational configuration, policy control, settlement finalization, and revocations.

## Non-Functional Priorities
- Offline-only operation (no external identity/email/SMS dependencies).
- Responsive behavior across desktop/tablet/mobile.
- Keyboard-only navigation and readable contrast defaults.
- UI performance target around first meaningful interaction under 2.5s on standard laptop.
- Immutable accountability records for security-sensitive flows.

## Security and Data Handling
- RBAC with API middleware + policy enforcement.
- Password policy and hashing.
- JWT token expiry/refresh/session-limit controls.
- Encrypted optional sensitive fields (for example phone numbers) and role-based masking.
- Audit trail and change history persisted in PostgreSQL.
