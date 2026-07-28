# Phase 20B — Seller Operations Workspace

## Status

The seller workspace is integrated in the canonical R5 lineage. It covers
roastery onboarding/bootstrap, whole-bean catalog and inventory identity, roast
batches, fulfilment, signed media uploads and the safe seller-facing part of
Rosta Hub receipt tracking. Repository source gates have passed; deployed
object-storage and browser acceptance remain open.

## Route and access

- `/panel` requires an authenticated account and is `noindex,nofollow`.
- A user without a seller role may submit roastery onboarding.
- Creating a roastery assigns the authenticated user the scoped `roastery_owner` role.
- `GET /seller/roasteries` returns only roasteries granted through `roastery_owner`, `roastery_manager` or `roastery_staff` assignments. Administrator is the explicit global exception.
- Private bootstrap data includes roastery moderation status and the exact access roles used by the UI.
- All subsequent catalog, inventory, media and order operations still pass through backend scope checks.

## Workspace lanes

### Orders and fulfillment

- Scoped seller order queue.
- Domain-state actions only:
  - accepted → preparing;
  - preparing → ready to ship;
  - ready to ship → shipped.
- Verified payment commits sub-orders automatically; sellers do not accept or
  reject paid orders.
- An inability to fulfil is reported as an incident and is resolved only by an
  administrator through the scoped R5H refund/restock path.
- Shipment requires carrier and tracking code.
- Internal operational notes are optional and remain server-side/private.

### Catalog and inventory

- Product listing and Draft creation.
- Origin, process, roast level, Arabica percentage and tasting notes.
- Fixed whole-bean weights only: 50, 100, 250, 500 and 1000 grams.
- Variant price and SKU creation.
- Draft, Review and Archive product actions.
- Immutable roast batch creation.
- Append-only stock ledger display.
- Idempotent stock adjustments with bounded manual reasons.
- Owner/manager/administrator catalog writes are visually separated from staff operational access.

### Media

- Allowed image MIME types only.
- Browser SHA-256 calculation.
- Backend-created scoped upload intent and object key.
- Signed PUT directly to S3/R2-compatible storage.
- Backend completion with authoritative dimensions and alt text.
- Browser never chooses the object key or public CDN URL.

## Strict frontend contracts

- `src/lib/api/seller-operations.ts`
- `src/lib/api/seller-onboarding.ts`
- `src/lib/api/seller-stock-ledger.ts`

The client parses seller roasteries, products, variants, roast batches, stock ledger, orders, media and upload intents through Zod/runtime contracts before rendering them.

## Backend additions

- Scoped `GET /api/v1/seller/roasteries` bootstrap endpoint.
- Regression test proving foreign roasteries are excluded and administrator access is explicit.
- Seller operations OpenAPI contract.
- Seller API included in route/OpenAPI drift detection.
- `audit:seller-operations` included in `composer check`.

## Permanent frontend gate

`audit:seller-operations` protects:

- AccountGuard and noindex;
- scoped roastery bootstrap;
- whole-bean-only variants;
- idempotent stock ledger writes;
- fulfillment state machine and tracking data;
- checksum-bound signed uploads;
- role boundaries;
- loading/error/empty states;
- `/panel` route and navigation registration.

## Staging acceptance still open

1. Verify scoped roastery bootstrap and owner/manager/staff/admin sessions against MySQL.
2. Test invalid product transitions, duplicate weights and concurrent stock adjustments.
3. Test automatic payment commitment, fulfilment incidents and duplicate tracking codes.
4. Enable R2 only on Staging and test CORS, checksum, content type and size failures.
5. Run mobile/tablet/desktop, keyboard, screen-reader and slow-network acceptance.
6. Keep payment, refund, SMS and production media providers disabled until their
   individual acceptance gates pass.

## Deliberately outside Phase 20B

- Administrator roastery/product moderation workspace.
- Review and Inquiry moderation UI.
- Product rich editing beyond the operational Draft/create/status/variant/batch workflow.
- Production provider credentials or fund movement.

The permanent inventory boundary remains unchanged: product variants, roast
batches, reservations and stock remain whole-bean only. Grinding is represented
only by the R5 order-item service contract and never becomes seller-managed
inventory identity.
