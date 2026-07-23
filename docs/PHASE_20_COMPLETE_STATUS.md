# Phase 20 — Operational Workspaces Complete

## Status

Phase 20 is implemented across the stacked Phase 20A, 20B and 20C branches. The code now covers administrator finance, seller daily operations, seller professional catalog management and administrator moderation/health operations.

It remains Draft and runtime-unverified until the repository Actions runner or an equivalent PHP 8.3/Bun/MySQL/Redis/R2 server executes every permanent gate.

## Administrator workspaces

### `/admin/finance`

- refund request, approval, dispatch and authoritative manual resolution;
- dual-control visibility;
- reconciliation queue and resolution;
- no provider payload exposure.

### `/admin/operations`

- roastery moderation;
- product moderation/publication;
- verified-purchase review moderation;
- support inquiry lifecycle;
- redacted Notification Outbox health;
- append-only Audit Log visibility with recursive metadata redaction.

Full notification destinations and encrypted notification payloads never enter the browser contract.

## Seller workspaces

### `/panel`

- seller onboarding and scoped roastery bootstrap;
- order fulfillment state machine;
- product creation;
- fixed whole-bean weight variants;
- immutable roast batches;
- idempotent stock ledger adjustments;
- signed S3/R2 media upload.

### `/panel/manage`

- roastery identity and shipping-policy editing;
- logo and cover selection from owned media;
- existing product content editing;
- origin, process, roast level and Arabica percentage;
- tasting notes and brewing suggestions;
- SEO title and description;
- primary image and gallery selection;
- SKU and price updates for fixed whole-bean variants.

An edit to verified roastery information or a published product follows the backend review-reset rules.

## Access boundaries

- customer: onboarding entry only;
- roastery staff: scoped operational access;
- roastery owner/manager: scoped catalog management;
- administrator: global moderation and operational visibility;
- every backend mutation rechecks active session and role/scope.

## Permanent business boundary

The marketplace remains whole-bean only.

Allowed weight SKUs:

- 50 g
- 100 g
- 250 g
- 500 g
- 1000 g

No grind selector, grind option or grind state exists in Phase 20.

## Permanent gates

Frontend:

- `audit:admin-finance`
- `audit:seller-operations`
- `audit:admin-operations`
- `audit:phase20`

Backend:

- `audit:finance`
- `audit:seller-operations`
- `audit:admin-operations`
- OpenAPI drift coverage for finance, seller and administrator operations.

## Regression fixes found while completing Phase 20

- seller backend audit referenced a nonexistent MediaUploadService path;
- seller backend audit expected a nonexistent `StockReason::manualValues()` method;
- onboarding users could not bootstrap an empty scoped roastery list;
- administrator roastery/product lists lacked private lifecycle status/filtering;
- notification and audit health had no privacy-safe browser contract;
- public availability could be mistaken for persistent Variant activation during editing.

These boundaries are now represented in code and permanent audits.

## Runtime gates still open

- generate and commit `backend/composer.lock`;
- install locked Composer and Bun dependencies;
- execute MySQL migrations;
- run PHPUnit, Larastan and Pint;
- run frontend audits, unit tests, TypeScript, ESLint/Prettier and production build;
- regenerate the real TanStack route tree and remove the temporary release tree;
- run two-session administrator finance acceptance;
- run seller scope and fulfillment E2E;
- run R2 CORS/signed-upload acceptance;
- verify responsive, keyboard and screen-reader behavior.

Real providers, production money movement and indexing remain disabled.
