# ROSTA Fulfillment Model Contract

Status: PS0.1 business architecture reference

## 1. Purpose

This document defines the responsibility boundary between Roastery, ROSTA Fulfillment and Carrier operations. It does not define provider-specific transport integrations.

## 2. Fulfillment modes

### 2.1 Direct Fulfillment

**Owner: Roastery**

Roastery responsibilities:

- prepare the accepted Sub-order;
- perform any Roastery-provided Order Item Services;
- pack the goods;
- dispatch to an authorized Carrier;
- provide shipment/tracking evidence required by ROSTA;
- manage seller-side preparation exceptions within SLA.

ROSTA responsibilities:

- customer-facing order experience;
- SLA monitoring;
- customer support;
- exception coordination;
- policy-based refund/replacement decisions where applicable.

Carrier responsibility begins at the evidenced handoff according to the carrier agreement.

### 2.2 ROSTA Fulfillment

**Owner: ROSTA for contracted hub operations after accepted custody handoff**

Typical stages:

```text
Roastery preparation
  -> Handoff to ROSTA
  -> Receiving
  -> Optional Grinding
  -> QC
  -> Packaging
  -> Optional Partner Insert
  -> Dispatch
  -> Carrier
  -> Customer
```

ROSTA responsibility for physical hub operations begins only after a valid custody receipt/handoff. Before that point, Roastery remains responsible for its preparation and transfer obligations.

## 3. ROSTA Hub capability model

### Receiving

- verify shipment/reference identity;
- record received quantity/condition required by policy;
- create Chain of Custody receipt;
- isolate mismatches or damage for incident handling.

### Grinding

Grinding is an **Order Item Service**, not a product variant.

Provider may be:

- Roastery; or
- ROSTA Hub.

When ROSTA Hub is provider, fulfillment records must connect the service execution to the correct Order Item and Sub-order.

### QC

QC may verify operational criteria such as:

- correct item/reference;
- package integrity;
- service execution evidence;
- weight/packaging checks defined by policy.

QC does not transfer product-origin truth from the Roastery to ROSTA.

### Packaging

ROSTA packaging may include:

- final shipping package;
- required marketplace material;
- approved Partner Experience inserts.

### Marketing Insert

Any insert/gift/sample is governed by the Partner Experience Engine and must not be added ad hoc outside an approved experience/campaign rule.

### Dispatch

Dispatch records the handoff from ROSTA to the Carrier and ends the ROSTA hub custody stage for the handed-off parcel, subject to evidence and any residual support obligations.

## 4. Chain of Custody

Chain of Custody is mandatory whenever physical responsibility changes.

Canonical custody events may include:

```text
Roastery prepared
Roastery -> ROSTA handoff
ROSTA received
ROSTA service/QC/packaging custody
ROSTA -> Carrier handoff
Carrier in transport
Carrier delivered / exception
```

Each custody event must be attributable to available evidence such as:

- actor or responsible organization;
- timestamp;
- location/facility where relevant;
- shipment/sub-order/item reference;
- previous custodian;
- next custodian;
- condition/exception code where relevant;
- evidence reference according to policy.

A status label alone is not sufficient proof of a responsibility handoff.

## 5. Multi-roastery fulfillment

Each Roastery Sub-order may have its own fulfillment mode. A single Master Order can therefore be partially Direct and partially ROSTA Fulfillment.

Example:

```text
Master Order
  |-- Sub-order A -> Direct Fulfillment
  `-- Sub-order B -> ROSTA Fulfillment
```

Shipment, SLA, custody and incidents must remain attributable to the correct Sub-order. A failure in Sub-order B must not overwrite the state of Sub-order A.

## 6. Carrier boundary

Carrier-caused loss, damage or delay:

**Primary operational responsibility: Carrier, subject to the carrier agreement, evidence and applicable claim rules.**

ROSTA remains owner of the customer-facing support case and is responsible for:

- customer communication;
- claim coordination;
- resolution workflow;
- refund/replacement decision according to ROSTA policy.

This business contract does not assert absolute legal liability independent of the governing carrier agreement or applicable law.

## 7. Incident boundaries

### Product-quality issue

Owner: Roastery.  
ROSTA coordinates the customer case and marketplace resolution.

### ROSTA hub execution issue

Owner: ROSTA Fulfillment.  
Examples include wrong hub grinding execution, packaging error or hub processing delay.

### Carrier-caused issue

Primary operational responsibility: Carrier under contract/evidence/claim rules.  
Customer-facing case owner: ROSTA.

## 8. SLA integration

Roastery obligations are governed by `roastery-sla-model.md`.

ROSTA Fulfillment and Carrier stages may have separate internal/provider SLA contracts. These must remain distinct so a carrier delay is not incorrectly recorded as a Roastery preparation breach.

## 9. Ownership, boundary, escalation

| Capability | Owner | Boundary | Escalation Path |
|---|---|---|---|
| Product preparation truth | Roastery | before custody handoff | Roastery -> ROSTA Support/Fulfillment |
| Direct packing/dispatch | Roastery | through carrier handoff | Roastery -> Support -> Carrier claim if applicable |
| ROSTA receiving/hub ops | ROSTA | accepted ROSTA custody | Fulfillment Operator -> Fulfillment lead -> Support/Admin |
| Carrier transport | Carrier | evidenced carrier custody | ROSTA Support -> Carrier claim workflow |
| Customer-facing resolution | ROSTA | entire marketplace case | Support -> domain owner -> Admin/Finance as policy requires |
