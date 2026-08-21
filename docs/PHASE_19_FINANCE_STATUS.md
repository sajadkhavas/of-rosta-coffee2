# Phase 19 — Refunds and Financial Reconciliation

## Status

The refund and reconciliation foundation is integrated in the canonical R5
lineage. Its safe default remains disabled, and real funds must not be activated
before staging acceptance and explicit provider approval.

The fiscal/tax consequences of refunds and returns are governed by
`docs/ROSTA_TAX_FISCAL_CONTRACT.md`. Payment/refund truth and fiscal-document
truth are separate state machines and must be reconciled explicitly.

## Provider decision

Rosta does not invent a Zarinpal refund endpoint. The currently available payment integration proves request and verify operations, but an approved refund API contract has not been established for this account and integration.

The refund provider layer therefore supports:

- `disabled`: fail-closed default.
- `manual`: records that an administrator must execute the refund in the provider's official panel and then register the authoritative outcome in Rosta.
- `testing`: deterministic successful refunds for automated tests; forbidden in Production.

A real provider adapter may be added only after receiving official access, endpoint documentation, authentication requirements, idempotency behavior and reconciliation rules.

## Financial invariants

- Refunds require a verified payment belonging to the order.
- Every request has an order-scoped idempotency key and a request hash.
- Reusing a key with a different amount or reason is rejected.
- Requested, approved, processing, succeeded and requires-review amounts reserve the refundable balance.
- Failed and cancelled attempts release their amount for a later retry.
- The total reserved/succeeded amount cannot exceed the verified payment amount.
- Omitting `amount` requests the current remaining refundable balance.
- Approval uses dual control by default: the requester cannot approve the same refund.
- Provider dispatch is serialized per refund with a distributed cache lock.
- A provider timeout or unknown result never becomes a fake failure or success; it becomes `requires_review`.
- Provider request/response payloads and reconciliation details are encrypted at rest.
- A full refund updates refund, payment, order and sub-order truth in one database transaction.
- A partial refund leaves the order in `refund_pending` and opens a reconciliation case for the remaining balance.
- A successful payment refund does **not** imply that the required fiscal return/cancellation/correction succeeded.
- An issued fiscal document is never destructively rewritten because a later refund or return occurs.
- Refund, fiscal adjustment, seller-ledger adjustment and settlement adjustment must remain independently auditable and cross-referenced.

## Refund lifecycle

```text
requested
  -> approved
  -> processing
  -> succeeded | failed | requires_review

processing | requires_review
  -> succeeded | failed | cancelled   (manual authoritative resolution)
```

## Fiscal coupling

The minimum canonical relationship is:

```text
Original fiscal document
        |
Return / cancellation / correction (as legally applicable)
        |
Refund attempt/result
        |
Seller-ledger adjustment
        |
Settlement adjustment
        |
Reconciliation
```

These records may complete at different times. Unknown external outcomes must remain reviewable; no subsystem may fabricate another subsystem's success.

Partial returns must preserve the original order/item quantities and monetary facts and create auditable successor records instead of overwriting history.

## Reconciliation lifecycle

```text
open -> investigating -> resolved | dismissed
```

Payment attempts that enter `requires_review` automatically open a deduplicated reconciliation case. Failed, partial or unknown-outcome refunds also open cases. Fiscal submission/reversal mismatches are also reconciliation-worthy once the fiscal adapter is enabled.

## Administrator API

- `GET /api/v1/admin/finance/refunds`
- `GET /api/v1/admin/finance/reconciliation`
- `POST /api/v1/admin/orders/{orderId}/refunds`
- `POST /api/v1/admin/refunds/{refundId}/approve`
- `POST /api/v1/admin/refunds/{refundId}/dispatch`
- `POST /api/v1/admin/refunds/{refundId}/resolve`
- `PATCH /api/v1/admin/finance/reconciliation/{caseId}`

All routes require a valid Sanctum session, an active Rosta session record and the `administrator` role.

## Environment contract

The safe defaults are:

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

## Permanent gates added

- Feature coverage for full refund, replay, partial refund, over-refund rejection and payment reconciliation.
- Unit coverage forbidding the testing provider in Production.
- `audit:finance` protects the financial invariants.
- Finance routes are added to OpenAPI drift detection.
- Backend readiness checks the new tables, provider activation, failed/review refunds and open cases.
- Future fiscal integration must cover original/correction/return/cancellation linkage and refund/fiscal mismatch reconciliation before Production activation.

## Staging and provider gates still open

The following results are not claimable until a healthy Docker/server environment is available:

1. Run all MySQL migrations from an empty database and from the current staging schema.
2. Run `php artisan rosta:readiness --json --strict` on deployed services.
3. Test Redis-backed refund dispatch locking with concurrent requests.
4. Test manual-provider reconciliation using non-production payment records.
5. Confirm encrypted payload sizes against realistic provider responses.
6. Verify backup and restore of refund/reconciliation records.
7. Keep refund execution disabled until these gates and administrator UI acceptance pass.
8. Before fiscal Production activation, complete the gates in `docs/ROSTA_TAX_FISCAL_CONTRACT.md`.

## Deliberately outside this phase

- A fabricated or undocumented Zarinpal refund adapter.
- A fabricated or undocumented Taxpayer-System adapter.
- Provider credentials or taxpayer secrets.
- Production fund movement.
- Seller permission to create or approve refunds.
- Automatic refund based only on a seller incident without administrator financial control.
- The administrator finance user interface; this belongs to Phase 20.

The permanent whole-bean boundary remains unchanged. Refund and reconciliation records contain no grind selector, grind option or grind state.
