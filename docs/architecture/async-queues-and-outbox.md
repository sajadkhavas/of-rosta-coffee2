# Async Queues and Outbox

Status: ARCH-0.2

## 1. Baseline evidence

**BUILT:** Laravel queue defaults to Redis. Redis/database queue connections use `after_commit=true`; failed jobs use the database UUID driver. Baseline queued jobs include OTP delivery and secure media processing. Notification delivery uses a persistent outbox plus scheduled dispatch.

**BUILT:** scheduler currently coordinates reservation expiry, fulfillment SLA checks, settlement release, notification dispatch, media lifecycle cleanup, quote/idempotency pruning and framework maintenance tasks.

## 2. Sync-versus-async decision

Keep an operation synchronous when the customer/domain invariant cannot be established safely without its result.

Use asynchronous execution when:

- the durable intent/state can be committed first;
- retries are acceptable;
- the external side effect is not required inside the committing transaction;
- work is expensive/slow or provider-dependent;
- eventual completion can be observed and reconciled.

Never queue away a missing transactional invariant merely to make an endpoint fast.

## 3. Commit-before-dispatch

Canonical pattern:

```text
DB transaction
  -> write authoritative domain state
  -> write durable outbox/work intent where needed
COMMIT
  -> enqueue/dispatch
  -> worker performs idempotent side effect
  -> records outcome
```

A worker must not observe half-committed order/payment/financial state.

## 4. Job contract

Every material job defines:

- stable job/event identity;
- payload containing identifiers rather than stale full mutable objects where possible;
- timeout;
- retry/backoff policy;
- maximum attempt/dead-letter behavior;
- idempotency/deduplication behavior;
- logging/correlation identifiers;
- failure visibility and operator recovery path.

Provider jobs must classify retryable versus terminal errors.

## 5. Notification outbox

Notification intent is durable before provider delivery.

The outbox should preserve:

- recipient/channel/template/event context;
- status/attempt count;
- scheduled/next-attempt time;
- provider response/reference where safe;
- terminal failure reason;
- correlation/source identifiers.

A provider timeout must not erase the fact that ROSTA intended a notification. Retrying must avoid uncontrolled duplicate messages.

## 6. OTP special rules

OTP is security-sensitive asynchronous work.

- challenge/hash/expiry/attempt/resend/rate-limit truth remains server-side;
- raw OTP is not written to normal logs/audit records;
- delivery failure does not turn an invalid challenge into valid auth;
- provider secrets are server-only;
- queue delay must not silently extend OTP TTL unless policy explicitly does so.

## 7. Media queue

Media processing is CPU/memory/IO heavy and should be isolated from latency-sensitive financial/notification work as volume grows.

Processing must be retry-safe and operate from trusted upload intent/object identifiers. Failed/terminal media states remain observable; orphan cleanup is scheduled.

## 8. Financial async boundaries

Provider payout/refund/settlement automation must not be invented where provider capability is absent.

When an external financial action is asynchronous:

- persist an attempt/request before external action;
- use idempotency/provider references;
- reconcile uncertain outcomes;
- create explicit reversal/adjustment records rather than deleting history;
- dual-control/manual workflows are valid production architecture when automation is not officially supported.

## 9. Scheduled jobs

Scheduled state transitions are application operations, not cron scripts that directly alter tables.

Rules:

- call domain services/commands;
- prevent overlapping where required;
- be safe after a missed run;
- process bounded batches;
- expose last-success/failure/lag metrics in production;
- maintain timezone semantics explicitly.

## 10. Queue prioritization target

**TARGET, not required immediately:** when measurable contention appears, split named queues such as:

```text
critical-finance
notifications
media
maintenance
```

Do not create queue topology complexity without workload evidence. The first launch may use minimal workers with bounded concurrency, while media processing must remain constrained to protect web/API memory.

## 11. Failure recovery

Operators need documented ways to inspect failed jobs/outbox entries, identify cause, retry only safe work and reconcile external uncertainty. Blind bulk retries of payments/refunds/payouts are prohibited.
