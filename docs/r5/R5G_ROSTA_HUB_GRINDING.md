# R5G — Rosta Hub Grinding and Multi-leg Routing

Status: **Implementation complete; acceptance pending**
Program branch: `integration/rosta-r5-marketplace`
Product branch: `program/r5g-rosta-hub-grinding-routing`
Acceptance candidate: locked product tree after Laravel Pint normalization

## Purpose

R5G offers an approved grinding profile through an enabled Rosta Hub only when the selected roastery explicitly declares grinding unavailable and the authoritative delivery address matches an enabled Tehran or Karaj service zone.

## Delivered scope

- Versioned Hub persistence for facilities, approved profiles, whole-bean weights, fee, preparation time and daily capacity.
- Explicit enabled service zones with separate roastery-to-Hub and Hub-to-customer shipping charges.
- Laravel-authoritative provider selection; the browser sends only `grinding_profile_id`.
- Cart-stage provisional Hub explanation and checkout-stage mandatory address revalidation.
- Hub grinding snapshot, zero roastery packaging override and explicit free Hub packaging invoice line.
- Rosta-owned grinding and shipping allocations that never enter roastery payable balance.
- Two immutable shipment legs: `roastery_to_rosta_hub` and `rosta_hub_to_customer`.
- Customer-visible provider labels and route stages.
- Permanent backend/frontend audits and feature/unit acceptance.

## Boundaries

- Product, variant, SKU, roast batch, stock ledger and reservation identities remain whole-bean only.
- A capable roastery remains authoritative; Hub fallback is forbidden.
- Live Hub operator assignment and processing-state transitions remain a later operations phase.

## Exit markers

```text
ROSTA_R5G_HUB_GRINDING_COMPLETE
ROSTA_R5G_HUB_GRINDING_FRONTEND_COMPLETE
```
