# R5I — Delivery Confirmation, Settlement Release and Roastery Payouts

Status: **Complete — final merge gates pending**
Program branch: `integration/rosta-r5-marketplace`
Product branch: `program/r5i-delivery-settlement-payouts`

The reviewed implementation is committed without temporary source-export, executor or payload files. Merge remains blocked until all six formal gates pass on this final product head.

## Purpose

Complete the marketplace lifecycle after shipment without weakening R5H fulfilment commitments or the whole-bean inventory boundary.

## Delivery contract

- `shipped` never makes a roastery allocation payable.
- Each shipment leg owns an independent tracking and delivery lifecycle.
- For a direct route, delivery of the final leg may complete the sub-order.
- For a Rosta Hub route, delivery of `roastery_to_rosta_hub` never means customer delivery; only the final `rosta_hub_to_customer` leg can do so.
- Delivery confirmation is idempotent and attributable to a trusted carrier callback, administrator or authenticated customer.
- Proof of delivery is encrypted at rest, private by default and exposed only through safe metadata to the customer.

## Dispute hold contract

- Final customer delivery snapshots a dispute deadline on the affected sub-order.
- Roastery-owned held allocations become eligible only after the deadline passes.
- Open fulfilment incidents, refunds, reversals, loss, damage or explicit settlement holds block release.
- Healthy sibling sub-orders are evaluated independently.
- A ten-minute scheduled release worker is idempotent and does not release Rosta-owned allocations.

## Settlement and payout contract

- Eligible allocations may be attached to exactly one settlement batch.
- Rosta-owned grinding, Hub packaging and Hub shipping allocations never enter a roastery payout batch.
- A settlement batch snapshots gross, discount, tax and net totals in the allocation currency.
- Payout transitions are idempotent and auditable: `pending -> processing -> paid|failed`.
- A failed payout can be retried on the same immutable batch.
- Paid allocations cannot be silently reversed; reconciliation is explicit.

## Delivered surfaces

1. Delivery and encrypted proof-of-delivery persistence
2. Direct and multi-leg Rosta Hub delivery confirmation
3. Dispute-window and allocation-release worker
4. Settlement batch creation and payout processing
5. Customer order delivery confirmation and safe timeline
6. Seller settlement balances and payout history
7. Administrator delivery, hold, batching and payout controls
8. Permanent backend/frontend audits, feature tests and browser acceptance

## Whole-bean boundary

R5I does not create grinding inventory, Hub SKUs, grind variants or any post-payment mutation of product, variant, roast-batch or reservation identity.

## Exit markers

```text
ROSTA_R5I_DELIVERY_SETTLEMENT_COMPLETE
ROSTA_R5I_DELIVERY_SETTLEMENT_FRONTEND_COMPLETE
```

## Exit gates

The phase merges only after all six formal gates pass on this clean final head:

- CI
- Backend CI
- Full-stack Integration CI
- Browser Acceptance CI
- R3 Final Gate
- R4 Staging Package CI
