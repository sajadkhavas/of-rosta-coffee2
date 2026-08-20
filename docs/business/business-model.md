# ROSTA Business Model Contract

Status: PS0.1 business architecture reference  
Project: ROSTA Coffee Marketplace

## 1. Business identity

ROSTA is not a single-vendor coffee shop. The canonical business model is:

**Multi-vendor Marketplace + Fulfillment Network + Coffee Experience Platform**

This document defines business truth only. It does not claim that every capability described here is already implemented.

## 2. Non-negotiable business invariants

1. ROSTA is a multi-vendor marketplace.
2. A customer checkout creates one **Master Order** and one or more **Roastery Sub-orders**.
3. Product ownership and product truth remain with the selling Roastery until transferred or otherwise governed by the applicable commercial/fulfillment agreement.
4. ROSTA owns the platform relationship and operates as the primary customer-facing marketplace operator; personal-data responsibilities are governed separately in `customer-data-governance.md`.
5. Whole bean is the inventory/product truth. Grinding is an optional **Order Item Service**, never a product variant.
6. Direct Fulfillment and ROSTA Fulfillment are distinct operating modes with distinct responsibility boundaries.
7. Every physical handoff in ROSTA Fulfillment must be represented by Chain of Custody evidence.
8. GMV is not ROSTA revenue.
9. Growth Partner commissions become payable only through ledger-backed financial records.
10. Partner experiences are provided through a generic Partner Experience Engine. Winimi is only an initial/example adapter and is never a Core dependency.
11. Every business capability must have an Owner, Boundary, and Escalation Path.

## 3. Marketplace model

### 3.1 Product ownership

**Owner: Roastery**

The Roastery is accountable for:

- coffee quality;
- origin and provenance information supplied to the marketplace;
- roast profile and production truth;
- product descriptions and product-specific claims;
- sellable inventory truth;
- product compliance obligations assigned to it by policy/agreement.

ROSTA may validate, moderate, normalize, reject, suspend, or present product information according to platform policy, but that does not transfer product-origin truth to ROSTA.

### 3.2 Customer relationship

**Primary platform relationship: ROSTA**

ROSTA is responsible for the marketplace customer journey, including:

- account experience;
- checkout orchestration;
- support;
- loyalty;
- marketplace communications;
- cross-roastery order experience;
- platform recommendations and discovery.

Roasteries receive only the customer/order data operationally necessary for their own Sub-orders and must not use fulfillment data for unrelated direct marketing.

### 3.3 Multi-vendor order model

A checkout is represented as:

```text
Customer
  |
Master Order (ROSTA orchestration)
  |-- Sub-order A -> Roastery A
  |-- Sub-order B -> Roastery B
  `-- Sub-order N -> Roastery N
```

The **Master Order** is the customer-facing aggregate. It coordinates payment state, overall customer experience, address context, support, loyalty effects and aggregate lifecycle.

Each **Roastery Sub-order** owns the seller-specific operational lifecycle for:

- its order items;
- its inventory commitments;
- its acceptance/rejection;
- its fulfillment mode;
- preparation and fulfillment milestones;
- its shipment legs and seller-scoped incidents.

A failure in one Sub-order must not silently rewrite the truth of healthy sibling Sub-orders.

## 4. Revenue model

### 4.1 GMV

**GMV = total marketplace transaction value attributable to customer purchases.**

GMV is a marketplace volume metric and must never be reported as ROSTA revenue.

### 4.2 ROSTA revenue sources

ROSTA revenue may include, subject to published commercial/financial policy:

- Marketplace Commission;
- ROSTA Fulfillment Fee;
- Grinding Service revenue where ROSTA provides the service;
- Marketing/Partner service revenue.

Canonical relationship:

```text
ROSTA Revenue
  = recognized Commission
  + recognized Fulfillment Fees
  + recognized Service Revenue
  + recognized Marketing/Partner Revenue
```

The financial system determines recognition, settlement, refunds, reversals and taxes. Business documents must not infer recognized revenue directly from GMV.

## 5. Fulfillment modes

### Direct Fulfillment

**Operational Owner: Roastery**

The Roastery prepares, packs and dispatches its own Sub-order and records shipment/tracking evidence according to marketplace policy. ROSTA remains the customer-facing support owner and monitors SLA/exception truth.

### ROSTA Fulfillment

**Operational Owner: ROSTA**

The relevant goods enter the ROSTA Fulfillment Network. ROSTA is responsible for the contracted hub stages such as receiving, optional grinding, QC, packaging, partner inserts and dispatch.

The exact physical responsibility of a stage begins only when its Chain of Custody handoff is recorded.

## 6. Grinding rule

Default product/inventory truth is **Whole Bean**.

Ground coffee is represented as an optional Order Item Service:

```text
Order Item
  + Grinding Service
      provider = Roastery | ROSTA Hub
      service profile = order-specific instruction
```

Grinding must not create a separate coffee product variant. This preserves one product/inventory truth while allowing service pricing, provider selection and fulfillment responsibility to evolve independently.

## 7. Growth and partner economy

Growth Partner attribution may create commission eligibility, but no commission is payable merely because attribution exists. A qualifying event must create an approved, auditable Financial Ledger entry and follow settlement/reversal policy.

Partner experiences use the generic Partner Experience Engine. Partners may contribute gifts, samples, coupons, inserts or campaign experiences under explicit rules. Winimi is an adapter/example, not a Core architectural dependency.

## 8. Customer data governance

Personal customer data is not described as property owned by ROSTA.

Canonical roles:

- Customer: Data Subject.
- ROSTA: Primary Controller/Custodian for platform operations.
- Roastery: limited authorized recipient/processor only where operationally necessary.

Purpose limitation, least privilege, policy-driven retention/deletion/privacy rights and restrictions on export/direct marketing apply. See `customer-data-governance.md`.

## 9. Business vision

The architecture must remain extensible to future subscription commerce without making subscription an implicit current capability. A future subscription may generate scheduled Master Orders which decompose into Roastery Sub-orders and then use the same fulfillment, ledger, SLA, privacy and support contracts.

See `subscription-vision.md`.

## 10. Decision rule

If a future implementation conflicts with this contract, the implementation must be changed or this contract must be explicitly reviewed and versioned. Silent divergence is not allowed.
