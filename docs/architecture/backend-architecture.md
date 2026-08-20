# Backend Architecture

Status: ARCH-0.2

## 1. Architectural style

ROSTA backend is a **Laravel modular monolith**.

**BUILT:** the baseline is one Laravel application with domain-oriented service folders, contracts/provider managers, controllers, Eloquent models, queues and scheduler. This is the canonical starting architecture.

**TARGET:** strengthen module boundaries inside the monolith before considering service extraction. A new capability does not justify a microservice by itself.

## 2. Responsibility layers

```text
HTTP / Console / Queue entry point
       |
       v
Application / domain service
       |
       +--> policy/resolver/domain rules
       +--> models/repositories via Eloquent
       +--> provider contract/manager at external edge
       |
       v
MySQL transaction + durable events/evidence
```

Controllers should remain transport adapters: validation, authorization/context extraction, invoking services and shaping responses. Cross-domain orchestration belongs in explicit application services rather than model observers or controller glue.

## 3. Existing service boundaries

The audited baseline includes service areas for catalog/media, checkout, finance, fulfillment, Hub operations, identity, Kavenegar/SMS, notifications, payments, settlement and other product concerns.

Important current anchors include:

- `Checkout/QuoteService` and `Checkout/OrderService`;
- quote/order idempotency and reservation state;
- `Finance/FinancialTruthEngine`, policy resolver/service and reconciliation;
- payment provider manager/service and provider implementations;
- `FulfillmentCommitmentService`, incident/SLA/fulfillment services;
- `Hub/RostaHubOperationsService`;
- settlement release/batch services;
- notification outbox service;
- secure media processing/upload services.

These names evidence current design; future refactors may rename classes while preserving the boundary contract.

## 4. Transaction rule

A database transaction should cover the smallest synchronous set of writes required to establish an invariant.

Examples:

- reserve/consume/release inventory with order/quote state;
- create idempotent order/payment records;
- transition payment/financial records after verified provider evidence;
- record chain-of-custody transition with the affected fulfillment state;
- create financial allocations/snapshots that must agree at commit time.

External HTTP calls should not be casually held inside long database locks. Where an external call is needed, use explicit attempt records/idempotency and reconcile the response into a transaction.

## 5. Model rules

Eloquent models represent persistent entities, not a license to place all business behavior in Active Record hooks.

- monetary arithmetic uses integer minor/base units and dedicated money helpers/policies;
- status transitions use explicit services/policies and validated enums where available;
- tenant/seller scope is explicit;
- snapshots preserve historical commercial facts instead of re-reading mutable product/policy state;
- deletion of financial/audit evidence is forbidden unless an explicit retention/legal policy allows it.

## 6. Checkout/order truth

Master Order is the customer aggregate and seller-specific Sub-orders remain independently attributable.

The backend owns:

- quote composition;
- price/service/shipping/discount allocation;
- inventory reservation/commitment;
- order creation idempotency;
- seller fulfillment commitment after valid payment;
- partial failure/resolution truth.

No routine seller accept/reject may be introduced after valid payment.

## 7. Financial truth

Payment, refund, reconciliation, allocation and settlement are separate concepts.

Backend invariants:

- provider callback/return is not success until verified by the payment domain;
- fulfillment state cannot imply payment/refund state;
- seller-owned/pass-through amounts are not recognized as platform revenue merely because ROSTA collected them;
- GMV, commission/service revenue, taxes, carrier amounts, discounts, refunds and seller payable remain separately attributable;
- historical policy/rate context is snapshotted/versioned where later mutation could change interpretation.

The legal/tax classification of marketplace collections remains an external policy input and is not hard-coded by this architecture phase.

## 8. Fulfillment truth

Whole-bean inventory identity remains unchanged by grinding. Grinding is a service attached to an Order Item with provider, instructions, fee/status and execution evidence.

Direct and ROSTA Fulfillment are separate supported plans. Centralized launch routing may prefer Hub processing but remains policy/configuration.

Physical responsibility changes require a durable custody event/evidence reference.

## 9. Provider isolation

Technical provider contracts/managers are allowed and encouraged in this phase. Core services depend on normalized interfaces/data, not provider SDK response shapes.

Provider adapters may be replaced without changing order/payment/notification/partner business invariants.

## 10. Extraction criteria

A module may become a separate deployable service only after an ADR demonstrates a concrete need such as:

- independent scaling impossible/economically poor in the monolith;
- isolation/security boundary;
- conflicting runtime requirements;
- ownership/deployment cadence that materially benefits from separation;
- proven reliability bottleneck.

Before extraction, define data ownership, consistency semantics, idempotency, observability and failure behavior. Distributed transactions are not accepted as an implicit consequence of decomposition.
