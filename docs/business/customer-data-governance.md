# ROSTA Customer Data Governance Contract

Status: PS0.1 business architecture reference

## 1. Privacy-safe roles

ROSTA business documentation must not describe personal customer data as property "owned by ROSTA".

Canonical privacy roles for the business architecture are:

- **Customer = Data Subject**.
- **ROSTA = Primary Controller/Custodian for platform operations**.
- **Roastery = limited authorized recipient/processor only where operationally necessary**, subject to applicable agreement/policy and the actual legal role determined for the processing context.
- External partners/providers receive only the minimum data required for an approved purpose and contract.

This document is an architecture contract, not a substitute for jurisdiction-specific legal/privacy advice or published privacy policy.

## 2. Core principles

### Purpose limitation

Customer data may be accessed/used only for the authorized platform, order, support, fulfillment, finance, loyalty, growth or partner purpose that justified the processing.

### Least privilege

Every role receives the smallest data scope needed for its task.

### Data minimization

Operational interfaces should prefer minimized fields, masked values, references and customer-safe snapshots over unrestricted profiles.

### No unrestricted customer export

Roasteries, Growth Partners, Carriers and experience Partners are not entitled to export the marketplace customer database.

### No unrelated direct marketing

A Roastery or Partner must not use customer information obtained for order fulfillment/support for unrelated direct marketing unless a separate lawful, explicit and policy-approved basis exists.

### Policy-driven retention and deletion

Retention, deletion, archival and legal-hold behavior must be defined by applicable policy and legal/contractual obligations. A technical record existing in the database does not itself justify indefinite retention.

### Privacy rights

Access, correction, deletion and other privacy/data-subject rights remain policy-driven and must be supported by future implementation where applicable.

## 3. ROSTA platform access

ROSTA processes customer data for platform operations such as:

- account management;
- authentication/session security;
- checkout and Master Order orchestration;
- support;
- loyalty;
- fraud/abuse/security controls;
- platform communications;
- recommendations and experience features according to policy.

Internal role access remains purpose-limited. Admin status is not blanket authorization to expose all personal data.

## 4. Roastery access

A Roastery may receive only what is necessary to perform its own authorized Sub-order responsibilities.

Potential operational fields may include, when required:

- customer/recipient name needed for fulfillment;
- delivery address/contact information needed for Direct Fulfillment;
- order item/service instructions;
- support context directly related to its own Sub-order.

A Roastery must not receive by default:

- full customer purchase history;
- other Roasteries' order data;
- loyalty/behavior profile;
- unrelated support history;
- unrestricted customer exports.

For ROSTA Fulfillment, Roastery access may be narrower because ROSTA performs more of the customer delivery operation.

## 5. Carrier access

Carrier data exposure is limited to transport/delivery execution and claim handling. Carrier does not receive loyalty, marketing profile or unrelated order history.

Carrier events returned to ROSTA should be stored and exposed according to evidence, security and retention policy.

## 6. Growth Partner and Partner Engine access

Growth Partners receive attribution/performance information necessary for their own program, preferably aggregated/minimized.

Experience Partners receive only the minimum context needed to execute an approved experience. A campaign does not automatically create access to customer identity.

## 7. Support and Finance access

Support receives case-minimum information required to resolve a customer issue.

Finance receives financial/transaction context required for payment, refund, reconciliation, ledger and settlement operations, without unrelated customer profile data.

## 8. Data sharing boundary table

| Recipient | Permitted purpose | Default data boundary | Prohibited default use |
|---|---|---|---|
| ROSTA operational role | assigned platform task | least privilege | unrestricted browsing/export |
| Roastery | own Sub-order operations | operational minimum | unrelated marketing/customer mining |
| Carrier | transport/delivery/claims | delivery minimum | loyalty/marketing use |
| Growth Partner | attribution/commission | minimized/aggregate | customer database access |
| Experience Partner | approved campaign experience | campaign minimum | unrelated reuse/export |

## 9. Incident and escalation

Suspected over-access, unauthorized export, unrelated marketing use or retention-policy breach must be treated as a privacy/security incident and escalated through ROSTA's designated privacy/security governance process.

## 10. Architecture rule

New features must identify:

- data subject;
- processing purpose;
- controller/custodian/authorized recipient boundary;
- minimum fields;
- retention rule;
- deletion/privacy-right impact;
- audit/security requirements.

If those cannot be identified, the feature is not ready for implementation.
