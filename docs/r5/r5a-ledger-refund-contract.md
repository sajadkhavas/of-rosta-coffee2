# R5A — Ledger, Settlement and Refund Contract

> Current implementation note: R5H replaces seller rejection with an incident.
> Exact allocation reversal and partial refund occur only through an
> administrator-scoped resolution; the ownership and over-refund invariants in
> this historical contract remain authoritative.

## 1. Principles

1. Customer collection and marketplace settlement are separate concerns.
2. The customer makes one payment for the parent order.
3. Every monetary component is recorded as an immutable allocation line.
4. Every allocation line has an explicit owner, source, tax treatment, refundability and settlement state.
5. No service or shipping amount owned by Rosta may enter a roastery payable balance.
6. Refunds reverse exact original allocations; they do not recalculate current prices.
7. Total refunded amount can never exceed total captured amount.

## 2. Required allocation types

```text
product_gross
roastery_packaging
roastery_grinding
rosta_hub_grinding
rosta_hub_packaging
rosta_route_shipping
roastery_direct_shipping
platform_commission
discount
tax
rounding_adjustment
```

`rosta_hub_packaging` must exist as a zero-value invoice line when the Hub packs a ground item.

## 3. Allocation ownership

| Type | Economic owner | Customer invoice visibility |
|---|---|---|
| product_gross | roastery | required |
| roastery_packaging | roastery | required, including zero |
| roastery_grinding | roastery | required when selected |
| rosta_hub_grinding | Rosta | required when selected |
| rosta_hub_packaging | Rosta | required and displayed as free |
| rosta_route_shipping | Rosta | required when used |
| roastery_direct_shipping | configured transport owner | required when used |
| platform_commission | Rosta | may be settlement-only unless law/business policy requires customer display |
| tax | statutory ledger | required when applicable |
| discount | allocation-specific contra line | required |

## 4. Rosta Hub path

When a roastery cannot grind and the customer selects an eligible Rosta Hub service:

```text
roastery payable source:
- product_gross

Rosta-owned source:
- rosta_hub_grinding
- rosta_route_shipping
- platform_commission

explicit free source:
- rosta_hub_packaging = 0

forbidden source for roastery:
- roastery_packaging for the Hub-routed item = 0
```

The roastery product allocation remains its product revenue source. Existing commission and statutory deductions may reduce the final payable amount, but Rosta service charges never increase it.

## 5. Snapshot contract

At quote acceptance and order creation, every line stores:

```text
allocation_type
owner_type
owner_id
sub_order_id
order_item_id
service_id
shipment_leg_id
quantity
unit_amount
subtotal
 discount_amount
 tax_amount
 total_amount
 currency
 pricing_version
 tax_code
 source_reference
```

Current catalogue, packaging, grinding, shipping or tax configuration cannot alter an existing snapshot.

## 6. Settlement states

```text
held
eligible
scheduled
paid
reversed
requires_review
```

Recommended eligibility boundaries:

- Product and roastery-owned service allocations remain `held` until the configured fulfilment milestone.
- Rosta Hub service allocations are recognised only after the service milestone required by finance policy.
- A rejected or customer-cancelled pending sub-order never becomes seller-payable.
- A disputed or incident-linked allocation enters `requires_review` without mutating the original ledger line.

## 7. Customer cancellation refund

Customer cancellation is legal only before roastery acceptance.

The refund plan includes all captured allocations exclusively attributable to the cancelled sub-order and its unexecuted services:

- product gross;
- roastery packaging;
- roastery grinding;
- Rosta Hub grinding;
- unused Rosta-route shipping;
- applicable allocated tax;
- allocated discount reversal according to the original discount policy.

Sibling sub-orders remain unchanged.

Inventory reservation is released exactly once after cancellation commits.

## 8. Roastery rejection refund

A roastery rejection must produce a partial refund plan with the same exact-allocation rules as cancellation, but the reason and actor are `rejected_by_roastery`.

The system must:

1. prevent seller payable eligibility;
2. release inventory exactly once;
3. reverse or refund unfulfilled service and shipping allocations;
4. preserve accepted sibling sub-orders;
5. update the parent aggregate;
6. create customer-visible refund events.

## 9. Post-acceptance incidents

There is no customer cancellation after acceptance.

An admin may open an incident for operational failure. Incident outcomes may include replacement, manual resolution or refund, but must not change the historical acceptance event or relabel the action as customer cancellation.

Administrative refund execution requires:

- reason category;
- affected allocation lines;
- evidence or internal note;
- operator identity;
- dual control when required by existing finance policy;
- provider reference when money is moved;
- append-only audit records.

## 10. Multi-roastery discount allocation

A parent-level discount must be distributed deterministically across eligible allocation lines before payment. The allocation method must be versioned and stored.

Refunds return the net captured amount attributable to the cancelled or rejected lines. They must not grant more discount value than originally allocated.

## 11. Shipping allocation

Shipping is modelled by shipment leg, not by roastery count alone.

Supported examples:

```text
roastery A → customer
roastery B → customer

roastery A → Rosta Hub
Rosta Hub → customer
```

Each charge must identify its shipment leg and owner. A leg that was never executed is refundable according to the captured allocation and finance policy. An executed leg may require admin incident handling instead of automatic cancellation refund.

## 12. Tax contract

Tax and statutory amounts are calculated per allocation line by the backend using the effective tax classification and date. No fixed tax rate is embedded in this R5A contract.

The customer funds applicable tax. Refunds reverse the exact tax attributable to refunded lines according to authoritative finance rules.

## 13. Reconciliation invariants

```text
captured_parent_total
= sum(captured_allocation_lines)

refunded_parent_total
= sum(refund_lines)

refunded_parent_total <= captured_parent_total

roastery_payable
= eligible_roastery_owned_lines
- commission_deductions
- statutory_deductions
- settled_refund_reversals
```

Every reconciliation mismatch enters `requires_review` and blocks duplicate settlement or refund execution.
