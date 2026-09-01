# ROSTA Pre-server Acceptance Status

Status: canonical current execution status for the PS path

Last synchronized baseline: `integration/rosta-release-candidate@40154b452d78e473d4f0da1e620e63bc7ffbbacc`

This file tracks the current PS0-PS9 execution path. Older planning documents may preserve historical phase names and should not override the accepted/integrated state recorded here and in phase-specific acceptance records.

## Integrated phases

- PS0 — Pre-server contracts and architecture audits: INTEGRATED
- PS1 — Release & Security Hardening: INTEGRATED
- PS2 — OTP & Notifications: INTEGRATED
- PS3 — Secure Media Pipeline: INTEGRATED
- PS4.1 — Financial Truth Core: INTEGRATED
- PS4.2 — Refund, Payout & Reconciliation: INTEGRATED
- PS5.1 — Quiz / Recommendations / Review Safety: INTEGRATED
- PS5.2 — Seller Organization & Availability: INTEGRATED
- PS5.3 — Carrier & Non-financial Admin Operations: INTEGRATED
- PS5.4 — Seller/Admin Workspaces & KPI Composition: INTEGRATED
- PS6B — Backend Refactor, Queue Reliability & Observability: INTEGRATED
- PS6A — Frontend Quality Freeze: INTEGRATED
- PS7 — Production Deployment Package / Rehearsal: INTEGRATED
- PS8A — Frontend Acceptance Audit: INTEGRATED (`5d6035244012679106008799051c7863c7f2ffce`)
- PS8B — Backend & Finance Acceptance Audit: INTEGRATED (`75c6b6771608a7e31c3ea93ef1e998d23b8b5e62`)
- PS8C — Infrastructure Acceptance Audit: INTEGRATED (`6631c68dda8036c3e5da95fe1da3035b72d07f81`)

## Pre-PS9 blocker closure

The independent PS9 audit identified a current-source API/provider contract blocker before freeze. PR #107 corrected the production Zarinpal request/verify/StartPay defaults, restored exactly one baseline `throttle:api` across all versioned API registrars, aligned affected OpenAPI contracts and added regression coverage.

PR #107 final head `340a5c6660e90f19fdabc7efa99097f630d4018d` passed all eleven applicable workflows on the same exact SHA: CI #893, Backend CI #571, Full-stack Integration CI #428, Browser Acceptance CI #418, R3 Final Gate #413, R4 Staging Package CI #378, Production Package CI #19, PS8A Frontend Acceptance #15, PS8B Backend Finance Acceptance #9, PS8C Infrastructure Acceptance #14 and PS1 Backend Wrapper CI #113. It was then normally merged as `40154b452d78e473d4f0da1e620e63bc7ffbbacc`.

Stale direct-to-release-candidate PRs #81, #83, #86 and #87 were explicitly dispositioned and closed rather than being allowed to enter the freeze from obsolete baselines.

## PS9 final state — mechanically derived

- PS9 — Final Integration, Tag & Pre-server Freeze: the authoritative state is derived from the immutable proof below, not from a manually edited status word.
- Canonical acceptance contract: `docs/pre-server/FINAL_PRE_SERVER_ACCEPTANCE.md`.
- Canonical release notes: `docs/pre-server/PS9_RELEASE_NOTES.md`.
- Canonical freeze tag: `rosta-pre-server-2026-09-01`.

The PS9 state is **PRE-SERVER GO** exactly when all of the following are true:

1. tag `rosta-pre-server-2026-09-01` exists and resolves to a commit on `integration/rosta-release-candidate`;
2. that exact commit's `PS9 Final Pre-server Freeze` push workflow completed successfully;
3. its release evidence reports literal verdict `PRE-SERVER GO` and the same candidate SHA/tree;
4. the GitHub Release for the canonical tag exists with the final manifest, SPDX SBOM, checksums and freeze proof attached.

If any of those facts is absent or disagrees, PS9 is **NOT YET PRE-SERVER GO**. This derived rule keeps the status record truthful both before finalization and after the immutable tag/release is created, without adding a post-freeze source commit.

## PS8 closure evidence

PS8C final candidate `6f29b602b8612eab975267e77f90b4c98a9f987a` passed every applicable pull-request workflow on the same exact SHA, including CI #890, Backend CI #569, Full-stack Integration CI #426, Browser Acceptance CI #416, R3 Final Gate #411, R4 Staging Package CI #376, Production Package CI #17, PS8A Frontend Acceptance #13, PS8B Backend Finance Acceptance #7, PS8C Infrastructure Acceptance #11 and PS1 Backend Wrapper CI #111. PR #102 was then merged normally into the release candidate as merge commit `6631c68dda8036c3e5da95fe1da3035b72d07f81`.

## Server boundary

The real production server is not a development/debugging environment. Real server activation starts only after PS9 freezes one exact accepted release SHA.

After PS9, server-day work is limited to host prerequisites, protected secrets/external credentials, database/Redis/storage connectivity, firewall/DNS/TLS, materializing the exact frozen release, versioned preflight, activation, acceptance, backup/restore/rollback evidence and real monitoring/alert proof.

No source fix discovered on the real server may be edited in place. It must return to its owning source phase and pass the relevant gates before a new release is frozen.
