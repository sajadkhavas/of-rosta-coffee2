# Rosta canonical phase register

This file is the single source of truth for phase order, completion evidence and the path to
staging. Older Phase 1–22 and R1–R5 documents remain implementation evidence; they do not define
an alternative execution path.

## Status language

- **verified**: implementation is present in the current program ancestry and its permanent
  GitHub gates passed.
- **closure verification**: implementation is present on the R5K product branch and must pass the
  six formal PR gates before merge.
- **runtime pending**: repository work is complete, but the result can only be proven on staging.
- **external pending**: a credential, DNS record, account or operational approval cannot be
  created from source code.

## The ten canonical phases

Phases must be executed in this order. A later phase may add evidence to an earlier phase, but it
must not create another numbering system.

| ID | Canonical phase | Legacy mapping | Cumulative evidence tip | Current status |
|---|---|---|---|---|
| C0 | Product identity and customer experience baseline | Phase 1–5 | `933c1a8` | verified |
| C1 | Security, browser trust and public API freeze | Phase 6 | `933c1a8` | verified |
| C2 | Laravel commerce and persistence foundation | Phase 7–12 | `933c1a8` | verified |
| C3 | Seller/admin/content integration and production UX | Phase 13–16 | `933c1a8` | verified |
| C4 | Release, recovery and staging architecture | Phase 17–22 | `3198da9` | verified in current ancestry; runtime pending |
| C5 | Deterministic frontend program gate | R1A–R1D | `4622e88` | verified |
| C6 | Deterministic backend and infrastructure gate | R2A–R2D | `457ac8c` | verified |
| C7 | Integrated runtime and adversarial acceptance | R3A–R3D | `5b92ec3` | verified |
| C8 | Immutable staging package and hosted rehearsal | R4A | `f790022` | verified |
| C9 | Multi-roastery marketplace and program closure | R5A–R5K | `56a33e2` plus R5K | closure verification |

The cumulative product branch is `integration/rosta-r5-marketplace`. After R5K passes all formal
gates, the exact merge SHA is frozen on `integration/rosta-release-candidate`. Staging accepts only
an immutable SHA in that release-candidate ancestry. `main` is not a staging source.

## Evidence matrix

| Phase | Primary code or document evidence | Permanent audit/test evidence | GitHub gate |
|---|---|---|---|
| C0 | `src/routes`, design system, whole-bean product surfaces | unit tests, TypeScript, ESLint, build | CI |
| C1 | `docs/ROSTA_PHASE_6_SECURITY_AUDIT.md`, runtime schemas, auth guards | `audit:phase6`, browser security tests | CI, Browser Acceptance CI |
| C2 | `backend/app`, migrations, OpenAPI and commerce services | `composer check`, PHPUnit, Larastan, Pint | Backend CI |
| C3 | seller/admin panels, live public loaders, CMS and SEO surfaces | `audit:phase16`, `audit:phase20`, `audit:phase21` | CI, Full-stack Integration CI |
| C4 | `deploy/staging`, Phase 22 runbook and recovery scripts | `audit:phase17`, `audit:phase22` | R4 Staging Package CI |
| C5 | `bun.lock`, `src/routeTree.gen.ts`, frontend production contract | frozen install and full `bun run check` | CI |
| C6 | `backend/composer.lock`, MySQL/Redis contracts | `composer check`, infrastructure acceptance | Backend CI |
| C7 | acceptance fixtures and full-stack/browser journeys | `audit:r3b`–`audit:r3d`, Playwright | Full-stack, Browser, R3 Final Gate |
| C8 | immutable images and `deploy/staging/rehearsal.sh` | `audit:r4a`, hosted rehearsal | R4 Staging Package CI |
| C9 | `docs/r5`, marketplace services, Hub, delivery and settlement | `audit:r5a`–`audit:r5k`, feature/unit tests | all six formal gates |

## C9 implementation sequence

| Step | Scope | Integration merge | Product evidence | Status |
|---|---|---|---|---|
| R5A | marketplace domain contract | `6ad73c0` | `4a539ac` | verified |
| R5B | multi-roastery schema | `c690589` | `d477517` | verified |
| R5C | grouped cart, quote and order | `393025b` | `44c4fa8` | verified |
| R5D | roastery packaging | `7560909` | `d8db9e4` | verified |
| R5E | roastery grinding capability | `7375dc7` | `4c94893` | verified |
| R5F | customer grinding selection | `8a3e1a5` | `e32d8cf` | verified |
| R5G | Rosta Hub eligibility and routing | `8691eda` | `02b2a7c` | verified |
| R5H | contractual fulfilment and incidents | `da5c8b7` | `3d01828` | verified |
| R5I | delivery, settlement and payouts | `adcdcfc` | `4aa26ad` | verified |
| R5J | Hub operations and chain of custody | `56a33e2` | `a344fd2` | verified |
| R5K | privacy correction, phase closure and release lineage | current product branch | `audit:r5k` | closure verification |

R5H supersedes the early R5A proposal for manual seller acceptance/rejection. New sub-orders begin
`awaiting_payment`; successful payment commits them automatically. Sellers prepare and hand off
paid orders, and exceptional inability to fulfil enters the incident/refund workflow.

## Formal R5K exit gate

The R5K head must pass these six workflows on the same clean commit:

1. CI
2. Backend CI
3. Full-stack Integration CI
4. Browser Acceptance CI
5. R3 Final Gate
6. R4 Staging Package CI

Only then may it merge into `integration/rosta-r5-marketplace` and advance
`integration/rosta-release-candidate` without force-push.

## Work that belongs on staging

These items are not missing source implementation:

- Ubuntu host bootstrap and self-hosted runner registration
- real MySQL and Redis round trips
- TLS issuance and external security-header acceptance
- DNS resolution for staging frontend, API and media
- Cloudflare R2 PUT/GET, public delivery, CORS and cleanup
- secure Sanctum cookie and credentialed CORS acceptance
- backup, rollback and destructive restore drills
- signed `/var/lib/rosta/staging/reports/latest.json` with `"accepted": true`

## External inputs

The following must never be fabricated or committed:

- server access and public IPv4
- actual staging domains and operational ACME email
- Laravel, MySQL and Redis secrets
- dedicated staging R2 bucket, scoped access keys, endpoint, custom domain and CORS policy
- payment/SMS provider credentials and operational approvals
- legal/trust content and production ownership approvals

Real payment, refund execution, SMS, production money movement and Google indexing remain disabled
until their separate staging and operational gates pass.

## Non-canonical history

Legacy stacked PRs and their branches are evidence only. Once R5K is merged and the release
candidate is frozen, superseded Draft PRs must be closed with a pointer to this register. They must
not be merged into `main` or deployed directly.
