# ROSTA PS9 — Final Pre-server Acceptance

Status rule: **PRE-SERVER GO if and only if** the canonical tag `rosta-pre-server-2026-09-01` resolves to the exact `integration/rosta-release-candidate` commit whose `PS9 Final Pre-server Freeze` workflow completed successfully and whose release evidence reports `PRE-SERVER GO`.

PS9 start baseline: `integration/rosta-release-candidate@40154b452d78e473d4f0da1e620e63bc7ffbbacc`.

PS9 branch: `phase/rosta-ps9-final-freeze`.

Canonical freeze tag: `rosta-pre-server-2026-09-01`.

## Purpose

PS9 is the final source-controlled acceptance boundary before any real server activation. It freezes one reviewed, reproducible release identity after the previously integrated PS0–PS8 acceptance path and the pre-PS9 API/provider contract blocker closure.

PS9 does **not** claim that the production VPS, DNS, TLS, provider credentials, live database, object storage, SMS delivery, payment rails, monitoring delivery or public cutover are already active. Those are post-freeze runtime facts and remain separate from this verdict.

## Pre-PS9 blocker closure

The independent PS9 audit found two source-contract defects before freeze:

1. Zarinpal production defaults still referenced historical request/verify/StartPay hosts instead of the current documented `payment.zarinpal.com` endpoints.
2. baseline `throttle:api` coverage was fragmented across API registrars, leaving the separately registered quiz/review routes outside the intended common throttle contract.

PR #107 repaired those issues from the current release-candidate baseline, aligned the affected OpenAPI contracts, added regression coverage, passed all eleven applicable workflows on exact head `340a5c6660e90f19fdabc7efa99097f630d4018d`, and was normally merged as `40154b452d78e473d4f0da1e620e63bc7ffbbacc`.

Stale direct-to-release-candidate PRs #81, #83, #86 and #87 were explicitly dispositioned and closed rather than silently entering the freeze.

## Final acceptance layers

### 1. Exact-head regression matrix

The PS9 PR deliberately includes a backend freeze-contract regression so the established pull-request workflow matrix runs again on the same PS9 head. Merge is forbidden until every applicable workflow is successful on that exact candidate, including:

- CI;
- Backend CI;
- Full-stack Integration CI;
- Browser Acceptance CI;
- R3 Final Gate;
- R4 Staging Package CI;
- Production Package CI;
- PS8A Frontend Acceptance;
- PS8B Backend Finance Acceptance;
- PS8C Infrastructure Acceptance;
- PS1 Backend Wrapper CI;
- PS9 Final Pre-server Freeze.

### 2. Final integrated/browser gate

The dedicated PS9 workflow independently re-runs the frozen dependency installs, High/Critical dependency policy, locked Composer advisory check, complete frontend and backend aggregate gates, fresh MySQL migrations, strict readiness, deterministic acceptance fixtures, production SSR build and every real Playwright browser journey against an integrated Laravel/Redis/SSR runtime.

### 3. Final staging rehearsal

The exact candidate re-runs the existing isolated R4 staging rehearsal. That rehearsal proves, without touching a real server:

- immutable frontend/backend image builds;
- MySQL, Redis, queue and S3-compatible object-storage acceptance;
- forward migrations;
- local edge/SSR/security/noindex behavior;
- transaction-consistent database backup and checksum;
- disposable restore with migration-count equivalence;
- image-only rollback without schema rollback;
- current/previous release bookkeeping;
- secret-safe evidence handling.

### 4. Final production package gate

The exact candidate re-runs production topology audit, production contract test and isolated production edge rehearsal, then builds the frontend, backend application and backend web images from the real production Dockerfiles. Application image identities and required non-root runtime users are verified.

This remains a pre-server rehearsal. It does not obtain public certificates, connect to a live production database or activate external providers.

### 5. Release identity, SBOM and checksums

After the runtime gates pass, PS9 generates and retains:

- frontend release manifest bound to the exact candidate SHA;
- SPDX JSON SBOM generated from the exact checked-out workspace;
- SHA-256 checksums covering lockfiles, Dockerfiles, production topology, release documents, manifest, SBOM and the machine-readable acceptance verdict;
- machine-readable `ps9-final-acceptance.json` with the literal candidate SHA/tree and `PRE-SERVER GO` verdict;
- workflow artifacts retained for final evidence.

GitHub documents SPDX SBOM export/generation as a supply-chain transparency mechanism. Third-party actions in the PS9 workflow are pinned to full commit SHAs rather than mutable tags.

## Immutable tag and release rule

The PS9 pull request itself never creates a tag or release.

After a normal merge into `integration/rosta-release-candidate`, the PS9 workflow runs again on the resulting merge SHA. Only after its integrated/browser, staging, production and release-evidence jobs are all successful may the finalization job:

1. create annotated tag `rosta-pre-server-2026-09-01` on that exact merge commit, or verify an existing tag already resolves to that same commit;
2. refuse to move the tag if it already protects a different SHA;
3. resolve the tag back to the exact commit as proof;
4. publish a GitHub release using `docs/pre-server/PS9_RELEASE_NOTES.md`;
5. attach the final manifest, SPDX SBOM, checksums and freeze proof to that release.

GitHub releases are tag-based; the tag is therefore the immutable source identity for this pre-server release rather than the moving branch name.

## Freeze invariants

1. No direct edit to `main` or `integration/rosta-release-candidate` is part of PS9 implementation.
2. PS9 uses a normal merge commit; no squash, rebase, amend, force-push or history rewrite is accepted.
3. The release candidate is not mergeable while another unrelated PR targeting the same release-candidate branch remains open.
4. All product/runtime defects discovered by PS9 must be fixed before freeze, not documented away as a PASS.
5. Dependency/security gates may not be skipped, weakened or permanently bypassed.
6. Release evidence must not contain real secrets, database dumps, private keys, OTP values or provider credentials.
7. The freeze tag is never force-moved.
8. A successful pre-server freeze does not imply successful real-server deployment.

## External/runtime boundary after GO

Even after `PRE-SERVER GO`, the following remain runtime acceptance items:

- host prerequisites and service ownership;
- protected production environment values and provider credentials;
- real DB/Redis/R2 connectivity and data state;
- firewall and public port policy;
- `rosta.shop`, `api.rosta.shop` and media DNS;
- real TLS certificate issuance/renewal;
- real Kavenegar delivery;
- real Zarinpal request/return/verify behavior with approved merchant configuration;
- backup retention/off-host copy and real-host restore rehearsal;
- monitoring/alert delivery;
- controlled activation, smoke acceptance and rollback/roll-forward.

Any source defect discovered during those steps invalidates deployment of that candidate; the defect returns to GitHub and requires a new accepted release identity.

## Canonical proof

Because a Git commit cannot contain its own future merge SHA, the literal final freeze SHA is recorded in machine evidence and GitHub release metadata. The canonical proof is:

`rosta-pre-server-2026-09-01^{commit}` = the final `integration/rosta-release-candidate` merge commit = `candidate_sha` in `ps9-final-acceptance.json` = `commit` in `freeze-proof.json`.

When those identities agree and the final PS9 workflow is successful, the project state is **PRE-SERVER GO**.
