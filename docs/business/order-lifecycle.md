# ROSTA Order Lifecycle Contract

Status: PS0.1 business architecture reference

## 1. Order identity

ROSTA uses a multi-vendor hierarchy:

- **Master Order**: one customer-facing aggregate checkout/order.
- **Roastery Sub-order**: one seller-specific child for each participating Roastery.
- **Order Item**: purchased whole-bean product line.
- **Order Item Service**: optional service attached to an Order Item, including Grinding.

A Master Order may contain multiple Sub-orders with different fulfillment modes and timelines.

## 2. Canonical lifecycle

Business lifecycle:

```text
Created
  -> Payment Pending
  -> Paid
  -> Roastery Accepted
  -> Fulfillment Decision
       -> Direct Fulfillment
       OR
       -> ROSTA Fulfillment
  -> Optional Grinding (Order Item Service only)
  -> QC where applicable
  -> Packaging
  -> Shipment
  -> Delivered
  -> Completed
```

This is a business lifecycle. Implementation may use more granular technical states, but must preserve these meanings and must not collapse distinct financial, seller or fulfillment truth.

## 3. Master Order states

### Created

The checkout/order record exists but no successful payment has been established.

### Payment Pending

Payment is in progress or awaiting a terminal provider outcome.

Allowed outcomes include:

- Paid;
- Failed Payment;
- Expired/abandoned according to policy.

### Paid

Payment has been verified according to the payment contract. This does not mean every Roastery has accepted its Sub-order.

### In Fulfillment

At least one accepted Sub-order is progressing through preparation/fulfillment and the Master Order is not fully terminal.

### Partially Delivered / Partially Resolved

Different Sub-orders may reach different terminal states. The customer-facing aggregate must preserve that partial truth rather than forcing all siblings into one state.

### Completed

All required Sub-orders have reached the policy-defined successful terminal state and any required completion conditions have passed.

## 4. Roastery Sub-order lifecycle

Each Sub-order independently tracks:

```text
Awaiting Roastery Acceptance
  -> Accepted
  -> Preparing
  -> Ready for Fulfillment/Handoff
  -> Shipped / In Transit
  -> Delivered
  -> Completed
```

A Sub-order may instead enter cancellation, refund, return, dispute or other exception states without corrupting sibling Sub-orders.

## 5. Roastery acceptance

After a valid paid order reaches the seller boundary, the Roastery must accept or reject according to its SLA and policy.

Acceptance is seller-specific. One Roastery accepting must not imply another Roastery accepted.

Timeout/non-response is an operational exception and follows the SLA/escalation policy in `roastery-sla-model.md`.

## 6. Fulfillment decision

Fulfillment mode is determined per applicable Sub-order/fulfillment contract:

### Direct Fulfillment

Roastery retains operational responsibility for preparation, packing, dispatch and carrier handoff.

### ROSTA Fulfillment

Roastery prepares the goods for handoff to ROSTA. Once a valid Chain of Custody receipt is recorded, ROSTA assumes responsibility for the contracted hub stages.

## 7. Grinding lifecycle

Grinding is never a product variant.

When requested:

```text
Order Item: whole-bean coffee
Order Item Service: Grinding
Provider: Roastery OR ROSTA Hub
```

The service must have its own execution truth and may have its own price, provider, status and failure reason without creating duplicate inventory SKUs for the same coffee solely because of grind choice.

## 8. Shipping lifecycle

Shipment may contain one or more legs depending on fulfillment topology.

Examples:

Direct:

```text
Roastery -> Carrier -> Customer
```

ROSTA Fulfillment:

```text
Roastery -> ROSTA Hub -> Carrier -> Customer
```

Each physical handoff must preserve Chain of Custody and tracking history.

## 9. Failure and exception states

### Failed Payment

No paid-order truth may be created from a failed or unverified payment.

Owner: ROSTA payment flow.
Escalation: Finance/Support as appropriate.

### Cancel

Cancellation availability depends on current state and policy. Cancellation of one Sub-order must not silently cancel healthy sibling Sub-orders.

### Refund

Refund is a financial consequence and must be represented by the financial system. Fulfillment state alone must never be treated as proof that money was refunded.

### Return

Return is a post-delivery/post-dispatch physical workflow whose eligibility and result are policy-driven. Return and Refund are distinct concepts.

### Dispute

A dispute records a contested outcome and must identify the responsible domain:

- product issue -> Roastery operational ownership;
- ROSTA fulfillment issue -> ROSTA operational ownership;
- carrier-caused loss/damage/delay -> Carrier primary operational responsibility subject to agreement/evidence/claim rules, while ROSTA owns customer-facing support and coordination;
- payment issue -> ROSTA Finance/payment ownership.

## 10. Completion invariants

An order must not be presented as fully Completed when:

- a required Sub-order is still active;
- a delivery is unconfirmed where confirmation is required;
- an open blocking dispute/incident prevents completion under policy;
- the platform has only a guessed or stale fulfillment state.

## 11. Auditability

Lifecycle-changing events must remain attributable to an actor/system source and timestamp. Physical responsibility changes additionally require Chain of Custody evidence as defined in `fulfillment-model.md`.
