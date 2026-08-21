# Checkout, Order and Snapshot Model

Status: ARCH-0.3 canonical persistence contract

## 1. Quote is temporary truth

The customer/browser submits cart intent. Backend quote services resolve current sellable product, inventory, seller grouping, services, shipping inputs, discounts and tax policy into a persisted `CheckoutQuote`.

Canonical flow:

```text
browser cart intent
  -> backend validation
  -> CheckoutQuote
       -> QuoteGroup per Roastery
       -> QuoteItem(s)
       -> QuoteItemService(s)
       -> InventoryReservation(s)
  -> order creation using validated quote/idempotency
```

The browser must never become the source of amount, discount, commission, tax, shipping or service-fee truth.

## 2. Multi-vendor grouping

One quote may contain multiple Roasteries. The authoritative seller partition is `checkout_quote_groups`, not a single quote-level `roastery_id`.

Each group captures seller-specific component totals and a pricing snapshot. Each quote item belongs to the relevant group; item services also bind to group/item and provider semantics.

This grouping is copied into durable Order/Sub-order structure at checkout.

## 3. Master Order and Sub-orders

A successful order creation results in:

```text
Master Order
  ├─ Sub-order for Roastery A
  │    └─ seller items/services
  ├─ Sub-order for Roastery B
  │    └─ seller items/services
  └─ ...
```

The Master Order is the customer-facing aggregate. Sub-orders are seller-scoped operational/commercial partitions.

A future migration must not collapse a multi-roastery order back into a single seller field or duplicate one payment into unrelated Orders merely to simplify seller views.

## 4. Snapshot policy

Historical commerce records must remain understandable even after mutable source data changes.

Snapshot candidates already evidenced include:

- product/name/SKU/weight and price facts on quote/order items;
- Roastery display identity on quote groups/order partitions where required;
- service/pricing snapshots for grinding and other order-item services;
- address snapshot on the Master Order;
- origin/destination snapshots on shipment legs;
- tax calculation snapshots;
- pricing version/source reference on settlement allocations;
- provider request/response evidence on payment/refund attempts.

### Snapshot rule

Use a snapshot when a later edit to the source entity would otherwise rewrite historical customer, accounting, tax, dispute or fulfillment meaning.

Do **not** use snapshots as an excuse to duplicate every mutable record. References remain useful for navigation/current entity identity; snapshots carry historical facts.

## 5. Address history

The account address is mutable. The Order address is historical delivery truth.

The Order model casts `address_snapshot` as `encrypted:array`; checkout must write the delivery snapshot that was validated for that purchase. Later edits/deletion of the reusable Address must not silently rewrite the order snapshot.

Sensitive-data retention/deletion must still follow privacy/legal policy; historical necessity is not equivalent to indefinite retention.

## 6. Money component model

Quote/Order data must store explicit integer money components in the canonical currency contract. Conceptually:

```text
customer total
 = product subtotal
 + grinding/service charges
 + packaging charges
 + shipping charges
 + tax
 - discounts
```

Exact recognized revenue and seller payable are Financial Truth concerns and must not be inferred from the customer total.

All financial math remains backend-controlled and uses integer minor/canonical currency units according to the current MoneyMath/finance contract. No floating-point financial persistence.

## 7. Grinding/service snapshots

Grinding is an Order Item Service. Quote and order service records preserve:

- service type;
- selected/provider type;
- provider reference where applicable;
- grinding profile/version reference;
- pricing/service snapshots;
- component amounts;
- service lifecycle state.

Changing a Roastery's future grinding capability or fee must not retroactively rewrite an already-created Order Item Service.

## 8. Idempotent order creation

`order_idempotency_keys` binds a customer + idempotency key to request hash/status/order/response evidence. The contract is:

- same key + same canonical request can return/recover the same result;
- same key + materially different request must not create a second order;
- processing/complete/failure state must be concurrency-safe;
- expired keys may be pruned according to policy only after their replay-safety window has passed.

Unique order/quote associations and inventory reservation constraints are additional database safety, not substitutes for command idempotency.

## 9. Order state is multi-axis

ROSTA must not compress all lifecycle meaning into one status. At minimum, separate truths include:

- Order/customer aggregate status;
- payment status;
- Sub-order/fulfillment status;
- shipment/leg status;
- refund status;
- settlement/allocation/payout status;
- service-work status.

Read models can derive customer-friendly summaries, but derived labels must never write back as authoritative cross-domain state.

## 10. Paid-order transition

Current canonical transition after verified payment is:

```text
verified payment attempt
  -> Order payment = paid
  -> inventory commit
  -> seller Sub-orders become fulfillment commitments automatically
  -> SLA/preparing truth established
  -> durable notification intent queued
```

No routine seller acceptance gate exists after verified payment. Historical acceptance columns/templates are compatibility debt recorded in `data-model-debt-register.md`.

## 11. Cancellation/refund separation

Order cancellation eligibility and refund execution are separate decisions:

- cancellation modifies authorized order/fulfillment state;
- refund modifies financial/provider state;
- one must not be assumed merely from the other;
- partial/multi-seller outcomes must preserve healthy sibling Sub-orders and allocation truth.

Policy/legal eligibility belongs to the policy-owning service; database status alone is not a legal decision engine.

## 12. Historical event evidence

Order events/status histories/internal notes serve different purposes:

- customer/audit event — what business transition occurred;
- status history — state-machine transition evidence;
- internal note — operator context, not authoritative state by itself.

New code must not mutate a status only and omit required event/history evidence where the owning service contract expects it.
