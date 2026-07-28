# Phase 9 — launch-critical catalog and inventory

## Goal

Move Rosta from an identity-enabled application to a storefront backed by real Laravel catalog and inventory data.

## Required for launch

- Public roastery list and detail endpoints
- Published product list, detail and related endpoints
- Catalog search and bounded filters
- Whole-bean variants limited to 50, 100, 250, 500 and 1000 grams
- Roast batches and latest available roast snapshot
- Stock ledger and authoritative availability
- Contract-aligned pagination and API resources
- Staging seed data
- Ownership and whole-bean regression tests

## Explicitly deferred

- Seller panel UI
- Filament administration UI
- Reviews, quiz and editorial content
- Non-critical animation or visual changes

## Permanent boundaries

- Grind state is forbidden in product, variant, roast-batch, reservation and
  stock identity. Later R5 service records are outside this catalog boundary.
- Public products must be published and belong to an active verified roastery.
- Laravel is authoritative for price, availability, stock and roast-batch truth.
- Stock changes are ledger entries; direct silent stock mutation is forbidden.

## Exit gate

`catalog_and_inventory=ready`
