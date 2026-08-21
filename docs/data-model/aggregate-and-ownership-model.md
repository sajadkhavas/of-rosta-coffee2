# Aggregate and Write-Ownership Model

Status: ARCH-0.3 canonical logical ownership

## Why this exists

ROSTA is a modular monolith. Database foreign keys make cross-domain reads convenient, but they do not grant every service permission to mutate every table. This document defines aggregate roots and the service/domain that owns state transitions.

## Aggregate map

| Aggregate / durable root | Primary children / evidence | Canonical write owner |
|---|---|---|
| User / Identity | addresses, auth sessions, OTP state, access records | Identity/Access |
| Roastery / Seller Organization | seller memberships, invitations, schedules/closures, profile/capabilities | Seller Organization + Catalog according to field purpose |
| Product | variants, catalog metadata, origin references, packaging/grinding capability references | Catalog |
| ProductVariant inventory | stock, reservations, movements/restocks | Inventory/Checkout reservation service |
| CheckoutQuote | quote groups, quote items, quote item services, temporary reservations | Checkout |
| Master Order | order items, snapshots, tax/event/history records | Checkout/Order orchestration |
| Roastery Sub-order | sub-order items, seller fulfillment commitment/status, incidents | Fulfillment/Order orchestration within seller scope |
| PaymentAttempt | provider request/callback/verification evidence | Payments |
| RefundAttempt | refund workflow/evidence | Refund/Finance |
| SettlementAllocation | component ownership/status/eligibility | Financial Truth / Settlement |
| SettlementBatch / payout record | batch items, payout evidence | Settlement/Payout |
| Shipment / ShipmentLeg | tracking and transport events | Fulfillment/Carrier adapter |
| HubWorkItem | Hub actions and custody-operational evidence | ROSTA Hub Operations |
| NotificationOutbox | delivery attempt state | Notifications |
| MediaUploadIntent / MediaAsset | processing/publication state | Media |
| ContentEntry | relations/publication/SEO state | Content |
| QuizAttempt / QuizVersion | answers/results/recommendation evidence | Quiz/Recommendation |
| Review | reply/moderation/report evidence | Review Safety |

The table is logical ownership. Existing classes may live in `Models/` and `Services/`; physical namespace layout does not override these boundaries.

## Master Order aggregate

`orders` is the customer-facing aggregate root for one checkout result. It owns:

- immutable order number and customer association;
- aggregate commercial snapshots/totals;
- aggregate payment-facing status fields;
- customer address snapshot;
- relationship to item, seller Sub-order, shipment and financial evidence.

The Master Order does **not** make every child state one coupled state machine. In particular:

- payment status is payment-owned truth;
- Sub-order fulfillment is fulfillment-owned truth;
- shipment leg status is carrier/fulfillment execution truth;
- settlement status is finance-owned truth.

Order-facing read models may compose those states, but no component may infer one from another without the owning service performing the transition.

## Roastery Sub-order aggregate

Each seller represented in a paid Master Order receives a seller-scoped Sub-order. The Sub-order is the operational boundary for:

- seller items;
- seller-specific pricing/allocation summaries;
- fulfillment commitment and SLA;
- fulfillment incidents;
- seller shipment legs;
- seller settlement allocations.

A Sub-order does not own sibling sellers' data. Seller authorization must scope access by organization/Roastery membership and Sub-order ownership.

### Legacy acceptance compatibility

The schema/model still contains `acceptance_status`, `accepted_at`, rejection fields and `SubOrderAcceptanceStatus` values from an earlier workflow. They are **not** the current business command boundary.

Current runtime truth is:

```text
verified payment
  -> inventory commit
  -> automatic seller fulfillment commitment
  -> Sub-order preparing/SLA state
```

New application code must not expose a routine `accept/reject paid order` command. Seller inability to fulfill is an incident/authorized exception workflow. Legacy acceptance columns may only be removed by a dedicated forward migration after proving no runtime, reporting, historical-data or API consumer depends on them.

## Product and inventory aggregate boundary

Product/catalog writes may update sellable metadata and variants. Inventory writes are separate transactional operations:

- direct mutation of `on_hand` / `reserved` outside the inventory service is prohibited;
- reservations belong to checkout/stock orchestration;
- commit/release/restock must create or preserve inventory evidence according to current service behavior;
- grind selection must never mutate ProductVariant identity.

Catalog publication and inventory availability are related but not the same state.

## Quote aggregate

A quote is a temporary calculated contract:

- it groups items by Roastery;
- captures service/pricing snapshots;
- holds expiring inventory reservations;
- includes explicit component totals;
- expires and can be recalculated/versioned.

Only Checkout creates or mutates quote truth. Frontend/cart storage is an input/request representation, never the authoritative quote.

## Payment aggregate

Payment attempts own provider communication state. A successful provider callback is not enough by itself; provider verification and transactional checks must establish the paid transition.

Payments may request the Order transition to Paid through the payment orchestration service, but provider raw values must not directly rewrite settlement or fulfillment records.

## Financial aggregate boundary

Financial Truth owns the classification of components and allocations. Settlement operates on eligible financial records; it must not recompute historical checkout prices from current catalog data.

ROSTA-owned service allocations, seller payable allocations, carrier/pass-through costs, taxes, discounts and reversals remain independently attributable.

## Fulfillment and custody aggregates

Fulfillment owns commitment, SLA, incident and physical-stage transitions. Shipment/Hub records provide execution evidence.

A custody transfer requires an evidenced event/action. A status string alone is not sufficient justification to change responsibility, seller payable or refund eligibility.

## Cross-aggregate mutation rule

A service may coordinate a transaction spanning aggregates only when it is the designated orchestration point and preserves each aggregate's invariant. Examples already evidenced include payment verification coordinating:

1. payment-attempt verification;
2. Master Order paid transition;
3. inventory commit;
4. automatic fulfillment commitment;
5. enqueue/persist notification intent after critical state truth.

This does not authorize arbitrary cross-table writes elsewhere.

## Read-model rule

Admin/customer/seller UIs may compose data from multiple aggregates. Composition is a read concern. UI convenience must not lead to a controller directly mutating unrelated aggregate tables.

## Future capability rule

A new capability must declare before migration:

- aggregate root;
- authoritative table(s);
- write owner/service;
- parent/reference relationships;
- idempotency boundary;
- historical snapshot needs;
- deletion/retention semantics;
- financial/custody impact if any;
- privacy classification;
- event/outbox needs.

If those cannot be stated, the schema change is not ready for implementation.
