# Frontend Architecture

Status: ARCH-0.2

## 1. Technology baseline

**BUILT:** React 19, TanStack Start/Router/Query, TypeScript, Vite, Tailwind, Zod and a Bun-managed frontend toolchain. The repository contains generated route-tree output, `src/router.tsx`, `src/server.ts` and server/client application entry points.

The frontend is an SSR-capable web application/PWA, not a source of transactional truth.

## 2. Layering

Preferred structure:

```text
routes / route loaders
  -> feature/components
      -> frontend domain helpers/state
          -> src/lib/api/*
              -> API client
                  -> Laravel
```

Routes/components should not independently encode backend financial or authorization policy.

## 3. API client boundary

**BUILT:** `src/lib/api/client.ts` provides:

- centralized API URL construction;
- JSON handling;
- credentials-included requests;
- Sanctum CSRF bootstrap/recovery for unsafe requests;
- timeout/abort/network error classification;
- request ID propagation from API error/header;
- 401/session-expiry signaling;
- retry-after parsing.

Feature API modules such as checkout, catalog, admin finance/operations and content build on this boundary.

Rules:

- one common transport/error contract;
- Zod or equivalent validation where external response correctness matters;
- no direct provider API calls from browser for secrets-bearing payment/SMS/storage operations;
- no hard-coded production hostnames outside configuration.

## 4. Server-state ownership

TanStack Query/API responses are caches/views of backend truth. Client state may improve UX but cannot become the authoritative source for:

- inventory availability;
- quote totals;
- commission/tax/shipping allocation;
- payment success;
- seller payable/settlement;
- order/fulfillment terminal state;
- role/permission authorization.

A refresh/deep-link must be able to recover authoritative state from the API.

## 5. Cart and checkout

Client cart persistence may retain user selections, but checkout is re-quoted server-side.

Canonical flow:

```text
client selections
  -> server quote
  -> immutable/snapshotted quote context
  -> order creation with idempotency
  -> payment attempt
  -> provider verification
```

The browser must display backend-returned totals. It must not reproduce money formulas and submit a client-calculated total as authority.

## 6. Authentication/session

**BUILT:** frontend API client is compatible with Sanctum cookie/CSRF flow and handles session expiry.

Rules:

- secure/auth cookies are not read as general application data;
- frontend route guards are UX controls, not authorization controls;
- every sensitive API action remains server-authorized;
- OTP values/secrets/provider credentials are never logged or persisted in browser state.

## 7. SSR and SEO

SSR remains required for public discoverability/SEO-critical routes. Canonicals, crawl controls, structured data and sitemap behavior are production contracts already represented elsewhere in the codebase.

Rules:

- server rendering must tolerate bounded backend dependency failures with safe error/degraded behavior;
- private authenticated data must not leak into shared/public caches;
- no personalized response may become globally cacheable by mistake.

## 8. Error and observability contract

Frontend should surface customer-safe messages while preserving correlation data such as API `request_id` for support/debugging.

Raw stack traces, provider responses, secrets and sensitive payment evidence must not be displayed to end users.

## 9. Performance

Architecture favors:

- route/data-level loading rather than giant global bootstraps;
- code splitting/lazy loading for heavy visual features;
- responsive media variants instead of original-file delivery;
- bounded animation/3D work with reduced-motion/accessibility support;
- no synchronous client work that blocks checkout correctness.

Performance optimization must not weaken server-side validation or financial truth.

## 10. PWA boundary

PWA/offline capabilities may cache public/static resources and customer-safe read views according to policy. Offline mode must not pretend an order/payment/refund mutation succeeded without server confirmation.

Future push notification work must integrate through the notification domain rather than creating a parallel message truth.
