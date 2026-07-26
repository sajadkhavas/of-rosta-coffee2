# R5E — Roastery Grinding Capability

Status: implementation phase

## Purpose

R5E publishes and manages each roastery's grinding capability without changing the product, SKU, roast-batch, inventory-reservation or stock-ledger identity. Rosta continues to sell and stock whole beans only.

## Delivered contract

- Rosta owns a versioned catalogue of approved grinding profiles.
- A roastery owner or manager can declare grinding as available or unavailable.
- An available capability declares supported profiles, supported whole-bean weights, free or fixed service fee, preparation time and optional daily capacity.
- Free grinding is represented explicitly with a zero amount.
- Public roastery resources expose the active capability and supported profiles.
- Inactive capability records are not published on customer-facing roastery resources.
- All seller writes are validated, scoped to the roastery and recorded in the append-only audit log.

## Initial approved profiles

1. Turkish coffee
2. Home espresso with pressurised basket
3. Moka pot
4. AeroPress
5. V60
6. Chemex
7. Filter coffee machine
8. French press
9. Cold brew

## Boundaries

R5E does not attach grinding to cart, quote or order items. Customer selection, immutable service snapshots, pricing allocation and settlement begin in R5F.

Rosta Hub eligibility, Tehran/Karaj service-zone validation, Hub packaging and route fulfilment remain outside R5E and begin only after the roastery-provided grinding path is complete.

## Exit criteria

- approved profiles are seeded idempotently;
- public profile catalogue is available;
- owner/manager capability writes are scoped and authoritative;
- public roastery detail displays the active capability;
- frontend contracts reject malformed capability data;
- permanent backend and frontend audits pass;
- no grinding variant, SKU or stock dimension is introduced.

Exit marker: `ROSTA_R5E_GRINDING_CAPABILITY_COMPLETE`
