# PS5.3 — Carrier & Non-financial Admin Operations

Status: implementation candidate. Final status becomes **verified** only after all required workflows pass on one exact head and the PR is merged into `integration/rosta-release-candidate`.

Baseline: `integration/rosta-release-candidate@73b52512bd8da7896c94fb698ede2f263c2f772c`.

Continuation branch: `phase/rosta-ps5c-carrier-admin-ops-r2`. The canonical branch name had been pre-created on obsolete Wave-2 SHA `a96d0e05478bc2c61852fdf91bb46da1782030df`; the `-r2` continuation preserves the published history and starts from the exact accepted post-PS4.2 baseline instead of rebasing or force-moving that branch.

## Official references reviewed

- Laravel 13 queue documentation: https://laravel.com/docs/13.x/queues
- Laravel 13 failed-job provider API: https://api.laravel.com/docs/13.x/Illuminate/Queue/Failed/FailedJobProviderInterface.html
- PHP `hash_hmac`: https://www.php.net/manual/en/function.hash-hmac.php
- PHP `hash_equals`: https://www.php.net/manual/en/function.hash-equals.php

No native Iran Post, Tipax or other carrier API is claimed by this phase because no current official provider contract, production credentials or merchant entitlement is evidenced in the repository. The launch-safe provider is deliberately manual and provider-neutral.

## Delivered contract

### 1. Provider boundary and manual carrier

`CarrierProvider` is the application edge. `ManualCarrierProvider` normalizes the externally observed carrier name and tracking code and explicitly reports `supportsAutomatedDispatch() = false`. PS5.3 never fabricates label-purchase, pickup-booking, rate-quote or carrier money APIs.

Administrator carrier operations can attach/update tracking and move a shipment leg only through an explicit state machine. `delivered` is deliberately excluded from this endpoint; delivery remains owned by the existing proof-of-delivery service so settlement release cannot be bypassed by an operational status edit.

### 2. Signed carrier event envelope

Provider-neutral inbound events use:

- `X-Rosta-Carrier-Timestamp`
- `X-Rosta-Carrier-Event-Id`
- `X-Rosta-Carrier-Signature: v1=<hex>`

The signature is HMAC-SHA256 over `v1:{timestamp}:{eventId}:{rawBody}`. The secret remains environment-only. Verification happens in middleware before FormRequest validation, uses `hash_equals`, enforces a bounded clock-skew window and rejects malformed signatures fail-closed.

The event ID is persisted in `carrier_webhook_receipts` with a SHA-256 hash of the raw payload. Exact replay is idempotent. Reusing the same event ID for different bytes is a conflict. Raw webhook bodies are not persisted.

The older `/webhooks/carriers/deliveries` route is retained as a compatibility surface but now uses the same timestamped signature envelope and the same replay ledger.

### 3. Carrier state ownership

Allowed signed-provider transitions are forward/exception transitions only. Recovery from failed/review states is administrator-controlled. Carrier events cannot mutate tax, commission, refund, payout, settlement allocations or recognized revenue.

Delivery events reuse `DeliveryConfirmationService` with `DeliveryConfirmationSource::Carrier`; therefore final-leg rules, delivery idempotency, dispute-window creation and settlement release boundaries remain unchanged.

### 4. Failed-job operations

The administrator browser can list only safe failed-job metadata: UUID, connection, queue, display name and failure time. Serialized payloads and exception traces never leave the server response.

Retry uses Laravel's official `queue:retry` behavior for one exact UUID and requires an operator reason that is audit-recorded.

Destructive forget is two-step:

1. administrator creates a pending forget request with reason;
2. a different administrator confirms it;
3. the application executes Laravel's `queue:forget` behavior and records the confirmer.

No endpoint exposes `queue:flush`, arbitrary command execution, raw serialized jobs or exception traces.

## Data added

- `carrier_webhook_receipts`: replay/evidence ledger containing event identity, shipment-leg identity, event type, timestamp and payload hash only.
- `failed_job_operations`: auditable control record for destructive failed-job actions.

## Security and privacy invariants

- carrier webhook secret is never committed, returned or logged;
- HMAC comparison is constant-time through `hash_equals`;
- timestamp and event ID are signed with the exact raw body;
- browser/admin responses never contain raw failed-job payloads or stack traces;
- destructive queue record deletion requires a second administrator;
- no carrier event changes financial truth;
- delivery remains proof-driven and cannot be set through the generic admin carrier transition endpoint;
- webhook receipts store only a payload digest, not customer/shipping payload bodies.

## Acceptance

Final PS5.3 acceptance requires, on one exact clean head:

1. clean and upgrade MySQL migrations;
2. carrier HMAC freshness/signature/replay tests;
3. manual carrier state-machine tests;
4. safe failed-job listing, single-job retry and dual-control forget tests;
5. permanent admin-operations contract audit;
6. PHPUnit, Larastan and Pint;
7. CI, Backend CI, PS1 Backend Wrapper CI, Full-stack Integration CI, Browser Acceptance CI, R3 Final Gate and R4 Staging Package CI;
8. reviewed merge into `integration/rosta-release-candidate` with no history rewrite.
