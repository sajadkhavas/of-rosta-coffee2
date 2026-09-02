# ROSTA PS10 — Final Source Acceptance Contract

## Purpose

PS10 is the post-freeze launch-capability closure required after the PS9 `PRE-SERVER GO` release exposed source surfaces and operational contracts that still needed to be closed before real server activation.

PS10 does not mutate the PS9 release. It produces a new accepted source release after all exact-head gates pass and the merge identity is sealed.

## Immutable lineage

- Historical PS9 tag: `rosta-pre-server-2026-09-01`
- Historical PS9 accepted SHA: `ab96dd2280f9e1870455cadc10297ee0fa20a308`
- PS10 source branch: `phase/rosta-ps10-launch-capability-closure`
- Integration target: `integration/rosta-release-candidate`
- PS10 release tag: `rosta-pre-server-2026-09-02`

The PS9 tag remains permanently associated with the PS9 SHA. A PS10 acceptance must fail rather than move or reuse that tag.

## Source acceptance requirements

A PS10 source candidate is acceptable only when all of the following are true:

1. The candidate descends from the PS9 accepted SHA.
2. The candidate diff passes `git diff --check`.
3. The launch-critical route, order-resolution, seller settlement and freshness contracts are represented in source and permanent tests.
4. Production freshness remains fail-closed if the required policy is absent.
5. Integrated test environments explicitly configure the tested freshness policy instead of changing the production default.
6. All required exact-head frontend, backend, browser, finance, infrastructure, staging and production-package workflows succeed.
7. No unresolved pull-request review threads remain.
8. The PR is merged with a normal merge commit and the expected source head is locked at merge time.
9. The merge commit tree is byte-for-byte Git-tree equivalent to the exact source head that passed the acceptance matrix.
10. The PS10 release workflow publishes a new tag and release evidence without modifying the PS9 tag.

## Required exact-head workflow matrix

The accepted source head must have successful completed runs for:

- `CI`
- `Backend CI`
- `Full-stack Integration CI`
- `Browser Acceptance CI`
- `R3 Final Gate`
- `R4 Staging Package CI`
- `Production Package CI`
- `PS8A Frontend Acceptance`
- `PS8B Backend Finance Acceptance`
- `PS8C Infrastructure Acceptance`
- `PS1 Backend Wrapper CI`
- `PS9 Final Pre-server Freeze`
- `PS10 Final Source Release`

A cancelled, skipped, stale-head or failed run is not acceptance evidence.

## Release evidence requirements

The published PS10 release must contain, at minimum:

- machine-readable final acceptance JSON;
- release lineage and exact-head workflow evidence;
- production frontend release manifest;
- SPDX JSON SBOM;
- checksums covering the release evidence and key dependency/package inputs;
- a freeze proof recording tag, merge SHA, source-head SHA and tree SHA.

## Final source verdict

The only source-level verdict emitted by this phase is:

`PRE-SERVER GO`

That verdict means the accepted source release is eligible to enter controlled production-server acceptance. It does not mean the public production runtime is active.

## External runtime acceptance still required

Before `PRODUCTION GO`, the server phase must still prove at minimum:

- host prerequisites, service ownership and firewall/public-port policy;
- production secret/env completeness and fail-closed feature switches;
- real MySQL, Redis, queue and encrypted session behavior;
- Cloudflare R2/object-storage configuration, upload and media processing;
- DNS for `rosta.shop`, `api.rosta.shop` and the media host;
- TLS issuance and renewal;
- Kavenegar OTP/notification delivery with approved production configuration;
- Zarinpal request, redirect, callback and verify with the approved merchant;
- refund, payout/settlement and reconciliation operational controls;
- off-host backup, checksum and disposable restore;
- monitoring and alert delivery;
- controlled activation, smoke tests, rollback and roll-forward.

If server acceptance discovers a source defect, deployment stops and the defect returns to GitHub. A new accepted source release is required; existing accepted tags are never force-moved.
