# Phase 11 — Payments, refunds, ledger and settlements

## Objective

Build an isolated, provider-agnostic and auditable payment boundary for Rosta without trusting browser callbacks or inventing production credentials.

## Required outputs

- persistent customer-owned payment attempts
- payload-bound idempotent payment requests
- provider redirect host allowlist
- signed provider callback parsing through an adapter
- callback events stored append-only and idempotently
- provider verification as the only source of paid truth
- atomic consistency across payment, order, reservations, stock and stock ledger
- reconciliation records for ambiguous or inconsistent paid responses
- full-refund workflow with provider confirmation
- balanced double-entry marketplace ledger
- explicit platform commission and seller payable accounts
- seller finance summary, ledger and settlement statements
- admin payment, refund, ledger, settlement and reconciliation visibility
- payment and settlement providers disabled until real credentials and legal accounts are configured

## Permanent boundaries

- browser query parameters and redirects never prove payment
- callback receipt alone never marks an order paid
- amount, currency, order ownership and provider transaction identity must match
- paid transition consumes active reservations under row locks
- a successful provider result for a cancelled or expired order opens reconciliation instead of silently changing truth
- ledger transactions and entries are append-only and balanced
- refunds do not restock inventory automatically; physical returns belong to fulfillment operations
- settlement drafts do not transfer money until an approved payout adapter exists

## Exit gate

`payments_and_ledger=ready`
