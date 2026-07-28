# R5A — API and Acceptance Contract

This document defines implementation-facing contracts for R5B–R5K. Endpoint names may evolve, but authority, idempotency, transition and acceptance rules may not.

> Historical decision note: R5H supersedes the manual seller-acceptance,
> rejection and customer-cancellation API described below. New sub-orders are
> committed automatically after verified payment; seller exceptions use
> incidents and administrator-scoped resolution.

## 1. Quote contract

The authoritative quote request may contain customer selections, but never trusted prices or settlement ownership.

Customer-controlled inputs may include:

- product variant identifier;
- quantity;
- selected approved grinding profile when requested;
- selected delivery address identifier;
- coupon code;
- delivery option.

The backend derives:

- roastery grouping;
- packaging policy and fee;
- roastery grinding capability;
- Rosta Hub eligibility;
- grinding provider;
- all service and shipping prices;
- taxes;
- discount allocation;
- parent and sub-order totals;
- quote expiry.

The quote response must expose grouped customer truth and an opaque quote reference. Order creation consumes the quote reference and revalidates stock, price, capability, capacity and address eligibility.

## 2. Order creation contract

Order creation must be idempotent and atomic across:

- parent order;
- sub-orders;
- order items;
- order-item services;
- inventory reservations;
- allocation ledger;
- shipment plans;
- payment intent binding.

A failure before commit creates no partial commercial order. A retry with the same idempotency key and identical payload returns the existing result. A changed payload with the same key fails.

## 3. Customer cancellation API

Conceptual route:

```text
POST /api/orders/{parentOrder}/sub-orders/{subOrder}/cancel
```

Required backend checks:

1. authenticated customer owns the parent order;
2. sub-order belongs to the parent order;
3. acceptance status is exactly `awaiting_roastery_acceptance`;
4. row lock is acquired;
5. no earlier accepted, rejected or cancelled transition exists;
6. refund and inventory-release effects are idempotent.

Success creates:

- `cancelled_by_customer` transition;
- exact refund plan or refund ledger action;
- exact-once inventory release;
- parent aggregate recalculation;
- customer notification.

After acceptance, return a domain conflict without side effects.

## 4. Roastery acceptance and rejection API

Conceptual routes:

```text
POST /api/seller/sub-orders/{subOrder}/accept
POST /api/seller/sub-orders/{subOrder}/reject
```

Required checks:

- actor is scoped to the owning roastery;
- sub-order is awaiting acceptance;
- the same row lock used by customer cancellation is acquired;
- acceptance confirms current operational ability;
- rejection requires a customer-safe reason category;
- replay is idempotent.

A seller cannot act on another roastery's sub-order or change an accepted order to rejected.

## 5. Grinding capability API

Seller configuration must support:

```text
grinding_capability: available | unavailable
supported_profile_ids: string[]
grinding_fee_mode: free | fixed
grinding_fee_amount: non-negative authoritative amount
preparation_minutes
supported_weights
capacity_policy
```

Public product and roastery responses expose a safe customer-facing capability summary. Internal equipment and capacity details remain private unless explicitly approved for public display.

Capability updates affect future quotes only and do not mutate existing order snapshots.

## 6. Packaging policy API

Seller product configuration must support:

```text
packaging_fee_mode: free | fixed
packaging_fee_amount: non-negative authoritative amount
```

Public catalogue responses expose both mode and formatted customer amount. Zero must not be omitted.

In an eligible Rosta Hub path, quote generation overrides the roastery packaging allocation for that item to zero and emits a separate zero-value Rosta Hub packaging line.

## 7. Tracking and event API

Customer order detail must return:

- parent aggregate state;
- grouped sub-orders;
- order-item services;
- shipment legs;
- append-only customer-visible events;
- current responsible party;
- next expected step;
- cancellation capability derived by backend;
- invoice lines and allocation labels safe for customers.

Internal event metadata, operator personal data, security fields and private notes are never exposed.

Initial frontend refresh may use bounded polling. A later real-time transport may be added without changing the event contract.

## 8. Required domain error categories

```text
quote_expired
price_changed
stock_unavailable
mixed_state_conflict
roastery_already_accepted
sub_order_already_rejected
sub_order_already_cancelled
customer_cancellation_closed
hub_zone_ineligible
hub_capacity_unavailable
unsupported_grind_profile
seller_scope_forbidden
order_scope_forbidden
idempotency_payload_mismatch
refund_requires_review
```

Errors must be customer-safe while preserving request identifiers for support and audit.

## 9. R5A acceptance matrix

### Multi-roastery commerce

- Two roasteries can coexist in one cart.
- One quote contains separate groups and one grand total.
- One payment creates one parent order and two sub-orders.
- Replayed payment callback creates no duplicate order or allocation.

### Packaging

- A free roastery packaging line is visible as free.
- A paid packaging line uses the snapshotted amount.
- A later seller edit does not change an existing order.
- Rosta Hub path has zero roastery packaging and explicit free Hub packaging.

### Grinding

- Customer sees whether each roastery provides grinding.
- A capable roastery can provide only configured profiles.
- An incapable roastery can route to Rosta Hub only in enabled Tehran or Karaj zones.
- Rosta Hub service is rejected outside enabled zones.
- Frontend price/provider tampering is ignored or rejected by backend.

### Cancellation and rejection

- Customer cancellation succeeds while awaiting acceptance.
- Customer cancellation fails after acceptance with no side effects.
- Parent cancellation succeeds only when every active sub-order is pending.
- A pending sibling may be cancelled after another sibling is accepted.
- Simultaneous acceptance and cancellation produce exactly one winner.
- Seller rejection refunds only affected allocations and preserves siblings.

### Settlement

- Rosta Hub grinding and Rosta-route shipping never enter roastery payable.
- Roastery receives the product allocation subject to existing deductions.
- Refund never exceeds captured amount.
- Duplicate refund or settlement execution is blocked.

### Tracking

- Customer sees independent state for every roastery.
- Customer sees Rosta Hub receipt, grinding, free packaging and shipment events.
- Parent state correctly reports partial acceptance, dispatch and delivery.
- Customer cancellation control matches backend capability.

### Security

- Customer cannot read another customer's order.
- Seller cannot read or mutate another roastery's sub-order.
- Hub operator cannot mutate an unassigned service request.
- State skipping, forged fees, forged provider and forged settlement owner fail closed.
- All accepted transitions produce append-only audit events.

## 10. R5A implementation boundary

R5A commits contracts and a permanent audit only. It must not introduce production database migrations, public UI, payment changes, seller mutations or live Hub operations. Those begin in R5B and later phases after the contract pull request is accepted.
