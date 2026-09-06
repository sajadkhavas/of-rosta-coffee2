# ROSTA PS12 — Cafe B2B Wholesale Marketplace

## Status

**SOURCE ACCEPTANCE CANDIDATE**

This document records the PS12 source-level acceptance contract. It does not claim merge, immutable release publication, real-server deployment, provider activation, DNS changes, or live production traffic before those events actually occur.

Final source acceptance is sealed only after PR #112 is merged into `integration/rosta-release-candidate` and `PS12 Final Source Release` creates and verifies the immutable `rosta-pre-server-2026-09-05` release.

## Identity and lineage

- Previous immutable source release: `rosta-pre-server-2026-09-03`
- Previous accepted source SHA: `af7a871d48ca4865a9509b8271b66ed380c57d24`
- PS12 branch: `phase/rosta-ps12-b2b-cafes-wholesale`
- PS12 pull request: `#112`
- Last implementation checkpoint before closure documents: `8adcb0dce494e6d4f2d0a70ed92c8bd4e822d4b6`
- Final PS12 source identity: the accepted merge commit protected by `rosta-pre-server-2026-09-05`; it must not be guessed or pre-recorded here.

## Accepted source scope

### Verified cafe accounts

- A cafe has an explicit lifecycle: pending, verified, suspended or rejected.
- Cafe application is authenticated and server-owned; admin verification/rejection/suspension is a separate privileged action.
- Cafe ownership/management uses scoped membership and `cafe_owner` / `cafe_manager` roles rather than a client-side flag.
- Wholesale entitlement is granted only when an eligible user belongs to a verified cafe.
- Pending, rejected and suspended cafes do not receive wholesale pricing.

### Wholesale pricing truth

- Roasteries may define variant-level wholesale unit prices at the fixed thresholds 5 kg, 10 kg, 20 kg and 50 kg.
- The authoritative total weight is derived from the persisted product Variant weight multiplied by requested quantity; the browser cannot submit an authoritative kilogram total.
- A wholesale unit price may not exceed its retail unit price, and tier ordering must remain non-increasing as quantity weight increases.
- Checkout quantity contracts support bulk orders while server-side availability and inventory controls remain authoritative.
- Quote resolves the eligible retail/wholesale unit price before the existing financial calculation and stores the result in the quote snapshot.
- Wholesale pricing snapshot identity is `ps12-wholesale-tier-v1`; existing order Financial Truth remains `ps4a-financial-truth-v1` and is not replaced by a parallel financial system.
- Quote-to-Order conversion re-resolves current cafe eligibility and tier selection and fails closed when the persisted quote no longer matches current authoritative pricing.

### Public cafe directory

- Only verified cafes are returned by the public directory.
- Public search supports city filtering and optional latitude/longitude/radius proximity ordering.
- Location queries are request-scoped directory search inputs. PS12 does not claim continuous background location tracking.
- Public cafe profile data is intentionally separate from private/admin membership and verification controls.

### Frontend workspaces

- Public cafe directory and cafe profile routes are present.
- Authenticated cafe application and cafe portal surfaces are present.
- Admin cafe verification workspace is present.
- Roastery wholesale-tier management workspace is present.
- Product detail visibly exposes the selected Variant's published wholesale tiers in IRR and explains that display alone does not create B2B entitlement; the Quote remains authoritative.
- The product remains whole-bean inventory. PS12 does not introduce a grind selector or change product identity.

## Commercial truth boundary

PS12 does **not** fabricate or silently add:

- trade credit or net-payment terms;
- deferred invoicing/account receivable limits;
- guaranteed delivery SLAs for cafes;
- provider-specific payout capability;
- tax/legal claims that are not already supported by the accepted finance contracts.

Any future credit, invoice, tax or contractual B2B term must be separately approved and source-modeled before being represented as available.

## Security and financial invariants

- wholesale entitlement is server-authorized;
- wholesale money is not accepted from the browser as authoritative;
- persisted Variant weight is the source of weight truth;
- Quote and Order both enforce the B2B pricing contract;
- stale eligibility or pricing fails closed instead of silently preserving an invalid discount;
- existing inventory, financial truth, idempotency, payment and refund contracts remain in force;
- cafe verification and seller wholesale management are role/scoped operations;
- historical PS9/PS10/PS11 release identities remain immutable.

## Required exact-head gates

Before PR #112 may merge, the exact PS12 source head must have successful pull-request runs for:

1. `CI`
2. `Backend CI`
3. `Full-stack Integration CI`
4. `Browser Acceptance CI`
5. `R3 Final Gate`
6. `R4 Staging Package CI`
7. `Production Package CI`
8. `PS8A Frontend Acceptance`
9. `PS8B Backend Finance Acceptance`
10. `PS8C Infrastructure Acceptance`
11. `PS1 Backend Wrapper CI`
12. `PS9 Final Pre-server Freeze`
13. `PS10 Final Source Release`
14. `PS11 Final Source Release`
15. `PS12 Final Source Release`

The post-merge release job independently verifies this exact-head PR matrix before creating the PS12 tag.

## Release verdict rules

PS12 may be declared **DONE / MERGED / FROZEN / RELEASED** only when all are true:

1. PR #112 is not Draft and has no unresolved blocking review state.
2. Every required exact-head workflow above is successful.
3. PR #112 is merged with a normal two-parent merge commit that preserves the accepted source tree.
4. `rosta-pre-server-2026-09-05` resolves to that accepted merge commit.
5. A non-draft GitHub release exists for the tag with sealed evidence assets.
6. `rosta-pre-server-2026-09-03` still resolves to `af7a871d48ca4865a9509b8271b66ed380c57d24`.

Until those conditions are verified, the truthful state is **PS12 IN PROGRESS / SOURCE ACCEPTANCE CANDIDATE**.

## External/runtime boundary

PS12 source closure does not claim deployment to a real VPS, DNS cutover, live payment/SMS/R2/carrier/payout activation, production cafe contracts, or live customer traffic. Those require separate runtime/provider evidence.