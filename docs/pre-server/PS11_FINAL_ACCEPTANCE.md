# ROSTA PS11 — Launch Operations & Growth Closure

## Status

**SOURCE ACCEPTANCE CANDIDATE**

This document records the source-level PS11 closure contract. It does not claim a merge, immutable tag, real-server activation, provider activation, or production traffic before those events actually occur.

Final acceptance is sealed only after PR #111 is merged into `integration/rosta-release-candidate` and the `PS11 Final Source Release` workflow creates and verifies the immutable `rosta-pre-server-2026-09-03` source release.

## Identity and lineage

- Previous immutable source release: `rosta-pre-server-2026-09-02`
- Previous accepted source SHA: `8dd13dda3a1e8a9b240112a659586f6eac52cdb3`
- PS11 branch: `phase/rosta-ps11-launch-operations-growth-closure`
- PS11 pull request: `#111`
- Last implementation/checkpoint head before closure documents: `2265aa401547dd430f6d49fa4030d9ab92471c02`
- Final PS11 source identity: the merge commit protected by `rosta-pre-server-2026-09-03`; it must not be guessed or pre-recorded here.

## Accepted source scope

### Growth partner, lead and B2B truth

- Growth partners have explicit lifecycle/terms identity rather than anonymous referral state.
- Growth leads support customer, roastery and `b2b` lead types.
- Lead PII is encrypted at rest through model casts; safe metadata is allowlisted instead of accepting arbitrary sensitive payloads.
- Lead ownership, deduplication, self-referral prevention and attribution locking are enforced server-side.
- Attribution conflicts fail closed rather than silently changing partner ownership.

### Commission financial lifecycle

- Partner commission uses a versioned, published, effective and checksum-protected Partner Commission Policy.
- Commission basis is platform revenue, not client-provided money and not gross merchant money.
- Paid-order accrual is append-only and idempotent by payment source identity.
- Successful refund reversal is a new ledger entry; the original accrual is never mutated.
- Cumulative refund allocation is deterministic and bounded by order financial truth.
- The order Financial Truth contract remains `ps4a-financial-truth-v1`; PS11 validates the authoritative status inside every grouped snapshot instead of weakening or replacing the existing truth format.
- Payment/refund retries cannot create duplicate commission entries because source identity is unique and transaction wiring is idempotent.

### Existing launch operations reused, not duplicated

PS11 intentionally reuses permanent repository contracts that already cover the launch operational surface:

- seller-scoped operations, inventory idempotency and fulfillment state enforcement;
- support inquiry persistence, encrypted PII, admin mutation and audit evidence;
- finance/admin maker-checker and audit/redaction contracts;
- encrypted sensitive storage checks;
- production/staging packaging, readiness, browser, SSR/hydration and infrastructure rehearsals.

No parallel seller, support, finance or privacy subsystem is introduced merely to satisfy a phase label.

### Support SLA truth boundary

The repository contains persisted inquiry lifecycle timestamps/status/assignee/resolution evidence that can support operational latency reporting. **No numeric first-response or resolution SLA is invented in source**, because no approved business SLA target exists in the repository contract. A numeric operating target remains a deployment/business-policy input and must be recorded explicitly when approved rather than silently hard-coded.

This is not represented as a provider/server PASS. Source acceptance proves the support lifecycle and observability boundary only.

## Security and privacy invariants

- no commission amount is accepted from the browser/client;
- no self-referral commission is allowed;
- no arbitrary sensitive lead metadata is persisted;
- growth/support PII remains encrypted at rest;
- audit metadata remains subject to the repository redaction contract;
- payment success is not rolled back merely because a growth-policy calculation fails;
- commission publication is durable/idempotent and does not mutate settled financial truth;
- historical PS9/PS10 release tags remain immutable.

## Required exact-head gates

Before PR #111 may merge, the exact PS11 source head must have successful pull-request runs for every still-applicable permanent workflow:

1. `CI`
2. `Backend CI`
3. `Full-stack Integration CI`
4. `Browser Acceptance CI`
5. `R3 Final Gate`
6. `R4 Staging Package CI`
7. `Production Package CI`
8. `PS8A Frontend Acceptance`
9. `PS8B Backend Finance Acceptance`
10. `PS8C Infrastructure Acceptance`
11. `PS1 Backend Wrapper CI`
12. `PS9 Final Pre-server Freeze`
13. `PS10 Final Source Release`
14. `PS11 Final Source Release`

The final push/release job independently verifies successful exact-head PR workflow evidence before creating the PS11 tag.

## Evidence before closure-doc commit

The implementation checkpoint `2265aa401547dd430f6d49fa4030d9ab92471c02` had all 13 pre-existing exact-head PR workflows completed successfully, including Backend CI #623. That Backend CI executed clean MySQL migrations, strict readiness, Redis contracts, permanent finance/seller/admin/support/privacy audits, the full test suite, PHPStan, Pint and aggregate `composer check`.

This evidence is historical checkpoint evidence only. The closure-doc/workflow commit must pass the complete matrix again on its own exact SHA.

## Release verdict rules

PS11 may be declared **DONE / MERGED / FROZEN** only when all of the following are true:

1. PR #111 is not Draft and has no unresolved blocking review state.
2. Every required exact-head workflow above is successful.
3. PR #111 is merged without rewriting the accepted source history.
4. `rosta-pre-server-2026-09-03` resolves to the accepted merge commit.
5. A non-draft GitHub release exists for that tag with sealed identity/evidence assets.
6. `rosta-pre-server-2026-09-02` still resolves to `8dd13dda3a1e8a9b240112a659586f6eac52cdb3`.

Until then the truthful state is **PS11 IN PROGRESS / SOURCE ACCEPTANCE CANDIDATE**.

## External/runtime boundary

PS11 source closure does **not** claim:

- deployment to the real production VPS;
- DNS changes;
- live payment, SMS, R2, carrier or payout-provider activation;
- a business-approved numeric support SLA;
- real production customer/partner traffic.

Those claims require separate runtime/provider evidence.