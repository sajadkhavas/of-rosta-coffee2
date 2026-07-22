# آزمون خصمانه انتشار Rosta

این ماتریس باید روی Release Candidate و Staging واقعی اجرا شود. نتیجه هر مورد باید همراه Request ID، Commit SHA و زمان اجرا ثبت شود.

## Auth و Session

- OTP اشتباه تا سقف مجاز و سپس Lockout
- Resend سریع OTP و Rate Limit
- Session منقضی، Session لغوشده و Login هم‌زمان بیش از سقف
- CSRF token حذف/منقضی
- Cookie روی HTTP، Domain نادرست و Origin غیرمجاز
- دسترسی Customer به Seller/Admin API
- فروشنده روستری A به منابع روستری B

## Payment

- Request تکراری با همان Idempotency Key و Payload یکسان
- همان Key با Order یا مبلغ متفاوت
- دو Request هم‌زمان برای یک Order
- Callback تکراری و Verify تکراری
- Authority اشتباه یا حذف‌شده
- Callback پس از انقضای Reservation
- Gateway موفق ولی مبلغ داخلی ناسازگار
- Gateway موفق ولی موجودی/Reservation ناسازگار
- دو Payment واقعاً تأییدشده برای یک Order
- قطع شبکه قبل/بعد از ثبت Payment Attempt
- بازگشت کاربر بدون Cookie و سپس مشاهده نتیجه بعد از Login

انتظار: موجودی فقط یک‌بار مصرف شود؛ حقیقت Gateway از بین نرود؛ ناسازگاری مالی وارد `requires_review` شود.

## Inventory و Fulfillment

- دو Checkout هم‌زمان روی آخرین موجودی
- انقضای Reservation هم‌زمان با Verify
- پرش مستقیم Pending → Shipped
- ارسال بدون Carrier/Tracking
- Delivered بدون Shipment
- Reject تکراری و Restock تکراری
- Seller خارجی روی Order دیگر
- Tracking code تکراری
- شکست Notification Provider هنگام Transition

انتظار: State Machine و Lockها مانع عملیات غیرمجاز شوند؛ Restock دقیقاً یک‌بار و با Ledger ثبت شود.

## Notification Outbox

- Worker crash پس از Processing و قبل از Sent
- Provider timeout
- Retry تا سقف و ورود به Failed
- اجرای هم‌زمان دو Dispatcher
- Duplicate business event
- Template غیرفعال یا حذف‌شده

انتظار: Deduplication حفظ شود؛ Stale processing بازیابی شود؛ تراکنش سفارش به SMS وابسته نباشد.

## Reviews و Support

- Review پیش از تحویل
- Review آیتم کاربر دیگر
- Review دوم برای همان OrderItem
- نمایش Review pending/rejected در Public API
- XSS/HTML در Title و Body
- Inquiry با Honeypot پر
- Inquiry تکراری در Window
- Flood از IP و Contact یکسان
- تلاش برای مشاهده Inquiry بدون Admin

انتظار: فقط Approved verified purchase review عمومی شود؛ موفقیت Contact فقط پس از Persist واقعی باشد؛ IP خام ذخیره نشود.

## Media Upload

- MIME اعلامی متفاوت از Object
- حجم متفاوت
- Checksum اشتباه در Signed PUT
- Object Key دست‌کاری‌شده
- Completion با User یا Roastery دیگر
- Completion پس از Expiry
- Completion تکراری
- فایل بسیار بزرگ
- SVG/HTML/Executable با پسوند تصویر
- CDN Base URL غیرHTTPS
- Bucket CORS بیش از حد باز

انتظار: هیچ URL دلخواه ثبت نشود؛ Asset فقط پس از Validation ساخته شود؛ Object رهاشده پاک شود.

## SSR، SEO و Cache

- API 404 برای Product/Content واقعی
- API 500/timeout هنگام SSR
- صفحه خصوصی در Service Worker cache
- `robots.txt` روی Staging
- Canonical با Host اشتباه
- Sitemap شامل URL حذف‌شده یا noindex
- HTML اولیه بدون داده و Hydration-only content
- Cache عمومی حاوی Address/Order/User

انتظار: 404 واقعی، خطای موقت noindex و fail-closed؛ صفحات خصوصی `no-store`؛ Staging کاملاً Disallow.

## Release و Operations

- Artifact دارای `.map`، `.env` یا Secret-shaped value
- Manifest مربوط به Commit دیگر
- Deploy بدون `composer.lock`
- Migration ناموفق
- DB یا Redis unavailable
- Payment/SMS/Media روشن با Credential ناقص
- Worker/Scheduler خاموش
- Restore از Backup با Hash اشتباه
- Rollback به Image قبلی با Schema additive

## Gate خروج

Release تنها زمانی قابل Promotion است که:

```text
composer install --no-dev --classmap-authoritative
composer check
php artisan rosta:readiness --json
bun install --frozen-lockfile
bun run release:verify
browser/adversarial suite passed
backup restore drill evidence exists
staging indexing disabled
payment/sms/media provider acceptance recorded
```
