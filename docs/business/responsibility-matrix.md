# Responsibility Matrix

Status: PS0.1 business architecture reference

This matrix is the canonical quick-reference for accountability, operational boundaries and escalation between ROSTA, Roastery, Carrier and Partners. Labels such as Owner in this document describe business/operational accountability; they do not by themselves determine legal title or liability.

## Product Quality

**Primary accountability: Roastery**

Responsible for:

- coffee quality;
- Origin/provenance information supplied to ROSTA;
- Roast Profile;
- product information and product-specific claims.

Boundary: ROSTA may validate/moderate marketplace content, but product-origin truth remains with the Roastery.

Escalation Path: Customer/Support -> Roastery for product investigation -> ROSTA policy resolution if the case remains unresolved.

## Customer Experience

**Primary accountability: ROSTA**

Responsible for:

- Account;
- Support;
- Loyalty surfaces;
- Communication;
- cross-roastery marketplace experience.

Boundary: operational partners receive only purpose-limited customer data. Customer personal data is governed by `customer-data-governance.md`, not by an ownership claim in this matrix.

Escalation Path: Customer -> ROSTA Support -> relevant domain owner -> Admin/policy owner where necessary.

## Payment

**Primary accountability: ROSTA**

Responsible for:

- Payment Flow;
- Transaction Status;
- Refund Process;
- payment/reconciliation customer communication.

Boundary: payment truth and refund truth come from the financial/payment domain, not fulfillment status. Refund/remedy decisions remain policy/contract/applicable-law driven.

Escalation Path: Support -> Finance Operator -> payment/refund policy owner/Admin.

## Direct Fulfillment

**Primary accountability: Roastery for seller-controlled stages**

Responsible for:

- preparation of committed paid Sub-orders;
- seller-provided Order Item Services when assigned;
- Packing;
- Dispatch;
- Tracking handoff evidence;
- seller-controlled preparation and fulfillment SLA;
- truthful incident reporting when a committed obligation cannot be performed.

Boundary: there is no normal post-payment seller accept/reject flow. Roastery operational responsibility continues until the applicable evidenced Carrier or ROSTA handoff, subject to contract/policy.

Escalation Path: Roastery -> ROSTA Support/Ops -> authorized exception workflow -> Carrier claim workflow when the incident begins after Carrier custody.

## ROSTA Fulfillment

**Primary accountability: ROSTA for contracted hub stages after accepted custody**

Responsible for:

- Receiving;
- Grinding when ROSTA Hub is the Order Item Service provider;
- QC;
- Packaging;
- Dispatch;
- approved Marketing/Partner Experience handling.

Boundary: responsibility transfers through Chain of Custody evidence. A centralized launch policy may make ROSTA Fulfillment the preferred route without deleting Direct Fulfillment as a supported capability.

Escalation Path: Fulfillment Operator -> Fulfillment lead -> ROSTA Support/Admin; Finance only for approved financial consequences.

## Shipping Carrier

**Primary accountability: Carrier for carrier-controlled transport operations**

Responsible for:

- Transportation;
- Delivery Process;
- Carrier Exceptions.

For Carrier-caused loss/damage/delay:

**Primary operational responsibility = Carrier, subject to carrier agreement, evidence and applicable claim rules.**

ROSTA remains owner of the customer-facing support case and is responsible for:

- customer communication;
- claim coordination;
- resolution workflow;
- remedy/refund/replacement decision according to policy, contract and applicable law.

Boundary: this contract does not assert absolute legal liability independent of the carrier agreement or applicable law.

Escalation Path: Customer -> ROSTA Support -> Carrier claim process -> ROSTA policy resolution.

## Partner Experience

**Primary accountability: ROSTA Partner Experience capability/policy**

Responsible for:

- Gift rules;
- Sample rules;
- Coupon integration rules;
- Campaign rules;
- Insert eligibility/orchestration;
- funding/data/fulfillment boundaries.

Boundary: external Partners provide only approved experience inputs/materials under agreement. Winimi is an initial/example Partner, not a Core dependency. Technical interfaces/adapters belong to later technical/API architecture.

Escalation Path: Partner/Fulfillment -> ROSTA Partner Ops -> Support/Admin/Finance depending on customer or financial impact.

## Growth Network

**Primary accountability: ROSTA Growth policy + Finance for ledger/payout truth**

Responsible for:

- Starter/Growth/Pro policy;
- First Qualified Referrer Wins attribution;
- Roastery/B2B lead ownership policy;
- qualifying event rules;
- partner ledger and payout controls.

Boundary: no payout for signup/code/cart alone; qualifying revenue and ledger state govern financial outcomes.

Escalation Path: Growth Partner -> Growth Ops -> Finance/Admin policy owner.

## Dispute Ownership / Operational Accountability

- **Product Issue -> Roastery** provides product-domain investigation/evidence; ROSTA owns customer-facing case coordination.
- **Seller fulfillment incident -> Roastery evidence/input + ROSTA authorized exception workflow**; the incident does not itself cancel/refund.
- **Fulfillment Issue in ROSTA custody -> ROSTA** operational accountability.
- **Carrier-caused Damage/Loss/Delay -> Carrier** primary operational responsibility subject to agreement/evidence/claim rules; ROSTA owns customer support and resolution coordination.
- **Payment Issue -> ROSTA** payment/finance accountability.

These labels do not independently determine legal liability; contract, evidence, policy and applicable law govern legal outcomes.

## Summary

| Domain | Owner | Primary Responsibility |
|---|---|---|
| Product Quality | Roastery | Product quality and product-origin truth |
| Customer Experience | ROSTA | Account, support, loyalty surfaces and communication |
| Payment | ROSTA | Payment, transaction, refund and reconciliation workflow |
| Direct Fulfillment | Roastery | Committed preparation, packing, dispatch/handoff and incident reporting |
| ROSTA Fulfillment | ROSTA | Receiving, service execution, QC, packaging and dispatch in ROSTA custody |
| Shipping | Carrier | Carrier-controlled transportation and delivery process, subject to agreement/claim rules |
| Partner Experience | ROSTA Partner Experience capability | Generic partner campaign/experience policy |
| Growth Network | ROSTA Growth + Finance | Attribution/lead policy plus ledger-backed commission/payout |
| Product Dispute | Roastery + ROSTA customer-case coordination | Product investigation plus marketplace resolution workflow |
| Seller Fulfillment Incident | Roastery + ROSTA authorized resolution | Incident evidence/input plus scoped policy resolution |
| Fulfillment Dispute | ROSTA | ROSTA custody/operational investigation |
| Carrier-caused Dispute | Carrier + ROSTA customer-case ownership | Carrier claim operations plus ROSTA communication/coordination/policy resolution |
| Payment Dispute | ROSTA | Payment/finance investigation and resolution |

## Universal rule

Every future business capability must define:

1. Owner / primary accountability;
2. Responsibility Boundary;
3. Escalation Path;
4. minimum necessary data access;
5. source of financial/operational truth;
6. policy/contract/legal dependency where relevant.

If any of these are undefined, the capability is not ready for implementation.
