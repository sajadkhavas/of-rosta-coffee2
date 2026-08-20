# ROSTA Innovation Evidence Registry

Version: **1.0**
Date: **2026-08-20**

## Purpose

This directory is the repository-side index for non-secret R&D and evaluation evidence.

Do **not** commit customer PII, credentials, confidential contracts, licensed datasets, raw production exports, payment information or restricted evidence here. Restricted artifacts belong in an access-controlled evidence vault; Git stores only safe metadata, hashes and references.

## Recommended structure

```text
docs/innovation/evidence/
├── README.md
├── experiments/
├── benchmarks/
├── datasets/
├── models/
├── pilots/
├── evaluations/
└── cutovers/
```

Directories are created when real evidence exists; empty placeholder files are not required.

## Evidence IDs

Use stable IDs:

- `RD-YYYY-NNNN` — experiment;
- `BM-YYYY-NNNN` — benchmark/evaluation;
- `DS-YYYY-NNNN` — dataset/fixture registry entry;
- `ALG-YYYY-NNNN` — algorithm/model release record;
- `PILOT-YYYY-NNNN` — pilot;
- `EVAL-YYYY-NNNN` — external/internal evaluation snapshot;
- `CUTOVER-YYYY-NNNN` — legal/company/provider cutover evidence.

IDs are never reused.

## Experiment file template

```markdown
# RD-YYYY-NNNN — <Title>

Status: proposed | running | completed | rejected | superseded
Candidate: taste-intelligence | fulfillment-intelligence | traceability-quality | other
Owner: <role/team>
Started: YYYY-MM-DD
Completed: YYYY-MM-DD | null
Baseline SHA: <full SHA>
Result SHA: <full SHA | null>
ADR: <id/path | null>
Dataset IDs: <list>
Benchmark IDs: <list>

## Problem

## Hypothesis

## Baseline / alternatives

## Protocol

## Pre-declared metrics / thresholds

## Results

## Interpretation

## Failed cases / limitations

## Privacy / security

## IP / licensing

## Decision / next step
```

## Dataset registry template

A dataset record stores metadata only unless the dataset is intentionally public/synthetic and safe for Git.

```markdown
# DS-YYYY-NNNN — <Name>

Class: PUBLIC_OR_SYNTHETIC | INTERNAL_NON_PERSONAL | PSEUDONYMIZED_CUSTOMER | RESTRICTED_PERSONAL | THIRD_PARTY_LICENSED
Owner/Custodian: <role>
Source: <description>
Schema version: <version>
Created/range: <date/range>
Vault/reference: <restricted reference if applicable>
Hash: <hash if appropriate>
Retention: <policy>

## Purpose
## Collection/provenance
## Transformations
## Access controls
## Bias/coverage limitations
## License/permission basis
```

## Benchmark template

```markdown
# BM-YYYY-NNNN — <Title>

Candidate: <candidate>
Implementation SHA: <full SHA>
Baseline SHA/version: <id>
Dataset IDs: <list>
Environment: <reproducible description>

## Metric definitions
## Selection/exclusion rules
## Protocol
## Results
## Variance/uncertainty
## Failure cases
## Conclusion
```

## Algorithm/model release template

```markdown
# ALG-YYYY-NNNN — <Version>

Candidate: <candidate>
Source SHA: <full SHA>
Dataset IDs: <list>
Evaluation IDs: <list>
Predecessor: <id | null>
Approved for: research | shadow | pilot | production
Approved at: <date>
Owner: <role>

## Method/configuration
## Known limitations
## Safety/fallback
## Rollback target
## Provider/license dependencies
```

## Evidence review

Quarterly review checks:

- missing experiment records;
- unreferenced models/algorithms;
- undocumented datasets;
- broken hashes/vault references;
- experiments without baselines;
- performance claims without benchmarks;
- unresolved privacy/IP flags;
- contributor/ownership gaps.

Before external evaluation, create an immutable evidence snapshot/index listing all records submitted or demonstrated and the exact source/release SHAs.
