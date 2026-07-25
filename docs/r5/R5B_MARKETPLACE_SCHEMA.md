# R5B — Marketplace Schema and Compatibility Layer

Status: implementation complete when all permanent backend and staging gates pass.

## Purpose

R5B converts the existing single-roastery persistence model into a database and model foundation capable of one parent order with multiple independently controlled roastery sub-orders.

## Compatibility decision

The existing `orders` table remains the parent commercial aggregate because payment attempts, refunds, reconciliation and customer identity already reference it.

The existing `sub_orders` table becomes the authoritative roastery child aggregate:

- the unique `order_id` constraint is removed;
- each sub-order retains one roastery;
- acceptance is separated from fulfilment;
- child financial totals are snapshotted;
- the legacy `Order::subOrder()` relation remains temporarily available;
- all new marketplace code must use `Order::subOrders()`.

Legacy orders and quotes are upgraded in place. They are not deleted, reseeded or assigned new public order identifiers.

## Added persistence boundaries

- checkout quote groups per roastery;
- quote-level item service snapshots;
- versioned grinding profiles;
- public roastery grinding capability configuration;
- order-item services;
- multi-leg shipment planning;
- immutable settlement allocation ownership;
- line-level tax records;
- append-only customer and operational events.

## Whole-bean boundary

Whole-bean inventory remains unchanged. Grinding is represented only as an order-item service. R5B does not add a grind field, selector, SKU dimension or stock identity to product variants, roast batches or inventory reservations.

## Cancellation boundary

`SubOrderAcceptanceStatus` is independent from the existing fulfilment status.

Only `awaiting_roastery_acceptance` is customer-cancellable. Accepted, seller-rejected and customer-cancelled states are closed to customer cancellation. API locking and transition execution are implemented in later phases, but the database and model contract are established here.

## Financial boundary

Every future monetary component can be stored as a `settlement_allocation` with:

- exact owner;
- source child aggregate;
- captured gross, discount, tax and net amount;
- pricing version;
- idempotency key;
- settlement state.

Rosta Hub grinding and Rosta-route shipping can therefore remain owned by Rosta without entering a roastery payable balance.

Tax rates are not hard-coded. `order_tax_lines` store the authoritative calculation snapshot and classification used at transaction time.

## Tracking boundary

`order_events` are append-only at the model layer. Customer-safe titles and descriptions are separated from encrypted internal metadata. Events can be scoped to the parent order, sub-order, item, service and shipment leg.

## Upgrade and rollback

The migration:

1. makes legacy parent `roastery_id` fields nullable;
2. removes the single-sub-order unique constraint;
3. backfills legacy acceptance and child totals;
4. creates one quote group for every legacy quote;
5. attaches existing quote items to that group;
6. adds the new service, logistics, financial and event tables.

Rollback is fail-closed. It refuses to restore the old one-roastery schema after multiple sub-orders or null legacy roastery pointers exist.

## Explicit non-goals

No public checkout behaviour is activated in R5B.

R5B does not:

- allow a customer to submit a multi-roastery checkout;
- change payment capture;
- implement seller accept/reject endpoints;
- activate customer cancellation endpoints;
- expose grinding selection in the frontend;
- calculate production tax;
- execute settlement or refund movement;
- operate a live Rosta Hub.

Those behaviours begin in R5C and later phases.

Exit marker: `ROSTA_R5B_MARKETPLACE_SCHEMA_COMPLETE`
