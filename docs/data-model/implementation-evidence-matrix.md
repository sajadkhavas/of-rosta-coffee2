# ARCH-0.3 Implementation Evidence Matrix

Exact baseline: `integration/rosta-release-candidate@f7e8a31cb0453fb2bff4eda1e4c2f07c6757fa5f`

This matrix prevents architecture documentation from turning roadmap intent into false implementation claims.

| Area | Status | Audited evidence / rule |
|---|---|---|
| Users / identity / reusable addresses | BUILT | users + identity/access migrations and `User` / `Address` models |
| Auth session / OTP persistence | BUILT | identity tables, `AuthSession`, `OtpChallenge`, OTP services/jobs |
| Seller organization / memberships / invites | BUILT | seller-organization migration and seller authorization services |
| Roasteries / catalog / origins | BUILT | catalog inventory migration + catalog models/services |
| Whole-bean variant inventory | BUILT | `product_variants`, `inventory_stocks`, movement/reservation services |
| Grinding as Order Item Service | BUILT | grinding profiles/capabilities + quote/order item service tables/models |
| Multi-roastery CheckoutQuote grouping | BUILT | `checkout_quote_groups`, quote item group/service persistence |
| Expiring inventory reservations | BUILT | reservation table/service + scheduled expiry |
| Master Order + Roastery Sub-orders | BUILT | `orders`, `sub_orders`, item mappings, Order/SubOrder services |
| Historical order/item/service snapshots | BUILT | quote/order snapshots and encrypted order address snapshot |
| Order command idempotency | BUILT | `order_idempotency_keys` and OrderService logic |
| Verified payment attempts | BUILT FOUNDATION / provider gated | PaymentAttempt + provider manager + Zarinpal/testing/disabled adapters; production credentials/provider gate external |
| Automatic paid -> fulfillment commitment | BUILT | PaymentService + FulfillmentCommitmentService |
| Routine post-payment seller accept/reject | LEGACY DEBT, NOT CURRENT CAPABILITY | old acceptance fields/enum remain; runtime auto-commits fulfillment; incidents are exception path |
| Fulfillment SLA and incidents | BUILT | SLA/incident migration and Fulfillment services |
| Direct fulfillment | BUILT | Sub-order fulfillment + shipments/tracking evidence |
| ROSTA Hub routing/work/custody | BUILT | Hub migrations, HubWorkItem/Action, RostaHubOperationsService |
| Multi-leg shipment routing | BUILT FOUNDATION | `shipment_legs` plus legacy shipment compatibility; provider-specific carrier integration remains edge work |
| Carrier provider abstraction / production carrier API | TARGET / provider work | Core shipment model exists; do not claim a specific carrier API live without verified adapter/credentials |
| Delivery confirmation | BUILT | delivery confirmation persistence/service |
| Settlement allocations | BUILT | line-level allocation table/model/financial services |
| Commission/Financial Truth policy | BUILT FOUNDATION | commission policies/rules + FinancialTruthEngine/PolicyResolver |
| Seller settlement release/batching | BUILT FOUNDATION | settlement release/batch services + scheduler; production payout provider/manual ops still provider/process gated |
| Refund attempts | BUILT FOUNDATION | refund persistence/contracts; actual production automation must match documented provider capability |
| Financial reconciliation | BUILT | reconciliation case/service foundation |
| Notification Outbox | BUILT | templates/outbox/service/scheduled dispatcher |
| Current seller-acceptance notification copy | LEGACY DEBT | seeded copy can reflect obsolete manual acceptance semantics; requires forward-safe data remediation |
| Secure media intent/asset processing | BUILT FOUNDATION | media intent/asset state + secure processor/queued job + S3-compatible storage boundary |
| Redis-only order/finance truth | PROHIBITED | Redis is cache/session/queue/locks, never sole durable commerce truth |
| Queryable PII uniformly field-encrypted | NOT CLAIMED / TARGET decision | order address snapshot is encrypted; account/address queryable columns need broader hardening/design decision |
| Content/CMS persistence | BUILT | content models/services/migrations |
| Quiz persisted attempts/versioning | BUILT | quiz models/safety migration |
| Recommendation foundation | BUILT | deterministic verified recommendation/taste profile foundation; future ML/semantic layers separate |
| Review verification/moderation safety | BUILT | review models/safety structures/services |
| Growth Network attribution/partner ledger | TARGET CAP-01 | business policy locked; dedicated persistence not claimed by current general finance tables |
| Loyalty ledger | TARGET CAP-03 | not fabricated in ARCH-0.3 |
| Store Credit/Cashback ledger | TARGET CAP-11 | not fabricated in ARCH-0.3 |
| Coffee Subscription | TARGET CAP-12 | capability registered; schema remains capability-owned |
| Discovery Subscription | TARGET CAP-13 | capability registered; schema remains capability-owned |
| Centralized all/most-orders-through-Hub launch policy | PROPOSED OPERATING POLICY | current data model supports Direct + Hub; launch policy must remain configurable/versioned unless separately approved |

## Evidence anchors reviewed

Core source anchors for this audit include:

- `backend/database/migrations/2026_07_21_000001_create_users_table.php`
- `backend/database/migrations/2026_07_21_010001_create_identity_access_tables.php`
- `backend/database/migrations/2026_07_21_020001_create_catalog_inventory_tables.php`
- `backend/database/migrations/2026_07_21_030001_create_transactional_checkout_tables.php`
- `backend/database/migrations/2026_07_22_190001_create_payment_attempts.php`
- `backend/database/migrations/2026_07_22_190002_create_notification_outbox.php`
- `backend/database/migrations/2026_07_22_200001_create_fulfillment_tables.php`
- `backend/database/migrations/2026_07_23_080001_create_refunds_and_reconciliation.php`
- `backend/database/migrations/2026_07_25_230001_create_r5b_marketplace_schema.php`
- `backend/database/migrations/2026_07_27_120001_create_r5g_rosta_hub_schema.php`
- `backend/database/migrations/2026_07_27_160001_create_r5h_fulfillment_commitments_and_incidents.php`
- `backend/database/migrations/2026_07_27_190001_create_r5i_delivery_settlement_payouts.php`
- `backend/database/migrations/2026_07_28_000001_create_r5j_hub_operations.php`
- `backend/database/migrations/2026_08_14_000001_create_seller_organization_tables.php`
- `backend/database/migrations/2026_08_14_080000_create_financial_truth_policies.php`
- `backend/database/migrations/2026_08_14_120000_create_quiz_and_review_safety_tables.php`
- `backend/app/Models/*` relevant to the domains above
- `backend/app/Services/Checkout/*`
- `backend/app/Services/Payments/*`
- `backend/app/Services/Finance/*`
- `backend/app/Services/Settlement/*`
- `backend/app/Services/Fulfillment/*`
- `backend/app/Services/Hub/*`
- `backend/app/Services/Notifications/*`
- `backend/routes/console.php`
- accepted `docs/business/` and `docs/architecture/` contracts

## Gate

A future PR that calls a TARGET/PROPOSED row “already implemented” must provide new code/migration/test evidence and update this matrix or its successor through review.
