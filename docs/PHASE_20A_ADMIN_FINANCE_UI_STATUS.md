# Phase 20A — Administrator Finance Workspace

## Status

The administrator finance workspace is implemented on `agent/phase-20a-admin-finance-ui`, stacked on Phase 19. It is code-complete for the refund and financial reconciliation APIs currently exposed by Laravel, but it is not runtime-accepted until the Bun/TypeScript/browser and Laravel server gates pass.

## Route

`/admin/finance`

The route is:

- protected by `AccountGuard`;
- restricted to users with the `administrator` role;
- marked `noindex,nofollow`;
- registered in the temporary Phase 17 route tree;
- linked from the administrator navigation with an explicit active state.

## Refund operations

The workspace supports:

- listing refund attempts by lifecycle status;
- viewing order/payment/provider references without exposing encrypted provider payloads;
- requesting a full remaining refund or a specific IRR amount;
- retaining one idempotency key when the same form request is retried;
- showing the dual-control boundary and disabling approval for the requester;
- approving a request as the second administrator;
- dispatching an approved refund through the serialized backend endpoint;
- recording an authoritative manual outcome with provider reference or failure code;
- displaying processing, success, failure, cancellation and requires-review states;
- explicit loading, error and empty states.

## Reconciliation operations

The workspace supports:

- filtering financial cases by status;
- displaying severity, kind, order and bounded safe details;
- viewing an existing resolution;
- resolving or dismissing a non-terminal case with a mandatory written explanation.

Unknown provider outcomes are never presented as successful. The interface instructs the administrator to use manual resolution only after observing an authoritative result in the provider's official system.

## Runtime contracts

`src/lib/api/admin-finance.ts` uses strict Zod schemas for:

- refund lifecycle values;
- reconciliation lifecycle values;
- canonical refund resources;
- reconciliation resources;
- pagination;
- operation results.

The client does not accept or render `request_payload` or `response_payload`.

## Permanent gates

- `audit:admin-finance` is part of `bun run check`.
- The audit protects administrator access, noindex, strict parsing, idempotency, dual control, authoritative resolution, confidential payload boundaries, loading/error/empty states, navigation and route registration.
- The Phase 17 contact audit was updated to recognize persisted Inquiry success instead of the obsolete mailto-only behavior.

## Server and browser gates still open

1. Generate the real TanStack route tree and remove the temporary Phase 17 tree.
2. Run `bun install --frozen-lockfile`.
3. Run `bun run audit:admin-finance`.
4. Run unit tests, TypeScript, ESLint and the production build.
5. Run the workspace against the real Laravel/MySQL/Redis services.
6. Verify unauthorized, customer and seller users are redirected or rejected.
7. Verify two separate administrator sessions are required for request/approval.
8. Simulate concurrent dispatch attempts and confirm only one provider execution.
9. Verify API 409/422/503 errors remain understandable and do not lose the idempotency key.
10. Test mobile, tablet, desktop, keyboard and screen-reader behavior.
11. Keep real payment/refund providers disabled during UI acceptance.

## Deliberately excluded from Phase 20A

- Seller product, inventory and fulfillment workspace.
- Administrator roastery/product moderation workspace.
- Review and Inquiry moderation workspace.
- Real provider credentials and production fund movement.
- Reporting/analytics beyond the authoritative refund and reconciliation records.

The whole-bean-only business boundary remains unchanged. No grind selector, option or state is introduced by the finance workspace.
