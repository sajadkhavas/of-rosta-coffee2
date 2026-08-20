# System Context

Status: ARCH-0.2

## 1. System of interest

ROSTA is a multi-vendor coffee marketplace, fulfillment network and coffee-experience platform. The technical system coordinates customer experience, seller operations, financial truth and physical fulfillment while keeping provider-specific concerns at the edges.

## 2. Human actors

### Customer
Uses public catalog, account/session, cart/checkout, payment, order tracking, quiz/recommendations, reviews and support. Customer-facing state is aggregated by ROSTA; internal seller/provider secrets remain hidden.

### Roastery operator
Manages its organization-scoped catalog, stock/batches, schedules/closures, grinding/packaging capability, committed Sub-orders, preparation/handoff, incidents, shipments and settlement views. It has no cross-roastery access.

### ROSTA support/admin/finance/fulfillment operators
Use permission-scoped operational surfaces. Admin is not a bypass around least privilege. Finance cannot mutate fulfillment truth merely to make money reconcile; fulfillment cannot mutate payment truth.

### Growth/experience partners
Interact only through approved capability boundaries. Growth Partner financial truth is ledger-backed. Experience partners receive only minimum campaign/fulfillment data.

## 3. ROSTA runtime systems

```text
Browser / PWA
     |
     | HTTPS
     v
TanStack Start SSR / Node runtime
     |
     | HTTPS JSON API + Sanctum session/CSRF
     v
Laravel API / application
     |
     +---- MySQL (transactional truth)
     +---- Redis (cache/session/queue/locks)
     +---- S3-compatible storage / R2 (media bytes)
     +---- Queue workers
     +---- Scheduler
     |
     +---- Payment provider boundary -> Zarinpal / disabled/testing
     +---- SMS boundary -> Kavenegar / disabled/testing as configured
     +---- Carrier boundary -> future/current provider integrations + manual-safe fallback
     +---- Partner boundary -> partner-specific integrations when approved
```

## 4. Trust boundaries

### Browser boundary
Browser input is untrusted. Prices, totals, availability, seller allocation, payment status, settlement, authorization and tax decisions must be recomputed or verified server-side.

### Frontend SSR boundary
SSR may safely use public/server-readable configuration but must not embed backend/provider secrets into rendered HTML or client bundles. SSR is presentation/orchestration, not financial authority.

### API boundary
Laravel authenticates/authorizes mutations, validates payloads, applies tenant/seller scope, enforces idempotency and owns transactional invariants.

### Database boundary
MySQL is the durable system of record for transactional domain state. Direct ad-hoc production database mutation is not an application workflow.

### Redis boundary
Redis loss may degrade sessions/cache/queue throughput but must not redefine durable order/finance truth. Recovery procedures must assume Redis is replaceable operational state unless a dedicated feature explicitly persists durable data elsewhere.

### Object-storage boundary
Object bytes are external to MySQL. Database metadata/keys bind ownership, lifecycle and variants. Client-supplied metadata is not trusted until server-side media validation completes.

### Provider boundaries
Payment/SMS/carrier/partner responses are external evidence, not automatically trusted domain commands. Responses/callbacks must be authenticated/verified where the provider supports it, normalized, idempotently processed and correlated with internal records.

## 5. Business-to-technical translation

- Master Order is customer aggregate; Roastery Sub-orders preserve seller-specific truth.
- Payment verification creates financial/payment truth; it does not prove fulfillment.
- Valid paid Sub-orders create seller fulfillment commitments.
- Direct Fulfillment and ROSTA Fulfillment remain separate capabilities; centralized Hub launch routing is policy/configuration.
- Whole bean is stock identity; grinding execution is a service record linked to an order item.
- Seller-owned/pass-through amounts are liabilities/allocations, not automatically ROSTA revenue.
- Customer support remains ROSTA-facing even where operational responsibility belongs to seller/carrier.

## 6. External/legal boundaries

Tax treatment, marketplace/agency classification, provider contracts and consumer/carrier liability are externally versioned policy inputs. Technical architecture must support explicit allocations, invoice/tax metadata and audit evidence without hard-coding a legal conclusion that has not been externally validated.
