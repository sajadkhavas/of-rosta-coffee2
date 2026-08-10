# ROSTA Release Evidence Template

Use this template for every implementation handoff, each PS8 evidence audit and the PS9 final freeze. Do not remove sections; write `N/A` with a reason when a section truly does not apply.

## Identity

- PS:
- Title:
- Repository: `sajadkhavas/of-rosta-coffee2`
- Baseline branch:
- Baseline SHA:
- Phase branch:
- Phase head SHA:
- PR:
- Target branch:
- Evidence generated at (UTC):
- Operator/owner:

## Scope

### Intended changes

-

### Explicitly out of scope

-

### Changed files

-

### Shared-file ownership check

- Ownership source: `docs/pre-server/WAVE_BRANCH_OWNERSHIP.md`
- Cross-owner files changed:
- Conflict owner approval/evidence:
- Lockfiles changed? If yes, confirm PS1 ownership:
- Financial models/migrations changed? If yes, confirm PS4 ownership:
- Production deployment files changed? If yes, confirm PS7 ownership:
- Feature Freeze active? If yes, explain why any code change is allowed:

## Baseline proof

```text
git fetch --all --prune
exit:
output:
```

```text
git rev-parse integration/rosta-release-candidate
exit:
output:
```

Expected PS0 starting SHA:

`a4d8afed40e55ec6f84d25308c5cde9524042bb0`

Dirty-worktree check:

```text
git status --short
exit:
output:
```

If the environment cannot execute a command, mark it **UNPROVEN** and state the exact cause. Do not substitute "should pass".

## Commands and exit codes

| Command | Environment | Exit code | Result | Evidence artifact/link |
|---|---|---:|---|---|
| `git diff --check` | | | | |
| `bun run audit:phase17` | | | | |
| `bun run audit:phase22` | | | | |
| `bun run audit:r4a` | | | | |
| `bun run check` | | | | |
| `cd backend && composer check` | | | | |
| phase-specific command | | | | |

For an omitted command, record why it is N/A. For a command blocked by environment, record **UNPROVEN**, not PASS.

## GitHub CI

| Workflow | Run URL | SHA | Conclusion |
|---|---|---|---|
| CI | | | |
| Backend CI | | | |
| Full-stack Integration CI | | | |
| Browser Acceptance CI | | | |
| R3 Final Gate | | | |
| R4 Staging Package CI | | | |

All required workflow evidence must point to the exact phase-head SHA.

## Contract checks

- Production frontend exactly `https://rosta.shop`:
- Production API base exactly `https://api.rosta.shop/api/v1`:
- OpenAPI drift check:
- Error envelope preserved:
- Auth/session/CSRF contract preserved:
- Idempotency contract preserved:
- Staging cookie namespace/domain isolated from production:
- Database migration policy followed:
- Provider truth boundaries followed:

## External/runtime acceptance

| Item | Status (`PASS` / `FAIL` / `EXTERNAL PENDING` / `N/A`) | Evidence or required input |
|---|---|---|
| DNS/TLS | | |
| SMS/OTP provider | | |
| R2/object storage | | |
| Payment | | |
| Refund | | |
| Payout/settlement rail | | |
| Carrier | | |
| Tax rule | | |
| Commission rule | | |
| Backup/restore | | |
| Monitoring/alerts | | |

`EXTERNAL PENDING` is not a synonym for PASS. PS8 records the source-independent boundary and PS9 decides whether it blocks `PRE-SERVER GO`; no item may be represented as real staging/provider acceptance before that execution exists.

## Risks

-

## Decisions required

For each decision, include owner, exact input required and deadline/blocking phase.

-

## Deferred work

-

## Secret review

- Secret values added to Git: **NO**
- Production `.env` added to Git: **NO**
- Backup/private payload added to evidence: **NO**
- Credentials printed in logs/artifacts: **NO**
- Review method/evidence:

Any answer other than **NO** above is a release blocker.

## Final status

- PS-owned gates all PASS:
- Required CI all PASS:
- Worktree clean after commit:
- No unresolved red gate:
- Final status: `COMPLETE` / `NOT COMPLETE`

If any owned gate is FAIL or UNPROVEN, final status must be `NOT COMPLETE`.
