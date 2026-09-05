# ROSTA PS12 Release Notes — Cafe B2B Wholesale Marketplace

## Release identity

- Previous frozen source: `rosta-pre-server-2026-09-03` -> `af7a871d48ca4865a9509b8271b66ed380c57d24`
- Candidate PR: `#112`
- New source tag after accepted merge: `rosta-pre-server-2026-09-05`

The new tag is created only by the post-merge PS12 release workflow after exact-head PR evidence is verified. This document does not predeclare the future merge SHA.

## What PS12 adds

- Explicit cafe account lifecycle with owner/manager membership and admin verification controls.
- Public verified-cafe directory with city and request-scoped proximity search.
- Server-authoritative wholesale tier pricing at 5/10/20/50 kg thresholds.
- Variant-derived weight truth and verified-cafe eligibility enforcement.
- Seller/roastery management endpoints and frontend workspace for wholesale tiers.
- Quote pricing snapshot integrated before the existing Financial Truth calculation.
- Quote-to-Order revalidation that fails closed on stale eligibility or pricing.
- Bulk checkout quantity support while existing stock/availability contracts remain authoritative.
- Cafe application, cafe portal, admin cafe workspace and public cafe routes.
- Visible wholesale tier table on product detail, with IRR formatting and explicit server-Quote authority.

## Existing systems deliberately reused

PS12 does not create a parallel checkout, finance, inventory, payment, refund, seller or admin architecture. Existing permanent contracts remain authoritative and are re-run in the aggregate exact-head gates.

## Whole-bean product boundary

PS12 keeps the accepted product identity as whole coffee beans. It does not add a grind selector or treat a grinding choice as a product Variant.

## B2B commercial boundary

This release does not claim trade credit, net terms, deferred invoicing, guaranteed cafe delivery SLA or other contractual B2B terms that have not been explicitly approved and modeled. Wholesale pricing is immediate server-authorized pricing only.

## Location/privacy boundary

Cafe latitude/longitude is used for public directory proximity calculations. User latitude/longitude is supplied per directory request when proximity search is requested; PS12 does not claim continuous/background location tracking.

## Database and rollback notes

PS12 introduces cafe membership/status and wholesale pricing persistence. Once real production data exists, rollback must preserve legitimate cafe, membership and pricing history where destructive migration reversal would lose operational truth. Prefer forward corrective migrations or feature disablement when data-safe rollback cannot be proven.

Before real production activation, deployment migration/backup/restore remains governed by the accepted production package and runtime release process.

## External activation

This is a source release. It does not by itself activate the production server, DNS, payment/SMS/R2/carrier/payout providers, live cafe contracts or production traffic.