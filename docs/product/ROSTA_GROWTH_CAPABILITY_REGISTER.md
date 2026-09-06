# ROSTA Post-Launch Growth Capability Register — PS12 Reconciliation

Status: authoritative post-launch product backlog and reconciliation input; **not implementation acceptance**

Historical planning source: `docs/product/ROSTA_GROWTH_CAPABILITY_REGISTER.md` @ `a9a5a70c3e69670c1589beb1d195a292caefd7bb`

Reconciled immutable baseline:

- frozen tag: `rosta-pre-server-2026-09-05`
- accepted merge SHA: `4a54780d504b91527a86777e7f04368022354686`
- accepted tree SHA: `42fa7da75ba1fa87f3750f4710de425b94faa647`

This register reconciles the original 34 approved growth capabilities against the source that was actually frozen after PS12. It also adds startup capabilities that were missing from the historical growth register. The frozen PS12 tag is immutable and is not modified by this document.

The next operational program is still real-server staging acceptance (`R4B — Live VPS Staging Deployment`). R4B is not a Growth Wave and must not be duplicated as a product-development phase.

## Status language

- **SUBSTANTIAL-PARTIAL** — a meaningful production-oriented foundation exists on the PS12 baseline, but the original capability contract is not complete.
- **PARTIAL** — some source/domain/UI capability exists, but important parts of the registered scope remain absent.
- **NOT-STARTED-AS-REGISTERED** — no sufficient evidence was found on the frozen PS12 baseline to claim the registered capability itself exists.
- **NEW / STARTUP-PRIORITY** — added by this reconciliation because it is needed for a controlled public pilot or for safely scaling growth, and it was not represented as a standalone capability in the historical register.

A status in this file is a planning verdict only. It is not a release, provider, runtime, legal, security or financial acceptance verdict.

## Product principles retained from the historical register

1. ROSTA should evolve from a coffee marketplace into a marketplace + CRM + loyalty + subscription + personalization platform.
2. Platform retention should come from superior product value rather than hiding roastery identity.
3. Promotions, loyalty, referral, credits, commissions and payouts must use authoritative rules, ledgers/idempotency, auditable events and financial snapshots instead of mutable browser-owned balances.
4. Revenue-share arrangements should not silently become lifetime obligations.
5. Automatic recurring charging must not be claimed until the chosen payment provider capability is officially verified.
6. AI/search recommendations must resolve against real catalog, inventory and pricing truth and must not fabricate commercial facts.
7. Existing financial, privacy, audit, provider and marketplace truth boundaries remain authoritative; this register must integrate with them rather than create parallel systems.

# Reconciled capability backlog

| ID | Capability | PS12 status | Evidence already present | Material remaining scope |
|---|---|---|---|---|
| CAP-01 | Rosta Growth Network / Growth Partner | **SUBSTANTIAL-PARTIAL** | PS11 partner lifecycle, customer/roastery/B2B leads, encrypted PII, ownership/dedupe/self-referral controls, attribution locking, versioned commission policy, append-only accrual and refund reversal | referral URL/QR experience, portfolios/pipeline UX, ranks/milestones/bonuses, mature partner analytics, payout history/workflows and full partner/admin product surface |
| CAP-02 | Promotion & Coupon Engine | **PARTIAL** | `Coupon` and `RoasteryPromotion` source models plus existing financial/quote foundations | one central conflict/stackability/funding engine, automatic offers, BxGy, free-shipping rules, segment targeting, complete snapshots across quote/order/settlement and management UX |
| CAP-03 | Rosta Club / Loyalty | **NOT-STARTED-AS-REGISTERED** | no complete loyalty points/tier/reward domain proven | points ledger, tiers, rewards, VIP benefits, missions, campaigns, perks and financial integration through CAP-02 |
| CAP-04 | Rosta Taste ID | **PARTIAL** | versioned quiz, persisted/syncable `QuizAttempt`, score profile and user-owned history | durable customer taste identity beyond individual attempts, learned preferences from purchase/review behavior, profile evolution and explicit match-profile lifecycle |
| CAP-05 | Personal Recommendation Engine | **PARTIAL** | deterministic `QuizService::recommendations()` scores real published/in-stock products and returns explainable reasons | persistent Taste ID inputs, purchase/review learning, personalized surfaces, complementary/next-coffee/subscription ranking, evaluation and ranking governance |
| CAP-06 | Wishlist | **NOT-STARTED-AS-REGISTERED** | no sufficient frozen-baseline wishlist domain evidence | favorites, save-for-later, management, availability/fresh-batch/promotion alerts and future sharing |
| CAP-07 | Follow System | **NOT-STARTED-AS-REGISTERED** | no sufficient follow graph evidence | follows for roasteries/products/origins/process/flavor plus notification/personalization events |
| CAP-08 | Smart Reorder | **NOT-STARTED-AS-REGISTERED** | order history exists, but no registered replenishment system proven | Buy Again, configuration recall, one-click reorder, consumption estimation and refill reminders |
| CAP-09 | Customer Segmentation / RFM | **NOT-STARTED-AS-REGISTERED** | commerce history exists but no central RFM/segment layer proven | recency/frequency/value and affinity segmentation, scheduled recomputation, privacy-safe audiences and downstream contracts |
| CAP-10 | Notification Center 2.0 | **PARTIAL** | transactional notification/SMS foundations and notification domain work exist | unified preference-aware in-app/email/SMS/web-push center, growth events, delivery preference UX and channel observability |
| CAP-11 | Store Credit / Cashback | **NOT-STARTED-AS-REGISTERED** | financial ledgers exist for other domains, not a customer closed-loop credit product | credit account/ledger, expiry, reversal, checkout redemption and non-withdrawable/non-transferable defaults |
| CAP-12 | Coffee Subscription | **NOT-STARTED-AS-REGISTERED** | no subscription-cycle domain proven | cadence, pause/skip/resume/cancel, product/weight changes, reminders and provider-truthful payment requests |
| CAP-13 | Rosta Discovery Subscription | **NOT-STARTED-AS-REGISTERED** | deterministic recommendations exist, not recurring discovery fulfillment | Taste-ID-based recurring selection, constraints/exclusions, feedback and learning |
| CAP-14 | Sampler / Discovery Box | **NOT-STARTED-AS-REGISTERED** | no sampler product-builder contract proven | multi-coffee discovery package, inventory/pricing truth and feedback-to-Taste-ID integration |
| CAP-15 | Build Your Box / Bundle Builder | **NOT-STARTED-AS-REGISTERED** | no bundle composition engine proven | mix-and-match bundle, weights, eligibility rules, inventory and CAP-02 discount integration |
| CAP-16 | Gift Card | **NOT-STARTED-AS-REGISTERED** | no gift-value domain proven | closed-loop gift value, message/scheduling, balance, expiry and redemption ledger |
| CAP-17 | Gift Subscription | **NOT-STARTED-AS-REGISTERED** | no gift-subscription domain proven | recipient flow, bounded term, delivery/notification and subscription integration |
| CAP-18 | Coffee Passport | **NOT-STARTED-AS-REGISTERED** | purchase/review data can become inputs but no passport domain is proven | exploration history by country/origin/roastery/process/roast and badge progress |
| CAP-19 | Challenges & Achievements | **NOT-STARTED-AS-REGISTERED** | no general mission/achievement engine proven | verified challenges, badge/point benefits and anti-abuse integration |
| CAP-20 | Coffee Journal | **NOT-STARTED-AS-REGISTERED** | no personal brew/tasting journal domain proven | brew records, grams/water/temp/grind/time/rating/notes and recipe association |
| CAP-21 | Brew Assistant | **NOT-STARTED-AS-REGISTERED** | brew content routes exist, but no registered interactive assistant contract proven | ratio calculator, grind guidance, water/temp/time, timer and saved recipes |
| CAP-22 | Search 2.0 | **PARTIAL** | public search route/catalog filtering foundations exist | autocomplete, typo tolerance, synonyms, richer facets/ranking, relevance evaluation and complete freshness/grinding filters |
| CAP-23 | Semantic Coffee Search | **NOT-STARTED-AS-REGISTERED** | no natural-language catalog retrieval layer proven | semantic query understanding mapped only to real catalog/availability/pricing records |
| CAP-24 | AI Coffee Concierge | **NOT-STARTED-AS-REGISTERED** | deterministic quiz recommendation foundation exists, not a conversational tool-backed advisor | tool-backed catalog/inventory/price/review retrieval, grounded explanations, safety/evaluation and conversation UX |
| CAP-25 | PWA + Web Push | **PARTIAL** | prior PWA/performance work and production web application foundations exist | web-push subscription/delivery, preference integration, complete install/update/offline acceptance and shortcut lifecycle as applicable |
| CAP-26 | Freshness System | **SUBSTANTIAL-PARTIAL** | roast/batch/freshness source and launch-critical fail-closed freshness policy exist | complete customer-facing freshness indicators/filters/alerts and cross-surface freshness productization |
| CAP-27 | Limited Drops / Waitlists | **NOT-STARTED-AS-REGISTERED** | inventory authority exists but no drop/waitlist domain proven | countdown, waitlist, early access, notification and allocation/idempotency safeguards |
| CAP-28 | Review 2.0 | **PARTIAL** | verified review/support/moderation foundations and review management surfaces exist | richer coffee review dimensions, photo/video, helpful votes, would-buy-again, seller reply and full moderation UX |
| CAP-29 | B2B Coffee Accounts | **SUBSTANTIAL-PARTIAL** | PS12 verified cafe lifecycle, scoped owner/manager roles, wholesale entitlement, authoritative wholesale tiers/quote/order, cafe directory and workspaces | multi-location/advanced approvals, customer-specific catalog, richer MOQ/procurement rules, quick reorder, recurring procurement, request-quote and invoice/statement history |
| CAP-30 | Seller CRM | **NOT-STARTED-AS-REGISTERED** | seller operational/finance surfaces exist, not the registered CRM/BI layer | cohorts, retention, product/customer affinities, demand signals, conversion and privacy-safe seller analytics |
| CAP-31 | Seller Campaigns | **PARTIAL** | `RoasteryPromotion` foundation exists | seller proposal workflow, platform approval, CAP-02 funding/conflicts, quotas and immutable financial snapshots |
| CAP-32 | Sponsored Discovery | **NOT-STARTED-AS-REGISTERED** | no sufficient sponsored-placement system proven | labeled placements, budget/campaign accounting and performance analytics |
| CAP-33 | Experimentation / A-B Testing | **NOT-STARTED-AS-REGISTERED** | no central experiment assignment/exposure system proven | stable assignment, exposure events, guardrails, conversion metrics and strict financial-rule isolation |
| CAP-34 | Community Layer | **NOT-STARTED-AS-REGISTERED** | no public user/community graph proven | public profiles/tasting notes/lists/recipes/follows/community recommendations after commerce/retention maturity |
| CAP-35 | Product Analytics & Launch KPI Instrumentation | **NEW / STARTUP-PRIORITY** | audit logs and domain truth exist, but no product funnel/measurement capability is registered | implement the minimum public-pilot instrumentation contract described below |
| CAP-36 | Consent, Communication Preferences & Data Rights | **NEW / STARTUP-PRIORITY** | privacy/legal pages and security/privacy controls exist, but no unified customer consent/preference receipt domain is proven | implement preference/consent truth before scaled marketing or any measurement setup that requires it |

# CAP-35 — Product Analytics & Launch KPI Instrumentation

Purpose: ROSTA must be able to learn from a real pilot without treating browser analytics as financial or order truth.

Minimum startup scope:

- versioned first-party event taxonomy;
- explicit anonymous/session/account identity boundaries with no unnecessary PII in analytics payloads;
- acquisition source and campaign attribution inputs such as UTM values where used;
- ecommerce/product funnel events covering discovery/search, product view, cart, checkout/quote, order creation, payment success/failure/refund and repeat purchase;
- cafe/B2B application funnel;
- seller onboarding/activation funnel where applicable;
- growth-partner attribution integration without creating a second partner-attribution system;
- new vs returning, cohort, repeat-purchase and retention reporting inputs;
- server-originated, idempotent commerce events for authoritative order/payment outcomes;
- dashboards or deterministic exports for launch KPIs;
- event schema validation, test fixtures and duplicate-event controls;
- retention/privacy configuration and redaction rules;
- analytics failure must never fail or mutate an otherwise valid payment/order transaction.

Implementation should remain vendor-neutral at the domain layer. If Google Analytics is used, map the internal contract to the provider's documented ecommerce events rather than allowing provider event names to become the business source of truth.

Minimum pilot KPI set:

- qualified traffic/source;
- product/search engagement;
- add-to-cart rate;
- checkout/quote start rate;
- order/payment conversion;
- payment failure/refund rate;
- first-to-second-order conversion;
- repeat purchase and cohort retention;
- cafe application -> verification -> wholesale-order funnel;
- seller/partner activation metrics where those channels are being piloted.

# CAP-36 — Consent, Communication Preferences & Data Rights

Purpose: provide one auditable source for customer communication/privacy preferences instead of scattering opt-outs across providers.

Minimum scope:

- versioned policy/notice identity attached to recorded choices;
- consent/preference receipt with account/anonymous subject where appropriate, timestamp, source, version and state;
- separate transactional communications from optional marketing communications;
- channel preferences for email, SMS and web push when those channels are enabled;
- opt-in/opt-out and later withdrawal/revocation flows;
- local ROSTA preference truth remains authoritative even if a provider has its own suppression/preference setting;
- integration contract for CAP-10 Notification Center 2.0;
- auditable admin/support access without silently overriding customer preference history;
- data export/deletion request workflow hooks, subject to separately approved legal/business retention policy;
- analytics/tag consent integration when a chosen measurement/advertising setup requires a consent signal;
- no jurisdiction-specific legal claim is considered approved merely because this capability exists.

# Startup prerequisite overlay

This overlay changes priority, not the historical Growth Wave numbering.

## S0 — Runtime first

`R4B — Live VPS Staging Deployment` remains the immediate operational next phase. It must prove the exact frozen PS12 release on a real staging VPS before public traffic. It is outside this growth register.

## S1 — Minimum measurement before a meaningful public pilot

Implement the minimum viable slice of **CAP-35** before or together with the first public pilot so that launch conversion, failures, repeat behavior and acquisition can be measured from day one. This does not require every future analytics dashboard before launch.

## S2 — Preference/consent gate before scaled outreach

Implement the minimum viable slice of **CAP-36** before scaled promotional SMS/email/web-push. If a selected analytics/advertising configuration requires user consent before collection/storage, that consent path must be in place before enabling the affected tags or providers.

## S3 — Then execute Growth Waves by dependency and observed pilot data

Do not re-build PS11, PS12, freshness, quiz/recommendation or existing notification/promotion foundations merely because their historical CAP is still incomplete. Each Growth Wave starts from the frozen/current accepted ancestry and closes only the remaining scope.

# Reconciled execution waves

| Wave | Scope | Reconciled state / next work |
|---|---|---|
| Growth Wave 0 | Contract & Architecture | **REOPEN ONLY FOR NEW GROWTH CONTRACTS** — add CAP-35/36 event/privacy contracts and common growth acceptance rules; reuse existing finance/audit/idempotency truth |
| Growth Wave 1 | CAP-02 Promotion Engine | **PARTIAL -> COMPLETE REMAINDER** |
| Growth Wave 2 | CAP-03 Rosta Club | **NOT STARTED** |
| Growth Wave 3 | CAP-04 + CAP-05 Taste ID + Recommendation | **PARTIAL -> COMPLETE REMAINDER** |
| Growth Wave 4 | CAP-06 + CAP-07 + CAP-08 Wishlist/Follow/Reorder | **NOT STARTED** |
| Growth Wave 5 | CAP-09 + CAP-10 Segmentation/Notification | CAP-09 **NOT STARTED**, CAP-10 **PARTIAL** |
| Growth Wave 6 | CAP-01 Growth Network | **SUBSTANTIAL-PARTIAL -> COMPLETE REMAINDER**; never duplicate PS11 financial truth |
| Growth Wave 7 | CAP-11 Store Credit + referral financial integration | **NOT STARTED** |
| Growth Wave 8 | CAP-12..15 Subscription/Discovery/Bundles | **NOT STARTED** |
| Growth Wave 9 | CAP-16..19 Gift/Passport/Gamification | **NOT STARTED** |
| Growth Wave 10 | CAP-20 + CAP-21 Journal/Brew Assistant | **NOT STARTED** |
| Growth Wave 11 | CAP-22 + CAP-25 Search 2.0/PWA, CAP-26 integration | **PARTIAL FOUNDATIONS -> COMPLETE REMAINDER** |
| Growth Wave 12 | CAP-23 + CAP-24 Semantic Search/AI Concierge | **NOT STARTED**; must depend on deterministic search/recommendation truth |
| Growth Wave 13 | CAP-29 + CAP-30 B2B/Seller CRM | CAP-29 **SUBSTANTIAL-PARTIAL**, CAP-30 **NOT STARTED**; never duplicate PS12 wholesale truth |
| Growth Wave 14 | CAP-31 + CAP-32 + CAP-33 Seller Campaigns/Sponsored/Experiments | CAP-31 **PARTIAL**, CAP-32/33 **NOT STARTED** |
| Growth Wave 15 | CAP-34 Community | **NOT STARTED / LATE STAGE** |

Cross-wave capabilities CAP-26 Freshness, CAP-27 Limited Drops and CAP-28 Review 2.0 remain dependency-driven. CAP-35 is startup/pilot cross-wave infrastructure. CAP-36 is privacy/communication cross-wave infrastructure.

# Future capability acceptance rule

Every capability or remaining slice moved from this register into implementation must receive an execution contract that covers, as applicable:

- exact accepted baseline SHA/tag and ancestry proof;
- dedicated branch and no direct edits to frozen tags;
- explicit statement of what existing subsystem is reused so duplicate domains are not created;
- domain boundaries and authoritative data ownership;
- migrations/backfill and rollback/roll-forward strategy;
- policy/rule versioning;
- idempotency, ledger and snapshot requirements for anything financial;
- API/OpenAPI contract;
- frontend/customer UX and relevant admin/seller/partner UX;
- roles/permissions and abuse/fraud controls;
- privacy/redaction/retention effects;
- analytics and notification events;
- provider capability truth and fail-closed behavior;
- permanent unit/feature/integration/browser tests as applicable;
- exact-head CI/gate evidence;
- review-thread closure;
- normal, non-history-rewriting merge;
- registry update with final source head, merge SHA and permanent evidence.

No capability is `DONE` merely because it appears in this register, has a model/table, has a UI mock, or passes a local happy-path test.

# Explicit boundaries

This register does not claim:

- R4B server acceptance;
- production DNS cutover or live traffic;
- live Zarinpal/Kavenegar/R2/carrier/payout capability;
- approved tax/legal/marketing policy;
- provider-supported recurring billing;
- production-safe AI merely because CAP-24 is planned;
- completion of any CAP marked partial/not-started.

The frozen PS12 release remains the source baseline for the upcoming server-stage proof; future growth development should begin only from an explicitly accepted descendant baseline after runtime defects, if any, are returned to GitHub and closed through normal release discipline.
