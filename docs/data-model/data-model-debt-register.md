# Data-Model Debt Register

Status: ARCH-0.3 audited debt — **not permission for unrelated cleanup**

This register records schema/data drift found while reconciling the current repository with the accepted business and technical contracts. Each item requires a dedicated owner and forward-safe remediation. Historical migrations must not be rewritten.

## D-01 — Legacy seller acceptance/rejection persistence

**Severity:** High semantic risk / Medium immediate runtime risk  
**State:** LEGACY DEBT  
**Primary owner:** Checkout/Order + Fulfillment  
**Evidence anchors:** `sub_orders.acceptance_status`, acceptance/rejection timestamps/codes, `SubOrderAcceptanceStatus`, `FulfillmentCommitmentService`.

### Finding

The schema/model still carries an earlier workflow with values such as:

- `awaiting_roastery_acceptance`
- `accepted`
- `rejected_by_roastery`

Current accepted business/runtime truth is different: after verified payment, `FulfillmentCommitmentService` automatically establishes seller fulfillment commitment, sets preparation/SLA state and compatibility acceptance state. Seller inability to fulfill is handled as an incident/authorized exception, not a routine accept/reject gate.

### Risk

A future developer can mistake legacy columns/enums for a still-supported business command and reintroduce seller rejection after payment.

### Required action

- treat the fields as compatibility-only;
- prohibit new routine seller accept/reject endpoints/UI;
- audit all reads/writes, reports, tests and historical data;
- when safe, use a dedicated forward migration/API compatibility plan to remove or replace legacy semantics;
- preserve historical event evidence during migration.

**Do not** delete these columns in ARCH-0.3.

---

## D-02 — Notification template seed drift from obsolete acceptance workflow

**Severity:** High customer-copy risk / Low schema risk  
**State:** LEGACY DEBT  
**Primary owner:** Notifications + Fulfillment/Product copy

### Finding

The historical notification-template migration seeds `order.paid`, `order.accepted` and `order.rejected` copy reflecting a manual seller-review/accept/reject workflow. Current paid-order runtime creates fulfillment commitment automatically.

### Risk

A deployed database seeded from those values can send misleading customer messages even if application code follows the new workflow.

### Required action

- audit current template keys/references and whether production/admin customization is allowed;
- define canonical post-payment/preparing/incident copy;
- implement an idempotent **forward data migration/command**, not an edit of the old migration;
- avoid overwriting legitimate operator-customized content without version/policy rules;
- remove/deprecate unused acceptance/rejection event keys only after usage proof.

---

## D-03 — Parallel legacy shipment and shipment-leg representations

**Severity:** Medium  
**State:** LEGACY DEBT / compatibility bridge  
**Primary owner:** Fulfillment/Carrier

### Finding

The repository contains `shipments`/`shipment_events` plus newer `shipment_legs`, including compatibility linking. The leg model is required for multi-leg Hub routing.

### Risk

New integrations can create a third truth or update one representation without the other, producing inconsistent tracking/customer/admin views.

### Required action

- document canonical write/read path per current endpoint;
- prefer `shipment_legs` for new multi-leg capability;
- keep explicit adapter/compatibility mapping for existing consumers;
- consolidate only after code/API/report/data usage audit and safe backfill.

---

## D-04 — Queryable identity/address PII not uniformly field-encrypted

**Severity:** High security/privacy importance; design-sensitive  
**State:** TARGET hardening decision, not a confirmed vulnerability  
**Primary owner:** Security/Identity + Infrastructure

### Finding

The Order historical `address_snapshot` is encrypted by the model, while reusable account/contact/address data needs queryable relational fields for current identity/delivery workflows. ARCH-0.3 found no basis to claim universal field-level encryption for those queryable columns.

### Risk

Over-broad database/admin/export/log access can expose PII. Conversely, blindly encrypting columns can break mobile uniqueness/login, search, routing and indexes.

### Required action before production/security freeze

- enforce backend authorization/minimal serializers;
- encrypt disks/backups and isolate DB/network;
- audit admin/export/logging access;
- define retention/deletion policy;
- decide whether selected fields require application-level encryption/tokenization/blind indexes;
- if chosen, design key rotation/recovery and backfill explicitly.

**Do not** casually encrypt/query-transform these fields in a documentation phase.

---

## D-05 — Durable JSON/snapshot schema version discipline

**Severity:** Medium  
**State:** TARGET  
**Primary owner:** Each domain owning durable JSON

### Finding

ROSTA correctly uses JSON/long-text snapshots for pricing, service, tax, provider and route/history evidence. Not every durable JSON shape has a single explicit schema-version convention.

### Risk

Future readers can assume latest object shape and fail on old persisted payloads, or migrations can rewrite historical meaning.

### Required action

- version durable payloads where interpretation can evolve;
- keep readers backward-compatible for supported history;
- include policy/pricing/source version where financially meaningful;
- never normalize old snapshots by destructive rewrite merely for shape consistency.

---

## D-06 — String-based lifecycle values need state-owner governance

**Severity:** Medium  
**State:** TARGET governance  
**Primary owner:** Domain state-machine owners

### Finding

Many lifecycle columns are strings interpreted by PHP enums/services. This is flexible and deployment-friendly, but the database cannot by itself prevent every invalid semantic value.

### Risk

Ad-hoc writes, scripts or future features can introduce states unknown to APIs/jobs/reporting.

### Required action

- state additions require enum/service/API/history/test review;
- preserve DB indexes for actual status/time workloads;
- use application validation and database constraints where safe/valuable;
- do not switch indiscriminately to database enums, which can create deployment rigidity.

---

## D-07 — Provider raw-payload retention/redaction policy incomplete as a cross-provider standard

**Severity:** Medium security/operability  
**State:** TARGET  
**Primary owner:** Integration owners + Security/Operations

### Finding

Payment/refund/carrier/SMS/provider evidence is operationally valuable, but a single cross-provider retention/redaction standard is not established by schema alone.

### Required action

- classify required normalized references versus raw bodies;
- redact credentials/tokens/unnecessary PII before persistence/logging;
- define retention windows and privileged access;
- retain enough evidence for reconciliation/disputes after raw payload expiry.

---

## D-08 — Growth Network persistence is not yet current built truth

**Severity:** Governance guard  
**State:** TARGET / CAP-01 owner  
**Primary owner:** `phase/rosta-cap01-growth-network`

### Finding

Business policy for ROSTA Growth Network is locked, but ARCH-0.3 must not pretend attribution, lead ownership, partner commission ledger and payout schema are already implemented simply because general commission/settlement foundations exist.

### Required action in CAP-01

Design dedicated:

- Growth Partner identity/profile/reference;
- first-qualified-referrer attribution and lock evidence;
- B2C/Roastery/B2B lead/event records;
- append-only partner commission ledger;
- policy version/effective date;
- anti-fraud/reversal evidence;
- payout integration with Financial Truth/Settlement.

Reuse existing Order/delivery/refund/finance truth; do not create a parallel order/payment system.

---

## D-09 — Loyalty/subscription/store-credit future balances must use explicit ledger/contract semantics

**Severity:** Governance guard  
**State:** TARGET, capability-owned

CAP-03 Loyalty, CAP-11 Store Credit, CAP-12 Coffee Subscription, CAP-13 Discovery Subscription and related capabilities are not to be implemented as speculative generic columns in `users` or `orders`.

Owning phases must define lifecycle, audit, expiration/refund/cancellation/financial interaction and idempotency before schema creation.

---

## D-10 — Legacy schema compatibility fields require usage proof before destructive cleanup

**Severity:** Medium release risk  
**State:** General rule

ROSTA has evolved from earlier single-vendor/manual workflows to current multi-vendor/Hub/financial architecture. Nullable legacy references, compatibility links and backfilled fields are expected during evolution.

Required cleanup process:

1. code-search all reads/writes;
2. inspect API/serialized contracts;
3. inspect jobs/scheduler/admin/report usage;
4. verify live-data distribution before production migration;
5. expand/backfill/switch/contract;
6. observe after switch;
7. remove only with a new forward migration.

Absence from the newest UI is not sufficient proof that a historical field can be dropped.

## Acceptance rule

ARCH-0.3 is accepted when these debts are documented and no new architectural ambiguity is introduced. Acceptance does **not** mean every debt is immediately repaired; remediation stays with the owning capability/refactor/security phase and must be evidenced before the relevant production gate.
