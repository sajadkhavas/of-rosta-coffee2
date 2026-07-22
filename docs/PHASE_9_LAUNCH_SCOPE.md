# Phase 9 — Launch-critical catalog and inventory

This phase is the shared data foundation for the customer storefront, seller panel, administration panel, quiz recommendations, reviews and complete first-release experience.

## Required outputs

- Public roastery, product, related-product and search APIs
- Whole-bean variants with allowed weights only
- Roast batches with immutable roast-date truth
- Authoritative stock ledger and availability rules
- Seller-scoped catalog, media, pricing, roast-batch and inventory APIs
- Admin-scoped roastery and product verification/moderation APIs
- Staging seed data for customer, seller and administrator acceptance
- Policies, audit logs, regression tests and business-contract gates

## Parallel surfaces unlocked

As contracts are completed, implementation proceeds without waiting for later phases:

- `/panel`: roastery profile, products, variants, roast batches and inventory
- `/admin`: roastery/product verification, catalog oversight and audit views
- public storefront: product, roastery, filtering and search integration
- quiz: inventory-aware product candidates
- reviews: product and roastery ownership targets

## Permanent boundaries

- Rosta sells only whole coffee beans; grind state is forbidden everywhere.
- Product and stock truth comes only from Laravel.
- Seller access is always scoped to an authorized roastery.
- Admin actions use domain services and produce audit logs.
- Public APIs expose only published and approved resources.
- Media URLs, metadata and ownership are validated server-side.

## Exit gate

`catalog_and_inventory=ready`
