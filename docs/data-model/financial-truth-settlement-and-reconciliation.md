# Financial Truth, Settlement and Reconciliation Model

Status: ARCH-0.3 canonical financial-data contract

## 1. Separation of financial concepts

ROSTA must persist and report distinct financial concepts rather than treating the customer payment total as platform revenue.

At minimum:

```text
GMV / customer transaction value
  != ROSTA recognized revenue
  != seller payable
  != carrier/pass-through amount
  != tax liability/collection
  != refundable amount
```

Financial Truth services/policies classify component ownership and recognition. UIs and reports must consume those persisted/classified values rather than recompute them from current catalog prices.

## 2. Integer money

Financial amounts are stored as integers in the canonical currency representation used by the backend. Floating-point arithmetic is prohibited for authoritative money math.

Every durable financial record that can outlive current configuration should carry enough currency/version/source context to be audited later.

## 3. PaymentAttempt

`payment_attempts` is the provider-facing payment aggregate. It records request/attempt state and provider evidence such as authority/reference/trace identifiers and callback/verification payload/timestamps according to the current provider implementation.

Rules:

- payment initiation must be idempotent/recoverable;
- callback receipt is not equivalent to payment success;
- provider verification establishes provider success truth;
- concurrent/duplicate callbacks must not double-pay or duplicate order transitions;
- provider payload is evidence, not the source of product/order amount truth;
- a verified attempt can transition the Order to Paid only through the payment orchestration rules.

## 4. RefundAttempt

Refunds are a separate financial workflow. `refund_attempts` records the requested amount/reason/provider/manual evidence and its lifecycle.

Refund policy/eligibility, provider capability and accounting result are distinct concerns. A cancelled shipment/order is not automatically a successful refund, and a refund result must not be inferred from a fulfillment status.

Where provider automation is unavailable, production-safe manual refund evidence is valid when governed by dual-control/idempotency/reconciliation policy. Never fabricate a provider refund API.

## 5. Settlement allocations

`settlement_allocations` is the line-level ownership/classification foundation. The schema supports references to Order, Sub-order, Order Item Service and Shipment Leg plus fields such as:

- allocation type;
- owner type / owner reference;
- held/eligible/scheduled/paid/reversed-style lifecycle state;
- gross, discount, tax and net amounts;
- currency;
- tax code;
- pricing version;
- source reference;
- unique idempotency key;
- eligibility/schedule/payment/reversal timestamps;
- metadata/evidence.

Allocations make the financial owner of each component explicit. They are not merely calculated report rows.

## 6. Seller payable

Seller payable is derived from seller-owned eligible allocations under the versioned financial/commission policy. It must exclude ROSTA-owned service allocations and other non-seller components.

A seller payout must never be computed by simply applying one percentage to the Master Order total when the Order contains shipping, Hub service, grinding, discounts, taxes, multiple sellers or reversals.

## 7. ROSTA revenue

Recognized ROSTA revenue may include correctly classified commission/service/marketing/technology revenue according to current policy. Passing funds through a ROSTA payment account does not make them ROSTA revenue.

Reports must preserve the distinction among:

- gross marketplace value;
- seller-owned amount/payable;
- ROSTA-owned recognized amount;
- carrier/pass-through allocation;
- tax allocation;
- discounts/promotion funding source;
- refunds/reversals.

## 8. Commission policy versioning

Commission policy/rule structures provide a versionable input to Financial Truth. Historical settlement must retain the policy/pricing version that was applied at transaction time.

Changing future commission policy must not retroactively rewrite prior allocations unless an explicit audited adjustment/reversal is created.

## 9. Tax lines

Tax calculation is explicitly persisted through order/allocation tax lines and calculation snapshots. Tax/legal classification is external/versioned policy and must not be hard-coded as an immutable marketplace fact.

Rules:

- tax components are explicit;
- calculation version/source must be auditable;
- current tax configuration cannot rewrite historical tax facts;
- legal/VAT classification changes use forward policy/migration changes and reconciliation when required.

## 10. Settlement lifecycle

Settlement services operate only on eligible allocations and persist batch/item/payout evidence. Conceptually:

```text
held allocation
 -> eligible after policy/evidence
 -> scheduled/batched
 -> paid
```

Exceptional paths include hold, reversal, reconciliation or audited adjustment according to policy.

Settlement release may depend on delivery/return/dispute evidence but remains a finance-owned transition.

## 11. Payout boundary

A bank/provider payout integration, when available, belongs behind a provider boundary. If no documented/usable production payout API exists, the canonical model must support manual production-safe execution with:

- dual control where required;
- idempotency/source reference;
- batch/item evidence;
- operator/audit record;
- reconciliation against bank/provider evidence.

Automation must never be simulated by merely marking a row paid.

## 12. Reconciliation

Reconciliation compares internal financial truth with external provider/bank/evidence truth. `FinancialReconciliationCase` and reconciliation services exist to make mismatches explicit instead of silently patching values.

A reconciliation case should preserve:

- subject/reference;
- expected internal amount/state;
- observed external evidence;
- mismatch category;
- investigation/resolution status;
- authorized adjustment/reversal references;
- actor/audit timestamps.

Direct SQL repair of financial truth is prohibited as a normal operating procedure.

## 13. Growth and future ledgers

ROSTA Growth Network requires an append-only commission ledger in its owning capability phase. ARCH-0.3 does not claim that Growth attribution/partner commission ledger is already implemented.

When CAP-01 is implemented, it must reuse Financial Truth principles:

- qualifying event linked to delivered/non-refunded business truth;
- Pending -> Approved -> Available -> Paid with Reversed/Rejected where applicable;
- policy version/effective date;
- immutable/audited adjustments;
- payout/reconciliation evidence;
- no direct mutation of historical earned amounts without a compensating record.

The same rule applies to future Loyalty/store-credit/gift-card/subscription balances: they need dedicated owner/ledger semantics rather than a generic mutable `balance` with no audit trail.

## 14. Financial write ownership

Only finance/payment/refund/settlement services may authoritatively mutate financial lifecycle records. Seller/admin/frontend surfaces submit commands; they do not write payout, revenue or refund truth directly.

## 15. Rebuild and reporting rule

Operational reports may aggregate persisted financial records, but if a report cannot explain each amount back to an Order/component/allocation/policy/source reference, it is not suitable as accounting/settlement truth.
