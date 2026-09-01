# ROSTA Pre-server Freeze — 2026-09-01

This release is the PS9 source-controlled **pre-server freeze** for Rosta.

## Verdict

`PRE-SERVER GO` is valid only when tag `rosta-pre-server-2026-09-01` resolves to the same commit recorded by the successful `PS9 Final Pre-server Freeze` workflow and its attached machine-readable evidence.

## What is frozen

- TanStack Start / React production SSR frontend and browser acceptance surface;
- Laravel backend API, auth/session, catalog, seller/admin operations and operational contracts;
- financial truth, payment/refund/payout/reconciliation source contracts;
- OTP/notification and secure media source contracts;
- staging and production deployment packages;
- MySQL/Redis/queue/object-storage staging rehearsal behavior;
- release manifest, SPDX SBOM and SHA-256 integrity evidence.

## Final audit correction before freeze

PS9 independently detected and closed a pre-freeze API/provider contract gap before this release was allowed to proceed. PR #107:

- moved Zarinpal production request/verify/StartPay defaults to the currently documented `payment.zarinpal.com` endpoints;
- restored exactly one baseline `throttle:api` across all versioned API registrars, including quiz/review routes;
- aligned affected OpenAPI contracts with runtime behavior;
- added regression coverage and passed the complete applicable workflow matrix before normal merge.

## Evidence attached to this release

The finalization workflow attaches, at minimum:

- `frontend-release-manifest.json`;
- `rosta-<SHA>.spdx.json`;
- `ps9-final-acceptance.json`;
- `PS9_FINAL_EVIDENCE.md`;
- `ps9-checksums.sha256`;
- `freeze-proof.json`;
- `ps9-release-assets.sha256`.

## Explicit boundary

This release does **not** claim that the real server or external providers are active. It does not claim production DNS/TLS, live Kavenegar delivery, live Zarinpal merchant acceptance, production R2 connectivity, live database state, off-host backup retention or delivered monitoring alerts.

The next work is controlled server/runtime acceptance using exactly this frozen release identity. If that process discovers a source defect, deployment stops and a new source-controlled release must be accepted; the freeze tag is never moved.
