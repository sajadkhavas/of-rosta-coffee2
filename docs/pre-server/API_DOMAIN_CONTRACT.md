# ROSTA API and Domain Contract

## Production domains

The production domain contract is exact:

- Frontend: `https://rosta.shop`
- API base: `https://api.rosta.shop/api/v1`

No production phase may silently introduce a second public API base, alternate production frontend hostname, or HTTP fallback.

Source alignment on the PS0 baseline:

- `src/config/site.ts` defines the site default as `https://rosta.shop`.
- `docs/openapi/rosta-v1.yaml` declares `https://api.rosta.shop/api/v1`.
- `backend/routes/api.php` places the API under the `v1` route prefix.
- `package.json` CI uses a local API only for test/build execution; that local test URL is not production truth.

Production builds must inject the exact API base above through the existing configuration surface. Server-side private routing may use the existing `ROSTA_INTERNAL_API_URL` mechanism in `src/config/site.ts`; it must never leak into browser configuration or replace the public contract.

## Staging isolation requirement

The baseline staging files currently use:

- frontend `https://staging.rosta.shop`
- API `https://api-staging.rosta.shop/api/v1`
- media `https://media-staging.rosta.shop`
- `SESSION_DOMAIN=.rosta.shop`

That cookie domain covers production hosts and therefore does **not** satisfy the new pre-server isolation requirement.

PS1 must establish the source-level isolated staging contract; PS7 must re-verify it against the completed Production package before PS8:

1. Staging session cookie name must be explicit and staging-only.
2. Staging cookie domain must not scope cookies to production `rosta.shop` / `api.rosta.shop`.
3. Frontend and API staging hostnames must be arranged so Sanctum/XSRF can work without a cookie domain that also covers production.
4. Staging `SANCTUM_STATEFUL_DOMAINS`, allowed origins, payment redirect allowlist and media origins must be reconciled to the chosen staging hosts.
5. PS1 must update the existing Phase 17/22/R4A audits if their old literal `.rosta.shop` expectation conflicts with the accepted isolated design; PS7 must preserve the corrected contract.
6. Production and staging must use different session-cookie names even after domain isolation.

PS0 does not mutate DNS or runtime configuration. PS1 owns the repository correction across the staging environment examples, Caddy/Compose/acceptance and cookie/XSRF tests; PS7 owns final Production/Staging reconciliation and hosted rehearsal. Until the PS1 correction is accepted, staging cookie isolation is **FAIL** on the baseline.

## Response envelope

Authoritative backend helpers are implemented by `backend/app/Support/ApiResponse.php`.

Success:

```json
{
  "data": {},
  "meta": {}
}
```

`meta` is optional.

Error:

```json
{
  "error": {
    "code": "stable.machine.code",
    "message": "human readable message",
    "request_id": "optional request correlation id",
    "fields": {
      "field": ["optional validation messages"]
    }
  }
}
```

`request_id` and `fields` are optional. `src/lib/api/client.ts` tolerates a legacy top-level `message`, but new backend work must use the nested `error` envelope rather than expanding the legacy shape.

HTTP status and machine `error.code` are both contract inputs. Frontend code must not infer a successful business state from HTTP 2xx alone when a runtime schema or transaction verification is already required.

## Authentication, session and CSRF

The current contract is stateful Laravel Sanctum:

- protected API routes use `auth:sanctum` and the repository's active-session middleware in `backend/routes/api.php`;
- `backend/config/sanctum.php` uses the `web` guard and Laravel's session/cookie/CSRF middleware;
- `src/lib/api/client.ts` sends `credentials: "include"`;
- unsafe methods read `XSRF-TOKEN` and send `X-XSRF-TOKEN`;
- on HTTP 419, the client may bootstrap `{api-origin}/sanctum/csrf-cookie` once and retry the unsafe request once;
- 401 or `auth.session_expired` emits the existing session-expiry event;
- secure production/staging cookies are mandatory; secrets never enter source.

PS2 may harden this behavior but must not replace it with a different auth model without an explicit versioned contract change.

## Idempotency

Current frontend/backend integration uses the JSON field `idempotency_key`, not an assumed header:

- `src/lib/api/checkout.ts` sends `idempotency_key` for order creation and payment request.
- `backend/app/Http/Controllers/Checkout/OrderController.php` passes the validated `idempotency_key` to the order service.
- `backend/.env.staging.example` currently exposes `ROSTA_ORDER_IDEMPOTENCY_TTL_HOURS=24`.

Rules:

1. The same business operation must reuse the same key on retry; a new user action gets a new key.
2. Keys are opaque; no PII, price, token or secret is encoded into them.
3. A phase must not change from body-field idempotency to header idempotency without coordinated frontend, backend and OpenAPI changes.
4. TTL, conflict semantics and replay response must come from implementation/config and tests; PS0 does not invent semantics not already proven.
5. Financial/provider side effects must remain fail-closed when idempotency state is missing or ambiguous.

## Versioning

Public API versioning is path based: `/api/v1`.

Relevant source truth:

- `backend/routes/api.php`: `Route::prefix('v1')`
- `backend/.env.staging.example`: `ROSTA_API_VERSION=v1`
- `docs/openapi/rosta-v1.yaml`: OpenAPI server ending in `/api/v1`

`ROSTA_CONTRACT_VERSION` is an internal contract marker, not a replacement for the public URL version.

Breaking changes require a new public API version or an explicitly approved compatibility plan. Additive response fields must remain backward compatible with current frontend runtime parsers.

## OpenAPI drift

OpenAPI sources live under `docs/openapi`. The current commerce drift audit is `backend/scripts/audit-commerce-openapi-drift.php`; it checks route presence against:

- `docs/openapi/rosta-v1-commerce-additions.yaml`
- `docs/openapi/rosta-v1-finance.yaml`
- `docs/openapi/rosta-v1-seller-operations.yaml`
- `docs/openapi/rosta-v1-admin-operations.yaml`

The base specification is `docs/openapi/rosta-v1.yaml`.

Any API change must update, in the same owning phase:

1. Laravel route/request/resource/service behavior;
2. frontend runtime contract/schema where consumed;
3. relevant OpenAPI document;
4. drift audit/tests.

Required backend drift gate:

```bash
cd backend && composer audit:openapi
```

A route that exists only in code or only in OpenAPI is a release defect.

## Business/provider boundary

The API contract may expose interfaces and configuration switches for payment, refund, carrier, R2, SMS, settlement or other providers. It must not hard-code a guessed provider decision, fee/rate, tax rule, bank account, credential, callback secret or operational SLA. Required truth inputs are governed by `docs/pre-server/PRODUCTION_TRUTH_BOUNDARIES.md`.
