# ROSTA Pre-server Acceptance Matrix

## Authority and scope

This document is the central acceptance register for PS0–PS8 before production-server work begins.

- Release-candidate baseline: `integration/rosta-release-candidate@a4d8afed40e55ec6f84d25308c5cde9524042bb0`
- PS0 branch: `phase/rosta-ps0-pre-server-contract`
- Canonical implementation history: `docs/PHASES.md`
- Repository-wide contribution rules: `AGENTS.md`
- Frontend package contract: `package.json`
- Backend package contract: `backend/composer.json`
- Staging architecture: `deploy/staging`
- OpenAPI sources: `docs/openapi`
- Historical release/staging evidence remains evidence, not a substitute for a fresh PS gate.

Status language:

- **PASS** — command or invariant was actually verified for the named SHA/environment.
- **FAIL** — command/invariant was actually checked and is not satisfied.
- **UNPROVEN** — source exists, but the command was not executed for the named SHA.
- **EXTERNAL PENDING** — acceptance depends on infrastructure, credentials, DNS, provider approval, or business input that source control cannot prove.
- **N/A** — not applicable to that PS.

A PS must not be called complete while any gate owned by that PS is **FAIL** or **UNPROVEN**. External acceptance may be deferred only when the PS explicitly records it as **EXTERNAL PENDING** and later PS8 treats it as a release blocker where required.

## Baseline evidence at PS0 start

| Check | Baseline state | Evidence |
|---|---|---|
| Release-candidate ref equals required SHA | PASS | GitHub ref for `integration/rosta-release-candidate` resolves to `a4d8afed40e55ec6f84d25308c5cde9524042bb0`. |
| Local `git fetch --all --prune` | UNPROVEN as a successful fetch | Attempted in the execution runtime; exited `128` because `github.com` DNS resolution was unavailable. The authoritative GitHub ref was checked separately. |
| `bun run audit:phase17` | UNPROVEN on baseline in this execution | Script exists at `scripts/audit-phase17-release-baseline.mjs` and is permanently included by `package.json`, but Bun is not installed in the local runtime and no PR-triggered workflow run was associated with the baseline SHA during PS0 inspection. |
| `bun run audit:phase22` | UNPROVEN on baseline in this execution | Script exists at `scripts/audit-phase22-staging.mjs` and is in the permanent `check` chain; fresh execution is required on the PS0 head. |
| `bun run audit:r4a` | UNPROVEN on baseline in this execution | Script exists at `scripts/audit-r4a-staging-package.mjs` and is in the permanent `check` chain; fresh execution is required on the PS0 head. |
| Production frontend contract | PASS as source contract | `src/config/site.ts` defaults the site to `https://rosta.shop`; production API is fixed below to `https://api.rosta.shop/api/v1`. |
| Production API server contract | PASS as source contract | `docs/openapi/rosta-v1.yaml` declares `https://api.rosta.shop/api/v1`; `backend/routes/api.php` is versioned under `v1`. |
| Staging indexing and secure-cookie flags | PASS as source contract | `.env.staging.example` disables indexing; `backend/.env.staging.example` has `APP_DEBUG=false`, encrypted sessions and secure cookies. |
| Staging cookie namespace/domain isolation from production | FAIL on baseline | `backend/.env.staging.example` currently uses `SESSION_DOMAIN=.rosta.shop` and no explicit staging-only `SESSION_COOKIE`. This is a PS7/PS8 blocker; PS0 must not silently rewrite runtime configuration. |
| Real SMS/payment/refund provider readiness | EXTERNAL PENDING | Staging config is fail-closed (`ROSTA_SMS_ENABLED=false`, `ROSTA_PAYMENT_ENABLED=false`, `ROSTA_REFUND_ENABLED=false`). |
| Real R2 acceptance | EXTERNAL PENDING | Staging declares S3/R2 integration but credentials/bucket/endpoint are placeholders. `scripts/audit-phase22-staging.mjs` requires real infrastructure acceptance later. |
| Real backup/restore and monitoring | EXTERNAL PENDING | Repository scripts exist under `deploy/staging`; source control cannot prove host execution, retained backups, restore success, alert delivery or monitoring ownership. |

The historical register in `docs/PHASES.md` reports C0–C8 as verified in repository ancestry while C4 still has runtime-pending work. PS0 does not convert that historical statement into a fresh PASS for this baseline without executable evidence.

## Common gate rules for every PS

Every PS handoff must include:

1. exact baseline SHA and phase-head SHA;
2. changed files;
3. commands exactly as run, with exit codes;
4. GitHub workflow names, URLs and final conclusions when CI is required;
5. `git diff --check`;
6. no-secret review;
7. explicit risks and deferred external acceptance;
8. proof that changes remain inside the ownership matrix in `docs/pre-server/WAVE_BRANCH_OWNERSHIP.md`.

The shared minimum source gate is:

```bash
bun run check
cd backend && composer check
```

A PS may use a smaller targeted command while iterating, but its final acceptance must not weaken permanent repository gates.

## PS0–PS8 matrix

| PS | Purpose | Required commands / checks | Environment | Required output/evidence | Primary owner |
|---|---|---|---|---|---|
| PS0 | Central contract and acceptance matrix | `git diff --check`; `bun run audit:phase17`; `bun run audit:phase22`; `bun run audit:r4a`; path existence review | Clean repository + GitHub CI when local Bun is unavailable | All six central documents; three audits green on PS0 head; no secret; clean committed head | PS0 |
| PS1 | Dependency lock and advisory hardening | `bun install --frozen-lockfile`; `bun audit`; `cd backend && composer validate --strict`; `cd backend && composer audit --locked`; full permanent gates | CI/dev with package registries available | Lockfiles reproducible; advisories dispositioned; dependency drift absent | PS1 |
| PS2 | Auth, OTP, session and CSRF readiness | `bun run check`; `cd backend && composer check`; auth/OTP tests already included by backend suite; contract review against `src/lib/api/client.ts`, `backend/config/sanctum.php`, `backend/config/session.php` | CI; provider runtime separately | Cookie/CSRF/session contract preserved; provider-dependent SMS evidence explicitly pending until real provider acceptance | PS2 |
| PS3 | Media and R2 readiness | `cd backend && composer audit:media-storage`; `bun run check`; `cd backend && composer check` | CI; real object-storage acceptance separately | Storage contract green; no fake public URL/credential; real PUT/GET/public delivery/CORS/cleanup remains runtime evidence | PS3 |
| PS4 | Finance truth, refunds, settlement and financial persistence | `bun run audit:admin-finance`; `cd backend && composer audit:finance`; full permanent gates | CI; real money movement disabled unless separately approved | Financial model/API/OpenAPI consistent; no invented rates/provider/bank behavior | PS4 |
| PS5 | Admin/panel operational readiness | `bun run audit:admin-operations`; `cd backend && composer audit:admin-operations`; full permanent gates | CI/browser acceptance as applicable | Role boundaries and panel operations pass without redefining PS4 finance truth | PS5 |
| PS6 | Backup, restore, monitoring and operational readiness contract | `cd backend && composer audit:staging`; `cd backend && composer audit:encrypted-storage`; `bun run audit:phase22`; full permanent gates | CI for source; staging/host for drills | Source scripts/config green; actual backup/restore/alert evidence remains external/runtime until executed | PS6 |
| PS7 | Production deployment preparation and environment reconciliation | `bun run audit:phase17`; `bun run audit:phase22`; `bun run audit:r4a`; full permanent gates; deployment-specific checks introduced by PS7 | CI + approved staging/rehearsal; no production secrets in repo | Production deployment files only from PS7; staging cookie isolation fixed and reconciled with audits before PS8 | PS7 |
| PS8 | Feature Freeze and final pre-server acceptance | `git diff --check`; `bun run check`; `cd backend && composer check`; all six formal workflows named in `docs/PHASES.md` on one immutable SHA | CI + required runtime/external evidence | No feature drift; all blocking external decisions resolved or formally blocking release; immutable release evidence | PS8 |

## Permanent repository gates referenced by this contract

The current frontend `check` chain in `package.json` includes the required PS0 audits and broader product gates. The current backend `check` chain in `backend/composer.json` includes validation, locked advisory checks, OpenAPI drift, finance, admin operations, staging, encrypted storage, acceptance fixtures, tests, static analysis and format verification.

The six formal GitHub gates named by `docs/PHASES.md` are:

1. CI
2. Backend CI
3. Full-stack Integration CI
4. Browser Acceptance CI
5. R3 Final Gate
6. R4 Staging Package CI

PS8 requires all required workflows on the same immutable candidate SHA. A historical run on another SHA is not transferable evidence.
