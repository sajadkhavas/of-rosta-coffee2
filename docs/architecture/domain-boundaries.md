# Domain Boundaries

Status: ARCH-0.2

## 1. Boundary purpose

Domains are logical ownership boundaries inside the Laravel modular monolith. They do not imply separate databases or deployables.

Cross-domain work must use explicit services/contracts/events rather than directly mutating another domain's tables to obtain a convenient result.

## 2. Identity & Access

Owns:

- customer identity/profile/address/session;
- OTP challenges and authentication flows;
- seller organization membership/roles/invites;
- admin/support/finance/fulfillment authorization context;
- session security/audit boundaries.

Does not own order/payment truth.

## 3. Catalog & Inventory

Owns:

- roastery/product/origin/product attributes;
- product variants/weights where they represent sellable whole-bean packaging identity;
- roast batches and stock/stock ledger;
- publication state;
- seller grinding/packaging capability definitions;
- catalog media references.

Invariant: whole bean is authoritative inventory identity. Grind choice does not create parallel coffee inventory SKUs.

## 4. Media

Owns:

- upload intent;
- ownership/key namespace;
- byte/MIME/checksum/size/dimension/frame validation;
- decode/sanitize;
- derived responsive WebP/AVIF variants;
- media lifecycle/cleanup metadata.

Object storage owns bytes; MySQL owns lifecycle/ownership metadata.

## 5. Quote & Checkout

Owns:

- requested basket interpretation;
- authoritative quote snapshots;
- seller grouping;
- item-service choices;
- packaging/shipping/discount calculation inputs and results;
- inventory reservation;
- order-creation idempotency.

It calls Finance for monetary policy and Fulfillment/Shipping for applicable plan/quote inputs rather than cloning those rules.

## 6. Orders

Owns:

- Master Order aggregate;
- Roastery Sub-order boundaries;
- immutable commercial snapshots needed for history;
- order items and Order Item Services;
- customer-safe aggregate lifecycle/events.

After verified payment, valid seller Sub-orders become fulfillment commitments.

## 7. Payments & Refunds

Owns:

- payment attempts;
- provider request/authority/reference evidence;
- callback/return handling;
- verification/idempotency;
- refund attempts/provider evidence where supported;
- disabled/manual-safe provider behavior when external capabilities do not exist.

Payment/refund truth is independent of shipment status.

## 8. Financial Truth & Settlement

Owns:

- monetary policy/rate resolution;
- allocations and recognized platform/seller/pass-through classification;
- commissions;
- tax lines as policy input/output records;
- reconciliation cases;
- settlement eligibility/release/batches/payout evidence;
- reversal/adjustment audit semantics.

Invariant: GMV != ROSTA revenue.

Growth Partner money, promotion funding, store credit and future revenue-sharing capabilities must integrate into authoritative finance primitives rather than create mutable side balances.

## 9. Fulfillment & Shipping

Owns:

- seller fulfillment commitment;
- preparation/ready/handoff milestones;
- seller incidents/SLA;
- fulfillment plan snapshots;
- shipments and shipment legs;
- tracking/delivery evidence;
- custody transitions.

Direct Fulfillment and ROSTA Fulfillment are both supported. Carrier-specific integration is an adapter concern.

## 10. ROSTA Hub Operations

Owns operational truth while ROSTA has evidenced custody:

- inbound receipt;
- work item/assignment;
- Hub grinding execution;
- QC/rework;
- Hub packaging;
- outbound handoff;
- custody evidence.

It does not change seller product-origin truth or payment status.

## 11. Notifications

Owns:

- notification templates/policy input;
- persistent outbox/delivery lifecycle;
- channel/provider normalization;
- retry/dispatch evidence.

Other domains request a notification by durable intent/event; they should not each implement Kavenegar/provider calls.

## 12. Reviews, Support & Trust

Owns:

- verified-purchase review eligibility;
- review moderation/report/reply state;
- support/inquiry/case records;
- customer-facing case coordination.

Operational issue ownership remains with the relevant product/fulfillment/carrier/payment domain.

## 13. Content & SEO

Owns CMS/blog/content publication, redirects and content metadata. Public rendering consumes this truth but should not bypass publication state.

## 14. Growth, Promotions, Loyalty and future CRM

These are product capability domains with strict finance integration.

- Growth owns attribution/lead/eligibility policy, not payout money truth.
- Promotion owns discount eligibility/funding policy, not arbitrary order total mutation.
- Loyalty owns points/reward ledger and customer tier policy.
- Seller/B2B CRM owns lead/pipeline state, not order/payment state.

The official Growth Network contract remains Starter/Growth/Pro and the locked attribution/commission rules in business/product policy.

## 15. Partner Experience

Technical architecture may define a generic provider/configuration port for partner integrations, but no Winimi-specific shape becomes Core. Partner campaign funding/data/fulfillment effects must route through their owner domains.

## 16. Cross-domain rule

For every cross-domain mutation, identify:

1. initiating domain;
2. authoritative owner of the target truth;
3. synchronous invariant versus asynchronous side effect;
4. idempotency key/event identity;
5. audit/correlation record;
6. failure and compensation/reconciliation behavior.

If these are unclear, the integration is not ready to implement.
