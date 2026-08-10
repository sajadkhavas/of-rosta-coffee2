# ROSTA Pre-server Acceptance Matrix

## Authority and scope

This document is the central acceptance register for PS0 through PS9 before any real server work. PS10 is the separately gated post-freeze feature intake.

- Release-candidate baseline: `integration/rosta-release-candidate@a4d8afed40e55ec6f84d25308c5cde9524042bb0`
- PS0 branch: `phase/rosta-ps0-pre-server-contract`
- Canonical wave/branch contract: `docs/pre-server/WAVE_BRANCH_OWNERSHIP.md`
- Canonical historical implementation record: `docs/PHASES.md`
- Repository history rule: `AGENTS.md`
- Frontend commands: `package.json`
- Backend commands: `backend/composer.json`
- Staging source package: `deploy/staging`
- OpenAPI sources: `docs/openapi`

The wave/branch names in `WAVE_BRANCH_OWNERSHIP.md` are normative. A phase does not rename, collapse, reorder or start itself from a placeholder or an unaccepted SHA.

Status language:

- **PASS** — the command or invariant was actually verified for the named SHA and environment.
- **FAIL** — the command/invariant was checked and is not satisfied.
- **UNPROVEN** — source or a historical result exists, but the required check was not executed for the named SHA.
- **EXTERNAL PENDING** — acceptance needs infrastructure, credentials, DNS, provider approval or business input that source control cannot prove.
- **N/A** — the check truly does not apply, with a recorded reason.

A phase is not complete while an owned gate is **FAIL** or **UNPROVEN**. `EXTERNAL PENDING` is never rewritten as PASS. PS8 and PS9 must distinguish source-complete pre-server evidence from checks that can only run later on approved staging/provider infrastructure.

## Baseline evidence at PS0 start

| Check | Baseline state | Evidence |
|---|---|---|
| Release-candidate ref equals required SHA | PASS | `origin/integration/rosta-release-candidate` resolves to `a4d8afed40e55ec6f84d25308c5cde9524042bb0`. |
| PS0 ancestry | PASS | The merge base of the PS0 branch and release-candidate is the exact required SHA; PS0 changes only the six central documents. |
| `bun run audit:phase17` on baseline | UNPROVEN as a fresh baseline run | The script exists at `scripts/audit-phase17-release-baseline.mjs` and is in the permanent `check` chain. Fresh PS0-head evidence is required. |
| `bun run audit:phase22` on baseline | UNPROVEN as a fresh baseline run | The script exists at `scripts/audit-phase22-staging.mjs` and is in the permanent `check` chain. Fresh PS0-head evidence is required. |
| `bun run audit:r4a` on baseline | UNPROVEN as a fresh baseline run | The script exists at `scripts/audit-r4a-staging-package.mjs` and is in the permanent `check` chain. Fresh PS0-head evidence is required. |
| Production frontend contract | PASS as source contract | `src/config/site.ts` defaults the site to `https://rosta.shop`. |
| Production API contract | PASS as source contract | `docs/openapi/rosta-v1.yaml` declares `https://api.rosta.shop/api/v1`; `backend/routes/api.php` is versioned under `v1`. |
| Staging indexing and secure-cookie flags | PASS as source contract | `.env.staging.example` disables indexing; `backend/.env.staging.example` has `APP_DEBUG=false`, encrypted sessions and secure cookies. |
| Staging cookie namespace/domain isolation | FAIL on baseline | `backend/.env.staging.example` uses `SESSION_DOMAIN=.rosta.shop` and has no explicit staging-only `SESSION_COOKIE`. PS1 owns the initial source correction; PS7 re-verifies it in the production package. |
| Dependency vulnerability policy | FAIL on baseline audit | The frozen frontend dependency graph has unresolved High advisories and `bun audit` is not yet a permanent Critical/High gate. PS1 owns closure and policy. |
| Production OTP delivery | FAIL on baseline | Non-local provider selection falls back to `DisabledOtpSender`; PS2 owns the real fail-closed Kavenegar OTP path and delivery tests. |
| Server-side image truth and variants | FAIL on baseline | Media completion trusts client metadata and does not generate verified responsive WebP/AVIF variants; PS3 owns closure. |
| Real payment/R2/SMS/carrier/provider activation | EXTERNAL PENDING | Provider credentials, approvals and infrastructure are intentionally absent from Git. Source contracts must be completed before later real staging acceptance. |
| Real backup/restore and monitoring delivery | EXTERNAL PENDING | Scripts/interfaces do not prove retained offsite backup, restored data or delivered alerts on a real host. |

Historical workflow or phase evidence remains useful evidence, but it is not transferable to a different SHA. The staging status in source remains runtime-pending until a later real staging acceptance produces its required machine-readable result.

## Common gate rules

Every phase handoff includes:

1. exact baseline branch/SHA, phase branch and phase-head SHA;
2. changed files and ownership check;
3. commands exactly as executed with environment and exit code;
4. exact-SHA CI workflow names, URLs and conclusions when required;
5. `git diff --check`, clean committed worktree and no-secret review;
6. risks, business decisions and external acceptance separated from PASS;
7. migration/backfill/rollback notes when schema changes;
8. proof that tests/audits were not removed, skipped, weakened or permanently bypassed.

The permanent aggregate gates are:

```bash
bun run check
cd backend && composer check
```

A phase may use targeted tests while iterating, but its final gate cannot weaken an existing aggregate gate that covers its changes.

## Phase matrix

| Wave / phase | Purpose | Required commands / checks | Environment | Required output/evidence | Owner |
|---|---|---|---|---|---|
| 0 / PS0 | Central contract | `git diff --check`; `bun run audit:phase17`; `bun run audit:phase22`; `bun run audit:r4a`; referenced-path review | Clean checkout and exact-head CI/local runtime | Six central documents; exact canonical wave map; audits green on PS0 head; no secret; clean head | PS0 |
| 1 / PS1 | Release and security hardening | `bun audit`; `bun run check`; `bun run check:backend`; duplicate-route contract; private-cache/PWA/browser audit; `bash -n deploy/staging/*.sh`; Staging Compose config when available | Registry-enabled CI/dev; Docker or hosted CI for Compose | No unresolved Critical/High; advisory before/after table; `/hub` no-store/noindex/bypass; duplicate route removed; cookie isolation and staging scripts consistent | PS1 |
| 1 / PS2 | Production OTP and notifications | `cd backend && composer check`; targeted IdentityOtp/NotificationOutbox/provider tests; readiness misconfiguration tests; redaction scan; OpenAPI drift; `git diff --check` | CI with HTTP fakes; real paid SMS explicitly separate | Production-safe Kavenegar OTP adapter; canonical mobile handling; bounded retries; no OTP/key/full-mobile leak; no unintended Production `DisabledOtpSender` | PS2 |
| 1 / PS3 | Secure media and R2 pipeline | `cd backend && composer check`; targeted media/worker tests; `bun run check`; Composer audit; hostile image fixtures; S3-compatible rehearsal; `git diff --check` | CI + local S3-compatible storage; real R2 separate | Server-derived media truth; bomb/EXIF/spoof protection; idempotent responsive variants; private ownership; state/retry/cleanup evidence | PS3 |
| 2 / PS4.1 | Tax, commission and ledger core | `cd backend && composer check`; clean and upgrade migrations; financial-boundary/transaction/R5C/R5I tests; OpenAPI/finance audits; `git diff --check` | MySQL/Redis CI | Versioned/effective policy engine; exact snapshots; no hidden hard-coded zero; allocation/ledger conservation; required business inputs listed | PS4.1 |
| 2 / PS5.1 | Quiz, recommendations and review safety | `cd backend && composer check`; `bun run check`; OpenAPI drift; new browser journey; clean/upgrade migration; cache/noindex/a11y checks; `git diff --check` | Full-stack/browser CI | Persisted/versioned Quiz and rules; consented guest sync; deterministic recommendations; real seller reply/report/abuse workflow | PS5.1 |
| 2 / PS5.2 | Seller organization and availability | `cd backend && composer check`; `bun run check`; permission/IDOR matrix; timezone/closure/invite race tests; public/checkout integration; OpenAPI/cache/a11y; `git diff --check` | Full-stack/browser CI | Server-enforced memberships; secure invite lifecycle; schedules/closures reflected in availability; no finance ownership breach | PS5.2 |
| 3 / PS4.2 | Refund, payout and reconciliation | `cd backend && composer check`; refund/R5I/ledger/reconciliation tests; `bun run check`; clean/upgrade migration; OpenAPI/audits; `git diff --check` | MySQL/Redis CI; external rail separate | Safe real or formal manual execution path; maker-checker; idempotent reversals; statements/batches; conservation across fixtures | PS4.2 |
| 3 / PS5.3 | Carrier and admin operations | `cd backend && composer check`; `bun run check`; fulfillment/R5H/R5I/carrier tests; OpenAPI/privacy/IDOR/audit; queue failure rehearsal; `git diff --check` | Full-stack/queue CI | Versioned manual/external boundary; signed/replay-safe webhooks; safe queue operations; no fabricated carrier success | PS5.3 |
| 4 / PS5.4 | Seller/Admin workspaces and KPIs | `bun run check`; `bun run test:browser` on integrated runtime; `bun run check:backend`; Axe/overflow/hydration/cache/role tests; `git diff --check` | Integrated browser CI | API gap matrix is zero or blocking; live typed APIs only; server-defined KPIs; full states/RTL/mobile/keyboard; no client money calculation | PS5.4 |
| 4 / PS6B | Backend refactor and observability | `cd backend && composer check`; MySQL/Redis migration/readiness/queue tests; characterization/concurrency suites; log-redaction test; query/benchmark evidence; `git diff --check` | MySQL/Redis/queue CI | Behavior-preserving refactor map; retry/dead-letter/health; redacted structured logs; metrics/trace catalog and PS7 wiring needs | PS6B |
| 5 / PS6A | Frontend quality freeze | Production-domain build env; `bun run check`; `bun run test:browser`; browser/visual/role audits; bundle comparison; `git diff --check` | Production build + integrated browser CI | Zero serious/critical Axe, overflow, hydration/page error or private leak; performance evidence; deterministic visual index | PS6A |
| 5 / PS7 | Immutable production package | Shell syntax/ShellCheck; Production and Staging Compose/Caddy validation; hosted rehearsals; Phase 22/R4A/backend deployment audits; pin/secret/SBOM checks; backup/restore/rollback rehearsal; `git diff --check` | Docker-enabled hosted CI; no real server | Independent `deploy/production`; SHA-pinned immutable package; fail-closed acceptance JSON; cookie separation; external-input list without values | PS7 |
| 6 / PS8A | Independent frontend acceptance | Frozen install/audit; production SSR build; aggregate and independent frontend gates; integrated role/SEO/PWA/a11y/performance matrix | Exact common Wave 5 candidate | Only `docs/pre-server/evidence/PS8A_FRONTEND_<SHORTSHA>.md`; `verdict: PASS/FAIL`; artifact hashes; zero product-source diff | PS8A |
| 6 / PS8B | Independent backend/security/finance acceptance | Locked Composer audit/install; MySQL/Redis clean+upgrade; aggregate and independent backend gates; auth/media/commerce/finance/carrier/admin/adversarial matrix | Same exact candidate as PS8A/C | Only `docs/pre-server/evidence/PS8B_BACKEND_FINANCE_<SHORTSHA>.md`; counts/invariants/vulnerability result; verdict; zero product-source diff | PS8B |
| 6 / PS8C | Independent infrastructure rehearsal | Shell/Compose/Caddy/env/pin/SBOM checks; hosted Production+Staging rehearsals; failure injection; restore/rollback; cookie/R2/queue/monitoring acceptance | Same exact candidate as PS8A/B; Docker CI | Only `docs/pre-server/evidence/PS8C_INFRA_<SHORTSHA>.md`; service/failure/restore matrices; accepted-JSON summary; verdict; zero source diff | PS8C |
| 7 / PS9 | Final audit, integration, tag and freeze | Independent PR/diff review; `bun audit`; both aggregate gates; browser/full-stack/R3/R4/Production rehearsals; migration/backup/restore/rollback/secret scan; tag resolve proof | One final immutable SHA | `FINAL_PRE_SERVER_ACCEPTANCE.md`, release notes, manifest/SBOM/checksum, exact SHA/tag/PR/test summary and `PRE-SERVER GO` or `NO-GO` | Central reviewer |
| 8 / PS10 | New feature after freeze | Impact analysis first; phase-specific gates plus every still-applicable permanent gate | Only after PS9 GO and an approved complete feature definition | Separate branch/PR, truth inputs, threat/data/API/UI/test plan and zero-regression evidence | Assigned after intake |

## Permanent workflows

The existing `package.json` `check` chain includes the three PS0 audits and the broader product gates. The existing `backend/composer.json` `check` chain includes validation, locked advisory checks, contract/OpenAPI audits, tests, static analysis and formatting verification.

The six established GitHub workflows referenced by `docs/PHASES.md` are:

1. CI
2. Backend CI
3. Full-stack Integration CI
4. Browser Acceptance CI
5. R3 Final Gate
6. R4 Staging Package CI

PS9 requires every applicable final workflow and rehearsal against the same candidate content. A green run on another SHA is not final evidence.
