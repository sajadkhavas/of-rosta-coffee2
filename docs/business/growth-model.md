# ROSTA Growth Partner and Loyalty Contract

Status: PS0.1 business architecture reference

## 1. Growth Partner model

A ROSTA Growth Partner is an approved acquisition/referral participant. Attribution and commission are distinct concepts.

Canonical flow:

```text
Referral Code / Approved Attribution Signal
  -> Customer Attribution
  -> Qualifying Purchase
  -> Eligibility Evaluation
  -> Financial Ledger Entry
  -> Approval / Hold / Reversal according to policy
  -> Partner Settlement
```

No commission is payable solely because a referral code was observed.

## 2. Referral code

Each eligible Partner may receive a unique referral/campaign identifier.

The identifier must support:

- attribution to the correct Partner;
- campaign/version context where needed;
- duplicate/idempotent handling;
- fraud/abuse controls;
- policy-driven expiry.

Referral identifiers must not encode sensitive customer data.

## 3. Customer attribution

Attribution rules must be published/versioned before being used for financial outcomes.

The business contract must be able to distinguish:

- first-purchase attribution;
- repeat-purchase attribution;
- attribution expiry/window;
- overridden/ineligible attribution;
- self-referral or abuse cases.

Attribution data is purpose-limited and does not grant the Partner access to the customer's private account/order history.

## 4. Commission model

### First purchase

A qualifying first purchase may create Partner commission eligibility according to the active Growth policy.

### Repeat purchases

Repeat-purchase commission may exist based on Partner tier and active policy. It must never be assumed permanently from the first referral.

### Partner levels

The platform may support policy-defined levels such as:

- Bronze;
- Silver;
- Gold;
- Platinum.

Level names and thresholds are configuration/policy, not immutable Core rules.

## 5. Financial Ledger contract

Growth Partner commission must be ledger-backed.

Conceptual entry:

```text
type: partner_commission
owner: partner_id
source: qualifying_order_or_event
policy_version: growth_policy_version
amount: calculated by approved finance/growth policy
status: pending | approved | reversed | settled
```

Business invariants:

- no off-ledger payable commission;
- one qualifying event must not create duplicate commission entries;
- refunds/cancellations/disputes may reverse or hold commission according to versioned policy;
- settlement must reference approved ledger truth;
- Partner UI shows only its own commission state.

PS0.1 does not define tax/accounting implementation details.

## 6. Loyalty model

ROSTA may maintain a customer loyalty system containing:

- Points;
- Levels;
- Rewards;
- Coupons;
- Referral Rewards.

### Points

Points may be granted for policy-approved events such as purchases, referrals or eligible engagement. Reward issuance must be idempotent and policy-versioned in future implementation.

### Levels

Customer levels are experience policy, not product ownership. Example labels may include Explorer/Lover/Expert/Ambassador, but final labels/thresholds remain configurable.

### Rewards

Potential rewards include:

- coupons;
- free-shipping benefits;
- samples;
- gifts;
- partner experiences.

A reward must not silently alter seller revenue or commission calculations outside the financial contract.

## 7. Abuse and privacy boundary

Growth systems must not expose unrestricted customer identities to Partners. Anti-abuse controls may evaluate attribution behavior without granting Partners access to raw unrelated customer data.

## 8. Ownership, boundary, escalation

| Capability | Owner | Boundary | Escalation Path |
|---|---|---|---|
| Growth policy | ROSTA | published/versioned campaign rules | Growth Ops -> Admin |
| Customer attribution | ROSTA | purpose-limited attribution data | Growth Ops -> Support/Admin |
| Commission ledger | ROSTA Finance | approved ledger records | Growth Ops -> Finance |
| Partner settlement | ROSTA Finance | approved payable ledger only | Finance -> Admin/policy owner |
| Partner conduct | Partner under ROSTA rules | own campaigns/referrals only | Partner Ops -> Admin |
| Loyalty program | ROSTA | customer platform experience | Support/Growth -> Admin |
