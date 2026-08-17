# ROSTA Execution Ownership & Phase Governance

Status: authoritative planning/governance companion to `ROSTA_GROWTH_CAPABILITY_REGISTER.md`

Baseline reviewed: `integration/rosta-release-candidate@a96d0e05478bc2c61852fdf91bb46da1782030df`

This document exists to prevent capability drift, phase ownership confusion, duplicated audits, and long-chat handoff failures.

## 1. Current pre-server status

Accepted and integrated through Wave 2:

- PS0 — Pre-server contract: DONE
- PS1 — Release & Security Hardening: DONE
- PS2 — OTP & Notifications: DONE
- PS3 — Secure Media Pipeline: DONE
- PS4.1 — Financial Truth Core: DONE
- PS5.1 — Quiz / Recommendations / Review Safety: DONE
- PS5.2 — Seller Organization & Availability: DONE

The exact combined Wave 2 integration head is:

`a96d0e05478bc2c61852fdf91bb46da1782030df`

Remaining pre-server phases, in canonical order:

- Wave 3: PS4.2 — Refund, Payout & Reconciliation
- Wave 3: PS5.3 — Carrier & Non-financial Admin Operations
- Wave 4: PS5.4 — Seller/Admin Workspaces & KPI Composition
- Wave 4: PS6B — Backend Refactor, Queue Reliability & Observability
- Wave 5: PS6A — Frontend Quality Freeze
- Wave 5: PS7 — Production Deployment Package / Rehearsal
- Wave 6: PS8A — Frontend Acceptance Audit
- Wave 6: PS8B — Backend & Finance Acceptance Audit
- Wave 6: PS8C — Infrastructure Acceptance Audit
- Wave 7: PS9 — Final Integration, Tag & Pre-server Freeze

No Growth capability implementation starts by silently consuming unfinished pre-server work. Growth implementation begins from the accepted post-PS9 baseline unless the central reviewer explicitly approves an earlier isolated branch.

## 2. Capability ownership rule — locked

Every approved product capability has exactly one permanent owner phase.

A scheduling wave may change when a phase starts, but the capability never moves to another owner phase.

Dependencies are consumed through accepted contracts; they do not transfer ownership.

A phase may be split into child phases only when its scope is too large. Child phases retain the same capability identity, for example `CAP-01A`, `CAP-01B`, `CAP-01C`. Scope from CAP-01 must never be completed opportunistically inside CAP-09, CAP-29, or another unrelated phase.

The previous grouped Growth Wave suggestions in `ROSTA_GROWTH_CAPABILITY_REGISTER.md` are now sequencing guidance only. This document is authoritative for implementation ownership.

## 3. Permanent capability-to-phase map

| Capability | Permanent implementation phase | Canonical branch |
|---|---|---|
| CAP-01 Rosta Growth Network / Growth Partner | G-CAP01 | `phase/rosta-cap01-growth-network` |
| CAP-02 Promotion & Coupon Engine | G-CAP02 | `phase/rosta-cap02-promotion-engine` |
| CAP-03 Rosta Club / Loyalty | G-CAP03 | `phase/rosta-cap03-loyalty` |
| CAP-04 Rosta Taste ID | G-CAP04 | `phase/rosta-cap04-taste-id` |
| CAP-05 Personal Recommendation Engine | G-CAP05 | `phase/rosta-cap05-recommendations` |
| CAP-06 Wishlist | G-CAP06 | `phase/rosta-cap06-wishlist` |
| CAP-07 Follow System | G-CAP07 | `phase/rosta-cap07-follow` |
| CAP-08 Smart Reorder | G-CAP08 | `phase/rosta-cap08-smart-reorder` |
| CAP-09 Customer Segmentation / RFM | G-CAP09 | `phase/rosta-cap09-segmentation` |
| CAP-10 Notification Center 2.0 | G-CAP10 | `phase/rosta-cap10-notification-center` |
| CAP-11 Store Credit / Cashback | G-CAP11 | `phase/rosta-cap11-store-credit` |
| CAP-12 Coffee Subscription | G-CAP12 | `phase/rosta-cap12-subscription` |
| CAP-13 Rosta Discovery Subscription | G-CAP13 | `phase/rosta-cap13-discovery-subscription` |
| CAP-14 Sampler / Discovery Box | G-CAP14 | `phase/rosta-cap14-discovery-box` |
| CAP-15 Build Your Box / Bundle Builder | G-CAP15 | `phase/rosta-cap15-bundle-builder` |
| CAP-16 Gift Card | G-CAP16 | `phase/rosta-cap16-gift-card` |
| CAP-17 Gift Subscription | G-CAP17 | `phase/rosta-cap17-gift-subscription` |
| CAP-18 Coffee Passport | G-CAP18 | `phase/rosta-cap18-coffee-passport` |
| CAP-19 Challenges & Achievements | G-CAP19 | `phase/rosta-cap19-achievements` |
| CAP-20 Coffee Journal | G-CAP20 | `phase/rosta-cap20-coffee-journal` |
| CAP-21 Brew Assistant | G-CAP21 | `phase/rosta-cap21-brew-assistant` |
| CAP-22 Search 2.0 | G-CAP22 | `phase/rosta-cap22-search-v2` |
| CAP-23 Semantic Coffee Search | G-CAP23 | `phase/rosta-cap23-semantic-search` |
| CAP-24 AI Coffee Concierge | G-CAP24 | `phase/rosta-cap24-ai-concierge` |
| CAP-25 PWA + Web Push | G-CAP25 | `phase/rosta-cap25-pwa-push` |
| CAP-26 Freshness System | G-CAP26 | `phase/rosta-cap26-freshness` |
| CAP-27 Limited Drops / Waitlists | G-CAP27 | `phase/rosta-cap27-limited-drops` |
| CAP-28 Review 2.0 | G-CAP28 | `phase/rosta-cap28-review-v2` |
| CAP-29 B2B Coffee Accounts | G-CAP29 | `phase/rosta-cap29-b2b-accounts` |
| CAP-30 Seller CRM | G-CAP30 | `phase/rosta-cap30-seller-crm` |
| CAP-31 Seller Campaigns | G-CAP31 | `phase/rosta-cap31-seller-campaigns` |
| CAP-32 Sponsored Discovery | G-CAP32 | `phase/rosta-cap32-sponsored-discovery` |
| CAP-33 Experimentation / A-B Testing | G-CAP33 | `phase/rosta-cap33-experimentation` |
| CAP-34 Community Layer | G-CAP34 | `phase/rosta-cap34-community` |

## 4. Shared foundations do not steal capability scope

Shared systems such as Financial Truth, Promotion rules, Notification delivery, analytics events, identity, media, search infrastructure, and deployment are dependencies.

Example rules:

- CAP-03 Loyalty consumes CAP-02 Promotion and Financial Truth, but Loyalty rules, points, tiers, rewards and UI remain CAP-03-owned.
- CAP-01 Growth Network may consume CAP-11 Store Credit and CAP-10 Notifications, but partner attribution, CRM, commission ledger, partner payout states and partner dashboard remain CAP-01-owned.
- CAP-29 B2B consumes CAP-01 attribution when needed, but organization accounts, buyers, locations, pricing contracts and approval flows remain CAP-29-owned.
- CAP-26 Freshness may feed CAP-22 search filters and CAP-05 recommendations, but freshness truth, batch-age semantics and freshness APIs remain CAP-26-owned.
- CAP-28 Review 2.0 extends the existing review foundation, but its new media/helpful/brew-accuracy features remain CAP-28-owned.

## 5. Definition of Done for every capability phase

A phase is not DONE because code exists locally or because one test passed.

DONE requires all applicable items:

1. exact 40-character baseline SHA recorded;
2. dedicated branch from that exact baseline;
3. explicit in-scope and out-of-scope contract;
4. database migration/model/domain work complete where required;
5. API and OpenAPI complete where required;
6. frontend/customer/seller/admin surfaces complete where required;
7. security, authorization, privacy, idempotency and fraud rules covered;
8. financial truth preserved; no browser-side money recomputation;
9. unit/integration/browser/accessibility tests green;
10. production build green;
11. permanent audit/gate added for critical invariants;
12. PR opened with exact head SHA and evidence;
13. CI green on the exact PR head;
14. compact handoff written;
15. central reviewer accepts and integrates it.

No unfinished requirement is silently moved to the next capability phase. A real blocker is recorded as a blocker against the same capability.

## 6. Chat and handoff workflow — lessons adopted from LBB and winimibakery

The supervisor chat must stay small and act as registry/integrator, not become the place where every implementation log accumulates.

Rules:

- one capability phase = one dedicated implementation chat;
- one central ROSTA supervisor chat = status, acceptance, integration and next-phase dispatch only;
- a large capability may use child chats such as CAP-01A/B/C, but all return to one CAP-01 acceptance record;
- implementation chats do not carry unrelated later-phase work;
- every implementation chat ends with a compact canonical handoff, so a new chat never needs the full prior transcript;
- the handoff must contain baseline SHA, branch, head SHA, PR, files/domains changed, tests/gates, known blockers, and exact next action;
- source truth is GitHub + committed evidence, not chat memory;
- no phase is accepted from screenshots, terminal snippets, or claims alone when repository evidence can prove it;
- no repeated full audit inside every chat: owner phase audits its scope, central reviewer audits integration, final acceptance audits the frozen candidate;
- a defect found later returns to its owning phase/branch or an explicitly named fix phase; it is not buried in the next feature.

## 7. Git/PR rules to avoid LBB-style registration confusion

- never implement directly on `main` or `integration/rosta-release-candidate`;
- every phase starts from a published exact integration SHA;
- one canonical branch per phase;
- one canonical PR per phase unless a documented child-phase split is approved;
- no rebase, amend, squash, force-push or history rewrite on published phase work;
- the implementer never merges its own PR;
- central reviewer merges in dependency order;
- after every integration wave, publish the new exact integration SHA before the next wave starts;
- do not create competing `final`, `final-fixed`, `candidate-2`, or ad-hoc release branches;
- temporary verification branches/PRs must be explicitly marked `DO NOT MERGE` and closed after evidence is collected.

## 8. Deployment rules to avoid repeated server/release failures

Feature implementation and server activation are separate concerns.

- product phases never mutate the real production server;
- PS7 owns the production package and rehearsal before real deployment;
- PS9 freezes one exact pre-server SHA;
- real deployment uses an immutable release directory keyed by SHA;
- `current` activation must be atomic and its previous target recorded for rollback;
- application, worker, scheduler, queue, Nginx/proxy, TLS, environment and storage identities must all be verified against the same release;
- build success alone is not deployment acceptance;
- after activation, health, public origin, service identity, logs, queues, scheduled jobs, media access and rollback path must be checked once in a defined deployment acceptance run;
- avoid repeating ad-hoc server commands across multiple chats; use a versioned runbook/script whenever the procedure becomes stable.

## 9. Status vocabulary

Only these states should be used in the central registry:

- NOT_STARTED
- IN_PROGRESS
- BLOCKED
- IMPLEMENTED_AWAITING_ACCEPTANCE
- ACCEPTED_AWAITING_INTEGRATION
- INTEGRATED
- FROZEN

`DONE` should be used conversationally only when the registry state is at least INTEGRATED, or FROZEN for a final release milestone.

## 10. Immediate next execution

Current Growth planning remains documentation-only. The active product-engineering path is the remaining pre-server sequence beginning with Wave 3:

- PS4.2 — Refund, Payout & Reconciliation
- PS5.3 — Carrier & Non-financial Admin Operations

Only after Wave 3 acceptance should Wave 4 begin.
