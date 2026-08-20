# ROSTA Innovation & Knowledge-Based Readiness

Status: **Architecture / evidence readiness contract**  
Version: **1.0**  
Baseline: `integration/rosta-release-candidate @ a96d0e05478bc2c61852fdf91bb46da1782030df`  
Phase: **PS0.6 — Entrepreneurship & Knowledge-Based Readiness**  
Last reviewed: **2026-08-20**

## Purpose

This directory establishes the evidence, governance, IP, product-boundary, accounting-boundary, and company-transition foundations required for ROSTA to grow from an individual-operated startup into a technology company without reconstructing its history later.

This phase does **not** declare ROSTA, any future company, or any ROSTA feature to be knowledge-based. Certification/eligibility is an external legal and technical determination under the rules in force at the time of application.

## Core rule

ROSTA must be able to prove the difference between:

1. ordinary marketplace/commerce operations;
2. genuine technology products developed through R&D;
3. operational software used internally by ROSTA; and
4. independently identifiable technology products/services that may later be evaluated, licensed, or sold.

No feature may be relabeled as AI, R&D, or knowledge-based merely for incentives.

## Documents

- `KNOWLEDGE_BASED_READINESS.md` — readiness rules, evaluation gates, no-overclaim policy.
- `TECHNOLOGY_PRODUCT_MAP.md` — separates commerce from technology-product candidates.
- `RD_EVIDENCE_POLICY.md` — permanent evidence capture from idea to production.
- `IP_OWNERSHIP_POLICY.md` — code/data/model/documentation ownership and future company assignment.
- `INNOVATION_ROADMAP.md` — staged R&D program and measurable technical milestones.
- `COMPANY_TRANSITION_PLAN.md` — individual-to-company cutover without losing chain of title or financial history.
- `ENTREPRENEURSHIP_METRICS.md` — employment, R&D, technology and commercial impact evidence.
- `EVALUATION_EVIDENCE_CHECKLIST.md` — application-readiness evidence pack and revalidation gates.
- `evidence/README.md` — evidence registry format.

## Non-negotiable boundaries

### Certification boundary

- `knowledge_based_status = false/unknown` until an authorized evaluation confirms otherwise.
- Marketing, contracts, investor material, invoices and UI must not claim official knowledge-based status before certification.
- Current legal thresholds, product lists, classifications and benefits must be revalidated from official sources at application time.

### Financial boundary

Financial Truth must keep at least these classes distinguishable:

- marketplace GMV;
- seller-owned amounts / seller payable;
- marketplace commission revenue;
- fulfillment revenue;
- grinding revenue;
- packaging/logistics revenue where applicable;
- marketing/partner revenue;
- technology-product revenue;
- grants/R&D support;
- R&D expense and capitalized development only where accounting policy permits;
- VAT/tax liabilities;
- refunds/reversals.

Knowledge-based incentives must never be assumed to apply to all ROSTA revenue. Any approved benefit must be mapped only to the exact approved company/product/revenue scope.

### Evidence boundary

Git commits alone are useful but insufficient. A defensible R&D record combines:

- problem statement;
- hypothesis;
- design/ADR;
- implementation identity (commit/release);
- experiment dataset or fixture identity;
- benchmark protocol;
- baseline and result;
- failures and rejected approaches;
- test/evaluation artifact;
- author/contributor identity;
- date;
- customer/pilot evidence where applicable;
- IP/licensing provenance.

### Privacy boundary

R&D does not override customer privacy. Datasets must be purpose-limited, minimized, access-controlled and preferably anonymized/pseudonymized for research. Raw production PII must not be copied into ad-hoc notebooks or repositories.

### Company boundary

The current individual business history and a future legal company are different legal/accounting periods. Transition must use explicit IP assignment/license, contract novation/re-execution where required, provider/account migration, opening balances and a documented cutover date. Historic individual transactions are not rewritten as company transactions.

## PS0.6 Definition of Done

PS0.6 is complete when:

- this document set is committed on a dedicated branch;
- commerce and technology-product boundaries are explicit;
- R&D evidence can be captured prospectively;
- IP chain-of-title requirements are documented;
- future company transition has a controlled cutover plan;
- tax/revenue tagging does not presume incentives;
- no source-code or migration changes are introduced by this phase;
- a PR targets `integration/rosta-release-candidate` and remains unmerged pending central review.
