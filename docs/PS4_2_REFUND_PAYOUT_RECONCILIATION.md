# PS4.2 — Refund, Payout & Reconciliation

Status: **verified**. Implementation and repository acceptance were proven on PR #88 at `b9c0ad836a3e4001dee5ec188329d2b670be918f`; this registration commit must pass the same gates before merge.

Baseline: `integration/rosta-release-candidate@f51fc7cbbb1ae98570fa6fea9ba47e09b298f2cf`.

PR: `#88 — PS4.2: Refund, payout and reconciliation closure`.

## Acceptance evidence

The implementation head `b9c0ad836a3e4001dee5ec188329d2b670be918f` passed every repository gate required for this phase:

| Gate | Run | Result |
|---|---:|---|
| PS1 Backend Wrapper CI | 74 | success |
| CI | 822 | success |
| Backend CI | 532 | success |
| Full-stack Integration CI | 377 | success |
| Browser Acceptance CI | 365 | success |
| R3 Final Gate | 347 | success |
| R4 Staging Package CI | 327 | success |

The backend wrapper additionally proved `audit:ps4b` (12 rules), all backend audits, 48 tests / 5825 assertions, Larastan with zero errors, and Pint with no style violations.

## Scope

PS4.2 closes the post-payment financial workflow without inventing provider capabilities. It reuses the existing PS4A financial truth/refund ledger and R5I settlement allocations, then hardens payout confirmation, unknown-outcome handling, reconciliation and evidence retention.

## Provider truth decision

The current official ZarinPal public workspace documents payment, refund, payout and instant-payout collections, including payout creation/status APIs. The public API surface alone does not prove that this Rosta merchant has production entitlement, bank-account/terminal binding, credentials or an approved operational contract for automated seller payouts.

Therefore this phase deliberately ships **manual-evidence payout** and the existing controlled refund workflow. No live payout transfer endpoint and no unverified live refund driver are introduced. A future provider adapter may be enabled only after production entitlement, credential scope, terminal/bank-account ownership and sandbox/live acceptance are verified.

Official references reviewed on 2026-08-28:

- ZarinPal official documentation: https://docs.zarinpal.com/
- ZarinPal official Postman workspace: https://www.postman.com/zarinpal-team/zarinpal/overview
- ZarinPal payout collection: https://www.postman.com/zarinpal-team/zarinpal/collection/lgcssvb/zarinpal-payout
- ZarinPal refund collection is listed in the same official workspace.

## Financial invariants

1. Money amounts remain integer IRR and are server-authoritative.
2. Settlement batches only contain already-eligible R5I allocations.
3. `process` records the maker in `processed_by_id`.
4. `paid` requires a different administrator when payout dual control is enabled.
5. Successful payout requires a globally unique payout reference plus method and evidence whose amount/currency exactly match the batch.
6. Payout evidence is encrypted at rest; a deterministic SHA-256 evidence hash is stored for replay comparison and audit metadata.
7. A paid batch is idempotently replayable only with exactly the same reference/method/evidence hash; conflicting replays fail closed.
8. Unknown payout outcomes enter `requires_review` and open critical reconciliation cases before any retry.
9. Failed payout outcomes open high-severity reconciliation cases.
10. Settlement reconciliation remains order-owned: one deduplicated financial case is opened per affected order, with `settlement_batch_id` in encrypted details.
11. Maker identity is never overwritten by the checker.
12. Allocations are marked paid only after the payout batch is successfully confirmed.
13. The PS4.2 migration refuses destructive rollback once payout confirmation evidence exists.

## State machine

`pending -> processing -> paid`

`processing -> failed -> processing`

`processing -> requires_review -> paid|failed`

`paid` is terminal. `requires_review` means the external outcome is unknown; the operator must reconcile external evidence before recording a final result.

## API contract

`POST /api/v1/admin/finance/settlement-batches/{batchId}/resolve`

Actions:

- `process`: maker starts the controlled payout workflow.
- `paid`: checker records `payout_reference`, `payout_method` and `payout_evidence`.
- `failed`: records a known failure and opens reconciliation.
- `review`: records an unknown outcome and opens critical reconciliation.

Allowed payout methods in this phase are `manual_bank_transfer` and `provider_dashboard`. They describe externally executed transfers; they do not invoke a money-transfer API.

## Acceptance gates

- Migration applies on clean and upgrade databases.
- R5I delivery/settlement tests prove maker/checker separation and exact paid replay.
- Same-actor payout confirmation is rejected.
- Evidence amount/currency mismatch is rejected.
- Unknown outcomes create reconciliation cases.
- `composer audit:ps4b` passes.
- Existing `composer check` remains green.
- OpenAPI route/request contract stays synchronized.
- No live payout/refund provider is fabricated.

## Rollback

Code can be rolled back before PS4.2 evidence is written. The migration intentionally refuses rollback after `confirmed_by_id`, `payout_evidence_hash` or encrypted evidence has been persisted. At that point rollback requires an explicit audited data-migration decision rather than silent loss of financial evidence.
