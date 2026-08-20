# ROSTA Knowledge-Based Readiness Contract

Status: **Readiness only — not a certification claim**
Version: **1.0**
Date: **2026-08-20**

## Objective

ROSTA will be engineered so that, if and when a future ROSTA company applies for Iranian knowledge-based evaluation, the company can present a coherent technology product, a documented R&D history, demonstrable technical mastery, product evidence, financial separation and IP chain of title.

This contract deliberately avoids depending on a frozen interpretation of current thresholds. Product lists, company categories, revenue thresholds, validity periods, tax benefits and evidence requirements can change. They must be revalidated against the then-current official rules before application or benefit usage.

## Working interpretation of evaluation readiness

A credible candidate should be able to answer all of the following with evidence:

1. **What is the specific product/service under evaluation?**
2. **What non-trivial technical problem does it solve?**
3. **Why is the implementation technically beyond ordinary configuration/CRUD/integration work?**
4. **What design and R&D was performed by the company/team?**
5. **How is technical mastery demonstrated rather than merely purchased or outsourced?**
6. **Is there a working prototype, pilot or commercial product at the maturity level required by the current rules?**
7. **Can results be reproduced through benchmarks, tests or technical evidence?**
8. **Does the applicant legally own or control the relevant IP?**
9. **Can revenue/costs of the evaluated technology be distinguished from ordinary commerce?**
10. **Can customer value and technical impact be evidenced without unsupported marketing claims?**

## ROSTA eligibility posture

### Ordinary marketplace capabilities

The following are commercially important but must not be treated as sufficient evidence of a knowledge-based product by themselves:

- multi-vendor catalog;
- cart and checkout;
- payment integration;
- seller dashboards;
- standard loyalty/referral functionality;
- ordinary shipping-provider integration;
- CMS/blog/SEO;
- generic notifications;
- ordinary admin CRUD;
- standard analytics dashboards;
- standard PWA functionality.

These can supply data, workflows and customers to technology products, but are not automatically the technology product.

### Technology-product candidates

ROSTA currently reserves three candidate tracks:

1. **ROSTA Taste Intelligence**
2. **ROSTA Fulfillment Intelligence**
3. **ROSTA Coffee Traceability & Quality Intelligence**

A candidate becomes application-ready only after passing the product gates in `TECHNOLOGY_PRODUCT_MAP.md` and evidence gates in `EVALUATION_EVIDENCE_CHECKLIST.md`.

## No-AI-washing rule

The presence of machine learning, an LLM API, embeddings, a vector database, recommendation labels, or an `AI` brand name does not by itself establish R&D or technical complexity.

Any AI/ML claim must identify:

- the target problem;
- input/output definition;
- training or decision methodology;
- evaluation dataset/fixtures;
- baseline comparator;
- metric(s);
- measured result;
- failure modes;
- human/automated safety controls;
- model/version identity;
- reproducibility and rollback strategy.

If a deterministic algorithm solves the problem better, ROSTA should use it. Eligibility must follow genuine technical work, not architecture theater.

## Evidence maturity levels

### E0 — Idea

Required:
- problem statement;
- target user/business impact;
- hypothesis;
- known alternatives.

No claim of product readiness.

### E1 — Technical proof

Required:
- architecture/design decision;
- prototype;
- controlled test fixtures;
- initial benchmark;
- known limitations.

### E2 — Reproducible prototype

Required:
- versioned implementation;
- repeatable test/evaluation procedure;
- baseline comparison;
- documented data provenance;
- security/privacy review where applicable.

### E3 — Pilot

Required:
- controlled real-world use;
- pilot cohort/partner identity where disclosure is permitted;
- operational metrics;
- incident/failure log;
- evidence of iteration based on results.

### E4 — Commercial technology product

Required:
- defined product/service SKU or commercial contract scope;
- production release identity;
- support/SLA boundary;
- revenue/cost attribution;
- customer/pilot evidence;
- technical documentation and ownership evidence.

## Application gate

ROSTA must not apply merely because a company has been registered. Application becomes reasonable only when at least one technology-product candidate:

- matches the then-current official eligible product/service framework;
- has reached the then-required maturity level;
- has a complete evidence registry;
- has clean IP ownership/control;
- has financial separation;
- has an identified technical owner/team;
- can survive an evaluator asking for a live demonstration and technical explanation.

## Official-rule revalidation gate

Before any application, counsel/qualified advisor and the ROSTA compliance owner must capture a dated evidence note containing:

- official evaluation regulation version;
- official eligible product/service list version;
- applicable company category;
- required technical criteria;
- required financial/company criteria;
- required application evidence;
- available support/benefit scope;
- tax treatment and exact approved-product scope;
- validity/re-evaluation rules;
- source links/PDF hashes where possible.

This note belongs in the evidence registry and must not be inferred from old blog posts.

## Tax and incentive safety

Knowledge-based status, if obtained, must never be represented internally as `company_tax_free = true`.

Use scoped entitlement records such as:

- legal entity;
- approval/certificate identifier;
- approved product/service;
- effective date;
- expiry/review date;
- benefit type;
- revenue/cost scope;
- legal basis/evidence reference.

Marketplace GMV and seller-owned funds are never reclassified because of innovation status. Marketplace commission, fulfillment, grinding, marketing and technology-product revenue remain separately tagged.

## Governance owner

Until a legal company exists, ROSTA Architecture is custodian of this readiness contract. After company formation, ownership must be assigned to a named executive/technology/compliance role without destroying Git history.
