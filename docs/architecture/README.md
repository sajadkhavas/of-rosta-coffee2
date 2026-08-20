# ROSTA Technical Architecture Contract

Status: ARCH-0.2 / legacy PS0.2 technical architecture consolidation
Baseline: `integration/rosta-release-candidate@d50af5ab516f64fe47072c8e67938d64614d8373`

## Purpose

This directory is the canonical technical-architecture contract for the current ROSTA codebase. It is an audit/consolidation of the architecture already implemented in the repository, not a greenfield rewrite and not a claim that every future capability is live.

The business contract in `docs/business/` remains authoritative for business semantics. This architecture translates those semantics into technical boundaries without redefining them.

## Status vocabulary

- **BUILT**: evidenced in the audited baseline code/configuration.
- **TARGET**: required architecture direction for current/future implementation.
- **PROPOSED**: an allowed option that still requires a dedicated product/technical decision before implementation.
- **EXTERNAL GATE**: depends on provider credentials, legal/commercial validation, network/DNS/TLS or another non-source prerequisite.

A TARGET or PROPOSED statement must never be represented as already deployed.

## Canonical documents

- `system-context.md` — actors, systems and trust boundaries.
- `runtime-topology.md` — runtime processes and stateful dependencies.
- `frontend-architecture.md` — TanStack Start SSR/client architecture and API truth boundary.
- `backend-architecture.md` — Laravel modular-monolith contract and orchestration rules.
- `domain-boundaries.md` — bounded domain ownership and cross-domain rules.
- `data-storage-and-consistency.md` — MySQL/Redis/object-storage truth and transaction rules.
- `async-queues-and-outbox.md` — queue, scheduler, outbox and retry semantics.
- `integration-boundaries.md` — payment/SMS/storage/carrier/partner provider ports.
- `security-trust-boundaries.md` — authentication, authorization, secrets and data minimization.
- `reliability-and-observability.md` — failure handling, health, correlation and operational evidence.
- `production-topology-contract.md` — production-ready-before-server runtime/deployment architecture.
- `architecture-decision-policy.md` — how architecture changes are proposed and accepted.

## Audited implementation anchors

The baseline audit confirmed, among other evidence:

- React/TanStack Start/Router/Query + Vite/TypeScript frontend, with `src/server.ts`, generated route tree and API client.
- Laravel 13 / PHP 8.3 backend with Sanctum and explicit service/domain folders.
- Redis defaults for cache, session and queue; queue `after_commit=true`; failed jobs persisted in MySQL.
- S3-compatible object storage configuration suitable for Cloudflare R2.
- payment provider manager plus Disabled/Testing/Zarinpal providers.
- OTP sender contract, Kavenegar integration and queueable OTP delivery.
- notification outbox model/service and scheduled dispatcher.
- secure media processing and queued media-upload processing.
- quote/order/idempotency, Financial Truth Engine, reconciliation and settlement services.
- fulfillment commitment/incidents/SLA, ROSTA Hub work items and chain-of-custody flows.
- scheduler jobs for reservation expiry, SLA monitoring, settlement release, notification dispatch and media cleanup.

These anchors are evidence, not a complete inventory of every class.

## Non-negotiable technical invariants

1. ROSTA remains a **modular monolith** unless measured evidence justifies extraction. Microservices are not a default goal.
2. MySQL is authoritative for transactional business truth.
3. Redis is operational infrastructure, never the sole source of financial/order truth.
4. The browser never recomputes authoritative quote, order, payment, settlement or tax truth.
5. Whole bean remains authoritative inventory identity; grinding remains an Order Item Service.
6. A valid paid seller Sub-order is a fulfillment commitment; no technical layer may reintroduce a normal seller reject path.
7. Financial states are independent from fulfillment/shipment states.
8. GMV, seller payable/pass-through amounts and ROSTA recognized revenue remain distinct.
9. Physical responsibility changes require explicit chain-of-custody evidence.
10. Provider-specific logic stays behind explicit provider boundaries; Zarinpal, Kavenegar, R2, a carrier or Winimi may not become Core domain truth.
11. Async jobs must be idempotent/retry-safe and observable; critical state transitions are committed synchronously before async side effects.
12. Production servers receive immutable, prevalidated artifacts/configuration. Source repair and first-time build/debug are not deployment steps.
13. Secrets remain server-side environment/secret-store values and are never shipped to the browser or committed to Git.
14. Architecture documents distinguish built capability from launch policy and future roadmap.

## Change rule

If implementation diverges from this contract, either the implementation must be corrected or the architecture contract must be explicitly versioned through a dedicated reviewed PR. Silent divergence is not accepted.
