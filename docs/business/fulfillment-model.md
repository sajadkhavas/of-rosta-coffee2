# ROSTA Fulfillment Model Contract

Status: PS0.1 business architecture reference

## 1. Purpose

This document defines the responsibility boundary between Roastery, ROSTA Fulfillment and Carrier operations. It does not define provider-specific transport integrations.

## 2. Fulfillment modes

### 2.1 Direct Fulfillment

**Operational responsibility: Roastery for seller-controlled stages**

Roastery responsibilities:

- prepare the committed Sub-order;
- perform any Roastery-provided Order Item Services;
- pack the goods;
- dispatch to an authorized Carrier;
- provide shipment/tracking evidence required by ROSTA;
- report seller-side preparation incidents truthfully within SLA;
- complete seller-controlled milestones until evidenced Carrier handoff.

There is no normal post-payment seller reject/cancel step. A seller problem becomes an incident and follows authorized exception policy.

ROSTA responsibilities:

- customer-facing order experience;
- SLA monitoring;
- customer support;
- exception coordination;
- policy-based remedy/refund/replacement workflow where applicable.

Carrier responsibility begins at the evidenced handoff according to the carrier agreement.

### 2.2 ROSTA Fulfillment

**Operational responsibility: ROSTA for contracted hub operations after accepted custody handoff**

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

### 2.3 Supported capability versus launch operating policy

The platform supports both Direct Fulfillment and ROSTA Fulfillment. Operational launch policy may choose to route all or most eligible orders through a centralized ROSTA Hub to consolidate receiving, grinding when required, ROSTA packaging, marketing/partner inserts and outbound dispatch.

Such a policy is versioned/configurable operating policy. It does not remove Direct Fulfillment as a supported platform capability and must not create a false custody event before ROSTA physically receives the goods.

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

Any insert/gift/sample is governed by approved partner/campaign policy and must not be added ad hoc outside an authorized experience.

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

Each Roastery Sub-order may have its own fulfillment plan. A single Master Order can therefore be partially Direct and partially ROSTA Fulfillment when policy allows.

Example:

```text
Master Order
  |-- Sub-order A -> Direct Fulfillment
  `-- Sub-order B -> ROSTA Fulfillment
```

Shipment, SLA, custody and incidents must remain attributable to the correct Sub-order. A failure in Sub-order B must not overwrite the state of Sub-order A.

A centralized launch policy may instead route both eligible Sub-orders through ROSTA Fulfillment. The underlying per-Sub-order truth remains explicit.

## 6. Carrier boundary

Carrier-caused loss, damage or delay:

**Primary operational responsibility: Carrier, subject to the carrier agreement, evidence and applicable claim rules.**

ROSTA remains owner of the customer-facing support case and is responsible for:

- customer communication;
- claim coordination;
- resolution workflow;
- remedy/refund/replacement decision according to policy, contract and applicable law.

This business contract does not assert absolute legal liability independent of the governing carrier agreement or applicable law.

## 7. Incident boundaries

### Product-quality issue

Roastery provides product-domain investigation and evidence. ROSTA coordinates the customer case and marketplace resolution.

### Seller fulfillment incident

A seller problem after paid commitment is reported as an incident rather than handled through a normal reject/cancel action. Incident reporting does not itself cancel, refund, release inventory or alter settlement.

### ROSTA hub execution issue

Operational responsibility: ROSTA Fulfillment.
Examples include wrong hub grinding execution, packaging error or hub processing delay.

### Carrier-caused issue

Primary operational responsibility: Carrier under contract/evidence/claim rules.
Customer-facing case owner: ROSTA.

## 8. SLA integration

Roastery obligations are governed by `roastery-sla-model.md`.

ROSTA Fulfillment and Carrier stages may have separate internal/provider SLA contracts. These must remain distinct so a carrier or hub delay is not incorrectly recorded as a Roastery preparation breach.

## 9. Ownership, boundary, escalation

| Capability | Owner / Primary Accountability | Boundary | Escalation Path |
|---|---|---|---|
| Product preparation truth | Roastery | before custody handoff | Roastery -> ROSTA Support/Fulfillment |
| Direct packing/dispatch | Roastery | through carrier handoff | Roastery -> Support -> Carrier claim if applicable |
| Seller fulfillment incident | Roastery evidence/input + ROSTA authorized resolution | affected committed Sub-order | Roastery -> ROSTA Ops/Support -> authorized exception owner |
| ROSTA receiving/hub ops | ROSTA | accepted ROSTA custody | Fulfillment Operator -> Fulfillment lead -> Support/Admin |
| Carrier transport | Carrier | evidenced carrier custody | ROSTA Support -> Carrier claim workflow |
| Customer-facing resolution | ROSTA | entire marketplace case | Support -> domain owner -> Admin/Finance as policy requires |
