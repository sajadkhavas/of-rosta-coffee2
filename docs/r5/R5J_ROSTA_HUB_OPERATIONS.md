# R5J — Rosta Hub Operations and Chain of Custody

Status: **Complete**
Program branch: `integration/rosta-r5-marketplace`
Product branch: `program/r5j-rosta-hub-operations`
Verification candidate: the final product head must contain no temporary workflow, payload or diagnostic source files.

## Purpose

Complete the operational lifecycle for Rosta Hub grinding orders after R5G routing and R5I delivery/settlement, without turning grinding into an inventory dimension.

## Operational contract

- only order-item services with `provider_type=rosta_hub` enter Hub operations
- the inbound `roastery_to_rosta_hub` leg must be delivered before Hub receipt
- Hub receipt, operator assignment, grinding, quality control, packaging and outbound handoff are explicit append-only transitions
- every transition is idempotent, actor-attributed and race-safe
- one active operator assignment exists per Hub work item
- a failed quality check returns the work item to a controlled rework state and never mutates product, SKU, roast-batch or stock identity
- the outbound `rosta_hub_to_customer` leg cannot be handed off before packaging passes quality control
- incident and cancellation rules from R5H/R5I remain authoritative

## Persistence

- one Hub work item per eligible Hub grinding order-item service
- immutable snapshots for Hub, profile, whole-bean weight, quantity and route identifiers
- operator assignment history and processing timestamps
- private processing evidence with safe public metadata only
- append-only Hub events linked to the parent order, sub-order, service and shipment legs

## Surfaces

- administrator Hub queue with filters, assignment and controlled transitions
- assigned operator workspace with least-privilege actions
- customer order timeline showing safe Hub progress labels
- roastery visibility limited to inbound handoff and receipt; R5K enforces this
  boundary in the API resource, event feed, seller UI and permanent tests so no
  private Hub evidence, operator identity or internal processing timestamp leaks

## Boundaries

- whole-bean inventory, reservation and stock-ledger identity remain unchanged
- Hub grinding, packaging and route allocations remain Rosta-owned and excluded from roastery payout batches
- no customer or browser request may supply operator identity, fee, capacity, settlement owner or transition timestamps
- no transition may silently release settlement, refund money or cancel sibling sub-orders

## Verification markers

- `ROSTA_R5J_HUB_OPERATIONS_COMPLETE`
- `ROSTA_R5J_HUB_OPERATIONS_FRONTEND_COMPLETE`

## Exit gates

CI, Backend CI, Full-stack Integration CI, Browser Acceptance CI, R3 Final Gate and R4 Staging Package CI must all pass on one clean final head before merge.
