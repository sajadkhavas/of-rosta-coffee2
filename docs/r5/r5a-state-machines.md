# R5A — Authoritative State Machines

> Historical decision note: the acceptance machine below records the original
> R5A proposal. R5H is authoritative in code: new sub-orders start at
> `awaiting_payment`, verified payment commits them to `accepted`, and seller
> inability to fulfil is an incident resolved by an administrator.

This document defines legal transitions for parent orders, roastery sub-orders, grinding services and shipment legs. Unknown or skipped transitions must fail closed.

## 1. Parent order aggregate states

Parent state is derived from children and is never edited directly by a seller.

```text
payment_pending
paid_processing
partially_accepted
fully_accepted
partially_preparing
fully_preparing
partially_dispatched
fully_dispatched
partially_delivered
fully_delivered
partially_rejected
fully_rejected
partially_refunded
fully_refunded
requires_review
```

Rules:

- `fully_delivered` requires every non-refunded sub-order and required service fulfilment to be delivered or completed.
- `partially_delivered` is used when at least one child is delivered and another active child is not.
- A seller rejection of one sub-order must not stop accepted sibling sub-orders.
- `requires_review` is an operational overlay and must not erase underlying child truth.

## 2. Roastery sub-order acceptance machine

```text
awaiting_roastery_acceptance
├── accepted
├── rejected_by_roastery
└── cancelled_by_customer
```

Legal transitions:

| From | To | Actor | Conditions |
|---|---|---|---|
| awaiting_roastery_acceptance | accepted | owning roastery | inventory still reserved; lock acquired |
| awaiting_roastery_acceptance | rejected_by_roastery | owning roastery | reason category required; lock acquired |
| awaiting_roastery_acceptance | cancelled_by_customer | owning customer | lock acquired; refund plan created |

Terminal acceptance states cannot transition to each other.

### Customer cancellation invariant

```text
customer_cancellable = acceptance_status === awaiting_roastery_acceptance
```

After `accepted`, every customer cancellation endpoint returns a domain conflict and makes no state, inventory or financial change.

## 3. Roastery fulfilment machine

This machine begins only after acceptance.

```text
accepted
→ preparing
→ ready_for_dispatch
→ handed_to_carrier_or_hub
→ in_transit_or_received_by_hub
→ delivered_or_hub_handoff_complete
```

Optional roastery grinding path:

```text
accepted
→ preparing
→ queued_for_roastery_grinding
→ ground_by_roastery
→ packed_by_roastery
→ ready_for_dispatch
```

Rules:

- A roastery without grinding capability cannot enter roastery grinding states.
- `ground_by_roastery` requires an approved profile snapshot.
- `handed_to_carrier_or_hub` requires a shipment or handoff record.
- No seller transition may mark a customer delivery without carrier/admin evidence.

## 4. Rosta Hub grinding service machine

```text
requested
→ waiting_for_roastery_dispatch
→ dispatched_to_rosta_hub
→ received_at_rosta_hub
→ quality_check
→ queued_for_grinding
→ grinding_in_progress
→ ground
→ packed_free_by_rosta
→ ready_for_final_dispatch
→ handed_to_carrier
→ in_transit
→ delivered
```

Exception states:

```text
rejected_at_hub
service_blocked
requires_review
```

Rules:

- The service can exist only for a roastery whose grinding capability is unavailable at quote time and order creation time.
- The delivery address must be in an enabled Tehran or Karaj zone.
- `received_at_rosta_hub` records received weight and package condition.
- `ground` records profile version, equipment identifier, operator and completion time.
- `packed_free_by_rosta` always has zero customer packaging charge.
- Service failure after roastery acceptance is an admin incident, not a customer cancellation.

## 5. Shipment leg machine

A customer order may contain several shipment legs.

```text
planned
→ awaiting_pickup
→ picked_up
→ in_transit
→ delivered
```

Exception states:

```text
pickup_failed
 delivery_failed
 lost
 damaged
 requires_review
```

Supported route types:

```text
roastery_to_customer
roastery_to_rosta_hub
rosta_hub_to_customer
```

Each leg owns its carrier, tracking reference, charge allocation, timestamps and customer-visible events.

## 6. Customer-visible aggregate examples

### Two pending roasteries

```text
Parent: paid_processing
A: awaiting_roastery_acceptance — cancellable
B: awaiting_roastery_acceptance — cancellable
```

### One accepted, one pending

```text
Parent: partially_accepted
A: accepted — not cancellable
B: awaiting_roastery_acceptance — cancellable
Parent cancellation: unavailable
```

### One accepted, one seller rejected

```text
Parent: partially_rejected
A: accepted and continues fulfilment
B: rejected_by_roastery and refund pending/completed
```

### Rosta Hub grinding

```text
Roastery sub-order: accepted → handed_to_hub
Service: received_at_rosta_hub → grinding_in_progress
Customer view: product is being ground by Rosta Hub
```

## 7. Transition audit record

Every transition writes an append-only record containing:

```text
aggregate_type
aggregate_id
previous_state
next_state
actor_type
actor_id
occurred_at
request_id
reason_code
customer_title
customer_description
internal_metadata
```

Customer descriptions must not expose internal notes, secrets, personal data of operators or security metadata.

## 8. Concurrency and idempotency

- Acceptance, rejection and customer cancellation lock the same sub-order row.
- Replayed transition requests return the existing result when the idempotency key and payload match.
- A reused idempotency key with a different payload fails.
- Inventory release, refund allocation, seller payable release and notification dispatch are exact-once effects.
- A parent aggregate recalculation occurs after every committed child transition.
