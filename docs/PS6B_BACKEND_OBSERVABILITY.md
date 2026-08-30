# PS6B — Backend Refactor, Queue Reliability & Observability

Status: **VERIFIED ACCEPTANCE CANDIDATE**. Implementation head `04157e3682a72957eff10184a59fd4143ab62c1d` passed the complete required repository workflow set. This evidence-registration head must pass the same set before PR #92 can be merged.

Baseline: `integration/rosta-release-candidate@52714ea03e385ada601b308184cecb6bbc4f6009`.

Branch: `phase/rosta-ps6b-backend-observability`.

PR: `#92 — PS6B: Backend refactor, queue reliability and observability`.

## Scope and behavior-preserving boundary

PS6B adds operational instrumentation around already accepted business behavior. It does not change checkout, payment, refund, settlement, carrier, fulfillment, seller or admin state machines. The existing `SendOtpCode` and `ProcessMediaUpload` retry/failure semantics remain authoritative and are characterized rather than rewritten.

Refactor/instrumentation map:

- queue lifecycle observation is isolated in `ObservabilityServiceProvider` + `QueueTelemetry`;
- public readiness consumes the narrow `QueueRuntimeHealth` service and exposes only a boolean `queue_runtime` check;
- structured operational events are sanitized before logging by `OperationalContextRedactor`;
- dead-letter health reads only aggregate metadata from `failed_jobs`; serialized payloads and exception bodies are never exposed by the health snapshot;
- thresholds and queue names live in `config/observability.php` so PS7 can materialize environment-specific values without source edits.

## Queue reliability contract

Laravel's accepted queue contract remains:

- Redis is the production-default queue backend;
- queued writes use `after_commit=true`;
- Redis `retry_after` remains greater than the timeout of the accepted OTP/media jobs;
- `failed_jobs` uses the UUID database failed-job provider as the durable dead-letter record;
- OTP retains four worker attempts plus its existing provider-aware retry/unknown-outcome logic;
- media retains one queue attempt and its existing domain-owned failure recording, avoiding automatic duplicate processing;
- Production using the synchronous queue driver is reported not ready.

The PS6B characterization suite guards these invariants so observability work cannot silently alter delivery behavior.

## Queue health/readiness

`QueueRuntimeHealth` reports only:

- configured driver;
- queue backend availability;
- existence of the failed-job table;
- failed-job count;
- age of the oldest failed job;
- depth of configured queue names when the backend supports it;
- `degraded` and `ready` booleans.

It never returns queue payloads, serialized commands, exception text, OTPs, destinations or provider credentials. `/health/ready` consumes only `snapshot()['ready']`, keeping the public response generic.

A backlog can be operationally degraded without making the backend connectivity check false. Production alerting can therefore distinguish an unavailable queue backend from an elevated backlog.

## Structured logs, metrics and traces

`QueueTelemetry` emits three structured event families:

| Event | Metric catalog name | Safe dimensions |
|---|---|---|
| `queue.processing` | `rosta.queue.processing.total` | connection, queue, job class, attempt, trace id |
| `queue.processed` | `rosta.queue.processed.total` | same + duration_ms |
| `queue.failed` | `rosta.queue.failed.total` | same + exception class only |

The trace id is an ephemeral correlation identifier for a worker lifecycle. It is not a customer/session/payment identifier and no job payload is read to build it.

`OperationalContextRedactor` recursively removes sensitive keys and also redacts embedded bearer credentials, token-like key/value pairs, email addresses and Iranian mobile numbers before the log channel receives the context.

PS6B intentionally does not pretend that a Prometheus/OpenTelemetry/SaaS backend is already deployed. PS7 owns wiring these structured events into the selected production monitoring sink and alert delivery path.

## Query and benchmark evidence

The queue-health characterization test inserts an intentionally secret-bearing failed-job payload and proves the snapshot never returns it. The test also measures database activity around the snapshot and enforces a bounded query budget of at most six statements, including schema inspection, failed count and oldest-failure lookup. Redis queue depth requires one `LLEN` per configured queue and does not deserialize jobs.

No new N+1 query is added to customer, seller, checkout or finance request paths because queue health is isolated to health/operational execution.

## Configuration / PS7 wiring needs

PS7 must materialize and review these values per environment:

- `ROSTA_OBSERVABILITY_LOG_CHANNEL`
- `ROSTA_OBSERVABILITY_QUEUES`
- `ROSTA_MAX_FAILED_JOBS`
- `ROSTA_MAX_FAILED_JOB_AGE_SECONDS`
- `ROSTA_MAX_QUEUE_DEPTH`

PS7 also owns the real log/metric sink, retention, dashboards, alert routing, worker supervisor configuration and proof that an injected queue failure produces an alert. Those runtime facts are not claimed by PS6B source acceptance.

## Permanent acceptance gate

`composer audit:ps6b` verifies that:

- the observability provider is registered;
- queue before/after/failing lifecycle hooks exist;
- queue telemetry contains processing/success/failure metrics and duration evidence;
- queue health has sync/Redis handling, dead-letter age and queue depth thresholds;
- the public readiness check includes generic queue runtime readiness;
- queue configuration preserves retry/after-commit/database-UUID failure contracts;
- recursive redaction and PS6B characterization tests remain present;
- queue telemetry never reads raw serialized payloads.

The script is registered in the permanent `composer check` chain and must not be removed or bypassed by later phases.

## Verified implementation evidence

Exact implementation head: `04157e3682a72957eff10184a59fd4143ab62c1d`.

| Required workflow | Run | Result |
|---|---:|---|
| CI | 852 | PASS |
| Backend CI | 561 | PASS |
| PS1 Backend Wrapper CI | 103 | PASS |
| Full-stack Integration CI | 406 | PASS |
| Browser Acceptance CI | 394 | PASS |
| R3 Final Gate | 376 | PASS |
| R4 Staging Package CI | 356 | PASS |

Backend CI independently passed clean MySQL migrations, strict readiness, Redis runtime contracts, every backend audit, all tests, PHPStan, Pint and the aggregate Composer gate. R3 passed complete backend/frontend gates and integrated browser journeys. R4 passed the immutable hosted staging-package rehearsal.

The first candidate exposed a Pint-only style defect in the new PS6B audit script. The script was reformatted without weakening the audit; the implementation head above then passed the full workflow set.

## Final acceptance requirements

1. `cd backend && composer check` — **PASS on implementation head**.
2. Clean MySQL migrations and Redis readiness/queue checks — **PASS on implementation head**.
3. PS6B characterization and query-budget tests — **PASS on implementation head**.
4. Static analysis and Pint without suppressions — **PASS on implementation head**.
5. Permanent `audit:ps6b` independently and in aggregate — **PASS on implementation head**.
6. No secret-bearing payload or exception body exposed by queue health/telemetry — **PASS by characterization + audit**.
7. Existing business-state tests remain green — **PASS on implementation head**.
8. All seven repository workflows on one exact head — **PASS on implementation head; final registration head pending rerun**.
9. Normal merge commit — **PENDING until this evidence-registration head is green**.

## Boundary to later phases

PS6A owns frontend quality freeze. PS7 owns production deployment materialization and real monitoring/worker/alert wiring. PS8B later independently audits backend/security/finance behavior on the shared frozen candidate; PS6B does not pre-claim that independent verdict.
