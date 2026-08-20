# ROSTA R&D Evidence Policy

Version: **1.0**  
Date: **2026-08-20**

## Goal

Capture R&D evidence while the work happens so ROSTA never has to manufacture a history later. Evidence is for engineering quality first and potential external evaluation second.

## Evidence principles

1. **Prospective, not retrospective.** Record experiments when performed.
2. **Reproducible.** A competent engineer should be able to reproduce the result or understand why reproduction is impossible.
3. **Negative results count.** Failed experiments are preserved.
4. **No marketing inflation.** Evidence records measurements, not adjectives.
5. **Privacy-safe.** Production PII is not copied into research artifacts without an approved purpose and controls.
6. **Traceable to code/release.** Every material experiment references immutable Git/release identities.
7. **Traceable to contributors.** Human authorship, external vendors and licenses are explicit.

## Required evidence record

Each material R&D experiment should have a Markdown record under an appropriate future evidence directory and include:

```yaml
id: RD-YYYY-NNNN
candidate: taste-intelligence | fulfillment-intelligence | traceability-quality | other
status: proposed | running | completed | rejected | superseded
owner: <role-or-team>
started_at: YYYY-MM-DD
completed_at: YYYY-MM-DD | null
baseline_sha: <full git sha>
result_sha: <full git sha> | null
dataset_id: <versioned non-secret identifier> | null
model_or_algorithm_version: <identifier> | null
adr: <path/id> | null
```

Mandatory narrative sections:

- problem;
- hypothesis;
- baseline/alternative;
- test protocol;
- environment/dependencies;
- dataset/fixture provenance;
- metrics and acceptance threshold declared before result review where feasible;
- result;
- interpretation;
- failure modes;
- privacy/security impact;
- licensing/IP notes;
- next decision.

## Benchmark policy

Benchmarks must avoid cherry-picking. Each benchmark must state:

- sample selection method;
- sample size;
- date range where relevant;
- excluded cases and why;
- baseline implementation/version;
- hardware/runtime assumptions if performance-sensitive;
- metric formula;
- random seed/configuration where applicable;
- repeated-run strategy where stochastic;
- confidence/uncertainty where meaningful.

Do not replace technical benchmark evidence with business KPIs such as revenue or conversion.

## Dataset policy

Every dataset/fixture used for R&D must have a registry entry with:

- source;
- lawful/authorized use basis;
- collection date/range;
- schema/version;
- transformation/anonymization steps;
- access classification;
- retention rule;
- known biases/coverage limitations;
- content hash or reproducible generation procedure where feasible.

### Data classes

- `PUBLIC_OR_SYNTHETIC`
- `INTERNAL_NON_PERSONAL`
- `PSEUDONYMIZED_CUSTOMER`
- `RESTRICTED_PERSONAL`
- `THIRD_PARTY_LICENSED`

`RESTRICTED_PERSONAL` data must not be committed to Git and requires explicit access controls and purpose review.

## Model/algorithm registry

For any learned model or materially versioned ranking/optimization algorithm, capture:

- semantic version or immutable ID;
- source code SHA;
- training/config parameters;
- dataset IDs;
- evaluation report IDs;
- approval date;
- production start/end dates;
- rollback predecessor;
- known limitations;
- owner.

External foundation models/APIs must additionally capture provider, model identifier, terms/licensing dependency, data-sharing boundary and fallback strategy. External model usage must never be represented as ROSTA-owned model IP.

## Architecture Decision Records

An ADR is required when a technology-product candidate makes a material decision involving:

- algorithm family;
- data representation;
- optimization objective;
- model provider;
- privacy-sensitive data use;
- buy-vs-build decision;
- major evaluation metric;
- online experimentation method;
- irreversible vendor dependency.

ADR must record considered alternatives and consequences.

## Production evidence

For candidate technology products, production releases should retain:

- release SHA/tag;
- feature/decision-engine version;
- schema/migration version where applicable;
- evaluation report used for promotion;
- deployment date;
- rollback target;
- post-deploy technical metrics;
- incidents/regressions.

## Customer/pilot evidence

Pilot evidence may include:

- signed pilot scope;
- partner/customer cohort definition;
- problem before deployment;
- technical acceptance criteria;
- measured result;
- feedback;
- invoices/contracts when commercial;
- privacy-safe screenshots/logs.

Do not expose confidential partner/customer information in a public repository. Keep references/hashes and store restricted evidence in an access-controlled evidence vault.

## Evidence integrity

Evidence should be append-oriented. Corrections must preserve what changed, by whom and why. Do not rewrite failed results to appear successful.

Recommended linkage:

`Problem -> ADR -> Experiment -> Commit -> Benchmark -> Release -> Pilot -> Commercial Evidence`

## Minimum evidence cadence

- every R&D experiment: one evidence record;
- every promoted algorithm/model: evaluation report;
- every material architecture change: ADR;
- every production release of a technology candidate: release evidence;
- quarterly: evidence completeness review;
- before external evaluation: independent evidence audit.
