# API & Integration Contract Audit

Status: ARCH-0.4 / PS0.4

Date: 2026-08-21

Baseline: `integration/rosta-release-candidate @ f51fc7cbbb1ae98570fa6fea9ba47e09b298f2cf`

Phase branch: `phase/rosta-ps0.4-api-integration-contract`

## 1. Purpose

This document is the audited contract for ROSTA HTTP API registration and external integration boundaries. It is intentionally evidence-driven: runtime code, tests and current official provider/framework documentation take precedence over remembered conventions or provider folklore.

The goal is to prevent silent drift between:

- Laravel runtime routes and middleware;
- TanStack Start browser integration;
- OpenAPI documentation;
- payment/SMS/provider adapters;
- callback and webhook trust boundaries;
- environment/deployment configuration.

## 2. Authoritative sources reviewed

Current official references reviewed for ARCH-0.4:

- Laravel 13 routing: https://laravel.com/docs/13.x/routing
- Laravel 13 middleware: https://laravel.com/docs/13.x/middleware
- Laravel Sanctum 13.x: https://laravel.com/docs/13.x/sanctum
- Laravel 13 CSRF protection: https://laravel.com/docs/13.x/csrf
- Zarinpal payment gateway connection guide: https://www.zarinpal.com/docs/paymentGateway/connectToGateway
- Zarinpal sandbox guide: https://www.zarinpal.com/docs/paymentGateway/sandBox
- Kavenegar REST API: https://kavenegar.com/rest.html
- OpenAPI Specification 3.1.2: https://spec.openapis.org/oas/v3.1.2.html

Provider capability must be re-checked against current official documentation before a future provider-specific feature is enabled or expanded.

## 3. Runtime API registration contract

### 3.1 Canonical prefix

Public application API routes are versioned under:

`/api/v1/*`

`routes/api.php` is registered by Laravel routing bootstrap and receives Laravel's `api` middleware group. ROSTA applies `throttle:api` explicitly to the v1 group.

Additional modular route files are registered by `AppServiceProvider` under the same `/api/v1` prefix and MUST receive both:

- `api`
- `throttle:api`

Reason: Laravel 13's default `api` middleware group contains `SubstituteBindings`; `throttle:api` is not implicitly part of that default group. ROSTA therefore makes the baseline API limiter explicit instead of relying on historical framework defaults.

Specialized limiters such as `payment-request`, `payment-callback`, `media-upload`, `fulfillment-transition`, or OTP limits are additive and may be stricter than `throttle:api`.

### 3.2 Contract enforcement

`backend/tests/Feature/ApiIntegrationContractTest.php` verifies that every registered `api/v1/*` route includes both `api` and `throttle:api` middleware.

OpenAPI core operations are also compared against runtime route method/path pairs. A documented HTTP method that is not registered at runtime is a failing contract.

## 4. Browser authentication and CSRF contract

ROSTA uses Laravel Sanctum stateful SPA authentication.

Accepted runtime contract:

- backend enables `statefulApi()`;
- frontend requests send cookies with credentials;
- unsafe requests use Laravel's `XSRF-TOKEN` cookie as `X-XSRF-TOKEN`;
- frontend can bootstrap CSRF through `/sanctum/csrf-cookie`;
- protected routes use `auth:sanctum` plus ROSTA session enforcement;
- CORS explicitly allows credentials and the required request headers;
- session cookie is HttpOnly and deployment security attributes are environment-controlled.

The physical session cookie name is configuration, not domain truth. OpenAPI uses the baseline name `rosta-session` and documents that deployments may override it with `SESSION_COOKIE`. Staging currently documents `rosta_staging_session` in its environment contract.

## 5. Response and correlation contract

Application JSON responses use the ROSTA envelope:

Success:

```json
{"data": {}}
```

Optional success metadata may be returned as `meta`.

Errors use:

```json
{
  "error": {
    "code": "stable.machine.code",
    "message": "human readable message",
    "fields": {},
    "request_id": "correlation id"
  }
}
```

`AssignRequestId` provides or validates `X-Request-ID`, adds it to logs and returns it to the client. Responses also expose `X-Rosta-Contract-Version`.

Provider secrets, raw credentials and sensitive payment card data must never enter public envelopes or normal logs.

## 6. Zarinpal payment integration

### 6.1 Official provider contract

The current official Zarinpal flow audited on 2026-08-21 is:

1. payment request: `POST https://payment.zarinpal.com/pg/v4/payment/request.json`;
2. buyer redirect: `https://payment.zarinpal.com/pg/StartPay/{authority}`;
3. browser return to merchant `callback_url` with `Authority` and `Status` query parameters;
4. only `Status=OK` proceeds to provider verification;
5. verify: `POST https://payment.zarinpal.com/pg/v4/payment/verify.json`;
6. provider code `100` is successful verification and `101` means the transaction was already verified.

Sandbox replaces the payment host with `sandbox.zarinpal.com` per Zarinpal's official sandbox documentation.

### 6.2 ROSTA boundary

ROSTA does NOT treat the browser return as proof of payment.

The runtime callback is provider-specific and uses:

`GET /api/v1/payments/zarinpal/callback/{paymentId}`

The handler correlates the route payment attempt with the stored provider authority before verification. Final payment success is accepted only from server-to-server provider verification.

The earlier generic OpenAPI `POST /payments/callback` signed-webhook description was incorrect for Zarinpal and has been removed. It confused a browser redirect contract with a signed webhook contract.

Production default Zarinpal hosts were also corrected from historical `api.zarinpal.com` / `www.zarinpal.com` defaults to the current official `payment.zarinpal.com` endpoints. Environment overrides remain possible for controlled provider changes.

## 7. Kavenegar SMS / OTP integration

The audited Kavenegar adapter follows the official REST structure:

`https://api.kavenegar.com/v1/{API-KEY}/...`

Current calls:

- OTP lookup: `verify/lookup.json`
- normal SMS: `sms/send.json`

The client uses HTTPS, explicit connect/request timeouts, provider response validation, circuit breaking and a single transport attempt for ambiguous send outcomes.

Kavenegar's optional `localid` exists for simple SMS sends, but it is not treated as a universal provider idempotency mechanism and is not available as proof that every Kavenegar operation is safely retryable. Any future use must be tied to ROSTA's durable notification identity and separately audited.

## 8. Payment idempotency and external-call safety

Payment initiation remains server-authoritative:

- authenticated user owns the order;
- order and attempt state are protected with database locking;
- idempotency key is bound to a deterministic request hash;
- replay with conflicting semantics returns a conflict;
- provider HTTP is not performed while holding the primary state transaction open;
- provider timeouts/errors become explicit domain failures rather than fabricated success;
- callback replay on terminal state is safe;
- frontend additionally checks returned payment/order/amount intent before presenting a paid result.

## 9. Carrier webhook boundary

`POST /api/v1/webhooks/carriers/deliveries` is an internal ROSTA target integration contract, not evidence that a specific carrier provider is already production-integrated.

Current ingress requires:

- HMAC-SHA256 over the raw request body;
- configured secret;
- `X-Rosta-Carrier-Signature`;
- bounded request validation;
- shipment correlation;
- domain idempotency key;
- rate limiting.

No carrier-specific signature format, retry promise or delivery semantics may be invented until a real carrier is selected and its current official contract is reviewed.

## 10. OpenAPI discipline

The canonical core specification is `docs/openapi/rosta-v1.yaml`.

ARCH-0.4 requires:

- runtime method/path parity for every operation documented in the core spec;
- no fictional generic provider callback;
- explicit public vs authenticated security;
- deployment-controlled cookie naming documented rather than assumed;
- provider-specific return/callback behavior represented as provider-specific transport;
- OpenAPI changes that alter public compatibility to be reviewed as architecture/API contract changes.

OpenAPI 3.1 remains accepted. The existence of a newer OpenAPI release alone is not a reason to migrate the repository format without toolchain or contract need.

## 11. Findings and disposition

| Finding | Severity | Disposition |
| --- | --- | --- |
| Modular v1 routes relied on `api` middleware for baseline throttling although Laravel 13 does not include `throttle:api` in that default group | High | Fixed: explicit `throttle:api` on modular route registration + regression test |
| Production Zarinpal default hosts used historical domains | High | Fixed against current official Zarinpal documentation + regression test |
| Core OpenAPI documented nonexistent signed `POST /payments/callback` | High | Fixed: provider-specific GET browser return + runtime method/path test |
| Core OpenAPI named `laravel_session` although ROSTA session cookie is configured independently | Medium | Fixed: baseline ROSTA cookie name plus `SESSION_COOKIE` deployment note |
| Commerce OpenAPI staging host used `api-staging.rosta.shop` while staging environment contract uses `api.staging.rosta.shop` | Medium | Fixed |
| Sanctum SPA credential/CSRF flow | Pass | No change; aligned with official Laravel Sanctum guidance |
| Kavenegar base/lookup/send paths and response-success handling | Pass | No change; aligned with official Kavenegar REST documentation |
| Zarinpal Authority correlation + server-side verify flow | Pass | No logic rewrite; existing implementation matches the official payment return/verify model |
| Carrier provider-specific contract | External gate | Keep provider-neutral internal HMAC boundary until a real carrier is selected and officially verified |

## 12. Acceptance gates

ARCH-0.4 is acceptable only when all of the following are true on the phase head:

- branch ancestry starts from the exact accepted baseline above;
- scoped diff contains only API/integration contract corrections and evidence;
- PHPUnit contract tests pass;
- existing repository `composer check` remains green;
- OpenAPI core operations have runtime method/path matches;
- every versioned API route has the baseline API limiter;
- legacy Zarinpal production endpoints are absent from the backend config source;
- no provider capability is claimed without current official evidence;
- unresolved provider-specific external gates remain explicitly marked rather than simulated.

## 13. Rollback and reversibility

The changes are fix-forward and reversible by normal Git commits only.

No published-history rewrite, force-push, rebase, amend or squash is required or permitted by this phase.

If a provider changes its official endpoint or callback semantics, update the provider adapter/config, contract tests and this audit in one reviewed change before deployment.
