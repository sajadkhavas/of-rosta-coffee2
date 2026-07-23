# Phase 20B — Seller Operations Workspace

## Status

The code-level seller workspace is implemented on `agent/phase-20b-seller-operations-ui`, stacked on Phase 20A. It covers roastery onboarding/bootstrap, whole-bean catalog, roast batches, authoritative inventory, fulfillment and signed media uploads. Runtime acceptance remains blocked until the Bun/Laravel/MySQL/Redis/Object Storage gates run on a healthy server.

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
  - pending acceptance → accepted or rejected;
  - accepted → preparing;
  - preparing → ready to ship;
  - ready to ship → shipped;
  - shipped → delivered.
- Rejection requires a reason and enters the existing refund-pending domain flow.
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

## Runtime gates still open

1. Generate the real TanStack route tree and replace the temporary Phase 17 tree.
2. Run frozen Bun install, seller audit, unit tests, TypeScript, ESLint/Prettier and production build.
3. Generate/commit `backend/composer.lock` and run migrations, PHPUnit, Larastan and Pint.
4. Verify the scoped roastery bootstrap test against MySQL.
5. Verify an onboarding user receives the new owner role and can bootstrap the created roastery without signing out.
6. Test owner, manager, staff and administrator permissions in separate sessions.
7. Test invalid product status transitions and duplicate whole-bean weights.
8. Test concurrent stock adjustments and repeated idempotency keys.
9. Test the full fulfillment path, rejection/restock/refund-pending behavior and duplicate tracking codes.
10. Enable R2 only on Staging, configure Bucket CORS/CDN and test checksum/content-type/size failures.
11. Run mobile/tablet/desktop, keyboard, screen-reader and slow-network acceptance.
12. Keep payment, refund, SMS and media providers disabled until their individual acceptance gates pass.

## Deliberately outside Phase 20B

- Administrator roastery/product moderation workspace.
- Review and Inquiry moderation UI.
- Product rich editing beyond the operational Draft/create/status/variant/batch workflow.
- Production provider credentials or fund movement.

The permanent business boundary remains unchanged: Rosta sells whole coffee beans only. No grind selector, grind option or grind state is present in the seller workspace or APIs.
