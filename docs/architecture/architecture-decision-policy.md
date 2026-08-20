# Architecture Decision Policy

Status: ARCH-0.2

## 1. Purpose

ROSTA architecture evolves through explicit evidence and reviewed decisions. This policy prevents undocumented infrastructure growth, provider lock-in and silent divergence between business truth, code and deployment.

## 2. When an ADR is required

Create or update an Architecture Decision Record when a change materially affects one or more of:

- runtime/deployment topology;
- data ownership/store/consistency model;
- authentication/authorization/trust boundary;
- financial truth or idempotency strategy;
- provider abstraction or external integration contract;
- queue/outbox/event delivery semantics;
- domain boundary or module extraction;
- public API compatibility/versioning;
- media/storage lifecycle;
- backup/RPO/RTO or disaster-recovery model;
- a non-trivial new dependency/platform service.

Routine implementation detail inside an accepted boundary does not need a new ADR.

## 3. ADR minimum fields

Every material ADR records:

```text
Title
Status: proposed | accepted | superseded | rejected
Date
Baseline / related Git SHA
Context / problem
Business constraints
Options considered
Decision
Consequences / trade-offs
Security/privacy impact
Financial/data consistency impact
Operational/deployment impact
Rollback/reversibility
Evidence/benchmark if relevant
Related PRs/docs
```

## 4. Evidence before complexity

A proposal to add a microservice, message broker, search cluster, extra database, Kubernetes-like orchestration or another always-on service must state the measured problem that the current architecture cannot reasonably solve.

"Best practice", expected future scale or novelty is not enough by itself.

Prefer:

1. optimize current code/query/index/config;
2. isolate worker/workload;
3. add bounded cache/index/provider service;
4. horizontally scale existing runtime;
5. extract a service only when ownership/failure/scale evidence justifies it.

## 5. Business-contract compatibility

An ADR cannot silently change accepted business truth.

Examples requiring business-contract review first or in the same governed change:

- turning grinding into a product variant;
- allowing seller post-payment rejection;
- treating GMV as platform revenue;
- making one partner/carrier/provider a mandatory Core concept;
- removing Direct or ROSTA Fulfillment capability rather than changing launch policy;
- redefining Growth Network tiers/attribution/payout rules.

## 6. Built / Target / Proposed discipline

Architecture PRs must distinguish:

- what exists in the baseline;
- what the accepted target requires;
- what is only a proposal;
- what is blocked by external/provider/legal validation.

Documentation may describe an interface target without creating fake implementation acceptance.

## 7. Provider decisions

Before adopting a provider-specific feature:

- verify official/current provider capability;
- define disabled/failure/manual fallback;
- keep provider data shape at adapter edge;
- define secret/configuration requirements;
- define idempotency/webhook/reconciliation behavior;
- document exit/replacement path.

Never implement fictional automatic refund/payout/carrier behavior because it would make a diagram look complete.

## 8. Data decisions

Any new durable store/entity must name its authoritative owner and retention/backup behavior.

Avoid duplicate writable truth. Read models/caches/indexes must identify their source and rebuild/reconciliation strategy.

## 9. Security/privacy review

Architecture changes crossing a trust/data boundary must state:

- actor/authentication;
- authorization scope;
- minimum data fields;
- secret handling;
- audit/logging;
- abuse/rate-limit concerns;
- retention/deletion impact.

## 10. Financial review

Any change affecting price, discount, tax, commission, payout, refund, credit, partner reward or seller settlement must identify the Financial Truth owner, money units, idempotency, ledger/allocation impact and reversal/reconciliation path.

Browser-only or analytics-only financial logic cannot become authority.

## 11. Production review

A decision is not complete if it cannot be deployed/recovered safely.

State:

- required environment/config changes;
- migration compatibility;
- health/observability signals;
- failure mode;
- rollout/rollback;
- backup/recovery impact;
- provider/external gates.

## 12. Acceptance workflow

Architecture changes use a dedicated branch/PR from an exact accepted Integration SHA.

Required evidence before central acceptance:

- exact baseline/head;
- scoped diff;
- links to affected business/technical contracts;
- CI/static/test gates appropriate to the change;
- no unrelated code;
- explicit unresolved blockers.

Published history is corrected fix-forward; no rebase/amend/squash/force-push without explicit central permission.

## 13. Superseding decisions

Do not delete history to make the present look inevitable. Mark old decisions `superseded`, link the replacement and preserve why the earlier trade-off was made.
