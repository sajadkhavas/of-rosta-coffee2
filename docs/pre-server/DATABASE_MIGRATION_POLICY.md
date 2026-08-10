# ROSTA Database Migration Policy

## Source of truth

Laravel migrations under `backend/database/migrations` are the authoritative production-schema migration source. Repository rules in `AGENTS.md` must be followed for any applicable shadow/schema synchronization.

Deployment evidence in `deploy/staging` already treats schema migration as forward execution with `php artisan migrate --force`; the Phase 22 audit explicitly rejects using `migrate:rollback` as the image rollback strategy.

## Ownership

Financial model and financial migration changes are **PS4.1/PS4.2-only**. No other pre-server phase may add, rewrite or opportunistically alter financial persistence.

A non-financial phase that discovers a required financial schema change must record the requirement and hand it to PS4.1 or PS4.2 according to the wave contract.

## Expand/contract rule

Production-compatible schema changes use an expand/backfill/switch/contract sequence:

1. **Expand** — add new tables/columns/indexes in a way that old application code can tolerate.
2. **Deploy compatible code** — code must handle both old and expanded states where a rolling transition exists.
3. **Backfill** — move historical data with a resumable, observable, bounded operation.
4. **Switch** — make the new field/path authoritative only after backfill and compatibility evidence pass.
5. **Contract later** — remove obsolete columns/constraints only in a later accepted release after all running code no longer depends on them.

Do not combine a destructive rename/drop with the first code deployment that needs the replacement.

## Additive and backward-compatible requirements

Before PS8, migrations should prefer:

- new nullable columns before required columns;
- new tables instead of repurposing unrelated existing columns;
- indexes/constraints introduced only after existing data is proven compatible;
- explicit defaults only when the default is genuine domain truth;
- online-safe/index strategies appropriate to the real production database.

Do **not** invent defaults for financial status, tax, commission, carrier, bank/provider identifiers, payout state or reconciliation truth merely to satisfy a non-null constraint.

## Zero-downtime rules

A migration intended for production must document:

- expected table(s) and data volume;
- lock/blocking risk;
- whether application versions N and N+1 can run against the expanded schema;
- read/write compatibility during rollout;
- backfill strategy;
- observable completion signal;
- forward-fix plan;
- rollback/image rollback behavior.

Long-running data movement must not be hidden inside a request lifecycle or an unbounded migration transaction.

## Backfills

Backfills must be:

- idempotent or safely resumable;
- chunked/bounded for real data volume;
- observable through counts/checkpoints without logging private data or secrets;
- safe to retry after interruption;
- separate from external provider side effects;
- verified before a new NOT NULL/unique/business invariant is enforced.

When a backfill requires business truth that source control does not have, the migration must remain fail-closed and wait for approved input rather than creating synthetic values.

## Rollback and forward-fix

Default production recovery is:

1. stop/abort the bad rollout;
2. keep the schema at the latest forward-compatible state;
3. roll application images back only when the older image is compatible with the expanded schema;
4. ship a forward-fix migration/code change when schema correction is required.

Laravel `down()` methods are development/rehearsal aids, not the default production recovery plan. A destructive schema rollback must be separately reviewed, backed up, tested and explicitly authorized.

The existing `scripts/audit-phase22-staging.mjs` requires image rollback and restore behavior that does not call `migrate:rollback`.

## Data backup and destructive operations

Before a destructive migration or restore can be accepted for production:

- a real backup must exist;
- checksum/integrity evidence must exist;
- restore must have been rehearsed against the actual database technology;
- the recovery owner and recovery point must be recorded;
- no backup payload or secret may be committed or uploaded as CI evidence.

Existing source evidence includes `deploy/staging/backup.sh`, `deploy/staging/restore-backup.sh` and `deploy/staging/rollback.sh`; their existence does not prove a production backup has run.

## Financial safety

PS4.1/PS4.2 financial migrations must additionally prove:

- immutable/auditable monetary history is not silently rewritten;
- amount and currency units are explicit;
- settlement/refund/payout state is not inferred from a provider callback without verification;
- backfills do not create fake payment/refund/payout success;
- constraints reflect approved business rules, not guessed rates.

## PS8 acceptance freeze and PS9 final freeze

PS8A/B/C are evidence-only and introduce no schema change. If they find a release blocker, the freeze reopens for the owning implementation phase; after the accepted fix, all three PS8 audits restart from the same new candidate SHA. PS9 does not introduce schema scope and does not tag until the retested candidate passes.
