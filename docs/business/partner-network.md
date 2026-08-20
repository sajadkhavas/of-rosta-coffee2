# ROSTA Partner Experience Business Contract

Status: PS0.1 business architecture reference

## 1. Purpose

ROSTA must support external experience partners without coupling Core marketplace, order, fulfillment, loyalty or finance truth to one brand/provider.

Canonical business relationship:

```text
External Partner
  -> Approved partner relationship/configuration
  -> ROSTA Partner Experience rules
  -> Approved campaign/experience
  -> Eligible order/fulfillment/customer experience
```

This document defines business boundaries only. Technical interfaces, adapters, provider contracts and API shapes belong to later technical/API architecture phases.

## 2. Generic Partner Experience model

The Core business concept is a generic **Partner Experience** capability.

ROSTA owns the platform-side rules for:

- partner identity/status;
- campaign eligibility;
- experience type;
- funding/commercial responsibility;
- gift/sample/coupon/insert rules;
- start/end lifecycle;
- fulfillment applicability;
- customer-data boundary;
- auditability and policy controls.

No partner-specific technical integration or data shape may become a Core business invariant.

## 3. Supported experience types

The capability may support approved experiences such as:

### Gift

An eligible customer/order receives a defined gift under campaign rules.

### Sample

An approved sample may be attached to an eligible fulfillment/package experience.

### Coupon

A Partner-funded or Partner-associated coupon may be represented under marketplace promotion/finance rules. Partner configuration must not bypass pricing or Financial Truth.

### Insert

An approved physical insert may be included only in a fulfillment mode/location capable of executing it.

### Campaign

A versioned set of eligibility, timing, funding and experience rules.

## 4. Winimi boundary

Winimi is treated only as an **initial/example Partner** for a possible gifted-cookie/customer-experience program.

Business invariants:

- Core ROSTA commerce must continue if Winimi is disabled, removed or replaced;
- no order, fulfillment, loyalty or financial rule may require Winimi specifically;
- future partners must be able to participate under the same generic business contract;
- any technical Winimi-specific integration is implementation detail for a later phase, not PS0.1 business truth.

## 5. Fulfillment integration

A physical partner experience must respect fulfillment reality.

Examples:

- ROSTA Hub gift/insert -> may be executed in ROSTA Fulfillment only when the campaign is eligible and material is available;
- Direct Fulfillment gift/insert -> requires an explicit operational agreement with the Roastery and must not be assumed merely because a campaign exists.

Every physical addition must preserve package responsibility and Chain of Custody where applicable.

A centralized launch operating policy may make ROSTA Hub the preferred place for partner gifts/inserts because ROSTA controls final packaging, but that remains an operating-policy choice rather than a permanent Core requirement.

## 6. Data boundary

Partners receive only data necessary for the approved experience.

Default restrictions:

- no unrestricted customer export;
- no unrelated direct marketing from fulfillment/order data;
- no access to other partners' data;
- identifiers should be minimized/pseudonymous where feasible;
- retention follows platform policy and partner agreement;
- a physical gift/insert does not automatically justify sharing customer identity with the Partner.

## 7. Financial boundary

Partner campaign funding, discounts, fees, barter/value exchange or commissions must connect to approved commercial and financial policy. The Partner Experience capability must not invent monetary truth independently.

Financial policy must distinguish, where applicable:

- Partner-funded gift/sample cost;
- ROSTA-funded experience cost;
- shared funding;
- marketing/service revenue paid to ROSTA;
- Promotion/Coupon funding;
- inventory/material custody;
- tax/invoice treatment as externally validated.

## 8. Extensibility

Future partner categories may include:

- coffee equipment brands;
- food brands;
- lifestyle brands;
- corporate partners;
- subscription-related partners.

Adding one must not require redesigning Master Order/Sub-order, product ownership, fulfillment Core or Financial Truth.

## 9. Ownership, boundary, escalation

| Capability | Owner / Accountability | Boundary | Escalation Path |
|---|---|---|---|
| Partner Experience policy | ROSTA | generic campaign/experience rules | Partner Ops -> Admin/policy owner |
| Partner-provided material/rule input | Partner under agreement | approved partner scope | Partner -> Partner Ops |
| Hub gift/insert execution | ROSTA Fulfillment | ROSTA custody/package stage | Fulfillment -> Partner Ops/Support |
| Direct gift/insert execution | Roastery only when explicitly agreed | own Direct Fulfillment scope | Roastery -> Partner Ops |
| Customer-facing case | ROSTA | customer relationship | Support -> Partner Ops/domain owner |
| Financial treatment | ROSTA Finance + applicable agreement/policy | ledger/invoice/funding truth | Partner Ops -> Finance -> policy/legal review where needed |
