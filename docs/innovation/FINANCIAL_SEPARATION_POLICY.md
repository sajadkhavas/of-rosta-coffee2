# ROSTA Innovation Financial Separation Policy

Version: **1.0**
Date: **2026-08-20**

## Purpose

ROSTA must be able to distinguish marketplace money, ordinary service revenue, technology-product revenue and R&D cost from the first real transaction. This prevents future knowledge-based, grant, investment, accounting or tax claims from being built on ambiguous totals.

This policy is architectural/accounting-readiness guidance. Exact accounting and tax treatment must be approved under the rules in force by qualified advisors.

## Permanent financial truth

The platform must never use a single ambiguous `revenue` total for all money collected.

At minimum distinguish:

- customer collections;
- marketplace GMV;
- seller-owned product amount;
- seller payable;
- ROSTA marketplace commission revenue;
- ROSTA grinding revenue;
- ROSTA fulfillment revenue;
- ROSTA packaging revenue where charged;
- shipping collected / carrier payable / shipping margin where applicable;
- marketing/partner revenue;
- technology-product revenue;
- grants/subsidies/support receipts;
- Growth Partner commissions;
- refunds/reversals;
- taxes/VAT liabilities;
- R&D expenses;
- ordinary product/operations engineering expense.

## Technology revenue must be explicit

A transaction may be tagged as technology-product revenue only if all of the following are true:

1. the technology product/service has a stable internal product identifier;
2. there is an approved commercial scope/SKU/contract line;
3. the customer/contract is paying for that technology product/service, not merely using the ROSTA marketplace;
4. the revenue amount is deterministically allocatable;
5. the product/version/effective period is retained;
6. the legal entity earning the revenue is explicit.

Examples of potential future technology revenue:

- B2B Taste Intelligence API subscription;
- Fulfillment Decision Engine service fee;
- Traceability/quality analytics subscription;
- licensed technology module.

Marketplace commission does not become technology revenue merely because the marketplace uses a recommendation engine internally.

## Cost tagging

R&D management accounting should support tags such as:

```text
cost_center = ROSTA_CORE | TASTE_RD | FULFILLMENT_RD | TRACEABILITY_RD | OPERATIONS | SALES | ADMIN
candidate = null | taste-intelligence | fulfillment-intelligence | traceability-quality
rd_record_id = optional
legal_entity_id = required when company cutover begins
```

A cost may be allocated across cost centers only using a documented allocation basis.

## Evidence linkage

For material R&D costs, retain references to:

- invoice/payroll/contract;
- payment evidence;
- period;
- contributor/vendor;
- R&D candidate;
- experiment/ADR/release where meaningful.

This creates a trace from financial spend to actual R&D activity.

## Grants/support/incentives

Record every support program as a scoped entitlement/receipt, not generic revenue exemption.

Capture:

- program/provider;
- legal entity;
- approval identifier;
- amount/type;
- approved purpose;
- technology/product scope;
- expense/revenue restrictions;
- reporting obligations;
- effective/expiry dates;
- evidence source.

## Knowledge-based tax safety

If a future ROSTA company/product receives an approved tax benefit, implementation must use a scoped policy object. Never set a global flag such as:

`ROSTA_IS_TAX_FREE = true`

Instead retain:

- approved legal entity;
- approved product/service identifiers;
- approved revenue categories;
- benefit basis;
- effective period;
- official approval/evidence;
- reporting method;
- advisor/auditor confirmation where required.

Unapproved revenue categories remain under normal accounting/tax treatment.

## Marketplace example

Customer pays for a Roastery product. The payment may pass through ROSTA, but Financial Truth must preserve:

```text
Customer Collection
  -> Seller-owned product amount
  -> Seller payable
  -> ROSTA commission (if earned)
  -> taxes/shipping/services/discount allocations
```

No technology classification changes seller ownership or GMV truth.

## Mixed invoice/contract example

A future B2B client could buy:

- ROSTA marketplace access/service;
- Taste Intelligence API;
- fulfillment operations.

The commercial/accounting layer must allocate each line to its own product/revenue category rather than reporting the entire contract as knowledge-based technology revenue.

## Reporting outputs

Future Finance/Admin systems should be able to export, by period and legal entity:

1. marketplace GMV;
2. seller payables and settlements;
3. ROSTA revenue by category;
4. technology revenue by candidate/product/version;
5. R&D spend by candidate;
6. grants/support;
7. tax/VAT category and policy version;
8. refunds/reversals;
9. reconciliation to bank/gateway.

## Change control

Financial category definitions are policy-versioned. Historic transactions retain the policy/version in force when booked; changing a label in the current settings must not rewrite historic financial truth.
