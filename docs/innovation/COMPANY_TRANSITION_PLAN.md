# ROSTA Company Transition Plan

Version: **1.0**
Date: **2026-08-20**

## Purpose

ROSTA may begin operation through an individual business and later move operations to a registered legal company. This document defines a controlled cutover so financial history, IP ownership, contracts, providers, evidence and production operations remain auditable.

This is an architecture/governance plan, not legal or tax advice. Exact transfer/novation/tax requirements must be revalidated with qualified Iranian legal/accounting advisors at cutover.

## Principle

A future ROSTA company is a new legal person. We do not rewrite historic individual transactions as company transactions.

Use a documented transition:

`Individual operating period -> Cutover date -> Company operating period`

## Trigger criteria for company formation

Company formation should be evaluated when one or more of the following becomes material:

- onboarding significant numbers of sellers/partners;
- larger GMV or contractual exposure;
- employees and formal payroll;
- investment/fundraising;
- multi-founder/shareholder governance;
- B2B enterprise contracting;
- substantial Hub/facility liabilities;
- provider/payment requirements;
- technology licensing/commercialization;
- preparation for knowledge-based evaluation;
- need to isolate personal and company liabilities/operations.

No single trigger automatically decides legal timing. Use advisor review.

## Pre-formation preparation

Before registration, maintain:

- clean business-only banking/payment records;
- contract registry;
- seller/partner master data;
- IP asset register;
- domain/brand register;
- repository/release history;
- provider/credential register;
- fixed-asset/equipment register;
- liabilities/receivables/payables;
- tax/compliance status;
- R&D evidence registry.

## Company formation package

At formation establish at minimum:

- legal name/type and articles/object suitable for actual activities;
- shareholders/founders and governance;
- authorized signatories;
- company tax registration;
- company bank account(s);
- company accounting books/system;
- company-owned production/service accounts where possible;
- employment/contractor templates;
- IP assignment/license package;
- privacy/terms/contracts in company identity.

Do not design the corporate object solely around knowledge-based wording; it must accurately cover real commerce, technology, fulfillment and service activities.

## Cutover workstreams

### 1. Financial cutover

Choose an explicit timestamp/date. Reconcile through the final individual operating period:

- gateway collections;
- seller payables;
- refunds;
- disputes/holds;
- partner commissions;
- taxes/VAT where applicable;
- accounts receivable/payable;
- cash/bank;
- equipment/assets.

Create a closing reconciliation for the individual period and opening records for the company period according to advisor-approved accounting treatment.

Orders must carry `legal_entity_id` or equivalent immutable commercial-party identity so post-cutover reporting never relies only on dates.

### 2. Seller/Roastery contracts

Classify each contract:

- expires before cutover;
- assignable/novatable;
- must be re-executed;
- requires counterparty consent.

Do not silently change contractual party in the database. Preserve contract version, effective period and evidence.

### 3. Customer legal terms

Publish a controlled version of Terms/Privacy/Marketplace terms reflecting the new legal operator. Keep historic terms and acceptance records.

Where required, notify customers and obtain updated consent/acceptance for materially changed terms rather than retroactively editing old acceptance.

### 4. Payment/provider migration

Inventory:

- payment gateway/merchant/terminal;
- tax-linked acceptance instruments;
- SMS provider;
- email/domain services;
- carrier accounts;
- Cloudflare/storage/CDN;
- hosting/server accounts;
- analytics/support tools.

For each determine:

`transfer | new company account | dual-run window | not transferable`

Never assume a Merchant ID, tax identity or provider contract transfers automatically.

### 5. IP cutover

Follow `IP_OWNERSHIP_POLICY.md`.

Create signed schedules for:

- source/repositories;
- algorithms/models;
- datasets/rights;
- domains/brand;
- documentation;
- equipment/technical assets where relevant;
- third-party licenses that permit transfer.

Record effective date and consideration/accounting treatment where required.

### 6. Employment/team cutover

Transition contributors to company contracts with:

- role;
- compensation;
- payroll/social insurance/tax obligations as applicable;
- confidentiality;
- IP/work-product terms;
- access control.

Disable obsolete personal/vendor access after transition.

### 7. Infrastructure identity

Production must not depend on the founder's personal identity as the only owner.

Move toward:

- organization-owned GitHub repository/org permissions;
- company billing identity where available;
- MFA/recovery ownership;
- multiple authorized administrators;
- documented secret rotation;
- provider ownership register.

## Dual-run rule

A limited dual-run period may be necessary for providers, but the same transaction must not be accounted as both individual and company revenue.

During dual-run, each transaction/order must resolve to exactly one legal seller/operator identity.

## Database requirements for future implementation

Financial/commercial records should be capable of storing immutable references to:

- operating legal entity;
- seller legal/tax identity snapshot;
- contract/policy version;
- invoice issuer identity;
- payment merchant/terminal identity;
- settlement account identity;
- tax/VAT policy version.

This prevents historical records from changing when current company settings change.

## Knowledge-based transition

After company formation:

1. do not immediately claim eligibility;
2. ensure candidate technology IP is legally held/controlled by company;
3. ensure R&D team and evidence are attributable to company/current chain of title;
4. separate technology-product revenue/costs;
5. revalidate current evaluation criteria/product lists;
6. select candidate and prepare evidence pack;
7. apply when technically ready.

## Cutover acceptance checklist

A company cutover is complete only when:

- individual-period financial reconciliation is closed;
- unresolved seller/customer liabilities are assigned/handled explicitly;
- company tax/bank/payment identities are active as required;
- provider routing is verified;
- seller contracts have valid company-party status;
- customer legal terms identify correct operator;
- IP transfer/license is executed;
- repository/provider access is company-safe;
- production rollback plan exists;
- first company transaction is traceable end-to-end;
- no transaction can be reported under both legal entities.

## Permanent audit artifact

Create a `ROSTA_LEGAL_ENTITY_CUTOVER_<date>.md` evidence record at transition containing the final approved date, identities, reconciliations, provider mapping, IP transfer references, contract migration status and responsible reviewers.
