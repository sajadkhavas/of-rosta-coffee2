# ROSTA Production-Ready-Before-Server Contract

Status: authoritative planning and acceptance input for PS7, PS8 and PS9

Reviewed baseline: `integration/rosta-release-candidate@a96d0e05478bc2c61852fdf91bb46da1782030df`

## Objective

The real production server must not become a development, build, configuration-debugging, migration-debugging or integration-testing environment.

The target is **zero-debug server activation**:

> Before touching the real server, one exact immutable release SHA must have already proven the full production topology in a production-like rehearsal. The real server session should be limited to machine provisioning/external secrets, release activation and one deterministic acceptance run.

A successful source build is not `SERVER_READY`.

## 1. Hard launch invariants

A release is not allowed onto the real server unless all applicable gates below are PASS on the same exact candidate SHA.

### Zero source edits on server

Forbidden on production:

- editing application source;
- cherry-picking or committing fixes;
- changing tracked configuration templates by hand;
- patching generated assets;
- manual SQL used to compensate for a broken migration;
- installing a missing package as an emergency code fix;
- changing a service definition only on the server without committing the same definition first.

If any of these is required, deployment stops and the defect returns to the owning source phase.

### Zero build surprises

The exact production artifact/image must be built and verified before server activation.

The real server should not be the first place that runs:

- frontend production compilation;
- Composer dependency resolution;
- lockfile generation;
- image optimization/asset generation;
- application packaging.

Where packaging technology permits, production pulls/copies a verified immutable artifact identified by the frozen SHA/release manifest.

### Zero migration surprises

Every migration sequence must be rehearsed before production on:

1. a clean database;
2. an upgrade database representing the previous accepted release;
3. realistic production-like data volume/constraints where relevant.

Destructive or backwards-incompatible migration behavior must be explicit. Roll-forward/rollback compatibility must be proven before release activation.

### Zero hidden service wiring

Application, worker, scheduler, cache/Redis, database, object storage, reverse proxy, health/readiness endpoints, backup jobs and log paths must be part of the versioned production package. There must be no undocumented server-only service.

## 2. One exact release identity

PS9 freezes exactly one deployable SHA.

The production package must expose and verify:

- source SHA;
- release ID;
- build/package manifest;
- dependency lock identity;
- migration set identity;
- frontend asset identity;
- environment-contract version;
- API/OpenAPI contract version where applicable.

Runtime health/version output must make it possible to prove which release is active without inspecting chat history.

No `final-v2`, `final-fixed`, `candidate-fixed`, ad-hoc server branch or untracked patch is accepted as production identity.

## 3. Required production package before real-server access

PS7 must deliver one versioned production package containing or generating, as applicable:

- immutable application release/artifact or container image;
- frontend SSR/static production artifact;
- environment example/schema and machine-readable validation;
- reverse-proxy configuration template;
- application runtime/service definition;
- queue worker definition;
- scheduler definition;
- backup definition/timer;
- health, liveness and strict readiness checks;
- server preflight command;
- release activation command;
- release verification/smoke command;
- rollback command;
- backup and restore commands;
- release manifest;
- operational runbook;
- secrets/external-provider checklist;
- firewall/port/DNS/TLS checklist;
- disaster and failed-deployment procedure.

The implementation mechanism may be Docker/Compose, systemd-managed immutable release directories, or another PS7-approved architecture, but the acceptance behavior above is mandatory.

## 4. Production-like rehearsal matrix

The same candidate intended for production must pass all applicable rehearsals before server access.

### Fresh bootstrap

From an empty production-like environment:

- create/provision database and Redis equivalents;
- materialize required storage directories/buckets;
- apply migrations;
- start app;
- start worker;
- start scheduler;
- start proxy;
- pass strict readiness;
- pass public smoke tests.

### Upgrade rehearsal

From the previous accepted release:

- capture backup;
- deploy the new immutable release;
- run migrations;
- activate atomically;
- verify app, API, frontend and background services;
- verify existing sessions/data according to the release contract;
- prove no accidental data loss or financial inconsistency.

### Rollback/roll-forward rehearsal

Before production, prove:

- previous release identity is retained;
- activation pointer/symlink is atomic;
- failed health after activation restores the previous application release automatically or through one deterministic command;
- database migration compatibility is understood and documented;
- backup restore works from a real generated backup;
- restored data passes integrity/readiness checks.

### Restart/reboot rehearsal

Simulate process and machine/service restarts and prove:

- app comes back;
- workers resume safely;
- scheduler resumes exactly once as intended;
- no duplicate jobs/financial operations are created;
- services have correct restart policies/dependencies;
- current release identity does not drift.

### Dependency outage rehearsal

Where practical, simulate temporary loss of:

- Redis/cache;
- queue worker;
- object storage/R2;
- external SMS provider;
- payment/provider callbacks;
- database connectivity.

The application must fail closed or degrade according to documented truth boundaries rather than fabricate success.

## 5. Environment and configuration gate

Before production, all required keys must be represented in an authoritative environment contract with:

- required/optional status;
- safe default or explicit absence;
- secret classification;
- accepted format/range;
- production-only constraints;
- cross-domain consistency checks.

The preflight must reject at least:

- `APP_ENV`/debug mismatch;
- placeholder secrets;
- missing app/session/encryption keys;
- wrong database/Redis host or port;
- wrong public API/frontend/CDN origin;
- invalid cookie/session domain;
- invalid CORS/Sanctum/stateful-domain ownership where applicable;
- staging/local URLs in production output/config;
- missing provider credentials for a feature that is declared enabled;
- disabled provider while the corresponding production feature is declared enabled;
- unsafe test/seed flags;
- missing storage bucket/endpoint/region contract;
- missing backup destination/encryption/retention contract.

Environment mistakes are source/preflight failures, not server-debug tasks.

## 6. Network, proxy, domain and TLS gate

Before real-server deployment, versioned proxy configuration must be syntax-validated in CI/rehearsal.

Acceptance covers:

- canonical hostnames;
- HTTP to HTTPS behavior;
- frontend/API routing;
- upload/body/timeouts where required;
- trusted proxy/client IP behavior;
- WebSocket/streaming behavior if used;
- security headers;
- compression/caching boundaries;
- private API cache prevention;
- public media vs private/raw media boundaries;
- SPA/SSR/static asset routing;
- service-worker/PWA routes;
- callback/webhook paths;
- certificate file/path contract;
- renewal/reload runbook.

DNS propagation and issuance of the real certificate may remain external server-day actions, but they must not require application code changes.

## 7. Database and financial gate

Before `SERVER_READY`:

- clean migrations PASS;
- upgrade migrations PASS;
- backup creation PASS;
- checksum/integrity verification PASS;
- restore rehearsal PASS;
- transaction/idempotency tests PASS;
- quote/order/tax/commission conservation PASS;
- refund/payout/reconciliation truth PASS once PS4.2 is integrated;
- production readiness fails closed without required published financial policies;
- no seed invents a real tax/commission/provider rate.

No financial truth is recomputed by the browser or repaired manually after deployment.

## 8. Queue, scheduler and asynchronous work gate

PS6B/PS7 must prove the production topology includes all required workers/timers.

Acceptance includes:

- queue connection contract;
- worker identity and concurrency;
- retry/backoff/timeout policy;
- failed/dead-letter visibility;
- controlled replay policy;
- graceful restart/release rotation;
- scheduler ownership and exactly-once expectations;
- backup timer;
- cleanup/media/notification jobs;
- no duplicate scheduler instances caused by deployment topology;
- readiness/monitoring for stalled workers or excessive failures.

A healthy web process with a dead worker is not a healthy ROSTA release.

## 9. Storage and media gate

Before production:

- R2/S3-compatible configuration validation PASS;
- private raw object access remains denied publicly;
- only sanitized/published media is externally addressable;
- upload/download roundtrip PASS in rehearsal/staging;
- signed/private access boundaries PASS;
- checksum/MIME/size/decode pipeline PASS;
- cleanup and orphan handling PASS;
- storage outage behavior is truthful;
- CDN/public media hostname contract is validated separately from internal acceptance probes.

## 10. Provider gate

External providers must never be faked as production-ready.

Before enabling each production feature, record an explicit verdict for:

- payment gateway/account/callback ownership;
- SMS/OTP provider credentials/template/account readiness;
- object storage credentials/bucket policy;
- mail provider if production email is required;
- carrier/webhook provider where applicable.

Use offline fakes/sandboxes for automated tests, then a bounded real-provider smoke only when credentials/account approval exist.

A provider that is externally pending is `SERVER_BLOCKED_EXTERNAL`, not a reason to modify source on the server.

## 11. Frontend and browser gate

PS6A/PS8A must prove on the final candidate:

- production SSR/client build PASS;
- no broken dynamic imports/assets/fonts;
- canonical/robots/sitemap behavior PASS;
- responsive layouts PASS;
- RTL PASS;
- keyboard/accessibility critical gates PASS;
- checkout/auth/account/seller/admin critical journeys PASS;
- error/loading/empty/offline/reconnect states PASS;
- PWA/service-worker cache boundaries PASS;
- no staging/local API URLs bundled into the client;
- no secrets or private config shipped to browser;
- visual regression for launch-critical routes PASS.

## 12. Security gate

Before production activation:

- dependency audit PASS under approved policy;
- secret scan PASS;
- static/type/lint analysis PASS;
- container/filesystem vulnerability scan when containers/artifacts are used;
- authentication/authorization/IDOR tests PASS;
- CSRF/CORS/cookie/session rules PASS;
- rate limiting PASS;
- webhook signature/replay controls PASS where applicable;
- upload/media security PASS;
- private-cache boundaries PASS;
- log/error redaction PASS;
- production debug/test endpoints disabled;
- least-privilege file/service ownership verified.

## 13. Observability and support gate

Before server access, ROSTA must already define how to answer:

- which release is active?
- is app ready?
- is DB reachable?
- is Redis reachable?
- are workers alive?
- is scheduler alive?
- are failed jobs growing?
- are provider failures growing?
- are media jobs stuck?
- are payment/refund/reconciliation operations inconsistent?
- where are logs and how are secrets redacted?
- when was the last successful backup?

PS6B owns instrumentation; PS7 wires it into the production package; PS8 verifies it.

## 14. Server-day allowed work

The desired real-server session is limited to the following categories:

1. provision/verify OS prerequisites, disk, memory and clock/timezone;
2. install/verify the chosen runtime/container engine and reverse proxy if not already present;
3. create service user/directories with predetermined ownership;
4. inject production secrets/environment values without committing them;
5. provision/verify DB/Redis/storage connectivity;
6. open only required firewall ports;
7. point DNS / issue the real TLS certificate when externally ready;
8. transfer/pull the exact PS9-frozen immutable release;
9. run the versioned preflight;
10. run the versioned activation command;
11. run the versioned post-activation acceptance command;
12. record active SHA/release and backup/rollback identity.

If a source change, dependency change, migration repair, frontend rebuild, service redesign or unplanned Nginx/systemd/Compose edit is required, deployment stops. The fix goes back to GitHub, reruns its owning gates, and produces a new frozen release.

## 15. Target deployment UX

The final PS7 package should reduce production activation to a small, deterministic interface, conceptually similar to:

```text
preflight <release>
activate <verified-release>
verify <active-release>
rollback <previous-release>
```

Exact commands are PS7-owned and must be versioned in the repository. The operator should not need to reconstruct commands from chat history.

## 16. Server touch budget

ROSTA launch target:

- no code/debug iteration on production;
- no dependency/build iteration on production;
- no manual DB repair;
- one planned activation session;
- one deterministic acceptance run;
- immediate rollback path already proven.

External DNS/TLS/provider approval can consume calendar time, but must not create software implementation work on the server.

## 17. Phase ownership for this contract

The remaining pre-server phases consume this contract as follows:

- PS4.2: completes refund/payout/reconciliation financial truth and reversal behavior;
- PS5.3: completes carrier/webhook/manual/admin operational truth;
- PS5.4: completes launch workspaces/KPI composition without mocks;
- PS6B: proves queues, failure handling, logs, metrics, traces and readiness;
- PS6A: freezes frontend/browser/visual/accessibility quality;
- PS7: builds the complete versioned production package and executes full production-like rehearsal without real-server mutation;
- PS8A: independent frontend evidence;
- PS8B: independent backend/finance evidence;
- PS8C: independent infrastructure/deployment/backup/rollback evidence;
- PS9: integrates one candidate, reruns final gates, writes final manifest/tag and may issue `PRE-SERVER GO` / `SERVER_READY` only when all required evidence is green.

A failed PS8 or production-rehearsal gate returns to its owning phase. It is not patched in PS9 and never patched directly on production.

## 18. Launch verdicts

Only these launch verdicts are valid:

- `SERVER_BLOCKED_SOURCE` — source/package defect exists;
- `SERVER_BLOCKED_EXTERNAL` — code/package is proven, but DNS/TLS/provider/server credential/account prerequisite is externally pending;
- `SERVER_READY` — exact frozen release has passed all pre-server gates and only allowed server-day actions remain;
- `ACTIVE_VERIFIED` — the exact frozen release is active on production and the deterministic post-activation acceptance passed.

The goal of PS7–PS9 is to enter the real server only with `SERVER_READY`.