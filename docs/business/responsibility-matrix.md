# Responsibility Matrix

Status: PS0.1 business architecture reference

This matrix is the canonical quick-reference for ownership, boundaries and escalation between ROSTA, Roastery, Carrier and Partners.

## Product Quality

**Owner: Roastery**

Responsible for:

- coffee quality;
- Origin/provenance information supplied to ROSTA;
- Roast Profile;
- product information and product-specific claims.

Boundary: ROSTA may validate/moderate marketplace content, but product-origin truth remains with the Roastery.

Escalation Path: Customer/Support -> Roastery for product investigation -> ROSTA policy resolution if the case remains unresolved.

## Customer Experience

**Owner: ROSTA**

Responsible for:

- Account;
- Support;
- Loyalty;
- Communication;
- cross-roastery marketplace experience.

Boundary: operational partners receive only purpose-limited customer data.

Escalation Path: Customer -> ROSTA Support -> relevant domain owner -> Admin/policy owner where necessary.

## Payment

**Owner: ROSTA**

Responsible for:

- Payment Flow;
- Transaction Status;
- Refund Process;
- payment/reconciliation customer communication.

Boundary: payment truth and refund truth come from the financial/payment domain, not fulfillment status.

Escalation Path: Support -> Finance Operator -> payment/refund policy owner/Admin.

## Direct Fulfillment

**Owner: Roastery**

Responsible for:

- Packing;
- Dispatch;
- Tracking handoff evidence;
- seller-controlled preparation and fulfillment SLA.

Boundary: Roastery operational responsibility continues until the applicable evidenced Carrier handoff, subject to contract/policy.

Escalation Path: Roastery -> ROSTA Support/Ops -> Carrier claim workflow when the incident begins after Carrier custody.

## ROSTA Fulfillment

**Owner: ROSTA**

Responsible for contracted hub stages after accepted custody:

- Receiving;
- Grinding when ROSTA Hub is the Order Item Service provider;
- QC;
- Packaging;
- Dispatch;
- approved Marketing/Partner Insert handling.

Boundary: responsibility transfers through Chain of Custody evidence.

Escalation Path: Fulfillment Operator -> Fulfillment lead -> ROSTA Support/Admin; Finance only for approved financial consequences.

## Shipping Carrier

**Owner: Carrier for carrier-controlled transport operations**

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
- refund/replacement decision according to ROSTA policy.

Boundary: this contract does not assert absolute legal liability independent of the carrier agreement or applicable law.

Escalation Path: Customer -> ROSTA Support -> Carrier claim process -> ROSTA policy resolution.

## Partner Experience

**Owner: ROSTA Partner Experience Engine**

Responsible for:

- Gift rules;
- Sample rules;
- Coupon integration rules;
- Campaign rules;
- Insert eligibility/orchestration.

Boundary: external Partners provide only approved experience inputs/materials. Winimi is an initial/example adapter, not a Core dependency.

Escalation Path: Partner/Fulfillment -> ROSTA Partner Ops -> Support/Admin depending on customer impact.

## Dispute Ownership

- **Product Issue -> Roastery** operational ownership; ROSTA owns customer-facing case coordination.
- **Fulfillment Issue in ROSTA custody -> ROSTA** operational ownership.
- **Carrier-caused Damage/Loss/Delay -> Carrier** primary operational responsibility subject to agreement/evidence/claim rules; ROSTA owns customer support and resolution coordination.
- **Payment Issue -> ROSTA** payment/finance ownership.

## Summary

| Domain | Owner | Primary Responsibility |
|---|---|---|
| Product Quality | Roastery | Product quality and product-origin truth |
| Customer Experience | ROSTA | Account, support, loyalty and communication |
| Payment | ROSTA | Payment, transaction, refund and reconciliation workflow |
| Direct Fulfillment | Roastery | Packing, dispatch and carrier handoff |
| ROSTA Fulfillment | ROSTA | Receiving, service execution, QC, packaging and dispatch in ROSTA custody |
| Shipping | Carrier | Carrier-controlled transportation and delivery process, subject to agreement/claim rules |
| Partner Experience | ROSTA Partner Experience Engine | Generic partner campaign/experience orchestration |
| Product Dispute | Roastery | Product investigation and product-domain resolution input |
| Fulfillment Dispute | ROSTA | ROSTA custody/operational investigation |
| Carrier-caused Dispute | Carrier + ROSTA customer-case ownership | Carrier claim operations plus ROSTA communication/coordination/policy resolution |
| Payment Dispute | ROSTA | Payment/finance investigation and resolution |

## Universal rule

Every future business capability must define:

1. Owner;
2. Responsibility Boundary;
3. Escalation Path;
4. minimum necessary data access;
5. source of financial/operational truth.

If any of these are undefined, the capability is not ready for implementation.
