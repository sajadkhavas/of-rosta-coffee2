# R5D — Optional Roastery Product Packaging

Status: implementation phase  
Parent program: `integration/rosta-r5-marketplace`

## Scope

R5D activates the product-level packaging policy approved in R5A and prepared by the R5B service schema.

A roastery owner or manager chooses one policy per product:

```text
free
fixed positive amount per purchased package
```

The customer must see the policy before checkout. A zero fee is never hidden; it is displayed as `بسته‌بندی روستری رایگان`.

## Authoritative rules

1. Laravel owns mode, amount, multiplication, totals and settlement ownership.
2. `free` always normalizes its amount to zero, even when a client sends another value.
3. `fixed` requires a positive amount.
4. The fixed amount is charged for each purchased package/quantity of the selected Variant.
5. Product and inventory identity remain whole-bean only; packaging is not a Variant or stock dimension.
6. Quote creation writes one immutable `packaging` service snapshot for every quote item, including free services.
7. Order creation copies each quote service to an `order_item_service`.
8. A non-zero packaging service creates a held roastery-owned settlement allocation.
9. A free packaging service remains visible on the customer invoice but does not create payable money.
10. Existing products and legacy orders remain compatible through the default `free / 0` policy.

## Financial example

```text
Product price                 2,000,000 IRR
Quantity                      3
Product subtotal              6,000,000 IRR
Packaging fee per package       125,000 IRR
Packaging total                 375,000 IRR
```

Discount allocation continues to apply to product lines under the R5C contract. Packaging is a separate invoice and settlement line.

## Customer surfaces

The contract exposes packaging on:

- public and seller product resources;
- cart validation and checkout Quote items;
- per-roastery Quote totals;
- created and historical orders;
- invoice-style order details;
- seller catalog controls.

## Deliberate boundary

Rosta Hub packaging remains outside R5D. R5D implements only packaging supplied by the product's roastery. When Rosta Hub grinding is activated later, that path will force the roastery packaging line to zero and add an explicit free Rosta Hub packaging service.

## Exit criteria

- product schema and enum committed;
- seller writes normalized by one domain service;
- Quote and Order service snapshots committed;
- paid packaging settlement allocation committed;
- zero fee visible in resources and UI contracts;
- feature tests, static analysis, formatting and all permanent gates green;
- permanent marker emitted:

```text
ROSTA_R5D_PRODUCT_PACKAGING_COMPLETE
```
