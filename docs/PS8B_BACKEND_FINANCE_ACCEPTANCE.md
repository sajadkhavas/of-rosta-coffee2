# PS8B — Backend & Finance Acceptance Audit

Status: **candidate acceptance** until the exact final PR head passes every applicable GitHub gate and is merged normally into `integration/rosta-release-candidate`.

Baseline: `integration/rosta-release-candidate@5d6035244012679106008799051c7863c7f2ffce`.

Branch: `phase/rosta-ps8b-backend-finance-acceptance`.

## Purpose

PS8B is an independent pre-server acceptance phase. It does not add a new payment, refund, tax, payout or settlement business model. It re-proves that the accepted backend and financial contracts remain deterministic, fail-closed and internally consistent on the current shared release candidate.

## Acceptance layers

### 1. Static/adversarial contract

`composer audit:ps8b` verifies that:

- payment mutations retain database transactions and row locks;
- payment request idempotency conflicts fail closed;
- verified payments re-check server-authoritative amount and currency before settlement;
- payment and refund providers remain disabled by default;
- testing providers cannot be used in production;
- refund mutations retain transactions, row locks and request idempotency;
- refund approval defaults to dual control;
- unknown refund outcomes enter reconciliation rather than being guessed as success or failure;
- no unproved live ZarinPal refund adapter is fabricated;
- settlement payout closure retains maker/checker separation;
- paid payout replay requires the same reference, method and evidence hash;
- payout evidence amount/currency must exactly match the settlement batch;
- unknown payout outcomes enter critical reconciliation;
- the Finance OpenAPI contract and the prior PS4A/PS4B/R5I/PS6B acceptance audits remain in the permanent backend gate;
- the PS4.2 provider-truth decision remains manual-evidence payout until real production entitlement is independently proven.

### 2. Integrated database/runtime acceptance in CI

The dedicated `PS8B Backend Finance Acceptance` workflow uses MySQL 8.4 and Redis 7.4, PHP 8.3 and the frozen Composer lockfile. It runs clean migrations, strict backend readiness, Redis infrastructure acceptance, finance/OpenAPI/validation audits and targeted lifecycle tests covering:

- checkout financial boundaries;
- financial truth policies;
- payment lifecycle and idempotency;
- refund lifecycle, dual control and reconciliation;
- delivery/settlement and payout evidence;
- transactional checkout;
- PS6B queue/runtime observability.

### 3. Full backend reproduction

The same exact candidate must also pass the repository's complete `composer check`, including all permanent audits, all backend tests, Larastan and Pint. The dedicated workflow records secret-safe evidence and rejects a dirty workspace.

## Financial invariants retained

1. Monetary truth is calculated by the server and represented in integer IRR contracts already accepted by the finance core.
2. Payment success is never inferred from a browser callback alone; provider verification and server-side amount/currency checks remain mandatory.
3. Concurrent payment/refund/settlement mutations retain database transactions and row-level locking.
4. Idempotency conflicts return explicit conflicts rather than silently reusing a mismatched request.
5. Refund and payout unknown outcomes are reconciliation states, not assumed successes.
6. Refund approval and payout confirmation retain independent-operator controls by default.
7. Settlement allocations become paid only after evidence-backed payout confirmation.
8. Existing encrypted financial evidence, audit trails and reconciliation cases remain authoritative; this phase does not introduce destructive rollback semantics.

## Provider truth and production boundary

Official ZarinPal documentation may describe payment, refund, payout and instant-payout APIs, but public documentation does not prove that the Rosta merchant has the required live entitlement, terminal/bank binding, credentials, operational approval or reconciliation access.

PS8B therefore makes **no claim** that live payment, live refund execution, automated seller payout or real money movement is enabled. It keeps the source contracts fail-closed and preserves the accepted manual-evidence payout boundary. Real provider credentials and entitlement must be verified later in the controlled server/runtime phase; they must never be fabricated or committed to the repository.

Likewise, this phase does not claim production DNS, TLS, database contents, queue workers, provider connectivity, alert delivery, backup/restore or a live `rosta.shop` runtime. Those are PS8C/PS9 and post-freeze server acceptance concerns.

## Official references

The implementation is aligned with the current official contracts reviewed for the preceding financial phases, including:

- Laravel 13 database query builder / transactions and locking;
- Laravel 13 event and queue transaction-boundary guidance;
- Laravel 13 validation and authorization contracts;
- Laravel 13 Redis integration;
- ZarinPal official documentation and official Postman workspace.

Provider documentation is evidence of an API surface only; it is not evidence of Rosta account entitlement.

## Exit gate

PS8B may be accepted only when every applicable workflow is successful on the same exact final branch head, including the dedicated `PS8B Backend Finance Acceptance` workflow. The PR must then be merged with a normal merge commit. Rebase, squash, amend, force-push and direct writes to `integration/rosta-release-candidate` are not part of this phase.

After PS8B is accepted, the next pre-server phase is **PS8C — Infrastructure Acceptance Audit**.
