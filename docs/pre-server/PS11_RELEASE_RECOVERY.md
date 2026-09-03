# ROSTA PS11 Release and Recovery Contract

## Immutable identities

- Previous accepted release: `rosta-pre-server-2026-09-02` -> `8dd13dda3a1e8a9b240112a659586f6eac52cdb3`
- PS11 release tag: `rosta-pre-server-2026-09-03`

Neither historical tag may be moved to make a later build appear accepted. A moved or conflicting tag is a release failure.

## Source recovery

If PS11 must be withdrawn before real production activation, the known-good source fallback is the immutable PS10 release tag `rosta-pre-server-2026-09-02`.

Application rollback and database rollback are separate decisions. Never infer that restoring application source makes it safe to destructively reverse data written by PS11.

## Financial/growth data recovery

- Partner commission accruals and reversals are append-only financial records.
- Do not edit/delete an accrual to represent a refund; use the reversal path.
- Do not truncate growth/commission tables as a rollback shortcut once real records may exist.
- Preserve unique source/idempotency identities so retries remain replay-safe.
- Preserve outbox records; recover delivery by replay/retry instead of inventing a second financial event.
- If a policy defect is found, publish a corrected version/effective interval rather than rewriting historical policy snapshots.

## Migration recovery

Before production data exists, disposable environments may use normal migration reset/rebuild procedures. After production data exists, any schema rollback must be proven data-safe against the deployed migration set and backups. If destructive reversal is unsafe, prefer a forward corrective migration plus application rollback/feature disablement.

## Runtime/provider recovery boundary

This source release does not claim real provider or VPS activation. When deployment occurs, the existing production package backup/restore/readiness/release process remains authoritative. Provider credentials and business-policy values must remain outside Git evidence.

## Release publication recovery

The PS11 GitHub workflow is idempotent with respect to the release identity:

1. if the PS11 tag does not exist, create it only for the accepted merge commit;
2. if it exists, require it to resolve to the same accepted commit;
3. if a release exists, verify its tag identity instead of moving the tag;
4. on later phases, verify the frozen PS11 tag/release and ancestry, then skip republishing PS11.

A conflicting existing tag or release is fail-closed and requires investigation, not force-push or tag replacement.