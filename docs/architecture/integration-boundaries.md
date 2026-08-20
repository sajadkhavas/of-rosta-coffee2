# Integration Boundaries

Status: ARCH-0.2

## 1. Principle

External providers are replaceable edge integrations. ROSTA Core depends on normalized technical contracts and internal evidence, not a provider's response schema or brand-specific business meaning.

Provider activation is configuration plus an external-readiness gate. A disabled provider must fail closed/explicitly rather than simulate production success.

## 2. Payment

**BUILT:** payment provider manager/service with Disabled, Testing and Zarinpal implementations, plus persistent `PaymentAttempt` state and verification/idempotency flow.

Technical port responsibilities:

- create/request payment;
- normalize authority/reference/redirect result;
- verify provider outcome;
- expose safe error classification;
- preserve raw evidence only where secure/necessary.

Rules:

- browser never holds merchant secrets;
- redirect/callback alone is not success;
- amount/order/authority must match internal attempt context;
- duplicate callbacks are idempotent;
- provider outage leaves an explicit pending/failed/reconciliation state, not guessed success.

Refund/payout capability is a separate provider contract. If official API capability is unavailable, use a production-safe manual/dual-control workflow rather than fabricated automation.

## 3. SMS / OTP / notifications

**BUILT:** `OtpSender` contract, SMS provider manager, Kavenegar service area, notification provider contracts/outbox.

Rules:

- Kavenegar is an adapter, not domain truth;
- notification templates/events are internal concepts;
- mobile-number normalization/validation is centralized;
- secrets stay in environment/config;
- provider delivery result is mapped into internal attempt/outbox state.

A future email/push provider integrates through the notification domain instead of bypassing it.

## 4. Object storage / Cloudflare R2

**BUILT:** S3-compatible filesystem config plus upload intents, media assets, secure server-side image processing and queued processing.

Port responsibilities:

- object key ownership/namespace;
- presign/upload/read/delete as authorized;
- no arbitrary client-chosen production keys;
- content checksum/size/type validation;
- lifecycle/cleanup handling.

R2 endpoint/bucket/access keys are deployment secrets/configuration. Public URL/CDN mapping must not be used as database identity.

## 5. Carrier/shipping

**TARGET:** provider-independent carrier boundary.

Core shipping concepts are shipment, shipment leg, quote/service level, pickup/handoff, tracking event, delivery/exception and claim evidence.

Allowed adapters may include Tipax, Chapar, Post, local ROSTA delivery or future providers. The exact provider mix remains a commercial/operational decision.

Required behavior:

- provider quote/create/tracking/pickup calls normalize to internal models;
- idempotency/retry/timeouts;
- webhook/polling reconciliation;
- multi-origin and multi-leg support;
- manual-safe fallback when provider API is unavailable;
- no hard-coded rates as provider truth in frontend code.

A centralized launch Hub can aggregate outbound pickup, while Direct Fulfillment remains an independently supported route.

## 6. Local ROSTA delivery

**PROPOSED launch operating adapter:** local delivery in supported zones such as Karaj may be executed by ROSTA operations. It must still produce shipment/custody/delivery evidence under the same shipping contract rather than becoming an undocumented side channel.

## 7. Partner Experience

**TARGET:** generic technical port/configuration around the business-level Partner Experience capability.

Winimi may be one adapter for a gift/sample/campaign relationship, but:

- no Winimi-specific fields in Master Order Core unless represented as generic experience metadata;
- no order dependency on partner availability unless campaign policy explicitly requires it;
- no partner receives customer data merely because a package contains its sample;
- partner funding/fees route through Finance/Promotion truth.

## 8. Growth integrations

Growth links/QR/lead sources are attribution inputs, not external authority for commission. ROSTA validates attribution and creates ledger-backed financial results only after locked qualification gates.

Third-party CRM/analytics may receive minimized events through explicit integrations in the future; raw customer/order databases are not exported by default.

## 9. Webhooks

Any external webhook endpoint must define:

- authentication/signature/source verification when provider supports it;
- replay/idempotency control;
- bounded body/parse validation;
- correlation to an existing internal record;
- timestamp/order constraints where appropriate;
- safe logging/redaction;
- retry/reconciliation strategy.

A webhook is evidence to process, not permission to mutate arbitrary internal state.

## 10. Timeouts and circuit/degraded behavior

All external HTTP calls require bounded connect/request timeouts. Retrying non-idempotent operations is prohibited without an idempotency strategy.

Provider failure should degrade the affected capability, not necessarily the whole site:

- payment unavailable -> checkout payment blocked with explicit state;
- SMS unavailable -> OTP delivery unavailable, existing authenticated browsing may continue;
- media provider unavailable -> uploads/process deferred, public existing media served if available;
- carrier API unavailable -> quote/create fallback/manual policy, not fake tracking;
- partner unavailable -> optional experience omitted/held according to campaign policy, Core order remains truthful.

## 11. Provider readiness registry

Production launch should maintain a provider checklist containing enabled driver, environment, credential presence, callback/webhook URLs, documented smoke test, failure mode, operator owner and rollback/disable procedure. External credentials are never committed to the repository.
