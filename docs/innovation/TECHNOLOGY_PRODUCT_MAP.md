# ROSTA Technology Product Map

Version: **1.0**  
Date: **2026-08-20**

## Purpose

This document separates ROSTA's commerce platform from independently identifiable technology-product candidates. The separation protects architecture truth, evaluation credibility, accounting clarity and future commercialization options.

## Layer 1 — Commerce & Operations Platform

These capabilities are essential to ROSTA but are not designated technology products by this contract:

- multi-roastery marketplace;
- master order and seller sub-orders;
- checkout/payment orchestration;
- seller organization and permissions;
- inventory/batch/order administration;
- direct fulfillment workflows;
- ROSTA Hub operations;
- grinding as an order-item service;
- packaging and partner inserts;
- shipment/tracking integrations;
- support, reviews and CMS;
- standard loyalty/referral/promotion capabilities.

They may emit data to or consume decisions from technology products through versioned internal APIs/events.

---

# Candidate A — ROSTA Taste Intelligence

## Problem

Coffee preference is multi-dimensional and sparse. A user's stated preferences, purchase history and review behavior do not map trivially to product attributes. The system should improve recommendation quality while respecting inventory, seller availability, freshness, price and explainability constraints.

## Current foundation

ROSTA already has quiz/profile and deterministic recommendation foundations. That is a product capability, but not sufficient by itself to claim a distinct advanced technology product.

## Intended technology boundary

Inputs may include:

- explicit Taste Profile/quiz responses;
- product sensory attributes;
- origin/process/roast attributes;
- brew-method context;
- purchase history;
- verified ratings/reviews;
- reorder/skip behavior;
- availability/freshness constraints;
- budget and package-size preferences.

Outputs may include:

- ranked candidate list;
- calibrated affinity score;
- reason/explanation codes;
- uncertainty/confidence signal;
- exploration/diversity decision;
- cold-start fallback;
- policy/safety exclusions.

## R&D questions

- How should sparse/subjective taste signals be normalized?
- What representation yields better matching than hand-written rules?
- How should cold start be handled for new customers and new coffees?
- How should ranking balance affinity, diversity, inventory truth and freshness?
- Can recommendations improve from post-purchase feedback without creating popularity lock-in?
- Which explanations are faithful to the ranking decision?

## Required evaluation metrics

At minimum maintain versioned definitions for:

- offline ranking metric(s) such as NDCG/HitRate/Recall where appropriate;
- calibration/affinity error where a target score exists;
- cold-start quality;
- coverage/diversity;
- invalid/out-of-stock recommendation rate (target: effectively zero after filtering);
- online conversion/reorder/acceptance metrics only as secondary business evidence.

A/B results alone do not prove technical novelty; they complement technical evaluation.

## Commercialization path

Potential scopes:

- embedded ROSTA recommendation service;
- B2B recommendation API for roasteries/coffee retailers;
- taste-profile intelligence module;
- subscription/discovery curation engine.

Any commercial SKU requires explicit contract scope and revenue tagging.

---

# Candidate B — ROSTA Fulfillment Intelligence

## Problem

A multi-roastery order may require direct shipping, ROSTA Hub processing, grinding, packaging, multiple carrier legs, local delivery, SLA constraints and limited processing capacity. Naive routing can create unnecessary cost, lateness or operational overload.

## Intended technology boundary

Decision inputs may include:

- seller origin and availability;
- customer destination/service zone;
- order decomposition;
- grinding/packaging requirements;
- ROSTA Hub eligibility;
- hub capacity and work queue;
- carrier service/rate/ETA/reliability data;
- cut-off times;
- SLA deadlines;
- shipment-leg constraints;
- local fleet availability;
- expected processing time;
- risk/retry policy.

Decision outputs may include:

- fulfillment mode per seller sub-order;
- shipment-leg plan;
- hub assignment;
- processing priority;
- dispatch window;
- carrier/local-delivery selection;
- cost/time/risk score;
- fallback plan.

## R&D questions

- How should cost, SLA risk and capacity be optimized simultaneously?
- When is consolidation through a hub superior to direct delivery?
- How should uncertain carrier ETA/reliability be incorporated?
- How should routing degrade safely when external rate/availability APIs fail?
- How can cut-off/batching policies reduce cost without unacceptable freshness delay?
- When future regional hubs exist, how should assignment scale without combinatorial explosion?

## Required evaluation metrics

Maintain controlled simulations/replays with:

- cost per delivered order;
- SLA violation rate;
- predicted vs actual delivery error;
- hub utilization/queue delay;
- number of shipment legs;
- reroute/fallback rate;
- failed-plan rate;
- computational latency.

Compare every advanced optimizer against a documented simple baseline policy.

## Commercialization path

Potential scopes:

- internal ROSTA routing engine;
- B2B fulfillment optimization service;
- hub planning/capacity decision service for specialty commerce.

---

# Candidate C — ROSTA Coffee Traceability & Quality Intelligence

## Problem

Coffee quality and customer experience depend on product identity, roast batch, freshness, chain of custody, processing/grinding, packaging, fulfillment and customer feedback. These signals are usually fragmented and hard to turn into actionable quality decisions.

## Intended technology boundary

Traceability graph may connect:

`Roastery -> Product -> Roast Batch -> Inventory Movement -> Order Item -> Grinding/QC Work -> Package -> Shipment Leg -> Delivery -> Verified Feedback`

Technology outputs may include:

- immutable/provenanced batch trace;
- freshness score/model;
- anomaly/risk signal;
- quality issue clustering;
- batch/seller/service performance analysis;
- trace evidence for incident investigation;
- recommendation features based on verified batch/freshness truth.

## R&D questions

- How can incomplete operational signals be reconciled into reliable traceability?
- How should freshness be modeled by roast date, package state, handling and elapsed time?
- Can anomalous complaint/review patterns be identified without overreacting to small samples?
- How can chain-of-custody evidence remain useful while minimizing personal data exposure?

## Required evaluation metrics

- trace completeness;
- orphan/unreconciled event rate;
- freshness prediction error where labels exist;
- anomaly precision/recall on labelled incidents where feasible;
- incident investigation time;
- false-positive operational alert rate.

---

# Candidate promotion gate

A capability may be promoted from `platform feature` to `technology-product candidate` only through an ADR/evidence decision showing:

1. a distinct technical problem;
2. non-trivial implementation beyond ordinary integration/configuration;
3. R&D questions and baseline alternatives;
4. measurable technical metrics;
5. a versioned implementation boundary;
6. identifiable IP ownership;
7. a plausible independent product/service boundary;
8. financial tagging if commercialization begins.

## Candidate retirement

A candidate may be retired if:

- complexity is not defensible;
- a commodity solution fully replaces the R&D need;
- evidence does not show improvement over the baseline;
- maintenance cost exceeds strategic value;
- official evaluation scope no longer matches.

Retirement is not failure. Evidence must remain preserved rather than rewritten.
