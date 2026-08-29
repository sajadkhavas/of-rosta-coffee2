# PS5.4 — Seller/Admin Workspaces & KPI Composition

Status: **ACCEPTED / MERGED**. PS5.4 passed the complete required workflow set on exact final head `42d039b91cb61516673b540a65725cf2f33c07e7` and PR #90 was merged into `integration/rosta-release-candidate` with merge commit `10eb59bbc2617cfcb0a1bfdecfe08997fbc9a52f`.

Baseline: `integration/rosta-release-candidate@9c49324b1e0cb4a39a32ac55bd3faf5661935a06`.

Branch: `phase/rosta-ps5d-workspaces-kpi`.

PR: `#90 — PS5.4: Seller/Admin workspaces and KPI composition`.

Accepted final head: `42d039b91cb61516673b540a65725cf2f33c07e7`.

Phase merge commit: `10eb59bbc2617cfcb0a1bfdecfe08997fbc9a52f`.

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

## Acceptance repair history

A compatibility correction for the accepted settlement `requires_review` state was applied on this phase branch. A later immutable staging rehearsal exposed six Prettier violations in the new seller/admin workspace UI that the earlier candidate had not closed. Those formatting defects were corrected without weakening linting or bypassing the package rehearsal. The implementation head then passed the full seven-workflow acceptance set.

## Verified implementation evidence

Exact implementation head: `aa0fb37b49cfd0c73e6199646bd51146b2fc4d24`.

| Required workflow | Run | Result |
|---|---:|---|
| CI | 848 | PASS |
| Backend CI | 558 | PASS |
| PS1 Backend Wrapper CI | 100 | PASS |
| Full-stack Integration CI | 403 | PASS |
| Browser Acceptance CI | 391 | PASS |
| R3 Final Gate | 373 | PASS |
| R4 Staging Package CI | 353 | PASS |

## Final registration evidence

Exact final registration head: `42d039b91cb61516673b540a65725cf2f33c07e7`.

| Required workflow | Run | Result |
|---|---:|---|
| CI | 849 | PASS |
| Backend CI | 559 | PASS |
| PS1 Backend Wrapper CI | 101 | PASS |
| Full-stack Integration CI | 404 | PASS |
| Browser Acceptance CI | 392 | PASS |
| R3 Final Gate | 374 | PASS |
| R4 Staging Package CI | 354 | PASS |

The R3 final gate passed its complete backend gate, complete frontend gate and integrated runtime/browser path. The R4 staging package workflow passed the immutable hosted rehearsal. No failing workflow remained on the accepted final head. PR #90 was then merged normally with no rebase, amend, squash or force-push.

## Acceptance gates — final disposition

1. Seller workspace scoping tests prove one seller cannot see another roastery — **PASS**.
2. Administrator workspace is rejected for non-administrators — **PASS**.
3. KPI values are calculated server-side and typed at the browser boundary — **PASS**.
4. Workspace payloads contain no reconstructed monetary totals — **PASS**.
5. OpenAPI drift gate includes both new workspace routes — **PASS**.
6. Permanent seller/admin operations audits enforce KPI composition, role boundaries and accessibility states — **PASS**.
7. Frontend route generation, unit tests, TypeScript, ESLint and production build — **PASS**.
8. Backend tests, Larastan and Pint — **PASS**.
9. All seven required repository workflows on one exact final head — **PASS**.
10. Normal merge commit into `integration/rosta-release-candidate` — **PASS**.

## Boundary to PS6B

PS5.4 owns presentation/composition only. Backend refactor, queue reliability, metrics, traces, log redaction and readiness instrumentation remain PS6B scope. PS6B is the next canonical phase after this accepted PS5.4 registration.
