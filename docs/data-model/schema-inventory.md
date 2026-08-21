# Schema Inventory

Status: ARCH-0.3 audited inventory
Baseline: `f7e8a31cb0453fb2bff4eda1e4c2f07c6757fa5f`

## Scope

This inventory groups the durable structures that carry current ROSTA business truth. Exact columns, indexes and foreign-key actions remain defined by the migrations. The purpose here is to make ownership and semantics explicit so future phases do not create parallel truth.

## 1. Identity and access

Primary persisted concepts include:

- `users` — customer/operator identity and authentication-facing account record.
- addresses / customer delivery-address records — reusable address truth for the account; order history must use snapshots rather than rereading a mutable address.
- auth/session and authorization support records.
- OTP challenge/state records.
- audit/security records such as authentication/session/audit evidence.
- seller organization, membership/invitation/role structures added by the seller-organization migrations.

Primary anchors:

- `2026_07_21_000001_create_users_table.php`
- `2026_07_21_010001_create_identity_access_tables.php`
- `2026_08_14_000001_create_seller_organization_tables.php`
- `backend/app/Models/User.php`
- `backend/app/Models/Address.php`
- `backend/app/Models/AuthSession.php`
- `backend/app/Models/OtpChallenge.php`

Boundary: identity records are not a license for seller/partner-wide customer export. Seller scope is tied to the seller organization and its own operational data.

## 2. Catalog, roasteries and inventory

Core structures:

- `roasteries`
- `origins`
- `products`
- `product_variants`
- `inventory_stocks`
- `inventory_movements`
- inventory restock/reservation supporting records
- roastery schedules/closures and seller availability structures
- product packaging/grinding capability structures introduced by later marketplace migrations

Key invariants:

- `product_variants` represent sellable coffee weight/SKU identity, **not grind choice**.
- `inventory_stocks` holds current stock counters; `inventory_movements` is operational evidence/history around changes.
- inventory reservations bind checkout intent to a variant without creating a second product identity.
- roastery/product relationships are seller-scoped and foreign-key protected.

Primary anchors:

- `2026_07_21_020001_create_catalog_inventory_tables.php`
- marketplace/grinding/packaging follow-up migrations
- `Product`, `ProductVariant`, `InventoryReservation`, `InventoryRestock`, `Origin` models
- `Services/Catalog/StockService.php`

## 3. Checkout quote

Core structures:

- `checkout_quotes` — temporary customer quote root.
- `checkout_quote_groups` — one quote group per Roastery in a multi-vendor cart.
- `checkout_quote_items`
- `checkout_quote_item_services` — grinding/other order-item services represented independently from the coffee variant.
- `inventory_reservations`
- `coupons` / redemption structures where applicable.

Quote data stores explicit money components and snapshots. A quote is temporary and versioned/expiring; it is not the durable customer purchase record.

The multi-roastery migration makes quote-level seller identity nullable/aggregate and moves seller grouping into `checkout_quote_groups`, preventing a single-vendor field from becoming the authoritative multi-vendor relationship.

Primary anchors:

- `2026_07_21_030001_create_transactional_checkout_tables.php`
- `2026_07_25_230001_create_r5b_marketplace_schema.php`
- `CheckoutQuote`, `CheckoutQuoteGroup`, `CheckoutQuoteItem`, `CheckoutQuoteItemService` models
- `Services/Checkout/QuoteService.php`

## 4. Master Order and seller Sub-orders

Core structures:

- `orders` — customer-facing Master Order aggregate root.
- `order_items`
- `order_item_services`
- `sub_orders` — seller-scoped operational/commercial partitions under one Master Order.
- `sub_order_items` — each order item maps to its seller Sub-order.
- `order_tax_lines`
- `order_events`
- order/sub-order status history and internal-note structures.
- `order_idempotency_keys`

The durable shape is:

```text
orders (Master Order)
  ├─ order_items
  │    └─ order_item_services
  ├─ sub_orders (1..n, grouped by Roastery)
  │    └─ sub_order_items
  ├─ order_tax_lines
  ├─ order_events / histories / notes
  └─ payment / refund / shipment / settlement references
```

Key invariants:

- Master Order is the customer aggregate; `sub_orders` carry seller-scoped execution truth.
- one failed/incident-affected Sub-order must not silently mutate healthy sibling Sub-orders.
- item/product/pricing/address snapshots preserve historical purchase truth.
- `address_snapshot` on the Order model is encrypted as an array; mutable address records must not rewrite it.

Primary anchors:

- `2026_07_21_030001_create_transactional_checkout_tables.php`
- `2026_07_25_230001_create_r5b_marketplace_schema.php`
- `Order`, `OrderItem`, `OrderItemService`, `SubOrder`, `SubOrderItem`, `OrderEvent`, `OrderTaxLine`, `OrderIdempotencyKey` models
- `Services/Checkout/OrderService.php`

## 5. Grinding and order-item services

Core structures introduced by the multi-vendor/service model include:

- `grinding_profiles`
- `roastery_grinding_capabilities`
- `roastery_grinding_profile`
- `checkout_quote_item_services`
- `order_item_services`

`order_item_services` carries service type, provider type, optional provider Roastery, optional grinding profile, service state, money components and immutable pricing/service snapshots.

This is the canonical representation for grind selection. No future migration may add grind size as a stock-bearing coffee variant without an explicit architecture reversal.

## 6. Fulfillment, shipments and chain of custody

Persisted families include:

- roastery schedules/closures.
- order/sub-order fulfillment histories.
- `fulfillment_incidents`.
- `shipments` / `shipment_events` for legacy/current shipment tracking surfaces.
- `shipment_legs` for explicit multi-leg routing.
- shipment share/access-token support.
- delivery confirmation/evidence structures.
- ROSTA Hub routing/eligibility structures.
- `hub_work_items` / `hub_work_item_actions` for receipt, assignment, grinding, QC, rework, packaging and outbound handoff evidence.

Key rule: `shipment_legs` and Hub action/custody records are physical execution evidence; they do not determine whether money is paid/refunded/settled.

Primary anchors:

- `2026_07_22_200001_create_fulfillment_tables.php`
- `2026_07_27_120001_create_r5g_rosta_hub_schema.php`
- `2026_07_27_160001_create_r5h_fulfillment_commitments_and_incidents.php`
- `2026_07_28_000001_create_r5j_hub_operations.php`
- `FulfillmentIncident`, `HubWorkItem`, `HubWorkItemAction` models
- `Services/Fulfillment/*`, `Services/Hub/RostaHubOperationsService.php`

## 7. Payment, refund and financial truth

Persisted families include:

- `payment_attempts`
- `refund_attempts`
- financial reconciliation case/evidence structures
- commission policy/rule structures
- `settlement_allocations`
- settlement batches/items and payout/settlement lifecycle structures
- order tax allocations/lines

The multi-vendor migration creates line-level `settlement_allocations` that can reference an Order, Sub-order, item service and shipment leg, with explicit allocation type, owner, gross/discount/tax/net amounts, status, pricing version, source reference and unique idempotency key.

Primary anchors:

- `2026_07_22_190001_create_payment_attempts.php`
- `2026_07_23_080001_create_refunds_and_reconciliation.php`
- `2026_07_25_230001_create_r5b_marketplace_schema.php`
- `2026_07_27_190001_create_r5i_delivery_settlement_payouts.php`
- `2026_08_14_080000_create_financial_truth_policies.php`
- `PaymentAttempt`, `RefundAttempt`, `CommissionPolicy`, `CommissionPolicyRule`, `FinancialReconciliationCase` and settlement models
- `Services/Payments/*`, `Services/Finance/*`, `Services/Settlement/*`

## 8. Notifications and async delivery state

Core structures:

- `notification_templates`
- `notification_outbox`
- failed-job/job-batch infrastructure supplied by Laravel migrations/configuration

Outbox rows contain subject, template, recipient, payload, status/attempt state, availability/sent/failed timestamps and unique dedupe key. They are durable delivery intent/evidence, not a replacement for the source Order/Payment/Fulfillment state.

Primary anchors:

- `2026_07_22_190002_create_notification_outbox.php`
- `NotificationOutbox`, `NotificationTemplate` models
- `Services/Notifications/NotificationOutboxService.php`

## 9. Media

Persisted families include:

- `media_upload_intents`
- `media_assets`
- processing state/evidence around secure image transformation and object-storage publication.

The database stores metadata/state; object bytes belong behind the filesystem/object-storage boundary. Object storage is not the only business truth for media ownership/publication state.

## 10. Content, quiz, recommendation and reviews

Persisted families include:

- content authors/entries/relations and SEO/content support records.
- quiz versions and attempts/profile/recommendation support records.
- review/reply/moderation/reporting safety structures.
- inquiries/support records.

Primary anchors include the content migrations/services and `2026_08_14_120000_create_quiz_and_review_safety_tables.php`.

These domains may reference Product/User/Roastery IDs but must not write order/payment financial truth directly.

## 11. Roadmap capabilities not claimed as current schema

The following are **not** made BUILT by this document:

- ROSTA Growth Network ledger/lead/attribution schema (CAP-01)
- Loyalty (CAP-03)
- Coffee Subscription (CAP-12)
- Discovery Subscription (CAP-13)
- other registered growth capabilities not already evidenced in baseline code

Their owning capability phases must extend the schema using the invariants in this contract rather than adding speculative tables here.

## 12. Deletion and foreign-key semantics

Observed migrations intentionally use a mixture of:

- `cascadeOnDelete()` for true children whose lifetime is owned by an aggregate record;
- `restrictOnDelete()` where deleting a catalog/seller reference could destroy historical/financial integrity;
- `nullOnDelete()` for optional actor/provider/reference metadata where the historical record must survive.

Future migrations must choose delete semantics according to historical/audit requirements, not convenience.
