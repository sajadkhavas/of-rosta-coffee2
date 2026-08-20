# ROSTA Partner Experience Engine Contract

Status: PS0.1 business architecture reference

## 1. Purpose

ROSTA must support external experience partners without coupling Core marketplace or fulfillment logic to one brand/provider.

Canonical architecture:

```text
External Partner
  -> Partner Adapter / Configuration
  -> ROSTA Partner Experience Engine
  -> Approved Experience/Campaign Rule
  -> Eligible Order/Fulfillment Experience
  -> Customer
```

## 2. Generic Partner Engine

The Core concept is **Partner Experience Engine**.

It owns platform rules for:

- partner identity/status;
- campaign eligibility;
- attribution/reference;
- gift/sample/coupon/insert rules;
- start/end lifecycle;
- fulfillment applicability;
- auditability and policy controls.

No partner-specific API or data shape may become the Core business model.

## 3. Supported experience types

The engine may support approved experiences such as:

### Gift

An eligible customer/order receives a defined gift under campaign rules.

### Sample

An approved sample may be attached to an eligible fulfillment/package experience.

### Coupon

A Partner-funded or Partner-associated coupon may be represented under marketplace coupon/finance rules. Partner configuration must not bypass pricing/financial truth.

### Insert

An approved physical insert may be included only in a fulfillment mode/location capable of executing it.

### Campaign

A versioned set of eligibility, timing and experience rules.

## 4. Winimi boundary

Winimi is treated as an **initial/example Partner adapter**, not a Core dependency.

Allowed architecture:

```text
PartnerProviderInterface / Adapter Boundary
  |-- Winimi adapter
  |-- future brand adapter
  `-- future corporate/lifestyle adapter
```

Core order, fulfillment, loyalty and finance models must continue to operate if Winimi is disabled or removed.

## 5. Fulfillment integration

A partner physical experience must respect fulfillment reality.

Examples:

- ROSTA Hub insert -> may be executed in ROSTA Fulfillment if the campaign is eligible and material is available.
- Direct Fulfillment insert -> requires an explicit Roastery/partner operational contract; it must not be assumed merely because an insert campaign exists.

Every physical addition must preserve Chain of Custody and package responsibility.

## 6. Data boundary

Partners receive only data necessary for the approved experience.

Default restrictions:

- no unrestricted customer export;
- no unrelated direct marketing from fulfillment/order data;
- no access to other partners' data;
- identifiers should be minimized/pseudonymous where feasible;
- retention follows platform policy and partner agreement.

## 7. Financial boundary

Partner campaign funding, discounts, fees or commissions must connect to approved financial/ledger policy. The Partner Engine must not invent monetary truth independently.

## 8. Extensibility

Future partner categories may include:

- coffee equipment brands;
- food brands;
- lifestyle brands;
- corporate partners;
- subscription-related partners.

Adding one must not require redesigning Master Order/Sub-order, product ownership or fulfillment Core contracts.

## 9. Ownership, boundary, escalation

| Capability | Owner | Boundary | Escalation Path |
|---|---|---|---|
| Partner Engine | ROSTA | generic campaign/experience rules | Partner Ops -> Admin |
| Partner-provided material/rule input | Partner | approved partner scope | Partner -> Partner Ops |
| Hub insert execution | ROSTA Fulfillment | ROSTA custody/package stage | Fulfillment -> Partner Ops/Support |
| Direct insert execution | Roastery only when explicitly contracted | own Direct Fulfillment scope | Roastery -> Partner Ops |
| Customer-facing case | ROSTA | customer relationship | Support -> Partner Ops/domain owner |
