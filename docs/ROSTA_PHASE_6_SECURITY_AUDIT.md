# Rosta Phase 6 — frontend security audit and contract freeze

Phase lock: `2026-07-21-phase-6`

Current-state note: this audit froze the original customer contract. R5C later
superseded its single-roastery cart assumption with authoritative multi-roastery
grouping while preserving every security and trust-boundary rule.

## Purpose

Audit the complete customer storefront built through Phase 5 before using its current TypeScript contracts as the Laravel implementation target.

The audit does not add live payment or SMS credentials and does not treat a successful build as proof of security.

## Authoritative boundaries

- Laravel owns identity, resource ownership, prices, stock, roast batches, delivery eligibility, discounts, totals, order state, cancellation eligibility and payment truth.
- The frontend sends only user selections and opaque server identifiers.
- Browser storage is untrusted, bounded, versioned and never an authority for payment, stock or ownership.
- TypeScript interfaces are not runtime validation.
- Gateway callback parameters are untrusted hints.
- The cart is cleared only after a runtime-validated backend response consistently proves the expected order and payment attempt are paid.
- Product and inventory identity are whole-bean only. Later R5 grinding is valid
  only as a server-validated order-item service.

## Confirmed gaps at phase start

1. API base URLs are normalized but not yet validated as approved production HTTPS origins.
2. API paths may currently be supplied as absolute HTTP(S) URLs, which weakens the intended API-origin boundary.
3. Network failures are normalized, but timeout, external abort and `Retry-After` behavior are not yet modeled distinctly.
4. API payloads are cast to TypeScript types rather than validated with runtime schemas.
5. Checkout mappers currently fill some missing product/variant fields with plausible defaults, which can turn malformed data into apparently valid product truth.
6. Payment redirects require HTTPS but are not yet constrained to an explicit host allowlist.
7. Payment verification currently returns status and order ID without a complete runtime consistency predicate tied to the active payment/order intent.
8. Permanent adversarial unit/browser tests do not yet cover malformed payloads, redirect attacks, duplicate callback, stale cart, changed stock or ambiguous checkout failures.

## Threats reviewed

### API and session

- malicious or accidental cross-origin API requests
- protocol-relative, backslash, control-character or traversal-like paths
- insecure production HTTP configuration
- CSRF refresh loops and duplicated bootstrap requests
- expired session leaving stale authenticated UI state
- open redirects after login
- malformed Iranian mobile and OTP digit normalization
- brute-force, resend and rate-limit feedback failures

### Catalog and cart

- malformed media URLs or executable schemes
- missing or contradictory price, currency, stock and availability
- fabricated fallback stock or published state
- stale product or Variant identifiers
- oversized or future-version LocalStorage payloads
- cross-tab cart races
- quantity tampering and cross-roastery grouping bypass
- relying only on a paginated catalog page to reconcile cart truth

### Checkout, orders and payments

- duplicate orders after reload or ambiguous network failure
- reusing an Idempotency key after quote/payload changes
- quote expiration or changed address/coupon/stock
- unsafe payment redirect hosts
- trusting callback query parameters
- clearing a cart for the wrong order/payment attempt
- retrying payment for another user, another order or a terminal order
- exposing cancellation when the backend does not allow it
- leaking recipient/payment details through URLs, logs or persistent storage

### Platform and deployment

- development mocks entering production bundles
- stale service worker pinning unsafe checkout behavior
- missing CSP, HSTS and anti-framing headers
- secrets embedded in frontend output
- inaccessible dialogs, focus traps or error announcements
- mobile safe-area and horizontal-overflow regressions

## Work breakdown

### 6A — API origin, request and session security

- strict environment schema
- approved HTTPS origin validation
- relative-path-only API calls
- timeout/abort/network distinction
- normalized 401/403/404/419/422/429/5xx errors and `Retry-After`
- one-time CSRF recovery
- global session-expiry invalidation
- safe same-origin return paths

### 6B — Runtime contract validation

- Zod schemas for envelopes, errors, pagination, auth, address, catalog, media, Variant, quote, order and payment responses
- reject malformed identifiers, currencies, prices, quantities, states and dates
- downgrade invalid optional media without validating the rest of a malformed product
- remove fabricated availability, published-state and stock defaults
- contract fixtures and hostile-payload unit tests

### 6C — Catalog and cart hardening

- bounded versioned cart schema
- exact Variant reconciliation against server endpoints
- unavailable state for stale or unknown stock
- cross-tab synchronization
- atomic URL filter changes and bounded pagination/search inputs
- deterministic roastery grouping and quantity behavior

### 6D — Checkout and payment hardening

- payload-bound expiring transaction intent
- persistent Idempotency lifecycle across reload/ambiguous failures
- explicit payment redirect host allowlist
- runtime quote/order/payment schemas
- verified-success consistency predicate
- safe retry/cancellation boundaries
- duplicate callback and browser back/refresh safety

### 6E — Content, accessibility, PWA and deployment

- persisted production forms only
- safe content rendering and trust-badge allowlists
- verified SEO claims
- keyboard/focus/landmark/live-region review
- reduced motion and mobile safe areas
- service-worker update and cache invalidation audit
- production CSP/security headers, cache rules, health and rollback documentation

### 6F — Final adversarial acceptance

- desktop/mobile Chromium journeys
- offline/reconnect and expired session
- 419/429/server failures
- stale/tampered cart and changed stock/price
- duplicate submit and ambiguous order response
- payment success/failure/cancel/replay/refund
- malformed payloads and hostile URLs
- full lint, TypeScript, production build and bundle gates

## Completion gates

- no API request can escape the configured approved origin
- every production API payload crossing a trust boundary is runtime validated
- malformed data becomes an explicit error/unavailable state, never fabricated business truth
- roastery grouping, quantity, stock and Variant boundaries are deterministic
- transaction intent is bound to its payload and safely survives ambiguous failures
- redirect and callback attacks cannot create paid UI state
- production mocks and secrets are absent from output
- mobile/desktop adversarial acceptance is green
- all fixes and permanent regression gates are merged

Final marker:

`frontend_security_and_contract_audited=ready`
