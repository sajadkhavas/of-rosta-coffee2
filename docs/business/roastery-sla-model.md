# ROSTA Roastery SLA Model

Status: PS0.1 business architecture reference

## 1. Purpose

Roastery SLA provides explicit operational expectations for seller-controlled stages. It must not attribute ROSTA Hub or Carrier delays to the Roastery after responsibility has transferred through an evidenced handoff.

Exact SLA durations are policy/configuration and must not be hard-coded into this business contract unless separately approved.

## 2. SLA profile

Each participating Roastery may have an effective SLA profile/version defining applicable targets.

Core dimensions:

### Acceptance SLA

Maximum permitted time to accept or reject an eligible paid Sub-order after it becomes available for seller action.

### Preparation SLA

Maximum permitted seller-controlled preparation time after acceptance.

### Handoff/Dispatch SLA

For Direct Fulfillment: time to dispatch/handoff to Carrier after preparation.
For ROSTA Fulfillment: time to hand goods to the ROSTA network after preparation.

## 3. Responsibility stop points

### Direct Fulfillment

Roastery SLA covers seller preparation and seller-controlled dispatch/handoff. Once Carrier custody is evidenced, transport delay is not a Roastery preparation SLA breach.

### ROSTA Fulfillment

Roastery SLA covers preparation and handoff into ROSTA custody. Once ROSTA receiving/custody is evidenced, hub processing time belongs to ROSTA operational SLA, not Roastery SLA.

## 4. Required timestamps/evidence

SLA evaluation must derive from actual events such as:

- Sub-order available/committed time;
- accepted/rejected time;
- preparation-start/ready time where applicable;
- handoff/dispatch time;
- Chain of Custody receipt;
- exception/closure periods approved by policy.

The system must not invent an SLA success event merely because time has passed.

## 5. Exceptions

SLA evaluation must support policy-defined exceptions such as:

- approved Roastery closure;
- platform incident;
- customer-requested hold;
- exceptional operational incident;
- ROSTA-authorized SLA extension.

An exception must be recorded; it must not silently rewrite historical timestamps.

## 6. Breach model

A breach may trigger policy-defined outcomes such as:

- warning;
- seller operational score impact;
- support escalation;
- temporary availability restrictions;
- review by ROSTA operations.

Financial penalties or commercial consequences are not implied by PS0.1 and require an explicit commercial/financial policy.

## 7. Multi-vendor behavior

SLA is measured per Roastery Sub-order. One seller's breach must not mark sibling Sub-orders as breached.

The Master Order may surface partial delay truth to the customer.

## 8. Customer communication

ROSTA owns customer-facing communication about marketplace order delay. Roastery provides accurate operational events/reasons for its own Sub-order; ROSTA translates them into customer-safe support/status messaging.

## 9. Ownership, boundary, escalation

| SLA stage | Owner | Boundary | Escalation Path |
|---|---|---|---|
| Acceptance | Roastery | seller action window | Roastery manager -> ROSTA Support/Ops |
| Preparation | Roastery | accepted -> ready | Roastery manager -> ROSTA Ops |
| Direct dispatch | Roastery | ready -> Carrier custody | Roastery -> Support/Carrier coordination |
| ROSTA handoff | Roastery until receipt; ROSTA after receipt | Chain of Custody receipt | Roastery/Fulfillment Ops |
| Hub processing | ROSTA | ROSTA custody | Fulfillment Ops -> Support/Admin |
| Carrier transport | Carrier under agreement | Carrier custody | ROSTA Support -> Carrier claim workflow |
