# Rosta security-first delivery roadmap

Roadmap lock: `2026-07-21-rosta-security-first`

## Current completed baseline

The customer storefront implementation through Phase 5 is merged into `main`:

- Phase 1: build, CI, environment, API foundation, SEO and PWA baseline
- Phase 2: RTL design system and shared UI primitives
- Phase 3: OTP/account/address/order frontend flows
- Phase 4: API-backed catalog, products, variants, roasteries, filters and search
- Phase 5: single-roastery cart, server quote, idempotent order creation, payment request and verification flow

This baseline is feature-complete enough to audit, but it is not yet treated as security-complete or production-ready.

## Permanent business boundaries

- Rosta sells whole coffee beans only. Grind selection must never appear in product, variant, cart, checkout, order, seller or administration data.
- One customer order belongs to one roastery. Cross-roastery carts are rejected before checkout and again by the backend.
- Laravel is authoritative for identity, ownership, price, stock, roast batch, delivery eligibility, fees, discounts, totals, order state and payment truth.
- Browser storage is untrusted and may only hold bounded UI drafts or snapshots.
- Payment callback query parameters are hints only and never prove payment.
- Secrets, gateway credentials, SMS credentials and settlement credentials are server-only.

## Delivery rule learned from the Winimi/Cooci audit

A green build is not proof of correctness. Every phase must combine:

1. line-by-line source review
2. threat and failure-mode analysis
3. runtime input and response validation
4. deterministic regression tests
5. browser/API acceptance where relevant
6. production-boundary validation

A phase is complete only when fixes are merged and all permanent gates are green.

## Phase 6 — Frontend security audit and contract freeze

Audit the already-built customer frontend before creating the Laravel implementation.

Scope:

- API origin/path policy, timeout, abort, CSRF, 401/403/419/422/429/5xx handling
- runtime schemas for all server responses
- OTP/session expiry and safe return paths
- catalog/product/media/variant/stock correctness
- bounded and versioned cart persistence
- quote/order/payment intent binding and expiry
- payment redirect host allowlist
- callback/retry/cancellation/verified-success boundaries
- content trust, SEO claims, accessibility, PWA cache safety and deployment headers
- adversarial mobile/desktop acceptance

Exit marker:

`frontend_security_and_contract_audited=ready`

The frozen API contract produced here becomes the only implementation target for the Laravel backend.

## Phase 7 — Laravel foundation and frozen public contract

Create a dedicated backend repository and establish:

- Laravel 11, PHP, MySQL, Redis, queues and Horizon
- Sanctum session authentication and strict CORS/CSRF configuration
- versioned API envelope, stable error codes, pagination and request IDs
- OpenAPI 3.1 contract generated and checked in
- environment validation, health/readiness endpoints and structured logging
- migrations, factories, seeders, policies, rate limits and audit logs
- CI for tests, static analysis, migrations and contract drift

Exit marker:

`backend_foundation_and_contract=ready`

## Phase 8 — Identity, OTP, roles and addresses

Implement:

- customer OTP challenges with hashed codes, expiry, resend limits and abuse controls
- session login/logout/profile and active-session invalidation
- customer addresses and ownership enforcement
- roastery staff, administrators and role/permission policies
- IDOR regression coverage for every customer and seller resource
- SMS provider adapter disabled by default until credentials are supplied

Exit marker:

`identity_and_access=ready`

## Phase 9 — Roasteries, catalog, media and inventory

Implement:

- roastery onboarding, verification and staff ownership
- origins, products, variants and allowed whole-bean weights
- roast batches and immutable roast-date snapshots
- media validation and safe generated variants
- stock ledger, adjustments and availability rules
- seller-scoped catalog management
- public filtering, search and pagination matching the frozen frontend contract

Exit marker:

`catalog_and_inventory=ready`

## Phase 10 — Transactional cart, quote, order and reservation

Implement:

- authoritative cart validation
- address/delivery/coupon quote with expiration
- one-roastery enforcement at database/service boundaries
- customer-scoped payload-bound idempotency
- atomic inventory reservations and overselling protection
- immutable price, product, variant and roast-batch snapshots
- order lifecycle and safe cancellation boundaries
- reservation expiry and recovery jobs

Exit marker:

`transactional_checkout=ready`

## Phase 11 — Payments, refunds, ledger and settlement model

Implement:

- persistent payment attempts
- provider adapter isolated from order domain
- safe initiation, verification, retries and duplicate callback handling
- atomic paid/failed/cancelled/refunded transitions
- reconciliation jobs and operational visibility
- marketplace ledger separating gross sale, gateway fee, Rosta commission, refund and seller payable
- settlement batches and seller statements
- provider and split activation disabled until legal/account credentials are available

Exit marker:

`payments_and_ledger=ready`

## Phase 12 — Fulfillment, shipping and notifications

Implement:

- roastery acceptance SLA and rejection reasons
- preparation, ready-to-ship, shipped and delivered state machine
- carrier, tracking and delivery events
- shipping rules and price snapshots
- notification outbox with retry/dead-letter behavior
- SMS/email adapters disabled by default
- customer and seller timelines

Exit marker:

`fulfillment_and_notifications=ready`

## Phase 13 — Seller panel

Build `/panel` for authorized roastery staff:

- dashboard and order queues
- acceptance, rejection, preparation and shipment actions
- products, variants, roast batches, prices and stock
- working hours and temporary closure
- sales, fees, wallet and settlement statements
- strict roastery ownership and server-side authorization

Exit marker:

`seller_panel=ready`

## Phase 14 — Administration and operations

Build `/admin` with Filament:

- roastery onboarding and verification
- products, users, orders, payments, refunds and fulfillment
- commissions, ledger entries and settlements
- coupons, content and SEO metadata
- audit logs, support tooling and restricted operational actions
- no manual mutation that bypasses domain services

Exit marker:

`admin_operations=ready`

## Phase 15 — Content, quiz, reviews and public trust

Implement persisted and moderated:

- articles, FAQs, legal pages and roastery stories
- taste profile quiz and inventory-aware recommendations
- verified-purchase reviews
- contact and partnership inquiries
- dynamic sitemap and structured data
- no unsupported marketing claims or unsafe raw HTML

Exit marker:

`content_and_recommendations=ready`

## Phase 16 — Full frontend/backend integration

Replace all development-shaped data sources with the frozen Laravel API:

- production mocks disabled
- contract-version guard enabled
- all customer flows integrated
- all loading, empty, error and ownership states verified
- coordinated frontend/backend CI

Exit marker:

`storefront_integrated=ready`

## Phase 17 — Adversarial acceptance and operational readiness

Test:

- desktop/mobile browser matrix
- expired sessions, rate limits and hostile redirects
- stale/tampered carts and changed stock/price/roast batch
- duplicate submits and ambiguous network failures
- payment success/failure/cancel/replay/refund
- IDOR, role boundaries and seller isolation
- concurrency, queue retry, backup/restore and rollback
- accessibility, performance, CSP and security headers

Exit marker:

`security_and_operations_accepted=ready`

## Phase 18 — Deployment and external activation

Deploy reproducibly with separate frontend/API origins, HTTPS, monitoring, backups and rollback.

External activation remains limited to values that cannot be invented in code:

1. payment provider credentials and approved callback/settlement account
2. SMS provider credentials and approved templates
3. legal/trust inputs such as eNAMAD badge and finalized business policies

Final marker:

`rosta_launch_ready=ready`
