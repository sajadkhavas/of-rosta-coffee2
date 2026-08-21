# Inventory, Fulfillment and Chain-of-Custody Model

Status: ARCH-0.3 canonical operational data contract

## 1. Inventory truth

The authoritative sellable inventory identity is the whole-bean `ProductVariant` (for example, a weight/SKU). Grind selection is not a stock-bearing variant.

Inventory persistence separates:

- current counters (`inventory_stocks`);
- temporary reservations (`inventory_reservations`);
- movement/restock evidence (`inventory_movements` and related restock records).

All stock-changing commands must flow through the inventory/checkout services that preserve locking, reservation and history semantics. Controllers, seller UIs and jobs must not directly decrement/increment stock columns.

## 2. Reservation lifecycle

A quote may reserve stock for a bounded time. A reservation is not a sale.

Canonical states/actions are conceptually:

```text
available stock
  -> reserve for quote
  -> commit when paid/order contract requires it
     OR
  -> release/expire when quote/order path does not complete
```

Reservation expiry is scheduled and must be idempotent. The system must never create negative available stock through racing quote/order operations.

## 3. Paid seller commitment

A verified paid Sub-order becomes a fulfillment commitment automatically. The persisted commitment/SLA fields record operational deadlines and milestones.

Canonical seller path:

```text
paid
 -> preparing
 -> ready
 -> evidenced handoff
 -> shipment/delivery progression
```

Seller inability to fulfill is represented by a `fulfillment_incident` and an authorized exception/remedy workflow. New code must not rely on legacy `acceptance_status` to recreate a routine seller rejection path.

## 4. Direct Fulfillment

For Direct Fulfillment, seller-controlled physical stages continue until evidenced carrier handoff. Persisted data must support:

- seller preparation/SLA milestones;
- packaging/service completion where applicable;
- shipment/leg creation;
- tracking evidence;
- incidents and internal operational evidence;
- carrier handoff/delivery evidence.

ROSTA remains the customer-facing orchestration layer but must not falsify physical custody before evidence exists.

## 5. ROSTA Hub Fulfillment

ROSTA Hub operations are represented as explicit operational work rather than a boolean flag.

Current foundation includes Hub/routing eligibility plus `hub_work_items` and `hub_work_item_actions` supporting stages such as:

```text
Roastery preparation
 -> inbound handoff / transport
 -> Hub receipt
 -> operator assignment
 -> optional grinding
 -> QC
 -> rework when required
 -> ROSTA packaging
 -> outbound handoff
 -> carrier/local delivery
 -> customer
```

A launch policy may route all or most eligible orders through a centralized Hub, but the database model must continue to support both Direct and Hub fulfillment unless a future reviewed migration explicitly changes the platform capability.

## 6. Shipment legs

`shipment_legs` is the canonical multi-leg concept for routes that require more than one physical transport segment. A leg carries, among other evidence:

- order/Sub-order relationship;
- optional service relation;
- route type and sequence;
- status;
- carrier/tracking evidence;
- charge-owner semantics and money evidence;
- origin/destination snapshots;
- planned/pickup/delivery timestamps.

The unique order/sequence relationship provides ordering of legs. New carrier integrations should extend provider evidence around this model instead of creating carrier-specific shipment tables as Core truth.

## 7. Legacy `shipments` versus `shipment_legs`

The repository contains both legacy/general `shipments`/`shipment_events` and newer multi-leg `shipment_legs` support, including a compatibility reference between them.

Rule:

- do not create a third parallel shipment truth;
- new multi-leg capability should prefer the canonical leg model and explicit compatibility mapping where existing surfaces still consume legacy shipments;
- removal/consolidation requires a dedicated migration after usage analysis, API compatibility review and data backfill evidence.

This is tracked as evolution debt, not silently resolved in ARCH-0.3.

## 8. Chain of Custody

Physical responsibility changes only when the relevant handoff/receipt/action is evidenced. A valid custody record should identify enough context to establish:

- item/work/shipment reference;
- previous and next operational party/stage where applicable;
- actor/operator/system source;
- time;
- condition/quantity where relevant;
- evidence/notes/reason when exceptional.

A planned route or status prediction is not custody evidence.

## 9. Grinding work and QC

When grinding provider is ROSTA Hub, the Order Item Service remains the commercial/service record while Hub work/actions hold execution evidence.

The system must preserve:

- requested grind profile/version;
- assigned provider;
- service state;
- actual work/QC/rework evidence;
- immutable fee/pricing snapshot from purchase time.

Operational execution must never mutate product inventory identity into a ground-coffee variant.

## 10. Fulfillment incidents

`fulfillment_incidents` is the canonical seller/operations exception record. An incident may capture type, description, evidence, lifecycle status and authorized resolution action.

Incident existence does not itself mean:

- the order is cancelled;
- a refund is approved;
- seller settlement is reversed;
- carrier liability is proven.

Those transitions belong to their owning policy/financial services and must reference evidence.

## 11. Delivery confirmation and settlement eligibility

Delivery evidence may be an input to settlement eligibility, but fulfillment does not write payout truth directly. Settlement services evaluate eligible allocations according to financial policy and evidence.

Likewise, a delivery failure can trigger investigation/remedy eligibility but cannot directly manufacture a provider refund result.

## 12. Carrier boundary

Carrier-specific status codes/payloads are external evidence. The canonical system should normalize them into ROSTA shipment/leg events while retaining sufficient raw/provider reference for support/reconciliation where policy permits.

Carrier API downtime must not corrupt Core state. Manual/provider-fallback operations must still produce canonical shipment/custody evidence.

## 13. Data retention and evidence

Fulfillment/custody evidence can be dispute-, finance- or support-relevant. Retention must therefore be policy-driven. Do not delete operational history merely because the current shipment is delivered; do not retain sensitive provider/customer payloads indefinitely without purpose.
