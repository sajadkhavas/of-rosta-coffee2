# Reliability and Observability

Status: ARCH-0.2

## 1. Reliability goal

ROSTA should fail explicitly, locally and recoverably. A provider outage or worker failure must not silently corrupt financial/order/fulfillment truth or turn unknown state into success.

## 2. Correlation

Every request/operation should be traceable through stable identifiers appropriate to the domain:

- request/correlation ID;
- user/organization scope where safe;
- Master Order / Roastery Sub-order ID;
- quote/idempotency key;
- payment/refund attempt/reference;
- settlement/reconciliation IDs;
- shipment/leg/custody/work-item IDs;
- notification/outbox/job ID.

The frontend API client already preserves server `request_id` where returned. Production logs should use the same correlation vocabulary.

## 3. Structured logging

Prefer structured events over free-form dumps.

A log event may contain:

- timestamp/environment/service role;
- level/event code;
- correlation/request ID;
- safe entity IDs;
- duration/status/result class;
- retry attempt/provider name where relevant.

Must not contain:

- raw OTP;
- secrets/tokens/session cookies;
- full card/payment-sensitive evidence;
- unnecessary customer addresses/phones;
- uploaded private source bytes.

## 4. Metrics

Minimum production signals:

### Web/API
- request rate/error rate/latency percentiles;
- 4xx/5xx by route family;
- SSR render latency/failures;
- PHP-FPM/application saturation.

### MySQL/Redis
- connection/slow query/lock pressure;
- disk/storage pressure;
- Redis memory/eviction/connectivity;
- cache/session/queue failure signals.

### Queue/scheduler
- queue depth/oldest-job age;
- job success/retry/failure by class;
- media job latency/resource usage;
- notification outbox pending/failed age;
- scheduler last-success and task duration.

### Commerce/finance
- payment initiation/verification failure rate;
- uncertain/reconciliation case count/age;
- refund/payout pending/failure age;
- settlement release lag;
- inventory reservation expiry anomalies.

### Fulfillment
- seller SLA breaches;
- incident age;
- Hub work-item age/stage;
- custody/tracking gaps;
- carrier exception rates.

## 5. Health endpoints

**TARGET:** distinguish liveness and readiness.

### Liveness
Answers whether the process/runtime is alive. It should not fail because an optional external provider is down.

### Readiness
Answers whether the role can safely serve its critical workload. API readiness may verify configuration plus required MySQL/Redis reachability with bounded checks; external providers are normally reported as dependency/degraded status rather than making every web request unready.

Health responses must not expose secrets, DSNs or internal stack traces.

## 6. Degraded modes

Explicit dependency state is preferred to global failure.

Examples:

- catalog browse can continue during payment provider outage;
- existing authenticated sessions may continue during SMS outage while new OTP delivery is blocked;
- media upload may pause while existing product pages still use available variants;
- carrier automation may fall back to manual-safe operations according to policy;
- optional partner experience outage must not invent completion or block Core unless campaign policy says it is mandatory.

## 7. Timeouts/retries

All external calls and expensive internal operations have bounded timeouts. Retry only failures known to be safe/retryable.

Use exponential/backoff/jitter where appropriate and cap total attempts. Financial unknown outcomes route to reconciliation rather than aggressive blind retry.

## 8. Reconciliation as reliability

ROSTA treats reconciliation as a first-class recovery mechanism for systems that cross trust boundaries:

- payment/refund/provider result versus internal attempt;
- settlement/payout evidence versus payable ledger;
- object-storage bytes versus media metadata;
- carrier tracking/provider event versus internal shipment;
- notification outbox versus delivery attempts.

Recovery creates explicit state/evidence; it does not erase original failures.

## 9. Backups and disaster recovery

**TARGET:** automated versioned backups, restore verification and documented disaster procedures before production acceptance.

Production phase must define measurable RPO/RTO and rehearse:

- fresh bootstrap;
- database restore;
- object/media relationship recovery;
- application rollback/roll-forward;
- restart/reboot;
- provider outage.

A backup that has never been restored in rehearsal is not sufficient evidence of recoverability.

## 10. Alerting

Alert on actionable conditions, not every warning. Priority alerts include:

- site/API unavailable or sustained 5xx;
- database unavailable/storage near full;
- queue/scheduler stalled;
- payment verification/reconciliation anomaly;
- settlement/payout blocked beyond threshold;
- media processing failure spike;
- certificate/critical provider configuration expiry/failure;
- backup/restore verification failure.

Exact thresholds are environment policy and must be tuned from baseline measurements.

## 11. Operational evidence

Release/deployment/smoke/rollback and provider-smoke results must be retained with release SHA, timestamp and operator/tool evidence. Production acceptance is evidence-based, not "it opened once".
