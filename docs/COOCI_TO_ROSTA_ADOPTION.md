# ممیزی Cooci/Winimi و برنامه انتقال به Rosta

تاریخ ممیزی: 2026-07-22

مخازن مرجع:

- Frontend: `sajadkhavas/cooci`
- Backend: `sajadkhavas/winimi-bakery-backend`
- مقصد: `sajadkhavas/of-rosta-coffee2`

این سند بر اساس کد فعلی شاخه `main` دو مخزن Cooci/Winimi تهیه شده است، نه فقط توضیحات PRها.

## اصل انتقال

Cooci یک فروشگاه تک‌فروشنده است؛ Rosta یک مارکت‌پلیس تک‌روستری در هر سفارش است. بنابراین فایل‌ها کورکورانه کپی نمی‌شوند. الگوهای اثبات‌شده منتقل و با Domain رستا تطبیق داده می‌شوند.

مرز دائمی رستا حفظ می‌شود:

- فقط دانه کامل
- بدون Grind state یا انتخاب آسیاب
- Laravel مرجع قیمت، موجودی، Roast Batch، سفارش و پرداخت
- هر سفارش دقیقاً متعلق به یک روستری است
- Seller access همیشه Roastery-scoped است

## بخش‌هایی که Rosta از قبل بهتر یا کامل‌تر دارد

### OTP و Session

Rosta از قبل دارای این موارد است:

- Cache lock و DB row lock برای request و verify
- HMAC digest وابسته به challenge/mobile/purpose
- Queue payload رمزگذاری‌شده
- Session record قابل ابطال و expiry مستقل از Cookie
- Role assignment و scope
- Audit log

نتیجه: `OtpService` Cooci جایگزین پیاده‌سازی رستا نمی‌شود. فقط Provider واقعی SMS و قواعد عملیاتی آن اقتباس می‌شوند.

### Catalog و موجودی

Rستا از قبل دارای:

- Product/Variant فقط دانه کامل
- Roast Batch تغییرناپذیر
- `stock_on_hand` و `stock_reserved`
- Stock Ledger
- Seller-scoped catalog
- Roastery approval و Product publication

نتیجه: مدل Bakery Cooci کپی نمی‌شود.

### Checkout و Reservation

Rosta از قبل دارای:

- Cart validation authoritative
- Checkout Quote
- Order idempotency
- Snapshotهای مالی و محصول
- Row locking ترتیبی
- Inventory Reservation
- Expiry و cancellation

نتیجه: فقط Payment lifecycle و عملیات پس از پرداخت روی این پایه اضافه می‌شود.

### SEO و Content

Rosta از Cooci جلوتر است:

- Structured content types
- workflow Draft/Review/Published
- Optimistic locking
- Redirect management
- Internal-link report
- ProductGroup و محتوای مرتبط با قهوه

نتیجه: CMS Cooci کپی نمی‌شود. الگوی SSR fail-closed آن استفاده می‌شود.

## قابلیت‌های اثبات‌شده Cooci که باید منتقل شوند

## 1. Payment Attempt و Provider Architecture — انتقال کامل با تطبیق

منبع اثبات‌شده Cooci:

- `app/Contracts/Payments/PaymentProvider.php`
- `app/Services/Payments/PaymentProviderManager.php`
- `app/Services/Payments/PaymentService.php`
- `app/Services/Payments/Providers/DisabledPaymentProvider.php`
- `app/Services/Payments/Providers/TestingPaymentProvider.php`
- `app/Services/Payments/Providers/ZarinpalPaymentProvider.php`
- `app/Models/PaymentAttempt.php`
- `app/Http/Controllers/Api/PaymentController.php`

قواعدی که عیناً حفظ می‌شوند:

- Payment Attempt مستقل و immutable-history
- Idempotency وابسته به Order + Provider + Amount
- Reuse تلاش فعال
- Callback فقط Hint است
- Verify Provider تنها مرجع پرداخت
- Amount mismatch موجب رد تراکنش می‌شود
- callback replay دوباره موجودی کم نمی‌کند
- Provider غیرفعال پیش‌فرض
- Testing provider فقط خارج Production
- Merchant ID و Provider payload خصوصی در Backend
- نگهداری payloadهای sanitize شده، نه اطلاعات حساس خام

تطبیق مخصوص Rosta:

- `grand_total` به‌جای `grand_total_toman`
- `User` به‌جای `Customer`
- مصرف Reservation باید هم‌زمان:
  - `stock_reserved -= quantity`
  - `stock_on_hand -= quantity`
  - Reservation → `consumed`
- وضعیت Order → `paid`
- وضعیت SubOrder → `pending_acceptance` باقی می‌ماند تا روستری آن را بپذیرد
- Audit event برای initiation/verify/failure/replay

## 2. Notification Outbox — انتقال کامل با تطبیق

منبع:

- `app/Contracts/Notifications/SmsProvider.php`
- `app/Services/Notifications/NotificationOutboxService.php`
- `app/Services/Notifications/SmsProviderManager.php`
- Providers: disabled/testing/Kavenegar
- `NotificationTemplate`
- `NotificationOutbox`
- command `notifications:dispatch`

قواعد انتقال:

- ایجاد Outbox داخل Transaction تجاری
- ارسال بیرون Transaction
- Pending → Processing → Sent/Failed
- بازیابی رکوردهای Processing مانده
- Retry خطی/کنترل‌شده و سقف attempts
- Provider disabled در Production تا ورود Credential
- Template key به‌جای متن Hardcoded
- destination و payload محافظت‌شده
- عملیات Admin برای inspect/requeue

تطبیق Rosta:

- OTP Sender و Order Notification دو concern جدا می‌مانند
- Templateهای سفارش شامل روستری و SubOrder نیز هستند
- Queueهای پیشنهادی: `critical`, `notifications`, `default`
- رویدادها:
  - OTP requested
  - order paid
  - sub-order accepted/rejected
  - preparing
  - ready to ship
  - shipped + tracking
  - delivered
  - cancelled/refunded

## 3. Order State Machine و Fulfillment — انتقال مفهومی و بازنویسی Domain

Cooci دارای state machine کنترل‌شده، tracking enforcement، status history، internal notes و restock exactly-once است.

در Rosta باید دو state machine وجود داشته باشد:

### Order مالی/تجمیعی

- awaiting_payment
- paid
- processing
- shipped / delivered
- cancelled / refunded

### SubOrder عملیاتی روستری

- pending_acceptance
- accepted
- preparing
- ready_to_ship
- shipped
- delivered
- rejected / cancelled / refunded

قواعد:

- Seller فقط SubOrder متعلق به Roastery خود را تغییر می‌دهد
- Order aggregate از وضعیت SubOrder محاسبه می‌شود
- tracking code برای `shipped` اجباری است
- history append-only
- cancellation بعد از پرداخت بدون Refund Provider به‌عنوان refunded ثبت نمی‌شود
- Restock مصرف‌شده exactly once
- Notification Outbox بعد از transition موفق ایجاد می‌شود

## 4. Verified Purchase Reviews — انتقال کامل با تطبیق

قواعد Cooci:

- فقط Order delivered
- فقط OrderItem متعلق به Customer
- یک Review برای هر OrderItem
- `is_verified_purchase=true`
- Pending moderation
- فقط Approved در Public API
- Summary count/average از داده Approved

تطبیق Rosta:

- Review به Product، Roastery، User، Order، OrderItem و SubOrder متصل می‌شود
- نمایش نام محدود و Privacy-safe
- Seller حق Publish/Delete ندارد؛ فقط Admin moderation
- Review در Product schema فقط از Approved reviews

## 5. Inquiry/Support Persistence — انتقال کامل

قواعد:

- FormRequest bounded
- Honeypot
- Rate limit
- IP با HMAC
- User-Agent hash
- duplicate window
- persisted success only after DB record
- Inquiry status و Admin workflow

انواع Rosta:

- support
- order_issue
- roastery_onboarding
- corporate_purchase
- content_correction
- privacy_request

فرم تماس فعلی فقط mailto است و پس از این انتقال به API واقعی متصل می‌شود.

## 6. Runtime API Contracts — ادغام انتخابی

Cooci دارای envelope ثابت، error code enum، request ID، contract version guard و pagination shape ثابت است.

Rosta از قبل Envelope و Request ID دارد اما Shape آن با Cooci متفاوت است. تصمیم:

- Contract موجود Rosta شکسته نمی‌شود
- موارد زیر اضافه می‌شوند:
  - machine-readable contract endpoint
  - OpenAPI drift gate
  - stable pagination helper
  - stable error code registry
  - frontend contract-version guard
- Migration به Envelope جدید فقط در یک Contract Version جدید و هماهنگ انجام می‌شود

## 7. SSR Fail-Closed — انتقال کامل مفهومی

از Cooci منتقل می‌شود:

- داده Public باید پیش از HTML در SSR موجود باشد
- Product/Content Not Found → HTTP 404 واقعی
- Backend temporary failure → HTTP 503
- Error responses:
  - `Cache-Control: no-store`
  - `X-Robots-Tag: noindex, nofollow`
  - `Retry-After` در صورت وجود
- صفحه ناقص یا Seed نباید با Status 200 ایندکس شود

پیاده‌سازی باید با TanStack Start loaders/server functions انجام شود، نه React Router files Cooci.

## 8. Release Integrity و Atomic Deployment — انتقال اصول، نه Runtime

Cooci از Node SSR + Nginx + systemd استفاده می‌کند؛ Rosta Frontend برای Cloudflare Workers طراحی شده است. بنابراین Node runtime کپی نمی‌شود.

موارد قابل انتقال:

- Release manifest با SHA-256
- Secret-shaped release scan
- ممنوعیت source map و `.env`
- pre-deploy verification
- health check
- rollback
- retention releases
- immutable assets
- no-store برای Transactional routes

برای Laravel VPS:

- Docker image immutable
- image tag وابسته به Commit SHA
- migration قبل از activation
- readiness بعد از activation
- rollback image + database compatibility decision

برای Cloudflare:

- build artifact verification
- Preview/Staging deployment
- production promotion فقط از Commit تأییدشده

## 9. Backup/Restore — انتقال کامل عملیاتی

از Cooci:

- Backup فقط وقتی معتبر است که Restore Drill موفق داشته باشد
- Database + media + private operational exports
- Source از GitHub release بازیابی می‌شود
- remote encrypted copy
- RPO/RTO مکتوب
- restore در محیط isolated
- readiness و reconciliation بعد Restore
- Payment/SMS خاموش هنگام Drill

برای Rosta علاوه بر موارد بالا:

- Stock Ledger
- Roast Batches
- Payment Attempts
- Notification Outbox
- Audit Logs
- Content/Redirects
- Roastery verification

## 10. Adversarial and Browser Acceptance — انتقال کامل تستی

سناریوهای Cooci که برای Rستا الزامی می‌شوند:

- 401/419/429/503 recovery
- hostile encoded URL
- open redirect
- XSS/JSON-LD breakout
- IDOR
- stale/tampered cart
- duplicate order/payment callback
- cross-roastery seller access
- offline fail-closed روی auth/cart/checkout/payment/panel/admin
- PWA update/recovery
- raw source SSR assertions
- 404/503 real status
- mobile/desktop keyboard and focus
- CPU throttled performance and stable document height

## مواردی که نباید از Cooci کپی شوند

- Bakery models و cooling delivery
- Filament bakery resources با نام و منطق Winimi
- React Router Framework route files
- Express SSR runtime
- Node/systemd frontend deployment برای Cloudflare target
- Winimi API envelope بدون versioned migration
- تک‌فروشنده order lifecycle
- URL/domain/brand/config constants
- Kavenegar/Zarinpal credentials یا badge code

## بسته‌بندی اجرایی

### Package A — Payments and notification foundation

- Payment Attempt migration/model/enums
- Provider contracts and managers
- Disabled/Testing adapters
- Rosta payment service
- Notification Outbox migration/model/templates/providers
- Routes and tests

### Package B — Seller/Admin fulfillment

- SubOrder transition service
- Seller transition/tracking APIs
- Admin override/internal notes
- aggregate order status
- exactly-once inventory restock

### Package C — Reviews and support

- verified-purchase reviews
- moderation APIs
- inquiry persistence
- real contact frontend

### Package D — Media and storage

- object storage configuration
- signed upload workflow
- media verification and limits
- CDN/host policy

### Package E — Release candidate gates

- readiness command
- OpenAPI/contract drift
- backup/restore runbook
- deterministic release evidence
- browser/adversarial matrix
- Staging acceptance

## وضعیت انتقال

- Audit actual code: complete
- Transfer matrix: complete
- Package A: next active implementation
- Production/Staging activation: intentionally deferred until full product completion
