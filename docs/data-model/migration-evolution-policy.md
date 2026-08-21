# Migration and Data-Evolution Policy

Status: ARCH-0.3 required schema-change policy

## 1. Forward-only rule

Applied migrations are historical deployment records. Once a migration can have run outside an ephemeral local environment, do not edit it to change production semantics.

Corrections use a **new forward migration**. Git history rewriting is not a database rollback strategy.

## 2. Migration design checklist

Every schema migration must state or make reviewable:

- owning domain/capability;
- exact new/changed tables and columns;
- null/default behavior;
- foreign-key delete/update semantics;
- unique/index strategy;
- backfill strategy;
- compatibility window for old/new code;
- rollback/roll-forward reality;
- data volume/lock risk;
- privacy/retention impact;
- financial/custody/history impact;
- expected tests and production preflight.

## 3. Expand / migrate / contract

For changes that cannot be safely deployed atomically, prefer:

1. **Expand** — add backward-compatible columns/tables/indexes.
2. **Migrate** — dual-read/write only when explicitly required; backfill in bounded/idempotent batches; verify counts/invariants.
3. **Switch** — move canonical reads/writes to the new representation with tests/observability.
4. **Contract** — remove legacy structures only after proving no code, job, API, report or historical-data need remains.

Do not drop/rename a live column in the same release that first stops using it unless the deployment topology proves compatibility.

## 4. Backfills

Large or externally relevant backfills must be:

- idempotent/resumable;
- bounded/chunked when row count or locks justify it;
- observable with progress/failure evidence;
- safe under retry;
- deterministic from authoritative source data;
- verified with postconditions before legacy data is removed.

Avoid long application-startup migrations that combine schema alteration, network calls and large data rewrites.

## 5. No provider/network work inside migrations

Migrations must not call payment, SMS, carrier, object-storage or partner APIs. External systems are unavailable/unpredictable during deploy and make migration rollback impossible to reason about.

Persist schema/defaults only. Provider synchronization belongs to explicit application commands/jobs with idempotency and reconciliation.

## 6. Financial migrations

Financial schema changes receive stricter review. They must preserve:

- historical amounts/currency;
- allocation ownership;
- policy/pricing version;
- provider/source references;
- paid/refunded/reversed truth;
- reconciliation evidence.

Never recalculate historical money from current Product/commission/tax configuration during a migration unless the migration is an explicitly audited correction with reconciliation evidence.

## 7. Status/state evolution

Many current state fields are strings interpreted by application enums/services. Adding a new state requires:

- owning state machine update;
- serializer/API compatibility;
- query/index review;
- histories/events update;
- scheduler/job logic review;
- admin/customer/seller read-model behavior;
- tests for old and new persisted values.

Do not silently reuse a legacy string with a new meaning.

## 8. JSON/snapshot evolution

JSON snapshots/payloads need version discipline. For durable structures:

- include/version the schema or retain enough source/version metadata where interpretation can change;
- readers should tolerate historical fields/versions they are expected to support;
- do not rewrite historical snapshots merely to match the latest object shape;
- sensitive fields require retention/redaction review.

## 9. Index policy

Indexes must reflect actual lookup/locking workloads. Review indexes for:

- foreign keys and tenant/seller scope;
- status + time queues;
- provider/reference/idempotency lookup;
- order/Sub-order/shipment timelines;
- financial owner/status/batch queries;
- expiry pruning;
- unique business invariants.

Every index has write/storage cost; do not add broad speculative indexes without a query/use-case.

## 10. Foreign keys and historical truth

Choose delete actions deliberately:

- cascade only for true aggregate-owned children;
- restrict where deletion could destroy commerce/finance/history integrity;
- null-on-delete for optional actor/provider references whose parent may disappear while evidence must survive.

Soft-deletion/status deactivation may be preferable to deleting seller/catalog entities referenced by historical commerce.

## 11. Identifier policy

Current domain records largely use ULIDs plus provider/business string identifiers where appropriate. New Core entities should follow existing identifier conventions unless there is a measured reason not to.

External provider IDs are attributes/references, not substitutes for internal durable IDs.

## 12. Seed/reference data

Seeded notification templates, statuses, policies and reference data are production data contracts after deployment. Changing an old migration/seed does not update an existing database.

Corrections require a forward data migration/command that:

- targets known old values/versions;
- is idempotent;
- does not overwrite legitimate operator-customized data without policy;
- records the new version/effective semantics where needed.

This rule directly applies to legacy seller-acceptance notification copy tracked in the debt register.

## 13. Production deployment compatibility

Production releases are immutable. Migrations are prepared/tested before server activation and must be runnable non-interactively from the release process.

If a release requires hand-editing schema, ad-hoc SQL repair or first-time migration design on the server, verdict is `SERVER_BLOCKED_SOURCE` and the fix returns to GitHub.

## 14. Rollback semantics

Not every data migration is safely reversible. A syntactic `down()` method is not proof of safe rollback after new production writes.

For destructive/data-transforming changes, the release plan must prefer roll-forward or document exact compatibility/restore requirements. Backup/restore is a disaster control, not a substitute for migration design.

## 15. Pre-merge validation

At minimum for a meaningful schema PR:

- migration tests on fresh database;
- upgrade path from supported prior schema where relevant;
- domain/unit/feature tests for changed invariants;
- static analysis/formatting;
- duplicate/idempotency/concurrency tests where relevant;
- rollback/roll-forward assessment;
- no unowned schema changes.

## 16. Production preflight

Before activation, prove:

- exact release SHA/artifact;
- database connectivity/version;
- backup availability and restore rehearsal policy;
- migration status and expected pending set;
- required extensions/config;
- free storage/resource headroom;
- queue/scheduler compatibility;
- provider/schema feature flags if needed.

Production is not the place to discover a migration's first successful run.
