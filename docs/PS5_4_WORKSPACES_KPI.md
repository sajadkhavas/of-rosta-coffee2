# PS5.4 — Seller/Admin Workspaces & KPI Composition

Status: implementation candidate. Final acceptance requires all repository gates to pass on one exact head and reviewed merge into `integration/rosta-release-candidate`.

Baseline: `integration/rosta-release-candidate@9c49324b1e0cb4a39a32ac55bd3faf5661935a06`.

Branch: `phase/rosta-ps5d-workspaces-kpi`.

## Scope

PS5.4 is a composition phase. It does not create new marketplace business behavior and does not duplicate financial truth. Existing seller/admin APIs remain the owners of writes. This phase adds server-defined operational KPI snapshots and composes the final private workspaces around accepted APIs.

## Official references reviewed

- Laravel 13 Query Builder aggregates: https://laravel.com/framework/docs/13.x/queries
- Laravel 13 Query Builder API: https://api.laravel.com/docs/13.x/Illuminate/Database/Query/Builder.html
- TanStack Query Important Defaults: https://tanstack.com/query/latest/docs/framework/react/guides/important-defaults
- TanStack Router Data Loading: https://tanstack.com/router/latest/docs/guide/data-loading
- TanStack Router External Data Loading: https://tanstack.com/router/latest/docs/guide/external-data-loading

The implementation follows these references by calculating aggregate counts in Laravel and treating TanStack Query as a cache/display boundary with explicit `staleTime`, not as a source of truth.

## Server-defined KPI contracts

### Seller workspace

`GET /api/v1/seller/workspace`

Returns only roasteries accessible to the authenticated seller (or all for an administrator) and one non-financial KPI snapshot per roastery:

- `pending_acceptance`
- `active_fulfillment`
- `active_shipping`
- `open_incidents`

These values are produced by `WorkspaceKpiService`. The browser does not rebuild them from list lengths.

### Administrator workspace

`GET /api/v1/admin/operations/workspace`

Administrator-only snapshot:

- `pending_roasteries`
- `products_in_review`
- `open_fulfillment_incidents`
- `failed_notifications`
- `open_financial_reconciliation`

The financial entry is a count of reconciliation cases only. No GMV, revenue, tax, commission, payout or settlement amount is recomputed in the workspace.

## Frontend composition

- `/panel/` keeps the existing seller operations dashboard and adds a server-authoritative KPI overview with loading/error/success states.
- `/admin/workspace` is the final administrator composition page with server-defined KPIs and keyboard-visible links to existing operations, finance, content and quiz/review surfaces.
- Both private routes remain `noindex,nofollow` and role guarded.
- Typed Zod contracts reject unexpected KPI response shapes.
- Query caching uses bounded `staleTime`; the cache is not treated as financial or operational truth.

## API-gap policy

A missing permanent API is a blocker. PS5.4 may add only composition/read contracts that aggregate already accepted domain truth. It must not create a mock, browser-only business rule, new financial rate, carrier behavior, refund behavior or fulfillment state transition.

## Acceptance gates

Final acceptance requires:

1. Seller workspace scoping tests prove one seller cannot see another roastery.
2. Administrator workspace is rejected for non-administrators.
3. KPI values are calculated server-side and typed at the browser boundary.
4. Workspace payloads contain no reconstructed monetary totals.
5. OpenAPI drift gate includes both new workspace routes.
6. Permanent seller/admin operations audits enforce KPI composition, role boundaries and accessibility states.
7. Frontend route generation, unit tests, TypeScript, ESLint and production build pass.
8. Backend tests, Larastan and Pint pass.
9. CI, Backend CI, PS1 Backend Wrapper CI, Full-stack Integration CI, Browser Acceptance CI, R3 Final Gate and R4 Staging Package CI all pass on one exact final head.
10. PR is merged through a normal merge commit with no rebase, amend, squash or force-push.

## Boundary to PS6B

PS5.4 owns presentation/composition only. Backend refactor, queue reliability, metrics, traces, log redaction and readiness instrumentation remain PS6B scope. If an optimization is needed beyond straightforward aggregate reads, PS6B owns that behavior-preserving engineering work after this phase is accepted.
