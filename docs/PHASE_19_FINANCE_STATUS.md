# Phase 19 — Refunds and Financial Reconciliation

## Status

The refund and reconciliation foundation is integrated in the canonical R5 lineage. PS4.2 extends that foundation with production-safe payout confirmation, maker/checker separation, encrypted payout evidence and settlement reconciliation.

PS4.2 implementation acceptance was proven on PR #88 at `b9c0ad836a3e4001dee5ec188329d2b670be918f` with all seven repository workflows green. The final registration commit must pass those same gates before merge.

Safe defaults remain disabled, and real provider-driven funds movement must not be activated before staging acceptance, merchant entitlement verification and explicit provider approval.

## Provider decision

The official ZarinPal public documentation/workspace now exposes refund and payout API collections. That public API surface does not prove that the Rosta merchant account has the required production entitlement, terminal/bank-account binding, credentials, limits or approved operational contract.

Rosta therefore does not invent or activate a live ZarinPal refund/payout adapter merely because public endpoints exist. The refund provider layer remains:

- `disabled`: fail-closed default.
- `manual`: records that an administrator must execute the refund in the provider's official panel and then register the authoritative outcome in Rosta.
- `testing`: deterministic successful refunds for automated tests; forbidden in Production.

A real provider adapter may be added only after official merchant access, authentication requirements, idempotency behavior, account/terminal binding, limits and reconciliation rules are verified in staging and production onboarding.

## Financial invariants

- Refunds require a verified payment belonging to the order.
- Every request has an order-scoped idempotency key and a request hash.
- Reusing a key with a different amount or reason is rejected.
- Requested, approved, processing, succeeded and requires-review amounts reserve the refundable balance.
- Failed and cancelled attempts release their amount for a later retry.
- The total reserved/succeeded amount cannot exceed the verified payment amount.
- Omitting `amount` requests the current remaining refundable balance.
- Refund approval uses dual control by default: the requester cannot approve the same refund.
- Provider dispatch is serialized per refund with a distributed cache lock.
- A provider timeout or unknown result never becomes a fake failure or success; it becomes `requires_review`.
- Provider request/response payloads and reconciliation details are encrypted at rest.
- A full refund updates refund, payment, order and sub-order truth in one database transaction.
- A partial refund leaves the order in `refund_pending` and opens a reconciliation case for the remaining balance.
- Settlement payout processing records a maker and successful payout confirmation records an independent checker.
- Paid settlement requires a unique payout reference plus method and encrypted evidence.
- Payout evidence amount/currency must exactly match the server-calculated settlement batch.
- Exact paid replay is idempotent; conflicting evidence fails closed.
- Unknown payout outcome enters `requires_review` and opens critical reconciliation before any retry.
- Failed payout opens reconciliation and does not mark allocations paid.
- Allocation status moves to `paid` only after authoritative payout confirmation.

## Refund lifecycle

```text
requested
  -> approved
  -> processing
  -> succeeded | failed | requires_review

processing | requires_review
  -> succeeded | failed | cancelled   (manual authoritative resolution)
```

## Settlement payout lifecycle

```text
pending -> processing -> paid
processing -> failed -> processing
processing -> requires_review -> paid | failed
```

`paid` is terminal. `requires_review` means the external transfer outcome is unknown and must be reconciled before retry/finalization.

## Reconciliation lifecycle

```text
open -> investigating -> resolved | dismissed
```

Payment attempts that enter `requires_review` automatically open a deduplicated reconciliation case. Failed, partial or unknown-outcome refunds also open cases. Settlement payout failures/unknown outcomes open one deduplicated case per affected order with the batch ID retained in encrypted reconciliation details.

## Administrator API

- `GET /api/v1/admin/finance/refunds`
- `GET /api/v1/admin/finance/reconciliation`
- `POST /api/v1/admin/orders/{orderId}/refunds`
- `POST /api/v1/admin/refunds/{refundId}/approve`
- `POST /api/v1/admin/refunds/{refundId}/dispatch`
- `POST /api/v1/admin/refunds/{refundId}/resolve`
- `PATCH /api/v1/admin/finance/reconciliation/{caseId}`
- `GET /api/v1/admin/finance/settlement-batches`
- `POST /api/v1/admin/finance/settlement-batches`
- `POST /api/v1/admin/finance/settlement-batches/{batchId}/resolve`

All routes require a valid Sanctum session, an active Rosta session record and the `administrator` role.

## Environment contract

The safe refund defaults are:

```env
ROSTA_REFUND_ENABLED=false
REFUND_DRIVER=disabled
ROSTA_REFUND_DUAL_CONTROL=true
```

Staging may temporarily use:

```env
ROSTA_REFUND_ENABLED=true
REFUND_DRIVER=testing
ROSTA_REFUND_DUAL_CONTROL=true
```

`testing` is rejected in Production even if environment configuration is incorrect.

For a controlled manual acceptance drill:

```env
ROSTA_REFUND_ENABLED=true
REFUND_DRIVER=manual
ROSTA_REFUND_DUAL_CONTROL=true
```

Settlement payout dual control defaults to enabled in code. This phase does not add a live transfer driver; `manual_bank_transfer` and `provider_dashboard` are evidence classifications for externally executed payouts.

## Permanent gates

- Feature coverage for full refund, replay, partial refund, over-refund rejection and payment reconciliation.
- Unit coverage forbidding the testing refund provider in Production.
- R5I acceptance coverage for payout maker/checker separation and exact paid replay.
- `audit:finance` protects the financial invariants.
- `audit:ps4b` protects 12 PS4.2 refund/payout/reconciliation rules.
- Encrypted-storage registry covers settlement payout evidence.
- Finance routes remain covered by OpenAPI drift detection.
- Backend readiness checks finance tables and fail-closed provider state.
- PS4.2 implementation head passed PS1 Backend Wrapper CI, CI, Backend CI, Full-stack Integration CI, Browser Acceptance CI, R3 Final Gate and R4 Staging Package CI.

## Runtime/provider gates still external

The following are not source-code completion blockers and must not be fabricated:

1. Verify the Rosta merchant's live refund/payout entitlement with the selected provider.
2. Verify terminal/bank-account ownership and beneficiary requirements.
3. Provision scoped production credentials outside Git.
4. Execute non-production refund and payout acceptance against provider-approved accounts.
5. Reconcile provider statements against Rosta refund/settlement ledgers.
6. Keep automatic provider money movement disabled until these operational gates pass.

## Rollback truth

The PS4.2 migration can roll back before payout confirmation evidence exists. Once `confirmed_by_id`, `payout_evidence_hash` or encrypted `payout_evidence` is persisted, rollback intentionally fails closed and requires an explicit audited data-migration decision.

## Deliberately outside this phase

- Fabricated or undocumented provider behavior.
- Provider credentials.
- Unapproved production fund movement.
- Seller permission to create/approve refunds or finalize payouts.
- Automatic refund based only on a seller incident without administrator financial control.

The permanent whole-bean boundary remains unchanged. Refund, payout and reconciliation records contain no grind selector, grind option or grind state.
