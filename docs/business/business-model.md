# ROSTA Business Model Contract

Status: PS0.1 business architecture reference
Project: ROSTA Coffee Marketplace

## 1. Business identity

ROSTA is not a single-vendor coffee shop. The canonical business model is:

**Multi-vendor Marketplace + Fulfillment Network + Coffee Experience Platform**

This document defines business truth. It distinguishes already-established marketplace invariants from future/backlog capabilities and must not be read as claiming that every roadmap capability is already live.

## 2. Non-negotiable business invariants

1. ROSTA is a multi-vendor marketplace.
2. A customer checkout creates one **Master Order** and one or more **Roastery Sub-orders**.
3. Product ownership and product truth remain with the selling Roastery until transferred or otherwise governed by the applicable commercial/fulfillment agreement.
4. ROSTA operates the primary customer-facing platform relationship; personal-data responsibilities are governed separately in `customer-data-governance.md`.
5. Whole bean is the inventory/product truth. Grinding is an optional **Order Item Service**, never a product variant.
6. A valid paid Roastery Sub-order creates a seller fulfillment commitment. There is no normal post-payment seller accept/reject flow; seller exceptions are reported as incidents and resolved through authorized policy workflows.
7. Direct Fulfillment and ROSTA Fulfillment are distinct supported operating modes with distinct responsibility boundaries. Launch policy may prefer one mode or a centralized ROSTA network without deleting the other supported capability.
8. Every physical custody handoff in ROSTA Fulfillment must be represented by Chain of Custody evidence.
9. GMV is not ROSTA revenue.
10. Growth Partner commissions become payable only through ledger-backed financial records under the approved Growth policy.
11. Partner experiences are governed generically. Winimi is only an initial/example partner and is never a Core dependency.
12. Every business capability must have an Owner, Boundary, and Escalation Path.

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
- loyalty surfaces governed by the dedicated Loyalty capability;
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

Each **Roastery Sub-order** carries seller-specific operational truth for:

- its order items;
- its inventory commitments;
- its automatic post-payment fulfillment commitment;
- its fulfillment mode/plan;
- preparation and fulfillment milestones;
- its shipment legs;
- its seller-scoped incidents and authorized exception outcomes.

A failure in one Sub-order must not silently rewrite the truth of healthy sibling Sub-orders.

## 4. Revenue model

### 4.1 GMV

**GMV = total marketplace transaction value attributable to customer purchases.**

GMV is a marketplace volume metric and must never be reported as ROSTA revenue merely because collection passes through a ROSTA payment account.

### 4.2 ROSTA revenue sources

ROSTA revenue may include, subject to published commercial/financial policy:

- Marketplace Commission;
- ROSTA Fulfillment Fee;
- Grinding Service revenue where ROSTA provides the service;
- Packaging/logistics service revenue where contractually applicable;
- Marketing/Partner service revenue;
- independently identifiable technology-product/service revenue where applicable in the future.

Canonical relationship:

```text
ROSTA Revenue
  = recognized Commission
  + recognized Fulfillment Fees
  + recognized Service Revenue
  + recognized Marketing/Partner Revenue
  + recognized Technology Revenue where applicable
```

Seller-owned amounts, taxes collected on behalf of another party, carrier amounts, refunds and other pass-through/payable allocations remain separately classified by Financial Truth.

The financial system determines recognition, settlement, refunds, reversals and taxes. Business documents must not infer recognized revenue directly from GMV.

## 5. Fulfillment modes

### Direct Fulfillment

**Operational responsibility: Roastery for seller-controlled stages**

The Roastery prepares the committed Sub-order, performs its assigned Order Item Services, packs and dispatches it, and records shipment/tracking evidence according to marketplace policy. ROSTA remains the customer-facing support owner and monitors SLA/exception truth.

### ROSTA Fulfillment

**Operational responsibility: ROSTA for contracted hub stages after accepted custody handoff**

The relevant goods enter the ROSTA Fulfillment Network. ROSTA is responsible for the contracted hub stages such as receiving, optional grinding, QC, packaging, approved partner inserts and dispatch.

The exact physical responsibility of a stage begins only when its Chain of Custody handoff is recorded.

### Launch operating policy versus supported capability

Technical/business capability supports both Direct and ROSTA Fulfillment. A launch operating policy may route all or most eligible orders through a centralized ROSTA Hub for consolidated receiving, grinding where required, ROSTA packaging and outbound dispatch. Such a launch policy is configuration/operations policy; it does not silently delete Direct Fulfillment from the platform contract.

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

ROSTA Growth Network is governed by the approved Growth Partner policy. Attribution and payable commission are distinct. A qualifying event must create an auditable Financial Ledger entry and follow approval, availability, reversal and payout policy.

Partner experiences are governed generically. Partners may contribute gifts, samples, coupons, inserts or campaign experiences under explicit rules. Winimi is an initial/example partner, not a Core architectural dependency.

## 8. Customer data governance

Personal customer data is not described as property owned by ROSTA.

Canonical roles:

- Customer: Data Subject.
- ROSTA: Primary Controller/Custodian for platform operations, subject to the actual applicable legal role and policy.
- Roastery: limited authorized recipient/processor only where operationally necessary and subject to the actual processing context.

Purpose limitation, least privilege, policy-driven retention/deletion/privacy rights and restrictions on export/direct marketing apply. See `customer-data-governance.md`.

## 9. Capability roadmap boundary

Approved roadmap capabilities such as Loyalty, Coffee Subscription and Discovery Subscription are product commitments/backlog concepts, not claims that the capability is already implemented or commercially active.

When implemented, they must reuse the existing marketplace, fulfillment, privacy and Financial Truth contracts rather than invent parallel commercial truth.

See `subscription-vision.md` for subscription compatibility rules.

## 10. Decision rule

If a future implementation conflicts with this contract, either the implementation must be corrected or this contract must be explicitly reviewed and versioned. Silent divergence is not allowed.
