# ROSTA Roastery SLA Model

Status: PS0.1 business architecture reference

## 1. Purpose

Roastery SLA defines explicit operational expectations for seller-controlled stages after a valid paid Sub-order becomes a fulfillment commitment. It must not attribute ROSTA Hub or Carrier delays to the Roastery after responsibility has transferred through an evidenced handoff.

Exact SLA durations are policy/configuration and must not be hard-coded into this business contract unless separately approved.

There is no normal seller **Acceptance SLA** after payment. Marketplace availability is checked before/at order creation, and a valid paid Sub-order becomes a seller fulfillment commitment.

## 2. SLA profile

Each participating Roastery may have an effective SLA profile/version defining applicable targets.

Core dimensions:

### Commitment-to-Preparation SLA

Maximum permitted seller-controlled time from the committed/available Sub-order timestamp to the required preparation-start milestone, where that milestone is tracked by policy.

### Preparation SLA

Maximum permitted seller-controlled time from commitment/preparation start to Ready for Fulfillment/Handoff.

### Handoff/Dispatch SLA

For Direct Fulfillment: time from Ready to evidenced dispatch/handoff to Carrier.

For ROSTA Fulfillment: time from Ready to evidenced handoff into the ROSTA network.

## 3. Responsibility stop points

### Direct Fulfillment

Roastery SLA covers seller preparation and seller-controlled dispatch/handoff. Once Carrier custody is evidenced, transport delay is not a Roastery preparation SLA breach.

### ROSTA Fulfillment

Roastery SLA covers seller preparation and handoff into ROSTA custody. Once ROSTA receiving/custody is evidenced, hub processing time belongs to ROSTA operational SLA, not Roastery SLA.

## 4. Required timestamps/evidence

SLA evaluation must derive from actual events such as:

- Sub-order paid/committed time;
- preparation-start time where applicable;
- ready time;
- incident-reported time and reason where applicable;
- handoff/dispatch time;
- Chain of Custody receipt;
- approved closure/exception periods that were valid under policy.

The system must not invent an SLA success event merely because time has passed.

A seller incident does not retroactively erase a breach or rewrite historical timestamps. Policy may determine whether a documented exception pauses, excludes or otherwise affects an SLA calculation.

## 5. Seller incidents and exceptions

A Roastery cannot use an ordinary reject/cancel action after payment to escape a committed Sub-order.

If fulfillment cannot proceed as committed, the Roastery reports an operational incident. The incident is escalated through ROSTA operations/support and may result in an authorized exception action according to policy.

Policy-defined exceptions may include:

- approved Roastery closure that was valid before commitment;
- platform incident;
- customer-requested hold where supported;
- exceptional operational incident;
- ROSTA-authorized SLA extension.

An exception must be recorded; it must not silently rewrite historical timestamps, payment truth, inventory truth or settlement truth.

## 6. Breach model

A breach may trigger policy-defined outcomes such as:

- warning;
- seller operational score impact;
- support/operations escalation;
- temporary availability restrictions;
- review by ROSTA operations;
- authorized remediation of affected Sub-orders.

Financial penalties or commercial consequences are not implied by PS0.1 and require an explicit commercial/financial policy.

## 7. Multi-vendor behavior

SLA is measured per Roastery Sub-order. One seller's breach or incident must not mark sibling Sub-orders as breached or cancelled.

The Master Order may surface partial delay/resolution truth to the customer.

## 8. Customer communication

ROSTA owns customer-facing communication about marketplace order delay. Roastery provides accurate operational events/reasons for its own Sub-order; ROSTA translates them into customer-safe support/status messaging and coordinates approved remedies.

## 9. Ownership, boundary, escalation

| SLA stage | Owner | Boundary | Escalation Path |
|---|---|---|---|
| Commitment to preparation | Roastery | paid/committed -> preparation milestone | Roastery manager -> ROSTA Support/Ops |
| Preparation | Roastery | committed/preparing -> ready | Roastery manager -> ROSTA Ops |
| Direct dispatch | Roastery | ready -> Carrier custody | Roastery -> Support/Carrier coordination |
| ROSTA handoff | Roastery until receipt; ROSTA after receipt | Chain of Custody receipt | Roastery/Fulfillment Ops |
| Hub processing | ROSTA | ROSTA custody | Fulfillment Ops -> Support/Admin |
| Carrier transport | Carrier under agreement | Carrier custody | ROSTA Support -> Carrier claim workflow |
| Seller incident resolution | ROSTA Ops/Admin under policy, with Roastery evidence/input | affected committed Sub-order only | Roastery -> ROSTA Ops/Support -> authorized exception owner |
