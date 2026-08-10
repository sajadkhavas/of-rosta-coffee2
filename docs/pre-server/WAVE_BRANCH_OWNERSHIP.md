# ROSTA Pre-server Wave and Branch Ownership

## Baseline and integration rule

PS0 starts from the immutable accepted baseline:

`integration/rosta-release-candidate@a4d8afed40e55ec6f84d25308c5cde9524042bb0`

Every later wave starts only after the previous wave's accepted pull requests have been merged into `integration/rosta-release-candidate` and the central reviewer has published the resulting full 40-character SHA. A phase branch must start from that exact SHA. Placeholders such as `{{WAVE_1_SHA}}` are never valid baselines.

No phase executor merges its own pull request. Direct work on `main` or the integration branch, rebase, amend, squash, force-push and any other published-history rewrite are prohibited by `AGENTS.md` and the Lovable history contract.

## Canonical wave schedule

| Wave | Phase | Canonical branch | Parallelism / dependency |
|---|---|---|---|
| 0 | PS0 | `phase/rosta-ps0-pre-server-contract` | Runs alone from the frozen baseline and creates this contract. |
| 1 | PS1 | `phase/rosta-ps1-release-security` | Parallel with PS2 and PS3 after PS0 acceptance. Exclusive dependency/lockfile owner. |
| 1 | PS2 | `phase/rosta-ps2-otp-notifications` | Parallel with PS1 and PS3; no dependency or media scope. |
| 1 | PS3 | `phase/rosta-ps3-media-pipeline` | Parallel with PS1 and PS2; no auth or release-security scope. |
| 2 | PS4.1 | `phase/rosta-ps4a-financial-core` | Parallel with PS5.1 and PS5.2 after Wave 1. Exclusive financial-core owner. |
| 2 | PS5.1 | `phase/rosta-ps5a-quiz-reviews` | Parallel with PS4.1 and PS5.2; Quiz/recommendations/reviews only. |
| 2 | PS5.2 | `phase/rosta-ps5b-seller-organization` | Parallel with PS4.1 and PS5.1; seller organization/availability only. |
| 3 | PS4.2 | `phase/rosta-ps4b-refund-payout-reconciliation` | Parallel with PS5.3 after Wave 2; consumes PS4.1 financial truth. |
| 3 | PS5.3 | `phase/rosta-ps5c-carrier-admin-ops` | Parallel with PS4.2; carrier and non-financial admin operations only. |
| 4 | PS5.4 | `phase/rosta-ps5d-workspaces-kpi` | Parallel with PS6B after Wave 3; consumes existing APIs and does not recreate business logic. |
| 4 | PS6B | `phase/rosta-ps6b-backend-observability` | Parallel with PS5.4; backend refactor, queues and instrumentation only. |
| 5 | PS6A | `phase/rosta-ps6a-frontend-quality` | Parallel with PS7 after Wave 4; frontend quality freeze, not feature work. |
| 5 | PS7 | `phase/rosta-ps7-production-deployment` | Parallel with PS6A; exclusive `deploy/production` owner. No real server mutation. |
| 6 | PS8A | `acceptance/rosta-ps8a-frontend` | Independent evidence-only audit of the same Wave 5 candidate SHA. |
| 6 | PS8B | `acceptance/rosta-ps8b-backend-finance` | Independent evidence-only audit of the same Wave 5 candidate SHA. |
| 6 | PS8C | `acceptance/rosta-ps8c-infrastructure` | Independent evidence-only audit of the same Wave 5 candidate SHA. |
| 7 | PS9 | central reviewer on `integration/rosta-release-candidate` | Runs only after all three PS8 verdicts are PASS; integrates evidence, reruns gates, tags and freezes. |
| 8 | PS10 | assigned after approved feature intake | Starts only from the PS9 final SHA after the new feature is fully defined. |

If any PS8 path fails, Feature Freeze reopens only for the owning phase. After a source fix is integrated, PS8A, PS8B and PS8C all restart from the same new candidate SHA. PS9 must not tag a mixed or partially retested candidate.

## Exclusive ownership

### PS0 — central contract

PS0 owns the six central files under `docs/pre-server`. Wave implementations reference them and do not rewrite them in parallel. PS8 owns only its phase-specific evidence files. PS9 owns the final acceptance, release notes and final manifest evidence.

### PS1 — release security and all lockfiles

Only PS1 may intentionally modify dependency declarations, dependency policy or lockfiles during parallel pre-server work:

- `package.json`
- `bun.lock`
- `backend/composer.json`
- `backend/composer.lock`
- dependency-gate changes under `.github/workflows`

PS1 also owns the Wave 1 corrections for private `/hub` cache boundaries, the duplicate seller-roasteries route, staging contract-version drift, staging preflight/deploy lock messaging and source-level staging cookie isolation.

If PS3 or another phase proves a dependency is essential, it must stop that part, hand the exact requirement to PS1 and consume the later accepted integration SHA. It must not regenerate a lockfile in parallel.

### PS2 — OTP and notification delivery

PS2 owns the OTP sender/provider selection, Iranian mobile normalization, OTP/request safety, notification-outbox delivery behavior, redacted observability, readiness and related OpenAPI/config keys. It does not own session-domain topology, dependency files, media or finance.

### PS3 — media pipeline

PS3 owns media ingestion, server-side validation/decoding, variant generation, media state/worker behavior, storage ownership, media OpenAPI and seller media progress. It must preserve private object storage and cannot change a lockfile without the PS1 serialization rule above.

### PS4.1 and PS4.2 — financial truth

PS4.1 is the exclusive owner of Tax/Commission policies, quote/order financial snapshots, financial-core models and migrations, allocation and ledger conservation.

PS4.2 is the exclusive later-wave owner of Refund/Payout/Reconciliation calculations, reversals, statements and their financial models/migrations. Other phases consume these APIs and never recompute money in the browser or invent rates/provider movement.

### PS5.1, PS5.2, PS5.3 and PS5.4 — product domains and workspaces

- PS5.1 owns Quiz, persisted recommendations, seller review replies and review report/abuse.
- PS5.2 owns seller membership/roles/invites, schedules, closures and the non-financial promotion lifecycle boundary.
- PS5.3 owns carrier interfaces/manual carrier, signed webhooks and non-financial admin operations including controlled failed-job actions.
- PS5.4 owns the final Seller/Admin workspace composition and KPI presentation. It consumes accepted server APIs and must report a missing contract instead of adding a permanent mock or reimplementing domain logic.

### PS6B and PS6A — behavior-preserving quality work

PS6B owns backend characterization-led refactors, queue reliability, redaction, metrics/traces and readiness instrumentation. PS6A owns frontend refactor, performance, accessibility and visual regression. Neither phase introduces new business behavior.

### PS7 — production deployment package

Only PS7 may create or modify files whose purpose is production deployment, production cutover, production environment materialization or production backup/restore/monitoring wiring. PS7 also re-verifies staging/production namespace isolation after PS1 and builds `deploy/production` without touching a real server.

### PS8 and PS9 — freeze and registration

PS8A/B/C branches may add only their named evidence file under `docs/pre-server/evidence`. They must not repair product or deployment source. PS9 reviews and merges valid evidence, reruns all final gates on one SHA, writes final acceptance artifacts and creates the collision-free annotated tag only after `PRE-SERVER GO`.

## Shared-file conflict table

| Shared surface | Conflict owner / order | Rule |
|---|---|---|
| `package.json`, `bun.lock`, `backend/composer.json`, `backend/composer.lock` | PS1 | All dependency and lockfile changes serialize through PS1. |
| `.github/workflows` | PS1 for dependency gates; PS7 for deployment/rehearsal; PS9 for final evidence only | Preserve established workflow names unless the owner documents a necessary change. |
| `backend/routes/api.php` | PS1 for duplicate-route cleanup; later phases use feature route files | Avoid unrelated route churn and move OpenAPI with API behavior. |
| `.env.staging.example`, `backend/.env.staging.example`, staging Caddy/Compose/acceptance | PS1 for Wave 1 cookie/contract hardening; PS7 for final production/staging reconciliation | Different sections may not be silently overwritten; later owner consumes accepted earlier behavior. |
| OTP/notification config, routes and OpenAPI | PS2 | PS1 may not redefine OTP delivery while changing release security. |
| Media routes, schema, worker and OpenAPI | PS3 | Other Wave 1 branches do not edit media semantics. |
| Financial models, migrations, finance API/OpenAPI | PS4.1 then PS4.2 | No parallel non-financial phase changes money truth. |
| Seller/Admin route shells and dashboard navigation | Domain owner first, PS5.4 composition later | Wave 2/3 phases isolate feature modules; PS5.4 consumes them after integration. |
| Backend services refactored by PS6B | Domain owners in Waves 2/3 first, PS6B after Wave 3 | Characterization tests must prove behavior preservation. |
| `src/config/site.ts`, `deploy/production`, production runbooks | PS7 | Frontend quality work may consume but not redefine deployment truth. |
| `docs/pre-server/evidence` | PS8A/B/C by named file | Each audit changes only its own evidence file. |
| `docs/pre-server/FINAL_PRE_SERVER_ACCEPTANCE.md`, release notes, tag | PS9 | Written and registered only after all valid final gates pass. |

## Conflict handling

1. Do not solve a shared-file collision through rebase, amend, squash, force-push or any history rewrite.
2. The designated owner lands first through a reviewed merge commit.
3. The dependent phase resumes from the newly accepted integration SHA or records a blocker; it does not copy unaccepted code from another branch.
4. Every pull request records its exact baseline SHA, accepted owner SHA where relevant and cross-owner files.
5. A failed valid gate blocks the wave. The next wave does not start until all accepted work is integrated and a new SHA is published.
