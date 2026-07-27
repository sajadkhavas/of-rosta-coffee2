# R5F — Roastery Grinding Selection

Status: **Implementation complete; official acceptance in progress**  
Program branch: `integration/rosta-r5-marketplace`  
Product branch: `program/r5f-roastery-grinding-selection`

## Purpose

R5F lets a customer attach a roastery-provided grinding service to an existing whole-bean cart line. The product, variant, SKU, roast batch and inventory reservation remain whole-bean identities. Grinding is stored only as a quote item service and immutable order item service.

## Customer contract

- Whole beans remain the default selection.
- Grinding is selected per cart line after a whole-bean variant exists.
- The customer chooses only an approved profile identifier.
- Laravel resolves provider, eligibility, fee, preparation time, capacity and settlement owner.
- An unavailable, unsupported or stale selection fails closed.
- A customer never submits a fee, provider identity, capacity result or settlement owner.

## Authoritative quote contract

Laravel validates:

1. the roastery is still public;
2. its grinding capability is active and available;
3. the selected profile is active and attached to that capability;
4. the whole-bean weight is supported;
5. daily operational capacity is sufficient;
6. the fee comes from the current capability;
7. the fee is multiplied by purchased package quantity with overflow protection.

The quote stores a versioned profile recipe, weight, quantity, fee mode, unit fee, line total, preparation time and provider snapshot.

## Order contract

Order creation locks and revalidates the capability before copying the quote service. The order receives:

- one immutable `grinding` service per selected order item;
- `provider_type=roastery`;
- the versioned grinding profile reference and snapshot;
- requested service state;
- one held roastery-owned grinding settlement allocation when the fee is non-zero;
- an append-only `grinding.requested` customer event;
- idempotent replay through the existing parent-order contract.

A later capability or recipe change cannot mutate the existing order snapshot.

## Customer surfaces

- cart selector with whole-bean default;
- server-validated grinding line and fee;
- checkout grinding total;
- order item service details, selected profile and state;
- explicit free grinding where applicable.

## Deliberate boundaries

R5F does not implement:

- Rosta Hub eligibility or Tehran/Karaj routing;
- Hub packaging or multi-leg Hub shipment planning;
- operator assignment;
- grinding service processing transitions;
- customer cancellation or seller rejection changes.

Those remain R5G and later phases.

## Exit markers

```text
ROSTA_R5F_ROASTERY_GRINDING_COMPLETE
ROSTA_R5F_ROASTERY_GRINDING_FRONTEND_COMPLETE
```

The phase is accepted only after all permanent frontend, backend, integration, browser, R3 and R4 gates pass on the official product PR.
