# Implementation Evidence Matrix

Status: ARCH-0.2 baseline audit evidence
Audited baseline: `d50af5ab516f64fe47072c8e67938d64614d8373`

This matrix distinguishes architecture evidenced in source from target/proposed architecture. Paths are evidence anchors, not permanent class-name APIs.

| Concern | Baseline evidence | Status / architecture conclusion |
|---|---|---|
| Frontend runtime | `package.json`, `src/server.ts`, `src/start.ts`, `src/router.tsx`, `src/routeTree.gen.ts` | BUILT — React/TanStack SSR-capable web runtime |
| API transport | `src/lib/api/client.ts`, `src/lib/api/*` | BUILT — centralized cookie/CSRF/timeout/error/request-ID boundary |
| Checkout client | `src/lib/api/checkout.ts` | BUILT — frontend calls backend checkout APIs; backend remains authority |
| Finance/admin client | `src/lib/api/admin-finance.ts`, `src/lib/api/financial-contracts.ts` | BUILT — explicit finance API surfaces/contracts |
| Backend runtime | `backend/composer.json`, `backend/routes/api.php` | BUILT — Laravel 13 / PHP 8.3 API application |
| Database | `backend/config/database.php` | BUILT — MySQL default; Redis connections configured separately |
| Cache | `backend/config/cache.php` | BUILT — Redis default cache store |
| Session | `backend/config/session.php` | BUILT — Redis default session driver, HTTP-only session cookie |
| Queue | `backend/config/queue.php` | BUILT — Redis default, `after_commit=true`, DB failed-job storage |
| Scheduler | `backend/routes/console.php` | BUILT — checkout/SLA/settlement/notification/media maintenance schedules |
| Object storage | `backend/config/filesystems.php` | BUILT — S3-compatible disk with endpoint/bucket/public URL configuration; production R2 is deployment config |
| Provider enable flags | `backend/config/services.php` | BUILT — SMS/payment driver + explicit enabled flags; external credentials remain deployment gates |
| Payment abstraction | `backend/app/Services/Payments/PaymentProviderManager.php`, `PaymentService.php` | BUILT — provider-managed payment application service |
| Payment providers | `backend/app/Services/Payments/Providers/DisabledPaymentProvider.php`, `TestingPaymentProvider.php`, `ZarinpalPaymentProvider.php` | BUILT — explicit disabled/test/production adapter boundary |
| Payment persistence | `backend/app/Models/PaymentAttempt.php` | BUILT — durable attempt/evidence state |
| Refund persistence/contracts | `backend/app/Models/RefundAttempt.php`, `backend/app/Contracts/Refunds/` | BUILT FOUNDATION — provider capability still subject to official/external support |
| Order idempotency | `backend/app/Models/OrderIdempotencyKey.php`, checkout services | BUILT — durable order idempotency/pruning |
| Quotes | `backend/app/Models/CheckoutQuote*.php`, `backend/app/Services/Checkout/QuoteService.php` | BUILT — server quote/group/item/service snapshots |
| Orders | `backend/app/Models/Order.php`, `OrderItem.php`, `OrderItemService.php`, `OrderEvent.php` | BUILT — persistent order/item/service/event truth |
| Checkout orchestration | `backend/app/Services/Checkout/OrderService.php`, `QuoteService.php` | BUILT — server-side commercial/order orchestration |
| Inventory reservation | `backend/app/Models/InventoryReservation.php`, checkout/stock services | BUILT — reservation lifecycle separate from browser state |
| Whole-bean/service rule | `OrderItemService.php`, `RoasteryGrindingSelection.php`, catalog grinding policy | BUILT — grind represented as service, not alternate coffee inventory truth |
| Financial policy | `backend/app/Models/CommissionPolicy*.php`, `backend/app/Services/Finance/FinancialPolicy*.php` | BUILT — versionable financial policy foundation |
| Financial truth | `backend/app/Services/Finance/FinancialTruthEngine.php`, `MoneyMath.php` | BUILT — centralized money/allocation calculation boundary |
| Reconciliation | `FinancialReconciliationCase.php`, `FinancialReconciliationService.php` | BUILT — explicit disagreement/recovery records |
| Settlement | `backend/app/Services/Settlement/SettlementReleaseService.php`, `SettlementBatchService.php` | BUILT FOUNDATION — release/batch flow and scheduled release |
| Fulfillment commitment | `backend/app/Services/Fulfillment/FulfillmentCommitmentService.php` | BUILT — paid valid seller order becomes fulfillment commitment |
| Seller incidents | `FulfillmentIncident.php`, `FulfillmentIncidentService.php` | BUILT — incident model instead of routine post-payment seller rejection |
| Fulfillment SLA | `FulfillmentSlaMonitorService.php`, scheduled SLA check | BUILT — durable/scheduled SLA monitoring |
| Hub operations | `HubWorkItem.php`, `HubWorkItemAction.php`, `RostaHubOperationsService.php` | BUILT — receiving/assignment/grinding/QC/rework/outbound work foundation |
| Delivery | `DeliveryConfirmationService.php` and shipment-related models/services | BUILT — delivery evidence domain exists |
| Notification outbox | `NotificationOutbox.php`, `NotificationOutboxService.php` | BUILT — persistent notification intent/delivery lifecycle |
| SMS abstraction | `backend/app/Contracts/Notifications/`, `SmsProviderManager.php`, Kavenegar service folder | BUILT — generic SMS boundary with Kavenegar adapter area |
| OTP | `OtpChallenge.php`, `OtpSender.php`, `Jobs/SendOtpCode.php` | BUILT — persistent challenge + sender contract + queued delivery |
| Media upload | `MediaUploadIntent.php`, catalog media upload service | BUILT — scoped upload intent/lifecycle |
| Secure image processing | `Services/Media/SecureImageProcessor.php`, `Jobs/ProcessMediaUpload.php` | BUILT — server-side queued processing foundation |
| Audit | `AuditLog.php`, `AuditRecorder.php` | BUILT — audit persistence/service foundation |
| Carrier provider API | internal shipment/fulfillment foundations; no canonical provider-specific dependency required by this contract | TARGET — provider-independent adapter + manual-safe fallback |
| Centralized all-order Hub launch policy | PS0.1 business docs + existing Hub/direct capability | PROPOSED/OPERATING POLICY — may be configured later; not represented as already enforced for every order |
| Partner adapter | PS0.1 generic Partner Experience business boundary | TARGET — technical adapter/configuration may be added later; Winimi is not Core |
| Production immutable artifacts | existing release/security/pre-server work + this architecture contract | TARGET — must be closed in deployment/rehearsal phases before server activation |
| Liveness/readiness/metrics | partial existing error/request-id/security foundations | TARGET — explicit production endpoints/metrics/alerts remain deployment/reliability work |

## Audit interpretation

A row marked `BUILT FOUNDATION` means the durable architecture boundary is present but production automation may still depend on a later phase, provider capability, UI/workspace or operational runbook.

This matrix must be updated when a later accepted phase materially changes a boundary. It is not a substitute for tests or API contracts.

## No-greenfield rule

Future implementation planning must begin from these audited anchors. It must not create a second payment, order, inventory, finance, notification, media or fulfillment architecture merely because a new capability is added.
