# Identity, Privacy and Sensitive Data

Status: ARCH-0.3 privacy-aware data contract

## 1. Privacy architecture boundary

This document is a technical/data contract, not jurisdiction-specific legal advice. Legal/controller/processor roles remain subject to the business privacy contract, applicable law and actual processing context.

Core rule: **a stored field is not an entitlement to expose it**.

## 2. Data classes

ROSTA should classify durable data at least as:

- public marketplace data — published product/Roastery/content facts;
- account data — identity, contact and profile data;
- delivery PII — recipient, mobile, address, postal/geolocation data;
- authentication/security data — OTP/session/token/audit evidence;
- financial/provider data — payment/refund/payout references and provider payloads;
- seller operational data — seller organization/membership/order scope;
- behavioral/profile data — quiz, recommendation, loyalty/growth data where implemented;
- support/moderation evidence — inquiries, incidents, reviews/reports;
- secrets — API keys, merchant credentials, signing secrets; these **must not be persisted in application business tables or committed to Git**.

## 3. User and reusable address data

Current baseline stores queryable identity/delivery fields in normal relational columns because authentication, uniqueness, seller/customer operations, search/routing or address editing may require deterministic access.

ARCH-0.3 does **not** falsely claim field-level encryption for every such column.

Required controls include:

- strict authorization and seller scoping;
- minimal API serializers/resources;
- TLS in transit;
- encrypted production disks/backups where infrastructure supports it;
- database/network isolation;
- audited administrative access;
- retention/deletion policy;
- log/error redaction;
- no unrestricted seller/partner export.

## 4. Historical order address snapshot

The Order model casts `address_snapshot` as `encrypted:array`. This is the immutable purchase-time delivery snapshot and is distinct from the user's mutable reusable Address record.

Rules:

- order rendering/fulfillment history should use the order snapshot for historical truth;
- reusable-address edits must not rewrite prior orders;
- sellers receive only fields needed for their Sub-order/fulfillment mode;
- for Hub fulfillment, seller data exposure may be narrower than Direct Fulfillment.

## 5. Field-level encryption decision

Encrypting queryable account/mobile/address columns cannot be introduced casually because it affects:

- uniqueness constraints;
- login/mobile lookup;
- filtering/search;
- routing/geospatial operations;
- indexing/performance;
- migrations and existing data;
- support/admin workflows.

Therefore a broader field-level-encryption/tokenization strategy is a **TARGET/security design decision**, not a silent claim in this phase. Any implementation must define deterministic lookup strategy or blind indexes where needed, key rotation/recovery, migration/backfill and operational impact.

## 6. OTP and authentication data

OTP/session records are security data. Contracts require:

- no plaintext OTP persistence when a hash/derived verifier is sufficient;
- bounded TTL/attempt/resend/rate-limit semantics;
- session/token expiry and revocation;
- no secret/API key exposure to frontend payloads;
- audit evidence for security-sensitive administrative actions.

OTP provider payloads must not become customer-profile truth.

## 7. Seller access

Seller access is organization/Roastery scoped. A seller may receive operationally necessary data for its own Sub-orders only.

Default prohibited access includes:

- another seller's Sub-orders/items/settlement;
- full customer purchase history;
- unrelated addresses/support history;
- loyalty/behavior profile unless a future explicitly authorized feature requires minimized access;
- bulk customer database export.

Seller authorization must be enforced backend-side; frontend route hiding is not authorization.

## 8. Carrier and fulfillment data

Carrier payloads should receive only delivery/claim fields needed for transport. Carrier webhooks/responses may contain customer/provider identifiers and must follow retention/redaction rules.

Hub operators should receive only work-item/customer fields required for receiving, processing, packaging and handoff. Operational dashboards must not expose unrelated financial/account data.

## 9. Growth/experience partner data

Growth Partners and package/experience partners are not entitled to customer identity by default. Future CAP-01/partner features should prefer:

- opaque attribution IDs;
- aggregate performance;
- minimized campaign context;
- consent/purpose-gated customer-facing data only when legitimately required.

A partner integration must not create a shadow copy of the customer database.

## 10. Provider payload retention

Payment, refund, SMS, carrier and other raw provider responses may be needed for troubleshooting/reconciliation but can contain sensitive metadata.

TARGET requirement:

- document which fields are required as durable evidence;
- redact credentials/tokens and unnecessary PII before persistence/logging;
- define retention window;
- restrict operator visibility;
- preserve enough normalized references for reconciliation after raw payload expiry where policy permits.

## 11. Logs and observability

Request IDs, entity IDs, provider references and error categories are preferred correlation fields. Avoid logging:

- OTP codes;
- API keys/merchant secrets;
- full authorization/cookie headers;
- full address/mobile/email unless explicitly required and protected;
- unrestricted provider raw bodies.

Logging policy must be compatible with security incident investigation without becoming a second uncontrolled PII store.

## 12. Retention and deletion

Deletion is domain-specific. Examples:

- expired ephemeral upload/OTP/session state can be pruned aggressively;
- order/financial/tax/custody evidence may require longer retention;
- reusable profile/address data may be deletable while legally/operationally required historical snapshots remain under a separate retention basis;
- legal hold/dispute state can override routine deletion according to approved policy.

Hard deletion, anonymization and archival must be designed per data class rather than applied globally.

## 13. Backups and exports

Production backups contain the same sensitivity as the source database. Backup/restore design must include access control, encryption, retention and restore testing.

CSV/admin exports of PII/finance data are privileged operations and should be minimized, audited, expiry-controlled where possible and never exposed through public object URLs.

## 14. New-schema privacy checklist

Before a migration adds customer/partner/behavior data, document:

1. data subject and purpose;
2. minimum fields;
3. write/read roles;
4. seller/partner exposure;
5. indexing/search need;
6. encryption/tokenization decision;
7. retention/deletion/legal-hold behavior;
8. audit requirements;
9. export/logging rules;
10. incident impact.

If these are unknown, the migration is not production-ready.
