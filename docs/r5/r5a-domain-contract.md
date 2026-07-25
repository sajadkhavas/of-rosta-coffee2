# R5A — Multi-Roastery Marketplace Domain Contract

Status: **Approved product contract**  
Approved: **2026-07-25**  
Program: **R5 — Multi-Roastery Commerce, Grinding and Fulfillment**  
Parent branch: `integration/rosta-r5-marketplace`

## 1. Purpose

R5 changes Rosta from a single-roastery order model to a marketplace checkout that supports products from multiple roasteries while preserving authoritative stock, pricing, payment, fulfilment, settlement and audit boundaries.

This document is the product-domain source of truth for R5B and later implementation phases. Code must not reinterpret these decisions.

## 2. Non-negotiable outcomes

1. A customer may place products from multiple roasteries in one cart.
2. The customer completes one checkout and one payment.
3. The platform creates one parent order and one sub-order per roastery.
4. Every sub-order has independent acceptance, fulfilment, shipment, settlement and incident state.
5. Product inventory remains whole-bean inventory. Grinding is an order-item service and is never a product variant, stock dimension or roast-batch dimension.
6. A customer may cancel a sub-order only while that sub-order is awaiting roastery acceptance.
7. After roastery acceptance, customer cancellation is permanently unavailable in both UI and API.
8. A roastery rejection is a seller action, not a customer cancellation, and triggers an authoritative partial refund flow.
9. Packaging fees are optional per product and may be zero.
10. A zero packaging fee must be visible as a positive customer-facing fact in product, cart, checkout and invoice surfaces.
11. Every roastery publicly declares whether it provides grinding.
12. Rosta Hub grinding is available only when the selected roastery does not provide grinding and the delivery address is eligible in Tehran or Karaj.
13. Rosta Hub packaging is always free.
14. In a Rosta Hub grinding path, no roastery packaging charge is collected for that order item.
15. Rosta Hub grinding and Rosta-route shipping amounts remain allocated to Rosta. The roastery receives only the product allocation, subject to the existing marketplace commission and statutory deductions.
16. Taxes and statutory charges are customer-funded when applicable and are calculated line by line by the authoritative backend.
17. The customer can see what is happening to every sub-order, order item service and shipment leg through an append-only timeline.

## 3. Aggregate model

```text
Cart
└── Marketplace Checkout
    └── Parent Order
        ├── Roastery SubOrder A
        │   ├── OrderItem A1
        │   └── OrderItem A2
        ├── Roastery SubOrder B
        │   └── OrderItem B1
        └── Rosta Service Fulfilments
            ├── Grinding Request
            └── Shipment Legs
```

### Parent order responsibilities

The parent order owns:

- customer identity;
- delivery address snapshot;
- checkout quote and expiry;
- one payment intent and payment result;
- parent totals and currency;
- aggregate status derived from child state;
- customer-visible invoice and timeline entry point.

### Roastery sub-order responsibilities

A sub-order owns:

- exactly one roastery;
- the roastery's order items;
- acceptance or rejection;
- roastery preparation and fulfilment state;
- roastery-owned packaging and grinding charges;
- roastery settlement allocation;
- independent rejection, refund and shipment consequences.

### Order-item service responsibilities

An order-item service owns a non-product operation, including:

- grinding provider: `roastery` or `rosta_hub`;
- grind profile selected from an approved profile catalogue;
- service price snapshot;
- free or paid packaging snapshot;
- responsible operator or facility;
- service state and service events;
- settlement owner.

## 4. Product and inventory boundary

Rosta continues to sell and stock whole coffee beans in exact supported weights:

- 50 g
- 100 g
- 250 g
- 500 g
- 1000 g

Grinding must not be added to:

- product identity;
- SKU identity;
- product variant identity;
- roast batch identity;
- stock ledger identity;
- inventory reservation identity;
- catalogue search facets.

Grinding is selected after a whole-bean order item exists in cart or checkout and is snapshotted as a service request attached to that order item.

## 5. Multi-roastery checkout contract

The customer experiences one commercial checkout:

- one cart;
- one authoritative quote;
- one displayed grand total;
- one payment attempt;
- one bank transaction;
- one customer invoice.

The backend creates independent allocations for:

- each roastery's products;
- each roastery's optional packaging;
- each roastery's grinding service when applicable;
- Rosta Hub grinding;
- Rosta-route shipping;
- platform commission;
- discounts;
- taxes and statutory charges;
- refunds and reconciliation.

A payment callback, retry or replay must never create duplicate parent orders, duplicate sub-orders or duplicate allocations.

## 6. Packaging contract

Each product has an explicit packaging policy:

```text
packaging_fee_mode = free | fixed
packaging_fee_amount = authoritative non-negative amount
```

Rules:

1. The roastery chooses whether the product packaging is free or paid.
2. The fee is displayed before the item enters checkout.
3. A free fee is displayed as `Roastery packaging: free`, not hidden.
4. The selected fee is snapshotted in the quote and order.
5. Later roastery price changes never mutate an existing order.
6. A Rosta Hub grinding selection forces the roastery packaging allocation for that item to zero.
7. Rosta Hub packaging is represented by an explicit zero-value invoice line.
8. Packaging charges cannot be injected or changed from the frontend.

## 7. Grinding provider contract

Every roastery has a public grinding capability:

```text
grinding_capability = available | unavailable
```

When available, the roastery may configure:

- supported approved grind profiles;
- free or fixed service fee;
- preparation time;
- supported weights;
- operational capacity.

When unavailable, Rosta Hub grinding may be offered only if all conditions are true:

1. the customer requested grinding;
2. the roastery is marked unavailable for grinding;
3. the authoritative delivery address is in an enabled Tehran or Karaj service zone;
4. an enabled Rosta Hub can accept the request;
5. the requested profile and weight are supported.

The frontend may explain eligibility but Laravel is authoritative and must revalidate it during quote and order creation.

## 8. Approved initial grind profiles

- Turkish coffee
- Home espresso, pressurised basket
- Moka pot
- AeroPress
- V60
- Chemex
- Filter coffee machine
- French press
- Cold brew

Profiles are versioned operational recipes. A profile version may change for future requests but cannot change an existing order snapshot.

## 9. Customer cancellation contract

### Sub-order cancellation

A customer may cancel only when:

```text
sub_order.acceptance_status = awaiting_roastery_acceptance
```

After the roastery accepts:

```text
sub_order.acceptance_status = accepted
customer_cancellable = false
```

This is a backend invariant. Hiding a button is not enforcement.

### Parent-order cancellation

The customer may cancel the whole parent order only while every non-terminal sub-order is awaiting roastery acceptance.

If at least one sub-order is accepted, the parent order cannot be customer-cancelled. The customer may still cancel any other sub-order that remains awaiting acceptance.

### Concurrency rule

Roastery acceptance and customer cancellation must lock the same sub-order record.

- If cancellation commits first, acceptance is rejected.
- If acceptance commits first, cancellation is rejected.
- Exactly one transition may win.

### Operational incidents after acceptance

After acceptance there is no customer cancellation route. Operational failures, seller breach, loss, damage, contamination, wrong item, impossible Hub service or other exceptional cases are handled through an admin incident/refund workflow. Such action is not represented as customer cancellation and requires an append-only audit trail.

## 10. Roastery rejection contract

A roastery may reject only while awaiting acceptance. Rejection must:

- close that sub-order as seller-rejected;
- release its inventory reservations exactly once;
- prevent later acceptance;
- calculate an exact partial refund for unfulfilled allocations;
- refund unused grinding, packaging, shipping and applicable tax allocations;
- leave accepted or completed sibling sub-orders unchanged;
- set the parent aggregate to an appropriate partial state;
- notify the customer with a clear reason category.

## 11. Settlement ownership contract

Allocation ownership is explicit:

| Allocation | Owner |
|---|---|
| Product gross amount | Roastery |
| Roastery grinding fee | Roastery |
| Roastery packaging fee | Roastery |
| Rosta Hub grinding fee | Rosta |
| Rosta Hub packaging fee | Rosta, amount always zero |
| Rosta-route shipping charge | Rosta |
| Platform commission | Rosta |
| Tax/statutory line | Statutory ledger owner |

The roastery product allocation may be reduced by the existing marketplace commission and statutory deductions during settlement. No Rosta Hub grinding or Rosta-route shipping amount can enter roastery payable balance.

## 12. Customer tracking contract

The customer order page must provide:

- parent aggregate status;
- one card per roastery sub-order;
- one card per Rosta service fulfilment;
- current state, previous events and next expected step;
- event timestamps;
- responsible party: roastery, Rosta Hub, carrier or Rosta support;
- shipment count and tracking identifiers;
- grinding provider and selected profile;
- packaging line, including free packaging;
- customer cancellation availability derived from backend state;
- delay or incident explanation safe for customer display.

All timeline events are append-only. Internal notes and sensitive operational metadata remain private.

## 13. Security and authority boundaries

- Laravel remains authoritative for eligibility, prices, stock, tax, totals, transitions, allocations, refunds and permissions.
- A roastery can read and mutate only its own sub-orders.
- A Hub operator can mutate only assigned Hub service requests.
- A customer can read only their own parent orders and children.
- A customer cannot choose settlement owner, provider identity, fee amount or service-zone eligibility through request payloads.
- Every state transition records actor, previous state, next state, time and request identifier.
- Money movement, inventory release and service completion are idempotent.

## 14. R5A exit criteria

R5A is complete when:

1. this domain contract is committed;
2. state machines are committed;
3. ledger and refund allocation rules are committed;
4. API and acceptance invariants are committed;
5. a permanent repository audit verifies the R5A contract files and package gate;
6. no production feature implementation is introduced in R5A;
7. the R5A audit emits `ROSTA_R5A_DOMAIN_CONTRACT_COMPLETE`.
