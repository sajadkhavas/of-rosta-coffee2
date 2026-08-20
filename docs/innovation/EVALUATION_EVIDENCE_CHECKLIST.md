# ROSTA External Evaluation Evidence Checklist

Version: **1.0**  
Date: **2026-08-20**

## Purpose

This checklist is the adversarial gate before ROSTA submits any future application for knowledge-based evaluation, grant, accelerator, innovation-financing program or other technology-status review.

Passing this checklist does not guarantee approval. Its purpose is to prevent premature applications and unsupported claims.

## Gate A — Legal entity

- [ ] Applicant is an eligible registered legal entity under the rules in force.
- [ ] Legal entity identity is consistent across tax, bank, contracts and application.
- [ ] Company object/activities accurately cover the actual technology and commercial activity.
- [ ] Authorized representative/signatory is documented.
- [ ] Required tax returns/financial records are available for the applicable category or any official grace rule is documented.
- [ ] Current official company-category requirements have been revalidated.

## Gate B — Official rules are current

- [ ] Official evaluation regulation version/date captured.
- [ ] Current approved product/service list captured.
- [ ] Candidate maps to an actual eligible scope; mapping is written, not assumed.
- [ ] Current technical-complexity criteria captured.
- [ ] Current technical-mastery/R&D criteria captured.
- [ ] Current maturity/prototype/commercialization requirement captured.
- [ ] Current financial/revenue/headcount thresholds, if any, captured.
- [ ] Current validity/re-evaluation rules captured.
- [ ] Current tax/support scope captured separately from certification criteria.
- [ ] Official sources/PDFs are archived or hashed where practical.

If current official rules cannot be verified, application is blocked.

## Gate C — Exact technology product

- [ ] Candidate has a stable internal product ID and name.
- [ ] Product boundary excludes generic marketplace/CRUD functionality.
- [ ] Problem statement is precise.
- [ ] Technical architecture is documented.
- [ ] Non-trivial technical component is identifiable.
- [ ] Build-vs-buy boundary is explicit.
- [ ] Third-party technology dependencies are disclosed.
- [ ] Product maturity matches current official requirement.
- [ ] A live demonstration can be performed.

## Gate D — Technical mastery

- [ ] Internal team can explain key algorithms/architecture without vendor scripts.
- [ ] Material design decisions have ADRs.
- [ ] R&D experiments show how the solution evolved.
- [ ] Baseline alternatives were evaluated.
- [ ] Source/release SHAs are preserved.
- [ ] Technical owner(s) and contributors are identifiable.
- [ ] Outsourced work is clearly separated from in-house mastery.
- [ ] Critical technology is not merely a thin wrapper around a third-party API unless the ROSTA-owned technical contribution is independently substantial and defensible.

## Gate E — Reproducible evidence

- [ ] Benchmark protocol documented.
- [ ] Baseline version documented.
- [ ] Evaluation dataset/fixture version documented.
- [ ] Metric formulas documented.
- [ ] Results are reproducible or reproducibility limits are explained.
- [ ] Failed/rejected experiments retained.
- [ ] Production validation/pilot evidence exists where required.
- [ ] Known failure modes are documented.
- [ ] Security/privacy review completed where applicable.

## Gate F — IP and licensing

- [ ] Applicant owns or has adequate rights to core source code.
- [ ] Founder-to-company IP transfer/license is executed.
- [ ] Employee/contractor rights are documented.
- [ ] OSS inventory/licenses reviewed.
- [ ] Dataset rights reviewed.
- [ ] External model/provider terms reviewed.
- [ ] No unknown-provenance code/data/assets remain.
- [ ] Domain/brand/product ownership/control is documented where relevant.

Any critical `IP_BLOCKED` item blocks application.

## Gate G — Financial evidence

- [ ] Marketplace GMV is separated from ROSTA revenue.
- [ ] Seller payables are separated from ROSTA revenue.
- [ ] Technology-product revenue is separately identifiable where commercialized.
- [ ] R&D expenditure is traceable by candidate/project.
- [ ] Grants/support are separately classified.
- [ ] Bank/gateway/accounting reconciliation is available.
- [ ] Financial statements/tax returns match the legal entity and period.
- [ ] Any claimed benefit is scoped only to approved activity/product/revenue.

## Gate H — Commercial/pilot evidence

Where applicable:

- [ ] customer/pilot contract or acceptance evidence;
- [ ] invoices/revenue evidence;
- [ ] production usage evidence;
- [ ] support/SLA evidence;
- [ ] customer feedback;
- [ ] before/after technical impact;
- [ ] privacy-safe screenshots/logs/demo data.

## Gate I — ROSTA candidate-specific evidence

### Taste Intelligence

- [ ] frozen deterministic baseline;
- [ ] taste representation/version;
- [ ] cold-start method;
- [ ] ranking metric report;
- [ ] coverage/diversity report;
- [ ] invalid/out-of-stock filtering evidence;
- [ ] explanation fidelity/decision reason evidence;
- [ ] offline improvement over baseline;
- [ ] controlled online/pilot evidence if used.

### Fulfillment Intelligence

- [ ] frozen policy-routing baseline;
- [ ] replay/simulation dataset;
- [ ] objective function/constraints;
- [ ] cost/SLA benchmark;
- [ ] capacity model;
- [ ] failure/fallback behavior;
- [ ] decision explanation/replay evidence;
- [ ] measurable improvement over baseline.

### Traceability & Quality Intelligence

- [ ] trace graph/schema;
- [ ] chain-of-custody integration;
- [ ] trace completeness metric;
- [ ] freshness model/rules evidence;
- [ ] anomaly evaluation where applicable;
- [ ] incident investigation evidence;
- [ ] privacy/minimization review.

## Gate J — Claims review

Before submission/public communication:

- [ ] no claim states ROSTA is knowledge-based before official approval;
- [ ] no claim implies all ROSTA products are approved if only one scope is approved;
- [ ] no claim treats GMV as technology revenue;
- [ ] no tax exemption is assumed globally;
- [ ] AI/ML claims match actual implementation;
- [ ] technical performance numbers cite evidence IDs;
- [ ] external evaluator feedback is archived.

## Final internal verdict

Only one of these verdicts is allowed:

- `NOT_READY` — material evidence/product/company gaps remain.
- `READY_FOR_ADVISOR_REVIEW` — technical pack complete; legal/accounting/current-rule review still required.
- `READY_FOR_APPLICATION` — all current-rule, legal, technical, IP and financial gates have been independently reviewed.
- `APPLICATION_SUBMITTED` — submission identity/date/evidence snapshot recorded.
- `APPROVED_SCOPE_RECORDED` — exact approved scope/effective dates/benefits recorded without overgeneralization.

The reviewer must record name/role, date and immutable evidence snapshot ID.
