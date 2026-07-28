# Phase 10 — Transactional cart, quote, order and reservation

Current-state note: this document records the original single-roastery
transactional foundation. R5C supersedes the grouping constraint with one parent
order/payment and one independently controlled sub-order per roastery.

## Objective

Implement the authoritative commerce boundary between the public catalog and payments.

## Required outputs

- strict cart validation against current published products, active variants and available stock
- deterministic grouping by roastery inside one cart, quote and parent order
- authenticated checkout quotes bound to customer, address, payload and expiration
- server-owned shipping, coupon, subtotal, discount and grand-total calculations
- customer-scoped payload-bound idempotent order creation
- deterministic row locking and atomic stock reservations
- immutable product, variant, price, roast-batch, address and shipping snapshots
- order and sub-order lifecycle boundaries
- safe cancellation and reservation release
- reservation expiration recovery scheduled without overlapping
- customer order list/detail APIs
- seller-scoped order queue and admin-scoped order visibility
- concurrency, replay, ownership, stale-price and overselling regression tests

## Permanent constraints

- product and inventory identity remain whole-bean; later R5 phases may snapshot
  grinding only as an order-item service
- browser totals and availability are never trusted
- a quote cannot be used by another customer or more than once
- an idempotency key cannot be reused with a different payload
- reservations never reduce stock-on-hand; they increase stock-reserved atomically
- order cancellation can release only active reservations
- payment status changes are outside this phase

## Exit gate

`transactional_checkout=ready`
