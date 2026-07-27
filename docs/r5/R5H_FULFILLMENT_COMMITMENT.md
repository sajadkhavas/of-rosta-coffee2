# R5H — Fulfilment Commitment, SLA and Exception Incidents

Status: **Implemented — final CI verification pending**
Program branch: `integration/rosta-r5-marketplace`
Product branch: `program/r5h-fulfilment-sla-incidents`

The reviewed product implementation is committed without temporary executor, transfer or source-export assets. Merge remains blocked until all six formal PR gates pass on the final head.

## Superseding decision

R5H supersedes the earlier R5A seller-acceptance and seller-rejection proposal. A roastery joins Rosta under a contractual obligation to fulfil every paid order whose product and inventory were active at checkout.

- Before payment, a new sub-order is `awaiting_payment`; historical `pending_acceptance` records remain readable but are never created by R5H.
- Successful payment automatically commits every sub-order and moves it to `accepted` with `acceptance_status=accepted`.
- The seller never accepts or rejects a paid sub-order manually.
- Customer cancellation after payment is not introduced by R5H.

## Fulfilment SLA

Payment verification snapshots two immutable operational deadlines on every sub-order:

- preparation due time
- handoff-to-carrier due time

The API exposes the contractual commitment, deadlines, SLA state and a dynamically evaluated breach flag. A ten-minute scheduler also persists breaches, emits a customer-safe timeline event and writes an append-only audit record exactly once. Seller operations are limited to:

1. `accepted -> preparing`
2. `preparing -> ready_to_ship`
3. `ready_to_ship -> shipped`

Delivery confirmation remains an administrator or trusted-carrier operation.

## Exception incidents

A seller that cannot fulfil does not reject the order. It reports an Incident with a controlled code, severity and internal description.

Reporting an Incident:

- does not cancel the order
- does not release or restock inventory
- does not reverse settlement allocations
- does not create a refund
- pauses seller status transitions until Rosta resolves it

An administrator may:

- resume fulfilment and extend the SLA; or
- cancel only the affected sub-order, restock its items exactly once, reverse its unpaid allocations and create an exact sub-order refund request.

Accepted, shipped or completed sibling sub-orders continue independently.

## Privacy

Customers see only a safe notice that an operational exception is under review. Incident descriptions, administrator notes and audit metadata remain private to authorised seller/admin surfaces.

## Whole-bean boundary

Fulfilment and incidents never alter product, SKU, roast batch, stock reservation identity or the whole-bean-only product contract.

## Exit markers

```text
ROSTA_R5H_FULFILLMENT_COMMITMENT_COMPLETE
ROSTA_R5H_FULFILLMENT_COMMITMENT_FRONTEND_COMPLETE
```
