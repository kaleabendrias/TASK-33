# Clarifications and Decisions

## 1. Authoritative runtime path for full verification
Question: Is full-stack verification expected to run natively or containerized?

My Understanding: Containerized verification should be the authoritative path for full-system acceptance, while non-Docker frontend checks remain useful for local iteration.

Solution: Defined Docker-based verification as primary and retained native frontend checks as a secondary developer workflow.

## 2. Session and authentication model for offline intranet
Question: How should authentication be enforced for an offline intranet deployment?

My Understanding: Authentication must enforce local session security plus policy controls (password complexity, lockout, inactivity timeout) under an offline-only operating mode.

Solution: Implemented local username/password + JWT session flow with configurable inactivity expiry, 7-day refresh/session window, two-device limit, and admin revocation capability.

## 3. Authorization enforcement location
Question: Should role checks happen only in frontend navigation or also in API services?

My Understanding: UI checks improve UX, but API authorization is the mandatory source of truth.

Solution: Enforced RBAC and permission checks in API middleware/policies, with Livewire consuming API endpoints instead of bypassing service boundaries.

## 4. Staff approval prerequisites
Question: What exactly blocks Staff from approval actions when profile details are incomplete?

My Understanding: Prompt requires employment profile completion (employee ID, department, title) before approval/check-in/check-out actions.

Solution: Added/kept profile-completion gating for those actions and returned authorization errors until profile requirements are satisfied.

## 5. Group-Leader performance date-window behavior
Question: Should group-leader performance metrics be fixed to current month or selectable by date range?

My Understanding: The prompt requires selectable date ranges for attributed orders and performance totals.

Solution: Added date-range inputs (`from`, `to`) on dashboard/report flow and wired them through API and service calculations.

## 6. Refund determinism and repeat safety
Question: How should cancellations and refunds behave when repeated calls or race-like retries happen?

My Understanding: Refund outcomes must remain deterministic and should not double-apply side effects.

Solution: Implemented deterministic policy (15-min full refund, else 20% fee unless staff-unavailable override) and idempotency safeguards for repeated refund/finalization attempts.

## 7. Audit immutability interpretation
Question: Is audit immutability limited to row edits or does it include destructive table-level operations like TRUNCATE?

My Understanding: "Immutable change history" implies protection against UPDATE, DELETE, and TRUNCATE pathways.

Solution: Enforced append-only behavior with mutation-blocking trigger logic and explicit privilege revocation strategy for destructive operations.

## 8. Logging channel usage intent
Question: Are segmented channels (`security`, `business`, `errors`) just configuration placeholders or required operationally?

My Understanding: Segmented channels should be used at critical call sites to preserve observability intent.

Solution: Enabled channelized logging structure and started routing security-relevant events to the security channel; broader business/error adoption remains an implementation follow-up.
