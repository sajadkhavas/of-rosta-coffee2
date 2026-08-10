# ROSTA Pre-server Wave and Branch Ownership

## Baseline and conflict rule

All pre-server branches are descendants of the accepted release-candidate lineage. PS0 starts from:

`integration/rosta-release-candidate@a4d8afed40e55ec6f84d25308c5cde9524042bb0`

Parallel work is allowed only when file ownership is disjoint. A phase must not opportunistically edit another phase's exclusive files. If a required change crosses an ownership boundary, the owning phase makes it first and the dependent phase consumes the accepted integration SHA; history must not be rewritten.

## Wave schedule

| Wave | PS | Canonical branch | Parallelism / dependency |
|---|---|---|---|
| 0 | PS0 | `phase/rosta-ps0-pre-server-contract` | Runs first. Establishes the contract; no product behavior changes. |
| 1 | PS1 | `phase/rosta-ps1-dependency-security` | May run in parallel with PS2–PS6 after PS0. |
| 1 | PS2 | `phase/rosta-ps2-otp-auth-readiness` | Parallel with PS1/PS3–PS6; must not own lockfiles or deployment files. |
| 1 | PS3 | `phase/rosta-ps3-media-r2-readiness` | Parallel with PS1/PS2/PS4–PS6. |
| 1 | PS4 | `phase/rosta-ps4-finance-truth` | Parallel with PS1–PS3/PS5/PS6; exclusive financial model/migration owner. |
| 1 | PS5 | `phase/rosta-ps5-panel-operations` | Parallel; consumes PS4 finance contracts instead of redefining them. |
| 1 | PS6 | `phase/rosta-ps6-backup-monitoring-readiness` | Parallel for source/runbook readiness; may not create production deployment files. |
| 2 | PS7 | `phase/rosta-ps7-production-deployment` | Starts after Wave 1 acceptance. Exclusive production deployment-file owner and staging-domain reconciliation owner. |
| 3 | PS8 | `phase/rosta-ps8-feature-freeze` | Starts after PS7. Feature Freeze: acceptance/integration only; no new feature scope. |

These branch names are reserved by PS0 as the central pre-server naming contract.

## Exclusive ownership

### PS1 — lockfiles and dependency resolution

Only PS1 may intentionally modify:

- `bun.lock`
- `backend/composer.lock`

When a dependency declaration truly must change, PS1 is also the conflict owner for the dependency-related portions of:

- `package.json`
- `backend/composer.json`

Other phases must not regenerate lockfiles as a side effect. Existing deployment logic in `deploy/staging/deploy.sh` already requires the reviewed committed Composer lock and must not resolve dependencies on a server.

### PS2 — auth/session/CSRF application contract

PS2 owns auth-specific changes under the existing application configuration and auth implementation, including:

- `backend/config/sanctum.php`
- `backend/config/session.php`
- auth/session logic referenced by `backend/routes/api.php`
- frontend cookie/CSRF behavior in `src/lib/api/client.ts`

PS2 must not choose production secrets or provider credentials. Environment-domain reconciliation remains PS7-owned.

### PS3 — media/R2 application contract

PS3 owns media-storage implementation and the existing media contract. It must preserve the fail-closed provider boundary and must not put credentials in source. The repository's media audit is `backend/scripts/audit-media-storage-contract.php`, invoked by `composer audit:media-storage`.

### PS4 — financial model and financial migrations

PS4 is the **only** owner of financial model changes and financial migration changes.

Its contract surface includes the current financial API/OpenAPI artifacts such as:

- `src/lib/api/admin-finance.ts`
- `src/lib/api/financial-contracts.ts`
- `backend/routes/finance.php`
- `docs/openapi/rosta-v1-finance.yaml`
- finance-specific changes under `backend/database/migrations`

PS5 may render or navigate to financial surfaces, but it must consume PS4 contracts rather than modifying financial persistence or inventing rates.

### PS5 — non-financial admin/panel operations

PS5 owns general admin/panel operational behavior and the existing admin operations contract, including `src/lib/api/admin-operations.ts`, `backend/routes/admin-operations.php` and `docs/openapi/rosta-v1-admin-operations.yaml`, except for finance-owned semantics.

### PS6 — backup/restore/monitoring acceptance contract

PS6 owns pre-production operational requirements and evidence definitions for backup, restore and monitoring. Existing staging scripts under `deploy/staging` are implementation evidence, but PS6 must not turn them into production deployment files. Provider/account selection and real alert delivery remain truth-boundary inputs.

### PS7 — production deployment files and environment mapping

Only PS7 may create or modify files whose purpose is production deployment, production environment materialization, production runner/deployment automation, or production cutover.

PS0 intentionally does not assert a production deployment path that does not yet exist on the baseline.

PS7 also owns the environment-level reconciliation required by `docs/pre-server/API_DOMAIN_CONTRACT.md`, including staging cookie namespace/domain isolation. Application-level Sanctum/session mechanics remain PS2-owned.

### PS8 — Feature Freeze

At PS8:

- feature work is frozen;
- no new dependency, schema, provider, pricing, tax, carrier or product behavior is introduced;
- defects are routed back to their owning PS when a code fix is required;
- PS8 may update acceptance evidence and release-status documentation but must not use the freeze to bypass ownership.

## Shared-file conflict table

| Shared surface | Conflict owner | Rule |
|---|---|---|
| `package.json`, `backend/composer.json` | PS1 for dependency changes | Other PSs may use existing scripts; dependency changes route through PS1. |
| `bun.lock`, `backend/composer.lock` | PS1 exclusively | No other phase regenerates or edits. |
| `backend/routes/api.php` | PS2 for auth/common versioning; feature-specific route files remain feature owner | Avoid unrelated route churn; OpenAPI must move with API changes. |
| `src/config/site.ts` | PS7 for environment/domain changes | PS2 may not change production/staging host truth. |
| `.env.staging.example`, `backend/.env.staging.example` | PS7 for domain/cookie/environment reconciliation | Provider values stay placeholders or disabled unless real acceptance inputs exist. |
| `docs/openapi/rosta-v1-finance.yaml` | PS4 | Financial semantics only. |
| `docs/openapi/rosta-v1-admin-operations.yaml` | PS5, excluding finance semantics | PS5 must not redefine PS4 money truth. |
| `deploy/staging` | PS6 for acceptance/runbook concerns; PS7 for deployment/domain reconciliation | Same-file edits must be serialized; no parallel conflicting commits. |
| `.github/workflows` | PS1 for dependency-gate changes; PS7 for deployment workflow changes | Existing CI names remain stable unless an owner has a documented reason. |
| `docs/pre-server` | PS0 contract; PS8 final acceptance updates | Wave 1 phases reference these documents; they do not rewrite the central contract in parallel. |

## Conflict handling

1. Do not solve a shared-file collision by rebase, amend, squash, force-push or history rewrite.
2. The designated owner lands the change first.
3. A dependent phase restarts or continues from the newly accepted integration SHA according to its own mission rules.
4. The dependent PR records the accepted owner SHA in its handoff.
5. PS8 rejects any branch carrying unrelated cross-owner changes.
