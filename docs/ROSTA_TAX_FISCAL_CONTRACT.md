# Rosta Tax & Fiscal Contract

Status: **Architecture contract / production gate**  
Scope: marketplace taxation, fiscal invoices, refunds, settlements, and reconciliation  
Applies to: `integration/rosta-release-candidate` descendants

## 1. Business truth

Rosta is operated as a multi-vendor marketplace / commercial intermediary, not as the economic owner of every roastery's merchandise.

The canonical accounting split for an order is therefore:

```text
order gross / GMV
  ├─ seller gross
  ├─ Rosta platform commission / service revenue
  ├─ tax components as legally applicable
  ├─ payment-provider fees
  └─ seller net settlement
```

**Invariant:** GMV MUST NOT be represented as Rosta revenue merely because a payment provider, bank account, or technical checkout is operated by Rosta.

The seller-of-record and invoice responsibility for each taxable supply MUST follow the actual commercial contract and applicable Iranian tax rules. The implementation MUST NOT invent a tax treatment from payment routing alone.

## 2. Current tax-file classification

The current Rosta tax-file activity classification is maintained as the project baseline:

- INTA activity code: `3070160`
- ISIC: `G525100`
- Activity share: `100%`

No code or deployment automation may silently change or reinterpret the registered tax activity. Any future change requires an explicit fiscal review and an architecture update.

No taxpayer identifiers, tax tracking codes, bank-account numbers, IBANs, certificates, private keys, or portal credentials may be committed to this repository.

## 3. Official legal anchors

This contract is based on the currently published Iranian statutory framework, including:

- The Law on Store Terminals and the Taxpayer System (قانون پایانه‌های فروشگاهی و سامانه مؤدیان), including the 1404 amendments: the Taxpayer System assigns a commercial/non-commercial workspace as applicable; each tax memory has a unique identifier; electronic invoices are issued by the seller; information registered by the taxpayer is presumed correct unless proved otherwise.
- The VAT Law of 1400 (قانون مالیات بر ارزش افزوده): the default tax period is three months aligned with Solar Hijri seasons; tax and duties for a period are paid after deduction of eligible input tax credit by the statutory deadline.
- The Taxpayer Facilitation Law and amendments: electronic-invoice obligations apply according to the current statutory rules and thresholds/exceptions in force.

Because rates, thresholds, exemptions, invoice patterns, product/service identifiers, and operational portal rules can change, **no tax rate or exemption is a permanent application constant unless sourced from an effective dated fiscal configuration**.

## 4. Fiscal source of truth

Rosta MUST maintain an immutable/auditable fiscal trail. The minimum logical model is:

```text
Order
  ├─ OrderItem
  ├─ SellerAllocation
  ├─ Payment
  ├─ FiscalDocument
  │    ├─ Original
  │    ├─ Correction
  │    ├─ Return
  │    └─ Cancellation
  ├─ Refund
  ├─ Settlement
  └─ ReconciliationCase
```

A fiscal document is not the same object as a payment or refund.

Required invariants:

1. Original issued fiscal facts are never destructively overwritten to hide a later return/refund.
2. Corrections, returns, and cancellations reference the appropriate earlier fiscal document when required by the active Taxpayer-System contract.
3. Refund execution and fiscal reversal/return are correlated but have independent statuses.
4. Seller allocation snapshots are immutable for historical orders, even if a seller later changes commission terms or settlement IBAN.
5. Monetary arithmetic uses integer minor/base currency units according to the project's money contract; floating point is forbidden.
6. Every external submission is idempotent and stores an immutable request fingerprint plus provider/tax-system correlation identifiers.
7. Unknown or timed-out external results become `requires_review`; they never become fabricated success or failure.

## 5. Taxpayer-System integration boundary

The backend SHALL expose a provider-neutral fiscal boundary. A concrete adapter may only be enabled after the current official integration method is verified for the Rosta taxpayer file.

Suggested domain contract:

```text
FiscalInvoiceProvider
  issueOriginal()
  issueCorrection()
  issueReturn()
  issueCancellation()
  getSubmissionStatus()
  reconcile()
```

Production requirements:

- secrets/certificates/private keys only through protected configuration/secrets management;
- no credential or taxpayer secret in logs;
- idempotent submission;
- durable outbox/retry semantics;
- encrypted sensitive provider payloads at rest where stored;
- explicit mapping between internal document ID and official tax identifiers;
- reconciliation job and administrator review queue;
- fail-closed when the active official contract is unknown or unavailable.

**No undocumented Taxpayer-System API may be fabricated.** If direct technical submission requires an approved intermediary/trusted provider or a different official channel, the adapter must implement that approved route instead.

## 6. VAT configuration

VAT MUST be configuration/data driven, effective-dated, and classified by the actual supplied item/service.

The system MUST NOT assume that:

- every coffee product has the same VAT treatment;
- marketplace commission has the same treatment as the underlying coffee sale;
- shipping has the same treatment as goods or platform service;
- the current general VAT rate is permanent.

Minimum fiscal classification snapshot per order item/service:

```text
fiscal_classification_code
classification_source
classification_version_or_effective_date
vat_treatment
vat_rate_if_applicable
seller_of_record
```

Before first production sale, the exact official product/service identifiers and VAT treatment for roasted coffee, Rosta marketplace commission/service, and shipping must be confirmed against the then-current official Taxpayer-System catalogue/rules.

## 7. Refund and return contract

A refund MUST NOT mutate historical sales truth.

Canonical flow:

```text
return/cancellation request
  -> business approval
  -> inventory consequence
  -> refund request
  -> fiscal return/cancellation/correction as applicable
  -> seller-ledger adjustment
  -> settlement adjustment
  -> reconciliation
```

Partial returns MUST be supported at line/quantity/amount level. The database must preserve the relationship between:

- original order/item;
- original fiscal document;
- return/cancellation document;
- refund attempt/result;
- seller financial adjustment;
- settlement/reconciliation evidence.

## 8. Marketplace settlement contract

Payment routing and accounting ownership are separate concerns.

Rosta may support provider capabilities such as split settlement, but the Financial Truth model remains:

```text
seller gross
- platform commission/contractual charges
+/- approved adjustments
- seller-attributable refunds/returns
= seller payable / settlement amount
```

Provider split settlement MUST NOT remove the internal seller ledger or reconciliation requirements.

Platform collection followed by payout is permitted in code only when the legal/banking/tax structure used by Rosta has been explicitly approved for that model. The application must not assume that an ordinary Rosta commercial bank account is an escrow/intermediary account.

## 9. Production gates

Before enabling fiscal submissions and real marketplace payments, all of the following must be evidenced:

1. Rosta taxpayer file remains active with the intended marketplace/intermediary activity classification.
2. Commercial bank/payment instruments used in production are correctly associated with the tax file as required.
3. Taxpayer-System workspace is active and the current fiscal submission method is confirmed.
4. Any required unique tax-memory identifier/certificate/key setup is completed through the official process.
5. Current official product/service identifiers and VAT treatment are confirmed for the actual Rosta catalogue and services.
6. Seller contract clearly defines seller-of-record, Rosta commission, returns, refunds, settlement, and tax-document responsibilities.
7. Fiscal original/correction/return/cancellation flows pass staging acceptance.
8. Refund and fiscal-return flows are tested together, including partial returns and unknown provider outcomes.
9. Tax, payment, seller-ledger, bank/PSP settlement, and order totals reconcile for the acceptance dataset.
10. Backup/restore proves preservation of fiscal documents, external identifiers, refund links, and audit history.

Until these gates pass, fiscal submission and real fund movement remain fail-closed where the official provider contract is not established.

## 10. Engineering acceptance rules

A future backend phase is rejected if it:

- treats order GMV as Rosta revenue by default;
- deletes or rewrites an issued fiscal event instead of recording its legal successor event;
- couples refund success directly to tax-document success without independent state;
- hard-codes a permanent VAT rate/exemption without an effective-dated source;
- accepts tax/seller allocation values from the client as authoritative;
- permits settlement without auditable seller allocation;
- invents an undocumented tax or payment-provider endpoint;
- cannot reconcile order, fiscal document, payment, refund, and settlement evidence.

This document is the canonical fiscal architecture contract until superseded by a reviewed, dated replacement.
