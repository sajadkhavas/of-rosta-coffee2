# Production Topology Contract

Status: ARCH-0.2

## 1. Production-ready-before-server principle

ROSTA production servers are activation targets, not development workstations.

The source of deploy truth is a reviewed Git SHA plus reproducible, prevalidated artifacts/configuration. If deployment requires source editing, ad-hoc tracked config changes, manual SQL repair, emergency package installation or the first successful application build, the correct verdict is `SERVER_BLOCKED_SOURCE` and the fix returns to GitHub.

## 2. Logical production components

```text
Public Internet
   |
DNS / TLS / edge protection
   |
Reverse proxy
   |-------------------------|
Frontend SSR / Node          Laravel API / PHP-FPM
                                  |
                         MySQL ---+--- Redis
                                  |
                           Queue workers
                           Scheduler
                                  |
                         S3/R2 + external providers
```

Exact service names/ports/paths are deployment implementation details and must be versioned in deployment assets/runbooks.

## 3. Initial lean deployment

**TARGET:** one appropriately sized VPS may host reverse proxy, Node SSR, PHP-FPM/API, worker, scheduler, MySQL and Redis for initial launch if capacity/security tests pass. R2/object storage remains external.

Requirements:

- explicit memory/CPU limits and conservative worker concurrency;
- swap as emergency pressure buffer, not normal capacity;
- bounded PHP-FPM/queue/media concurrency;
- DB/Redis persistence/config tuned for the host;
- log rotation/storage pressure controls;
- automated backup destination not dependent on the same single disk only.

This topology is intentionally evolvable. It is not a mandate to keep all components colocated after growth.

## 4. Immutable release model

Production activation should use a release layout conceptually like:

```text
/releases/<git-sha-or-release-id>/
/current -> selected immutable release
/shared/ -> permitted runtime state/config links only
```

Rules:

- release artifact is built/tested before activation;
- dependencies are lockfile-derived;
- tracked files are not edited after build;
- rollback selects a previously validated release rather than reverse-editing source;
- activation is atomic where possible;
- migrations follow a reviewed forward/backward compatibility plan.

Exact implementation is finalized in the deployment phase.

## 5. Frontend artifact

SSR build must be reproducible from the repository lockfile/toolchain and proven in CI/rehearsal before server activation.

Server should not be the first place where:

- route generation succeeds;
- Node SSR boots;
- production frontend build completes;
- static assets are fingerprinted/packaged.

Runtime-only environment values must be distinguished from compile-time public values; secrets never enter client bundles.

## 6. Backend artifact

Laravel artifact/deployment includes only required production code/dependencies/assets plus validated configuration contract.

Before activation prove:

- `composer` lock/dependency install works in production mode;
- migrations are known and rehearsed;
- config/cache/routes boot under production environment;
- required PHP extensions exist;
- queue and scheduler commands boot;
- storage permissions/paths are explicit.

No Composer/npm/Bun dependency surprise is accepted during first production activation.

## 7. Environment validation

Preflight validates without exposing values:

- environment/app key/runtime mode;
- canonical app/API URLs and trusted hosts;
- MySQL connectivity/schema migration status;
- Redis connectivity for configured roles;
- queue/failed-job setup;
- S3/R2 bucket/endpoint configuration;
- payment/SMS enable flags and credential presence;
- callback/webhook URLs;
- filesystem/log/write permissions;
- TLS/DNS expectations where externally testable.

Optional providers may be disabled explicitly. Missing credentials must not silently activate fake providers.

## 8. Service supervision

Reverse proxy, Node SSR, PHP-FPM, queue worker and scheduler need process supervision/restart policy. Worker deployments must restart onto the new release safely and drain/handle in-flight jobs according to framework semantics.

The scheduler must run exactly with the intended leader behavior; duplicate scheduler execution must not create duplicate financial effects.

## 9. Database migrations

Deployment migration rules:

- migration files are reviewed source artifacts;
- backup/preflight exists before high-risk migration;
- prefer expand/migrate/contract patterns for zero/low-downtime changes;
- application rollback compatibility is known;
- destructive cleanup does not occur in the same release if it would prevent rollback unless explicitly approved/rehearsed;
- no manual production schema edits outside emergency procedure followed by source reconciliation.

## 10. Activation and smoke

After activation, smoke checks cover at least:

- public homepage/SSR;
- API liveness/readiness;
- database/Redis-backed safe read;
- authentication/session/CSRF safe path;
- representative catalog/product route;
- provider configuration state without leaking secrets;
- queue/scheduler health;
- object/media access;
- payment/SMS/carrier bounded real smoke only when credentials/contracts allow it.

Do not run irreversible real financial transactions as a casual smoke test.

## 11. Rollback

Rollback procedure defines:

- release selection;
- application/worker restart;
- database compatibility decision;
- cache cleanup only where safe;
- smoke verification;
- incident/release evidence.

Rollback must be rehearsed before calling the server production-ready.

## 12. Backup/restore

Production acceptance requires automated backups plus a demonstrated restore procedure. Exact RPO/RTO are deployment-policy decisions, but the restore must account for MySQL and object-storage/media relationships.

## 13. External readiness

Payment, SMS, R2, mail, carrier and webhook providers are separate gates. Source may be production-ready while a provider remains externally blocked.

Canonical deployment verdicts:

- `SERVER_BLOCKED_SOURCE`
- `SERVER_BLOCKED_EXTERNAL`
- `SERVER_READY`
- `ACTIVE_VERIFIED`

No weaker phrase replaces these when making a release decision.

## 14. Rehearsal requirement

Before real launch rehearse on a clean environment:

1. fresh bootstrap;
2. artifact install/activation;
3. migrations;
4. worker/scheduler startup;
5. smoke;
6. upgrade to next artifact;
7. rollback/roll-forward;
8. service restart/reboot;
9. representative dependency outage;
10. backup restore.

Any manual source repair discovered during rehearsal becomes a GitHub fix and the rehearsal restarts from a clean artifact.
