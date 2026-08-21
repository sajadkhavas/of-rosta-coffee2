# ROSTA Domain & Database Contract

Status: **ARCH-0.3 / legacy PS0.3 — audited and consolidated contract**

Exact audited baseline:

`integration/rosta-release-candidate@f7e8a31cb0453fb2bff4eda1e4c2f07c6757fa5f`

## Purpose

This directory records the domain/data architecture already evidenced by the ROSTA repository and the rules that future migrations and capabilities must preserve. It is **not** a greenfield data-model redesign and it does not claim that roadmap capabilities are already implemented.

Business semantics remain governed by `docs/business/`. Runtime/module boundaries remain governed by `docs/architecture/`. This contract defines how those truths are represented, persisted, related, evolved and audited in MySQL and adjacent operational stores.

## Status vocabulary

- **BUILT** — directly evidenced in models/migrations/services on the audited baseline.
- **BUILT FOUNDATION** — persistence/runtime foundation exists but the wider product capability is not complete.
- **TARGET** — required design direction for future implementation; not yet claimed as live.
- **LEGACY DEBT** — historical schema/data/API semantics retained for compatibility and requiring an explicit forward migration/remediation before removal.
- **EXTERNAL GATE** — correctness depends on current provider, legal, policy or production configuration outside source code.

## Canonical documents

- `schema-inventory.md` — domain-grouped persisted structures and implementation anchors.
- `aggregate-and-ownership-model.md` — aggregate roots, write ownership and cross-domain references.
- `checkout-order-and-snapshots.md` — quote, Master Order, Roastery Sub-order and historical snapshot rules.
- `inventory-fulfillment-and-custody.md` — inventory reservations, seller commitment, shipments, Hub work and custody evidence.
- `financial-truth-settlement-and-reconciliation.md` — payment, refund, commission, settlement and reconciliation boundaries.
- `identity-privacy-and-sensitive-data.md` — identity/PII data minimization, retention and encryption boundaries.
- `consistency-idempotency-and-history.md` — transaction, locking, dedupe, event/history and async consistency rules.
- `migration-evolution-policy.md` — forward-only schema evolution, compatibility and rollout rules.
- `data-model-debt-register.md` — audited legacy drift and remediation ownership.
- `implementation-evidence-matrix.md` — BUILT/TARGET evidence map for this phase.

## Non-negotiable data invariants

1. MySQL is authoritative for transactional marketplace, order, payment, financial, fulfillment and audit truth.
2. Redis is operational infrastructure; it must never be the only durable source of order or financial truth.
3. A customer checkout produces one **Master Order** and one or more seller-scoped **Roastery Sub-orders**.
4. Whole bean remains the authoritative inventory identity. Grinding is persisted as an **Order Item Service**, never as a coffee product variant.
5. A verified paid seller Sub-order becomes a fulfillment commitment automatically. New schema or code must not reintroduce a routine post-payment seller accept/reject workflow.
6. Payment/refund/settlement states are not inferred from shipment or fulfillment states, and vice versa.
7. Financial component truth is explicit: GMV, seller payable/pass-through amounts, tax/carrier allocations and recognized ROSTA revenue are distinct.
8. Historical order/quote values use snapshots and immutable/auditable records where later catalog/profile edits would otherwise rewrite history.
9. Physical responsibility changes only on evidenced custody/handoff transitions.
10. Provider identifiers/payloads are evidence at integration boundaries; provider-specific values must not become Core domain identity.
11. Critical state transitions commit synchronously before non-critical async notifications or side effects are dispatched.
12. Idempotency/deduplication is required for checkout creation, payment verification/callbacks, notification dispatch, refunds, settlement/payout operations and other externally retried commands.
13. Applied migrations are historical records and are not edited to change production truth. Schema/data corrections are forward-only.
14. Sensitive data access follows purpose limitation and least privilege. The presence of a column is not permission to expose it.
15. Growth Network, Loyalty, Subscription and other roadmap capabilities receive dedicated schema only in their owning capability phase; ARCH-0.3 does not fabricate future tables.

## Aggregate summary

```text
User
  ├─ Addresses / Sessions / OTP
  └─ CheckoutQuote
       ├─ QuoteGroups (per Roastery)
       ├─ QuoteItems / ItemServices
       └─ InventoryReservations
             ↓ committed checkout
Master Order
  ├─ OrderItems / ItemServices / TaxLines
  ├─ OrderEvents / StatusHistory / InternalNotes
  ├─ PaymentAttempts / RefundAttempts
  ├─ Roastery Sub-orders
  │    ├─ Sub-order Items
  │    ├─ Fulfillment Incidents / Status History
  │    ├─ Shipments / Shipment Events
  │    └─ Settlement Allocations
  └─ Reconciliation / settlement evidence

ProductVariant
  ├─ InventoryStock
  └─ InventoryMovements

ROSTA Hub
  └─ HubWorkItems
       └─ HubWorkItemActions / custody-operational evidence
```

This diagram is logical. Exact foreign keys and support tables are recorded in migrations and the inventory document.

## Change rule

Any future implementation that changes an aggregate root, financial meaning, custody boundary, historical snapshot, PII exposure, idempotency contract or provider identity must either conform to this contract or update it through a dedicated reviewed architecture/data PR. Silent divergence is not accepted.
