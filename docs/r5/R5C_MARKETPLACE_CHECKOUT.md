# R5C — Multi-Roastery Cart, Quote and Order Creation

Status: implemented on `program/r5c-multi-roastery-checkout`.

## Scope

R5C activates the commercial core prepared by R5B:

- a cart may contain variants from multiple verified roasteries;
- Laravel groups authoritative items by roastery;
- every checkout quote contains one persisted `checkout_quote_group` per roastery;
- shipping is quoted independently for every group;
- a global coupon may be allocated across groups;
- a roastery-scoped coupon is valid only when the quote contains exactly that roastery;
- one order request creates one parent order and one sub-order per group;
- stock reservations, settlement allocations, shipment plans and initial events are created in the same transaction;
- retries with the same idempotency key return the same parent order.

## Deferred from R5C

The R5B service schema remains available, but public grinding and packaging selection is intentionally deferred. R5C does not accept a customer-selected provider, fee or settlement owner. Those flows require dedicated capability, zone and operational acceptance in a later phase.

## Quote authority

The request contains only variant identifiers, quantities, address and an optional coupon code. Laravel derives:

- product and roastery availability;
- authoritative unit prices;
- available stock;
- roast-batch snapshots;
- group membership;
- shipping per group;
- discount allocation;
- group and parent totals;
- quote expiry.

A multi-roastery quote stores `roastery_id = null` on the parent compatibility column. A single-roastery quote continues to populate it for legacy readers.

## Order transaction

The order transaction locks the customer, idempotency record, quote and variants. It then creates:

1. one parent `orders` record;
2. one `sub_orders` record for every quote group;
3. immutable `order_items` snapshots;
4. one inventory reservation per item;
5. product settlement allocations owned by the relevant roastery;
6. one direct shipment leg and shipping allocation per sub-order;
7. append-only order and sub-order events;
8. the consumed quote and completed idempotency record.

Any exception rolls back the complete commercial order.

## Compatibility

- Existing single-roastery checkout behaviour remains valid.
- `Order::subOrder()` remains a temporary compatibility relation.
- New marketplace code uses `Order::subOrders()` and quote groups.
- Existing payment remains bound to the parent order and one grand total.
- Existing customer cancellation of an unpaid single parent remains available; independent post-payment sub-order cancellation is outside R5C.

## Validation

The R5C feature test proves:

- two roasteries in one quote;
- one parent order with two sub-orders;
- two reservations;
- two direct shipment legs;
- four settlement allocations: two products and two shipping lines;
- append-only initial events;
- idempotent replay without duplicate commercial records.

Exit marker: `ROSTA_R5C_MARKETPLACE_CHECKOUT_COMPLETE`.
