# Runtime Topology

Status: ARCH-0.2

## 1. Current runtime shape

ROSTA is designed as a small number of deployable runtime roles backed by shared durable services, not as a fleet of microservices.

```text
Internet
  -> Reverse Proxy / TLS
      -> Frontend SSR (Node/TanStack Start)
      -> Laravel API (PHP-FPM/application runtime)
          -> MySQL
          -> Redis
          -> S3-compatible object storage / R2
          -> Queue worker(s)
          -> Scheduler
          -> External providers
```

The exact process manager/proxy implementation belongs to deployment phases, but the logical roles are stable.

## 2. Frontend SSR role

**BUILT:** TanStack Start application includes `src/server.ts`, `src/start.ts`, route generation and server/client routing. It is not contracted as a static-only SPA.

Responsibilities:

- SSR/public rendering and SEO-compatible route output;
- browser application bootstrapping;
- UI state/query orchestration;
- calls to backend API through typed/validated client modules;
- customer-safe error handling and request correlation.

It does not own transactional money/inventory/order truth.

## 3. Laravel API role

**BUILT:** Laravel 13 application with service modules, models, controllers, provider managers, contracts and scheduled tasks.

Responsibilities:

- authentication and authorization;
- seller/customer/admin scoping;
- validation and idempotency;
- quote/order/payment/finance invariants;
- fulfillment/shipment/chain-of-custody state;
- persistent provider evidence;
- API response contracts.

## 4. MySQL

**BUILT/TARGET:** Primary durable relational store for business records, job failure/batch metadata and transactional consistency.

Rules:

- critical business transitions use database transactions/locking where concurrency requires it;
- financial/order truth cannot depend on cache survival;
- schema changes are repository migrations, never ad-hoc production SQL repair.

## 5. Redis

**BUILT:** defaults in baseline configuration use Redis for cache, session and queue. Queue configuration uses `after_commit=true` and failed jobs are persisted in the database.

Redis roles:

- cache;
- session backing;
- queue transport;
- distributed locks/rate-limit state where needed.

Redis is not the sole durable ledger/order store.

## 6. Queue workers

**BUILT:** queued OTP and media processing exist; additional background work may use the same runtime boundary.

Worker rules:

- separate from web request processes;
- bounded concurrency;
- retry/backoff/timeout configured per workload;
- idempotent or deduplicated side effects;
- failed jobs visible and recoverable;
- expensive media work isolated so it cannot starve financial/customer-critical jobs.

**TARGET:** use named queues/priorities when workload volume makes contention measurable, rather than adding infrastructure prematurely.

## 7. Scheduler

**BUILT:** scheduler runs reservation expiry, fulfillment SLA monitoring, settlement release, notification dispatch, media expiry/cleanup, quote/idempotency pruning and Laravel maintenance commands.

Scheduler rules:

- one logical scheduler leader per environment;
- `withoutOverlapping` or equivalent guards for scheduled jobs that must not overlap;
- a missed scheduler run must be observable;
- time-based financial/settlement transitions remain auditable and idempotent.

## 8. Object storage

**BUILT:** S3-compatible disk configuration supports endpoint/bucket/public URL and is suitable for Cloudflare R2. Secure media processing and queued media jobs exist.

Production object bytes should live outside the application release filesystem. Releases are disposable; media is not.

## 9. Initial production sizing philosophy

**TARGET:** begin with a lean single-VPS application topology where frontend SSR, reverse proxy, PHP application, worker and scheduler can coexist if capacity measurements permit, while MySQL/Redis may initially share the host under strict resource limits. R2 remains external.

This is a launch topology, not a permanent scaling ceiling.

Scale triggers should be evidence-based:

- sustained CPU/memory/IO pressure;
- database contention/latency;
- queue lag;
- SSR saturation;
- operational isolation requirements;
- availability/RPO/RTO requirements.

## 10. Scale-out order

Preferred evolution:

1. increase/segregate worker capacity;
2. isolate database and/or Redis when resource/availability evidence justifies it;
3. horizontally scale SSR/API behind proxy/load balancer;
4. introduce replicas/specialized services only for measured needs.

Do not split domains into microservices merely because the product roadmap grows.
