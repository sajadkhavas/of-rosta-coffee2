# Phase 20A — Administrator Finance Workspace

## Status

The administrator finance workspace is integrated in the canonical R5 lineage.
Its permanent source audits and repository CI gates have passed. Production
refund execution remains disabled until the staging and provider-approval gates
below are accepted.

## Route

`/admin/finance`

The route is:

- protected by `AccountGuard`;
- restricted to users with the `administrator` role;
- marked `noindex,nofollow`;
- registered in the generated TanStack route tree;
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

## Staging acceptance still open

1. Run the workspace against the deployed Laravel/MySQL/Redis services.
2. Verify unauthorized, customer and seller users are redirected or rejected.
3. Verify two separate administrator sessions are required for request/approval.
4. Simulate concurrent dispatch attempts and confirm only one provider execution.
5. Verify API 409/422/503 errors remain understandable and retain idempotency.
6. Test mobile, tablet, desktop, keyboard and screen-reader behavior.
7. Keep real payment/refund providers disabled during acceptance.

## Deliberately excluded from Phase 20A

- Seller product, inventory and fulfillment workspace.
- Administrator roastery/product moderation workspace.
- Review and Inquiry moderation workspace.
- Real provider credentials and production fund movement.
- Reporting/analytics beyond the authoritative refund and reconciliation records.

The whole-bean-only business boundary remains unchanged. No grind selector, option or state is introduced by the finance workspace.
