# Data Storage and Consistency

Status: ARCH-0.2

## 1. Storage roles

ROSTA uses different storage technologies for different consistency needs:

| Store | Role | Durable business authority? |
|---|---|---|
| MySQL | transactional relational state | Yes |
| Redis | cache, session, queue transport, locks/rate limits | No, except explicitly reconstructible operational state |
| S3-compatible/R2 | media/object bytes | Bytes yes; ownership/lifecycle metadata remains in MySQL |
| application release filesystem | immutable code/build assets/log-local staging where configured | No customer/business media authority |

## 2. MySQL authority

MySQL is the authoritative store for orders, payments, financial allocations, settlements, inventory, fulfillment/custody, persistent notification intents, media metadata and audit evidence represented in the domain model.

Rules:

- all production schema evolution is through versioned migrations;
- application writes use domain/application services, not direct table manipulation from unrelated modules;
- foreign keys/unique constraints/checks/indexes should reinforce high-value invariants where practical;
- concurrency-sensitive transitions use transactions plus row/atomic locking or compare-and-set semantics;
- timestamps/event history remain attributable and must not be silently rewritten to hide incidents.

## 3. Money representation

Monetary values use integer units and explicit currency/context. Floating-point arithmetic is not accepted for authoritative money.

Financial records distinguish at least conceptually:

```text
customer collection / GMV
seller gross allocation
ROSTA commission/service revenue
seller payable
shipping/carrier allocation
grinding/fulfillment/packaging allocation
discount funding
VAT/tax lines when policy requires
refund/reversal
partner/growth payable
settlement/payout
```

One aggregate `total` cannot replace allocation truth.

## 4. Snapshot rule

Mutable catalog/policy data must not rewrite historical orders.

At commitment time, persist/snapshot the data necessary to reproduce the commercial decision, such as:

- product/variant identifiers and customer-facing labels;
- unit price/quantity;
- seller identity;
- service provider/fee/instructions;
- fulfillment plan/routing context;
- packaging/shipping quote components;
- discount/promotion funding;
- commission/tax/policy version identifiers where required.

## 5. Idempotency

**BUILT:** order/payment idempotency records exist in the baseline.

Idempotency is mandatory for externally retried/high-impact operations including:

- order creation;
- payment initiation/verification/callback processing;
- refund initiation/reconciliation;
- settlement/payout actions;
- provider webhook/event handling;
- growth commission creation;
- notification side effects where duplicate delivery is material.

An idempotency key is scoped to actor/operation/context and has a durable outcome or safely repeatable state.

## 6. Inventory consistency

Inventory reservation/commitment/release must be concurrency-safe. Client-visible availability is advisory until the server establishes a reservation/commitment.

Whole-bean stock remains the single inventory truth even if the customer requests grinding. Grinding capacity/service availability is evaluated separately.

## 7. Order consistency

One Master Order may contain multiple Roastery Sub-orders. A failure/incident/refund affecting one Sub-order must not automatically mutate sibling truth.

Aggregate customer state is derived from child states by explicit policy, not by copying one child status to the parent.

## 8. Payment/finance consistency

Provider return/callback state is evidence requiring verification. Internal payment attempt state, financial allocation and order fulfillment are independently persisted.

Reconciliation exists for disagreement between internal truth and external/provider/bank evidence. Reconciliation never means overwriting history without an adjustment/audit trail.

## 9. Fulfillment/custody consistency

Shipment and custody transitions are append/evidence oriented. A status update claiming a handoff must identify the applicable shipment/sub-order and evidence/actor/time according to policy.

Hub work items and shipment legs must remain linkable to the originating seller Sub-order and order item/service.

## 10. Redis failure semantics

Cache misses fall back to durable sources.

Session loss may require reauthentication but must not corrupt orders or financial records.

Queue loss/outage must be detected; critical business state is committed before dispatch because baseline queue connections use `after_commit=true`. Jobs must be safe to retry after worker crash.

Redis flush/restart must not delete the only evidence of a completed payment, settlement, custody handoff or notification intent.

## 11. Object storage consistency

Media lifecycle is two-system consistency:

```text
MySQL upload/asset metadata
       +
object bytes/derived variants in R2/S3
```

Use explicit processing states and cleanup/reconciliation for orphaned/failed objects. A presigned upload completion is not accepted solely from client metadata; server-side byte verification/processing establishes trusted asset state.

## 12. Backup/restore boundary

Backup design must cover MySQL plus the object-storage metadata/object retention relationship and required configuration/secrets inventory. Redis is handled according to its operational role.

Exact RPO/RTO and restore rehearsal are finalized in production/deployment phases; PS0.2 requires that all durable truth be identifiable so those guarantees can be tested.
