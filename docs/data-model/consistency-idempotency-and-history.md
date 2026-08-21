# Consistency, Idempotency and Historical Evidence

Status: ARCH-0.3 canonical consistency contract

## 1. Transaction authority

MySQL transactions and database constraints are the final guard for critical state changes. Application validation improves errors but does not replace:

- unique constraints;
- foreign keys;
- row locking where races matter;
- transactional commit/rollback;
- idempotency/deduplication records.

Redis locks/cache may coordinate work but may not be the only proof that a financial/order command happened.

## 2. Lock when decisions depend on current mutable state

Use transaction-scoped locking when a command must make a decision from mutable rows that can be changed concurrently, including cases such as:

- payment attempt/order paid transition;
- inventory reservation/commit/release;
- seller/settlement eligibility transitions;
- refund/payout command dedupe;
- ownership/last-owner membership protection;
- queue/outbox claim/dispatch state where multiple workers can compete.

Do not add locks indiscriminately; define lock order to avoid deadlocks and keep transactions bounded.

## 3. Command idempotency

Every externally retried or high-impact command must have an idempotency strategy.

### Already evidenced foundations

- checkout/order idempotency key records;
- payment authority/request state and duplicate callback handling;
- unique notification outbox dedupe keys;
- unique allocation idempotency keys;
- provider/business unique keys and foreign-key constraints.

### Required future coverage

Refund, payout, carrier shipment creation, Growth ledger awards and other future external commands must define their own scope/key/replay behavior in the owning phase.

## 4. Idempotency key contract

A durable idempotency record should bind:

- actor/customer/tenant scope where relevant;
- key;
- canonical request hash or semantic command identity;
- processing/terminal state;
- durable result/reference;
- expiry only after the required retry/replay window.

Same key with a different command body must fail rather than create divergent truth.

## 5. Commit before side effect

Critical internal state must be committed before non-critical or retriable external side effects.

Current architectural pattern:

```text
DB transaction / critical transition
  -> commit durable truth
  -> persist outbox or dispatch after-commit job
  -> retry side effect independently
```

Queue configuration uses `after_commit=true`. Future queued jobs that depend on new database rows must preserve this rule.

## 6. Notification Outbox

The notification outbox is durable delivery intent. Dedupe prevents repeated business events from sending duplicate notifications where the event identity is the same.

Outbox status is not Order truth. If SMS/email delivery fails, the paid Order remains paid; the notification row records/retries communication failure.

## 7. Retry-safe jobs

Every async job must be safe under at-least-once execution. A worker crash after partial work must not duplicate money, stock, shipment or externally billed actions.

A job should use one or more of:

- durable idempotency key;
- compare-and-set/locked state transition;
- provider idempotency/reference;
- unique constraint;
- recorded attempt/result before retry.

`tries`/backoff alone does not make a job idempotent.

## 8. State histories and domain events

ROSTA persists multiple forms of historical evidence. Their intent must remain explicit:

- `order_events` — business transition/evidence across order-related entities;
- order/sub-order status histories — before/after lifecycle transitions;
- shipment events / Hub actions — physical execution evidence;
- inventory movements — stock mutation evidence;
- audit logs — privileged/security/operator evidence;
- financial reconciliation/allocations — monetary evidence.

Do not overwrite historical entries to make current state look cleaner. Corrections should use a compensating/new audited record where the domain requires history.

## 9. Snapshot consistency

Snapshots are written from validated source truth at the moment the owning transaction establishes the contract. Avoid reconstructing historical snapshots later from current Product/Roastery/Address configuration.

If one transaction cannot atomically establish all required state, define the intermediate state and recovery path explicitly.

## 10. Cross-domain orchestration

A cross-domain transaction is justified when partial commit would violate a non-negotiable invariant. Keep its scope narrow.

Example: verified payment transition coordinates order payment truth, inventory commit and automatic fulfillment commitment. An async notification is outside the critical financial/stock transaction and is independently retryable.

Future distributed/provider operations should use durable state + reconciliation rather than holding a database transaction open during long network calls when avoidable.

## 11. Provider callbacks and webhooks

Callbacks/webhooks are untrusted, repeatable external inputs. Handler contract:

1. authenticate/validate provider input where supported;
2. locate durable provider/business reference;
3. lock/read current state;
4. ignore or return the existing result for valid duplicate terminal events;
5. verify authoritative state with provider when required;
6. apply exactly one canonical transition;
7. store normalized/provider evidence;
8. emit outbox/event only once per business transition.

Webhook arrival order must not be assumed unless the provider guarantees it and the integration explicitly enforces that contract.

## 12. Reconciliation as consistency repair

External systems can acknowledge, time out or diverge from local observation. Reconciliation is the supported repair path for money/provider discrepancies.

Do not treat network timeout as definitive provider failure and do not repair production finance with ad-hoc SQL. Persist an ambiguous state/evidence and reconcile.

## 13. Cache consistency

Cache is disposable optimization. Cache invalidation may be event-based/versioned/TTL-based, but deleting Redis must not delete durable order/payment/settlement truth.

No financial command may rely solely on a cached price/permission/state without backend validation against authoritative persistence/policy.

## 14. Scheduler concurrency

Scheduled work uses overlap protection where current configuration provides it. Scheduled commands must still be idempotent because process restart, manual invocation or distributed scheduler mistakes can repeat execution.

## 15. Failure evidence

For important async/provider operations, persist enough evidence to answer:

- what command/event was attempted;
- for which entity;
- with which idempotency/provider reference;
- what state existed before/after;
- whether external outcome is known, failed or ambiguous;
- what retry/reconciliation action is allowed.

If the system cannot answer these questions, the operation is not production-safe.
