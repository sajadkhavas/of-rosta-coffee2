# ROSTA PS11 Release Notes — Launch Operations & Growth Closure

## Release identity

- Previous frozen source: `rosta-pre-server-2026-09-02` -> `8dd13dda3a1e8a9b240112a659586f6eac52cdb3`
- Candidate PR: `#111`
- New source tag after accepted merge: `rosta-pre-server-2026-09-03`

The new tag is created only by the post-merge PS11 release workflow after exact-head PR evidence is verified. This document does not predeclare the future merge SHA.

## What PS11 adds

- Explicit Growth Partner, lead and attribution contracts, including B2B lead intake.
- Encrypted lead PII, safe metadata allowlisting, deterministic dedupe and exclusive attribution rules.
- Anti-self-referral boundaries for partner attribution and commission.
- Versioned maker/checker Partner Commission Policy.
- Append-only, idempotent paid-order commission accrual.
- Append-only successful-refund commission reversal with cumulative deterministic allocation.
- Durable Payment/Refund lifecycle wiring with source-unique commission publication.
- Correct validation of the existing grouped `ps4a-financial-truth-v1` order snapshot.
- Historical PS10 release workflow hardened so later phases verify PS10 immutability instead of incorrectly requiring later heads to equal the PS10 frozen SHA.

## Existing systems deliberately reused

PS11 does not duplicate already accepted seller operations, support inquiries, finance/admin controls, privacy storage contracts, browser acceptance, production packaging or infrastructure rehearsal. Their permanent audits remain part of the aggregate gates and are re-run on the PS11 exact head.

## Database and rollback notes

PS11 introduces growth/partner persistence required by the feature. The commission ledger is append-only by design. Once production data exists, rollback must not delete or rewrite commission history merely to restore an older application release; use a forward corrective migration or operational disablement where data preservation requires it.

Before any real production activation, deployment migration/backup/restore procedures remain governed by the accepted production package and runtime release process.

## Support SLA boundary

The source provides persisted support inquiry lifecycle/audit evidence, but this release does not fabricate a numeric first-response or resolution target. The approved business target must be supplied and recorded separately before it can truthfully be enforced as a production SLA.

## External activation

This is a source release. It does not by itself activate the production server, payment/SMS/R2/carrier/payout providers, DNS or live customer traffic.