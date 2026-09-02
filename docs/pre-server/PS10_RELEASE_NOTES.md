# ROSTA PS10 — Launch Capability Closure Release

## Release identity

- Baseline frozen release: `rosta-pre-server-2026-09-01` at `ab96dd2280f9e1870455cadc10297ee0fa20a308`
- Candidate branch: `phase/rosta-ps10-launch-capability-closure`
- New release tag after accepted merge: `rosta-pre-server-2026-09-02`
- Target branch: `integration/rosta-release-candidate`
- Verdict after the PS10 release workflow publishes the tag: `PRE-SERVER GO`

The PS9 tag is historical and must never be moved, deleted, reused or treated as the identity of PS10. PS10 creates a new release-specific tag for the accepted merge commit.

## Scope closed by PS10

PS10 closes launch-critical source gaps discovered after the PS9 freeze while keeping provider and server activation outside source-only acceptance:

- regenerate and verify the TanStack route tree so intended launch surfaces are actually registered;
- close the customer order-resolution and help path;
- add seller settlement destination profile contracts;
- hard-gate settlement verification before financial operations can rely on that destination;
- add fail-closed coffee freshness dispatch policy;
- make the freshness policy explicit in integrated acceptance runtimes instead of weakening the production guard;
- update fixtures and regression coverage required by the new launch contracts.

## Required exact-head acceptance

PR merge is forbidden unless the exact final source head has successful runs for all of these workflows:

1. `CI`
2. `Backend CI`
3. `Full-stack Integration CI`
4. `Browser Acceptance CI`
5. `R3 Final Gate`
6. `R4 Staging Package CI`
7. `Production Package CI`
8. `PS8A Frontend Acceptance`
9. `PS8B Backend Finance Acceptance`
10. `PS8C Infrastructure Acceptance`
11. `PS1 Backend Wrapper CI`
12. `PS9 Final Pre-server Freeze` (historical freeze verification)
13. `PS10 Final Source Release` (release-contract verification)

The merge must be a normal merge commit. Squash, rebase, amend, force-push and published-history rewriting are not accepted.

## Release publication contract

On the accepted push to `integration/rosta-release-candidate`, the PS10 release workflow must:

- prove the first merge parent is the PS9 accepted integration SHA;
- identify the merged PR source head as the second parent;
- prove the merge commit tree is identical to that exact tested source-head tree;
- verify all required exact-head PR workflows completed successfully;
- verify the PS9 tag still resolves to the PS9 accepted SHA;
- build the production SSR artifact from frozen dependencies;
- generate a release manifest and SPDX SBOM;
- seal checksums and machine-readable acceptance evidence;
- create or verify the new annotated PS10 tag;
- publish the GitHub release only for that tag and commit.

If any identity, lineage, tree-equivalence, gate or release check fails, publication must stop.

## Runtime boundary

`PRE-SERVER GO` is still not `PRODUCTION GO`. This source release does not claim that the real VPS, DNS, TLS, production MySQL/Redis, Cloudflare R2, Kavenegar delivery, Zarinpal merchant/payment verification, refund/payout operations, monitoring delivery, off-host backup or public cutover are active.

Those claims require controlled real-server acceptance after this release.

## Freshness policy note

Production remains fail-closed when `ROSTA_MAX_DISPATCH_ROAST_AGE_DAYS` is not configured. Integrated CI explicitly supplies `30` days because the project regression contract tests that policy and acceptance fixtures use a recent roast batch. CI configuration must not be interpreted as a production default.
