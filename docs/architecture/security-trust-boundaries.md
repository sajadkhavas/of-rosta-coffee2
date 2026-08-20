# Security and Trust Boundaries

Status: ARCH-0.2

## 1. Security model

ROSTA treats browsers, provider callbacks, uploaded objects, partner inputs and seller-scoped operations as untrusted until authenticated/validated/authorized by the appropriate backend boundary.

Security controls must reinforce business isolation: customer-self, seller-own-organization, fulfillment-assigned-work, finance-authorized-operation and platform-admin scopes are distinct.

## 2. Authentication

**BUILT:** Laravel Sanctum session/cookie architecture, OTP challenge model and frontend CSRF bootstrap/recovery.

Rules:

- production cookies use secure domain/TLS configuration;
- HTTP-only session cookie remains inaccessible to application JS;
- unsafe requests require CSRF protection under cookie auth;
- OTP challenge TTL, resend, attempt and rate limits are server authority;
- raw OTP is never stored/logged in plaintext as routine evidence;
- session invalidation/pruning and device/session management remain auditable.

## 3. Authorization

Every sensitive API mutation requires server-side authorization independent of frontend navigation.

At minimum enforce:

- customer can access only own records;
- roastery membership/role plus organization scope for seller resources;
- no cross-roastery order/customer/finance visibility;
- fulfillment operators only assigned/authorized operational records;
- finance permissions for refunds/settlements/reconciliation;
- admin high-impact actions with reason/audit and least privilege.

Identifiers in URLs are never authorization.

## 4. Secrets

Secrets include:

- payment merchant credentials;
- Kavenegar/SMS credentials;
- S3/R2 access keys;
- database/Redis credentials;
- Laravel app key;
- webhook secrets;
- future carrier/partner credentials.

Rules:

- environment/secret-store only;
- no secrets in Git, client bundles, SSR HTML, logs, screenshots or API errors;
- rotate on suspected exposure;
- production credentials are never required to build/test source gates;
- startup/preflight validates required secret presence without printing values.

## 5. Payment/security evidence

Payment APIs expose only customer-safe references/status. Full provider payloads, bank/card data or sensitive callback evidence are restricted to the minimum operational scope.

Never accept client-submitted payment status or total as authority.

## 6. File/media security

Upload flow must enforce:

- authenticated ownership/intent;
- generated/scoped object key;
- byte limit;
- actual magic-byte/MIME validation;
- checksum/size revalidation;
- pixel/dimension/frame/decode limits;
- server-side decode/sanitize/re-encode;
- no trust in client-supplied width/height/MIME;
- derived public variants separate from unsafe source bytes.

Media processors must defend against decompression bombs/resource exhaustion and unsupported animated/malformed input.

## 7. Customer data minimization

The privacy business contract is implemented through field-level/API-level minimization:

- seller sees only fields necessary for own fulfillment/support role;
- centralized ROSTA Fulfillment may reduce seller access to delivery details;
- carrier receives delivery/claim minimum;
- Growth Partner receives attribution/portfolio minimum, not customer database;
- experience partner does not receive identity by default.

Logs/analytics inherit the same minimization requirement.

## 8. Rate limiting and abuse

Rate limits are required around authentication/OTP, public abuse-prone endpoints, checkout/payment attempts, reviews/support and future Growth attribution/lead operations.

Rate limits supplement, not replace, domain anti-fraud rules. Financial self-referral/duplicate/fraud eligibility is persistent business logic, not only an IP limit.

## 9. Audit logging

High-impact events require actor/source/time/reason/correlation where applicable, including:

- role/membership/security changes;
- admin exception cancellation/refund/financial adjustment;
- settlement/payout operation;
- custody/QC/rework exceptions;
- provider configuration activation/deactivation;
- privacy-sensitive export/access actions where implemented.

Audit data must be tamper-resistant at application-policy level and must not contain secrets/raw OTP.

## 10. Headers and edge security

Production reverse proxy/application should enforce appropriate TLS, host validation, content-type sniffing protection, frame policy, referrer policy, permissions policy and CSP appropriate to actual third-party dependencies.

CORS/Sanctum stateful domains are explicit allowlists; wildcard credentialed CORS is prohibited.

## 11. Dependency and CI security

Lockfiles remain authoritative. CI audits high/critical dependency findings, runs backend/frontend static/test gates and fails on known security contract violations.

Dependency changes require reviewed PRs; production hosts do not run opportunistic package upgrades.

## 12. Incident posture

Security/privacy incidents preserve evidence, revoke/rotate affected credentials, contain access and use documented recovery. Hiding an incident by deleting logs/history is prohibited.
