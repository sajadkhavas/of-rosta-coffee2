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
  -> Paid / Payment Verified
  -> Seller Fulfillment Commitment (automatic for each valid paid Sub-order)
  -> Preparing
  -> Ready for Fulfillment/Handoff
  -> Fulfillment execution according to the snapshotted plan
       -> Direct Fulfillment
       OR
       -> ROSTA Fulfillment
  -> Optional Grinding execution where requested
  -> QC where applicable
  -> Packaging where applicable
  -> Shipment / In Transit
  -> Delivered
  -> Completed
```

There is **no normal post-payment seller accept/reject step**. Once a valid paid Sub-order is committed to a Roastery under the marketplace/availability rules, the seller must prepare and hand it off according to its fulfillment obligation.

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

### Paid / Payment Verified

Payment has been verified according to the payment contract. Seller Sub-orders created from that valid payment are fulfillment commitments, not invitations that sellers may routinely reject.

### In Fulfillment

At least one committed Sub-order is progressing through seller preparation, handoff, hub processing or transport and the Master Order is not fully terminal.

### Partially Delivered / Partially Resolved

Different Sub-orders may reach different terminal states. The customer-facing aggregate must preserve that partial truth rather than forcing all siblings into one state.

### Completed

All required Sub-orders have reached the policy-defined successful terminal state and any required completion conditions have passed.

## 4. Roastery Sub-order lifecycle

Each valid paid Sub-order independently tracks a fulfillment commitment such as:

```text
Committed
  -> Preparing
  -> Ready for Fulfillment/Handoff
  -> Handoff / Dispatch
  -> Shipped / In Transit
  -> Delivered
  -> Completed
```

The fulfillment plan may require Direct Fulfillment or handoff into ROSTA Fulfillment.

A seller operational problem is reported as an **incident**. It does not create a normal seller cancellation/rejection path and does not silently mutate inventory, settlement, sibling Sub-orders or refund state.

## 5. Seller incident and authorized exception model

A Roastery that cannot perform a committed obligation must report an incident with truthful reason/evidence according to policy.

Examples may include:

- unexpected inventory/quality issue discovered after commitment;
- equipment or facility incident;
- inability to meet preparation/handoff obligation;
- other seller-controlled exception recognized by policy.

Incident reporting:

- does not by itself cancel the Sub-order;
- does not by itself issue a refund;
- does not by itself release inventory or settlement;
- must be auditable and attributable.

Where policy permits, an authorized ROSTA admin/operations workflow may resolve the affected Sub-order through scoped cancellation, refund, inventory release, rerouting or another approved exception action. Healthy sibling Sub-orders remain unchanged.

## 6. Fulfillment plan

Fulfillment mode/plan is determined by the authoritative quote/order/routing policy and snapshotted for the Sub-order. It is not a discretionary post-payment seller acceptance decision.

### Direct Fulfillment

Roastery retains operational responsibility for preparation, assigned seller services, packing, dispatch and carrier handoff.

### ROSTA Fulfillment

Roastery prepares the goods for handoff to ROSTA. Once a valid Chain of Custody receipt is recorded, ROSTA assumes responsibility for the contracted hub stages.

A launch operating policy may centralize all or most eligible Sub-orders through ROSTA Fulfillment while the platform continues to support Direct Fulfillment as a distinct capability.

## 7. Grinding lifecycle

Grinding is never a product variant.

When requested:

```text
Order Item: whole-bean coffee
Order Item Service: Grinding
Provider: Roastery OR ROSTA Hub
```

The service has its own execution truth and may have its own price, provider, status, evidence and failure reason without creating duplicate inventory SKUs for the same coffee solely because of grind choice.

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

### Cancellation

Cancellation is a policy/authorized exception outcome, not a normal seller accept/reject action after payment. Cancellation of one Sub-order must not silently cancel healthy sibling Sub-orders.

### Refund

Refund is a financial consequence and must be represented by the financial system. Fulfillment state or incident status alone must never be treated as proof that money was refunded.

### Return

Return is a post-delivery/post-dispatch physical workflow whose eligibility and result are policy-driven. Return and Refund are distinct concepts.

### Dispute

A dispute records a contested outcome and identifies the responsible domain:

- product issue -> Roastery operational investigation/input;
- ROSTA fulfillment issue -> ROSTA operational ownership;
- carrier-caused loss/damage/delay -> Carrier primary operational responsibility subject to agreement/evidence/claim rules, while ROSTA owns customer-facing support and coordination;
- payment issue -> ROSTA Finance/payment ownership.

Customer remedy, refund/replacement and legal liability remain policy/contract/applicable-law driven rather than being inferred solely from the operational owner label.

## 10. Completion invariants

An order must not be presented as fully Completed when:

- a required Sub-order is still active;
- a delivery is unconfirmed where confirmation is required;
- an open blocking dispute/incident prevents completion under policy;
- the platform has only a guessed or stale fulfillment state.

## 11. Auditability

Lifecycle-changing events must remain attributable to an actor/system source and timestamp. Physical responsibility changes additionally require Chain of Custody evidence as defined in `fulfillment-model.md`.
