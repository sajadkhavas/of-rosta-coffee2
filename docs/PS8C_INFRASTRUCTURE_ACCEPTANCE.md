# PS8C — Infrastructure Acceptance Audit

Status: **ACCEPTED / INTEGRATED** on `integration/rosta-release-candidate@6631c68dda8036c3e5da95fe1da3035b72d07f81`.

Baseline: `integration/rosta-release-candidate@75c6b6771608a7e31c3ea93ef1e998d23b8b5e62`.

Branch: `phase/rosta-ps8c-infrastructure-acceptance`.

Final accepted candidate: `6f29b602b8612eab975267e77f90b4c98a9f987a`.

Final merge commit: `6631c68dda8036c3e5da95fe1da3035b72d07f81`.

## Purpose

PS8C is the final independent **pre-server infrastructure acceptance** layer. It does not claim that the real VPS, DNS, TLS, provider credentials, production database, queues or monitoring are already live. Instead it proves that the repository's production topology and deployment package are internally coherent, isolated, restartable and testable before a controlled server cutover.

A real gap was closed in this phase: `Production Package CI` previously watched the deployment package and Dockerfiles but did not cover every real application build input. Backend or frontend source could therefore change without automatically rebuilding the production images. The workflow now covers frontend source/public/dependency inputs, all backend inputs, `.dockerignore`, production Dockerfiles and the production deployment package.

## Acceptance layers

### 1. Static/adversarial infrastructure topology

`deploy/production/infrastructure-audit.sh` renders the real production Compose model and rejects unsafe drift. It verifies:

- the exact eight-service topology: MySQL, Redis, API, API web, worker, scheduler, frontend and edge;
- MySQL and Redis are attached only to the internal backend network;
- only the edge service publishes host ports;
- API processes receive explicit egress while stateful dependencies stay isolated;
- every long-lived service uses `no-new-privileges` and avoids host networking, privileged mode, host PID/IPC namespaces, device mounts and added Linux capabilities;
- health-gated startup dependencies are preserved;
- stateful volumes and Caddy state are durable;
- application storage is shared while the API web mount is read-only;
- application images are keyed by the exact release SHA;
- executable production topology does not use `latest` or staging Dockerfiles;
- frontend and backend application runtimes retain non-root users;
- Docker build contexts exclude real `.env` files;
- deploy, rollback, backup and restore safety guards remain present.

The permanent PS7 production contract now invokes the PS8C infrastructure audit, so future production-package changes cannot bypass it.

### 2. Production image build and runtime wiring in CI

The dedicated `PS8C Infrastructure Acceptance` workflow builds the real production frontend, backend app and backend web images. It then uses the production Compose file with disposable, secret-safe CI environment files to prove:

- exact application images exist for the candidate SHA;
- frontend runs as `node` and backend application runs as `www-data`;
- MySQL and Redis start through their production healthchecks;
- the backend Compose network is actually internal at runtime;
- stateful services publish no host ports;
- clean MySQL migrations run from the production backend image;
- Laravel's MySQL/Redis cache/session/queue infrastructure acceptance runs from that production image;
- API, API web, worker, scheduler and frontend can start together without public edge cutover;
- API/API-web/frontend healthchecks pass and worker/scheduler process identities match their intended commands.

### 3. Stateful durability and backup evidence

The disposable CI topology proves data behavior instead of only checking YAML:

- a MySQL probe survives service restart through the named database volume;
- a Redis probe survives service restart through AOF-backed storage;
- `backup.sh` creates a non-empty gzip backup and SHA-256 sidecar;
- the checksum and gzip stream are verified;
- the backup is restored into a second disposable database;
- the restored database contains migration history before the probe database is destroyed.

The actual destructive production `restore-backup.sh` is **not** executed against CI as though CI were the VPS. Its checksum gate, mandatory pre-restore backup and explicit confirmation remain statically enforced, while the backup bytes themselves are independently proven restorable in disposable infrastructure.

### 4. Edge rehearsal and fail-closed production boundary

The existing isolated Caddy rehearsal remains mandatory and proves routing, security headers, private-cache controls, robots behavior and canonical API liveness without issuing real production certificates.

The production env example intentionally keeps payment, refund, OTP/SMS and media rails disabled. PS8C verifies that this example does **not** accidentally report strict production readiness. Passing PS8C therefore cannot be mistaken for provider activation or a live public cutover.

## Defects closed during exact-head acceptance

The exact-head runtime gate exposed and closed four real acceptance/runtime defects without weakening safeguards:

1. encrypted Redis sessions were verified semantically through Laravel instead of incorrectly requiring plaintext in Redis;
2. fail-closed readiness evidence was normalized without treating cache-warmup output as JSON;
3. production Nginx FastCGI was corrected to route to the actual Compose application service `api:9000`, allowing `api-web` to become healthy before public cutover;
4. catalog roast-date SSR was made deterministic with the canonical `Asia/Tehran` timezone, closing React hydration error #418 and adding a local-day-boundary regression test.

## Infrastructure invariants

1. Only the edge service may bind public host ports.
2. MySQL and Redis remain inaccessible from the edge or general egress networks.
3. Application processes must not gain host-level container privileges.
4. Startup ordering that depends on readiness uses health checks rather than process-start assumptions.
5. Stateful data lives on named volumes and is tested for restart persistence.
6. Production image builds use frozen application dependency lockfiles and SHA-keyed Rosta application tags.
7. Backup artifacts require integrity evidence and must be demonstrably restorable before production acceptance can rely on them.
8. Public DNS/TLS and real provider connectivity remain runtime facts, never source-code assumptions.
9. Production env examples remain fail-closed until real activation prerequisites are materialized.
10. Production package CI must trigger whenever a real production image build input changes.

## External image immutability boundary

Docker's official guidance notes that image tags are mutable and that digest pinning is required for full base-image reproducibility. The current Rosta production package uses explicit, non-`latest` version tags for external base/runtime images and records/inspects the resulting Rosta application images, but it does **not** claim that every external image is digest-pinned.

PS8C records `external_image_digest_pinning_claimed=false` rather than overstating supply-chain immutability. Digest locking may be added through a dedicated dependency-refresh process; it must not be fabricated from stale or unverified digest values.

## Official references reviewed for PS8C

- Docker Compose services reference — `depends_on`, `service_healthy`, healthchecks and service configuration.
- Docker Compose networking reference — named networks and `internal: true` external isolation.
- Docker build best practices — multi-stage builds, non-`latest` versioning and immutable digest pinning for full reproducibility.
- Docker image pull/reference documentation — digest-addressed images as immutable identifiers.
- Caddy automatic HTTPS documentation — public DNS requirements, ports 80/443 and persistent certificate storage.
- React hydration diagnostics — deterministic server/client text rendering and hydration mismatch causes.

## Final acceptance evidence

Every applicable pull-request workflow completed successfully on the same exact final candidate `6f29b602b8612eab975267e77f90b4c98a9f987a`:

- CI #890 — PASS
- Backend CI #569 — PASS
- Full-stack Integration CI #426 — PASS
- Browser Acceptance CI #416 — PASS
- R3 Final Gate #411 — PASS
- R4 Staging Package CI #376 — PASS
- Production Package CI #17 — PASS
- PS8A Frontend Acceptance #13 — PASS
- PS8B Backend Finance Acceptance #7 — PASS
- PS8C Infrastructure Acceptance #11 — PASS
- PS1 Backend Wrapper CI #111 — PASS

The stacked defect-closure chain was merged only with normal merge commits: PR #105 into #104, #104 into #103, #103 into #102, then PR #102 into `integration/rosta-release-candidate` as `6631c68dda8036c3e5da95fe1da3035b72d07f81`. No squash, rebase, amend, force-push or direct integration mutation was used.

## Production boundary

PS8C makes no claim that:

- `rosta.shop`, `api.rosta.shop` or `media.rosta.shop` currently resolve to the intended VPS;
- ports 80/443 are reachable on the real host;
- Caddy has obtained real public certificates;
- real production environment files or secrets exist on the server;
- OTP/Kavenegar, ZarinPal, R2 or another external provider is activated;
- the production database contains the expected live data;
- backup retention exists off-host;
- rollback/restore has been executed against the real production release;
- monitoring alerts have actually been delivered.

Those facts require controlled server/runtime acceptance after the source candidate is frozen.

## Exit gate

**PASS.** Every applicable workflow succeeded on the same exact final candidate and PR #102 was merged with a normal merge commit.

PS8C is closed. The next canonical phase is **PS9 — Final Integration, Tag & Pre-server Freeze**. No production cutover is implied by PS8C closure alone.
