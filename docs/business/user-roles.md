# ROSTA User Roles Contract

Status: PS0.1 business architecture reference

## Role design principles

- Access is least-privilege and purpose-limited.
- Operational access does not imply ownership of data.
- Cross-roastery access is forbidden unless the role is explicitly platform-scoped and authorized.
- Sensitive mutations require authorization, auditability and an explicit operational reason where applicable.
- Financial secrets, OTP secrets, private payment evidence and unrelated customer data are never exposed merely because a user has an operational role.

## 1. Customer

### Responsibility

- maintain their account and delivery information;
- place and manage their own orders;
- choose optional services such as grinding when offered;
- use loyalty/rewards according to policy;
- raise support, return or dispute requests where permitted.

### Access

May view:

- public catalog and availability;
- their own Master Orders and Sub-order progress;
- their own loyalty/reward state;
- their own referral/partner-attribution disclosures where applicable;
- their own support and privacy controls.

### Limitations

Must not access:

- another customer's data;
- internal Roastery operational data;
- platform-wide financial/settlement data;
- private admin/support notes not intended for the customer.

### Data visibility boundary

Customer sees their own personal and transactional data plus customer-safe status/evidence. Internal operational metadata is minimized.

### Escalation path

Customer -> ROSTA Support -> responsible operational/finance owner -> Admin exception process where policy requires.

## 2. Roastery

### Responsibility

- product truth and product quality;
- inventory truth for its products;
- accepting/rejecting its own Sub-orders within SLA;
- fulfilling Direct Fulfillment responsibilities when selected;
- handing goods into the ROSTA network correctly when ROSTA Fulfillment is selected;
- maintaining seller operational information required by marketplace policy.

### Access

May view or manage, subject to seller permissions:

- its own products and inventory;
- its own Roastery Sub-orders;
- fulfillment information for its own Sub-orders;
- only customer delivery/contact fields required to execute its authorized operational step;
- its own settlement/business records allowed by finance policy.

### Limitations

Must not access:

- unrestricted customer database exports;
- customer purchase history unrelated to its own Sub-orders;
- behavioral/marketing profiles not required for fulfillment;
- data belonging to another Roastery;
- platform secrets or global financial records.

Roastery must not use fulfillment/customer data for unrelated direct marketing unless a separate lawful and policy-approved basis exists.

### Escalation path

Roastery operator -> Roastery manager/owner -> ROSTA Support/Fulfillment -> Admin/Finance depending on incident type.

## 3. ROSTA Growth Partner

### Responsibility

- acquire or refer customers under approved campaigns;
- use assigned referral/attribution mechanisms accurately;
- comply with campaign, privacy and brand rules.

### Access

May view:

- own referral code/campaign state;
- aggregated attributed performance allowed by policy;
- ledger-backed commission state and settlement status;
- own partner tier/eligibility.

### Limitations

Must not access:

- full customer profiles;
- unrestricted customer exports;
- private order contents unless explicitly required by a specific partner experience and permitted by policy;
- other partners' commission/attribution data.

### Escalation path

Growth Partner -> ROSTA Partner/Growth Operations -> Finance for ledger/settlement disputes -> Admin for policy exceptions.

## 4. Admin

### Responsibility

- platform governance and exceptional administrative actions;
- policy enforcement;
- oversight of users, sellers, operations and risk boundaries.

### Access

May receive platform-scoped operational access only as required by permission. Admin is not a bypass around data minimization.

### Limitations

Even Admin interfaces must not expose secrets, OTP values, full payment evidence or unnecessary private customer data. Sensitive mutations require audit, reason and confirmation according to policy.

### Escalation path

Admin decisions involving financial, privacy, legal or high-impact operational exceptions must be routed to the corresponding policy owner rather than resolved by unrestricted discretionary access.

## 5. Support Agent

### Responsibility

- customer-facing case management;
- order-status explanation;
- coordination of product, fulfillment, carrier and payment incidents;
- escalation and resolution tracking.

### Access

May view:

- identity/contact hints necessary for the active case;
- customer-safe order and shipment state;
- case-related incident history;
- approved resolution options.

### Limitations

Must not view:

- secrets or OTP data;
- unrelated customer history;
- full payment evidence unless specifically permitted for the case;
- unrestricted seller financial records.

### Escalation path

Support -> Product issue: Roastery; Fulfillment issue: ROSTA Fulfillment; Carrier-caused issue: Carrier claim workflow; Payment issue: Finance Operator; policy exception: Admin.

## 6. Finance Operator

### Responsibility

- payment/reconciliation operations;
- refunds according to policy;
- seller/partner settlement workflows;
- ledger reconciliation and finance exceptions.

### Access

May view financial records necessary for the assigned operation, including ledger entries, transaction status and settlement state.

### Limitations

Must not modify product truth or fulfillment state merely to make finance records reconcile. Must not receive unnecessary private customer profile data.

### Escalation path

Finance Operator -> Finance policy owner/Admin -> external payment/settlement provider process where applicable.

## 7. Fulfillment Operator

### Responsibility

Within ROSTA Fulfillment:

- receiving;
- Chain of Custody recording;
- optional grinding execution;
- QC;
- packaging;
- partner insert handling;
- dispatch and operational incident recording.

### Access

May view only order/sub-order/item/service data required for assigned physical operations.

### Limitations

Must not access unrelated customer history, payment secrets, loyalty profiles or other operational scopes not needed for fulfillment.

### Escalation path

Fulfillment Operator -> Fulfillment lead -> Support/Admin for customer-impacting incident -> Finance only when an approved financial consequence is required.

## Role boundary summary

| Role | Scope | Customer data boundary | Financial boundary |
|---|---|---|---|
| Customer | self | own data | own customer-safe payment/refund state |
| Roastery | own seller scope | minimum required for own Sub-orders | own permitted settlement view |
| Growth Partner | own attribution scope | aggregate/minimized only | own ledger-backed commission |
| Admin | platform governance | purpose-limited, no secret bypass | permission-limited |
| Support Agent | active support cases | case-minimum | customer-safe status only unless specifically authorized |
| Finance Operator | finance operations | minimum required | authorized finance records |
| Fulfillment Operator | assigned physical operations | delivery/operational minimum | no general financial access |
