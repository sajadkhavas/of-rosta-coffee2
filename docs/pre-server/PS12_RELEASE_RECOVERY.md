# ROSTA PS12 Release and Recovery Contract

## Immutable identities

- Previous accepted release: `rosta-pre-server-2026-09-03` -> `af7a871d48ca4865a9509b8271b66ed380c57d24`
- PS12 release tag: `rosta-pre-server-2026-09-05`

Neither historical tag may be moved to make a later build appear accepted. A moved or conflicting tag is a release failure.

## Source recovery

If PS12 must be withdrawn before real production activation, the known-good source fallback is the immutable PS11 release tag `rosta-pre-server-2026-09-03`.

Application rollback and database rollback are separate decisions. Restoring source does not make destructive reversal of cafe or wholesale records automatically safe.

## Cafe and wholesale data recovery

- Do not silently convert a verified/suspended/rejected cafe to another state merely to match an older application release.
- Preserve cafe memberships and role scope where real operational ownership records may exist.
- Preserve wholesale tier history/data unless a data-safe corrective migration explicitly proves deletion is acceptable.
- If a wholesale pricing defect is found, correct current tiers or disable wholesale entitlement while preserving order/quote financial evidence.
- Existing order Financial Truth and paid/refunded financial records must never be rewritten to make a rollback appear consistent.
- Stale Quote pricing must continue to fail closed rather than be force-accepted after rollback.

## Migration recovery

Before production data exists, disposable environments may use normal migration reset/rebuild procedures. After production data exists, schema rollback must be proven data-safe against the deployed migration set and backups. Prefer a forward corrective migration plus application rollback/feature disablement when destructive reversal is unsafe.

## Directory/location recovery

Public directory availability may be disabled independently of cafe identity. Disabling proximity search must not require deleting persisted cafe coordinates. User request coordinates are not modeled as continuous tracking history by PS12.

## Runtime/provider recovery boundary

This source release does not claim real provider or VPS activation. When deployment occurs, the accepted production package backup/restore/readiness process remains authoritative. Provider credentials and business contractual values must stay outside Git evidence.

## Release publication recovery

The PS12 GitHub workflow is idempotent with respect to release identity:

1. if the PS12 tag does not exist, create it only for the accepted merge commit;
2. if it exists, require it to resolve to the same accepted commit;
3. if a release exists, verify its tag identity instead of moving the tag;
4. on later phases, verify the frozen PS12 tag/release and ancestry, then skip republishing PS12.

A conflicting existing tag or release is fail-closed and requires investigation, not force-push or tag replacement.