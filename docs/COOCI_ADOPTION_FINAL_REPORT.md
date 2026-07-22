# گزارش نهایی ممیزی و انتقال Cooci به Rosta

تاریخ: 2026-07-22

## نتیجه ممیزی

کد واقعی دو مخزن `sajadkhavas/cooci` و `sajadkhavas/winimi-bakery-backend` دوباره بررسی و با Rosta مقایسه شد. انتقال به‌صورت کپی مستقیم انجام نشد؛ الگوهای اثبات‌شده Cooci برای Domain مارکت‌پلیس رستا بازطراحی شدند.

رستا در Auth/session، کاتالوگ چندروستری، RoastBatch، Checkout Quote، Inventory Reservation، CMS ساختاریافته و SEO از Cooci جلوتر بود؛ این بخش‌ها جایگزین نشدند.

موارد تک‌فروشنده، قیمت‌گذاری یا وضعیت‌های ناسازگار با مارکت‌پلیس، مسیرهای قدیمی و هرگونه Grind selector/state منتقل نشدند.

## Package A — Payment و Notification Outbox

Branch: `agent/phase-18-cooci-adoption`
PR: `#17` → `integration/rosta-complete-build`

- Payment Attempt مستقل، شماره‌دار و Idempotent
- Providerهای Disabled، Testing و Zarinpal
- Callback و Verify امن و مالک‌محور
- Authority اجباری زرین‌پال
- Payloadهای Provider رمزگذاری‌شده و حذف داده کارت
- مصرف اتمیک `stock_on_hand` و `stock_reserved`
- Reservation → consumed و Order → paid
- `requires_review` برای ناسازگاری مبلغ، رزرو، موجودی یا پرداخت دوم
- Notification Outbox رمزگذاری‌شده، Deduplication، Retry و Stale recovery
- Providerهای Disabled، Testing و Kavenegar برای پیام‌های سفارش
- قالب‌های نسخه‌بندی‌شده و Scheduler
- تست و Audit دائمی در `composer check`

## Package B — Fulfillment مارکت‌پلیس

Branch: `agent/phase-18b-fulfillment`
PR: `#18` → Package A

- State Machine واحد برای Seller و Admin
- Roastery-scoped writes و محافظت IDOR
- Accepted، Preparing، Ready to ship، Shipped و Delivered
- Carrier و Tracking اجباری
- Shipment persistence
- Status History و Internal Notes append-only
- رد سفارش پرداخت‌شده → `refund_pending`، نه لغو ساختگی
- Restock دقیقاً یک‌بار با Guard مستقل و Stock Ledger
- Notification Outbox برای تغییرات عملیاتی
- تست مسیر قانونی، پرش وضعیت، Tracking، IDOR و Restock تکراری

## Package C — Verified Reviews و Support

Branch: `agent/phase-18c-reviews-support`
PR: `#19` → Package B

- یک Review برای هر OrderItem تحویل‌شده
- فقط مالک سفارش و `is_verified_purchase=true`
- Pending تا Moderation ادمین
- Public summary فقط از Approved review
- هویت عمومی مشتری Privacy-safe
- Inquiry واقعی با Reference ID
- Mobile، Email و Message رمزگذاری‌شده
- IP فقط HMAC و بدون ذخیره IP خام
- Honeypot، Rate Limit و Duplicate window
- Workflow ادمین برای Inquiry
- فرم تماس Frontend متصل به Persisted API
- تست و Audit دائمی

## Package D — Media/Object Storage

Branch: `agent/phase-18d-media-storage`
PR: `#20` → Package C

- Upload Intent پایدار و Roastery/User scoped
- Object Key فقط توسط Backend
- Presigned PUT سازگار با S3 و Cloudflare R2
- MIME، حجم و SHA-256 اجباری
- Content-Type و Checksum داخل امضای Provider
- Completion با بررسی وجود Object، حجم و MIME
- URL عمومی فقط از CDN HTTPS تنظیم‌شده
- Completion تکراری Idempotent
- Pending، Completed، Failed و Expired
- ثبت Failure پس از Rollback تراکنش
- Cleanup خودکار Object و Intent رهاشده
- S3 dependency و تنظیمات Fail-closed
- تست و Audit دائمی

## Package E — Release Integrity و Recovery

Branch: `agent/phase-18e-release-integrity`
PR: `#21` → Package D

- `php artisan rosta:readiness --json`
- بررسی Composer lock، APP_KEY، APP_DEBUG، DB، Redis و Schema
- بررسی فعال‌سازی Payment، SMS و Media
- هشدار Paymentهای نیازمند Reconciliation و Outboxهای Failed
- Release Manifest شامل SHA-256 تمام Artifactها و Commit SHA
- Secret scan و ممنوعیت `.env`، Source Map، Private Key و Backup/Dump
- Deploy فرانت فقط بعد از `release:verify`
- OpenAPI برای APIهای Commerce و Drift audit
- Backup/Restore Runbook با Restore Drill، RPO و RTO
- آزمون خصمانه Auth، Payment، Inventory، Outbox، Fulfillment، Reviews، Support، Media، SSR و Release

## مواردی که عمداً هنوز کامل اعلام نشده‌اند

- `backend/composer.lock` هنوز تولید نشده است.
- GitHub Actions مخزن قبل از Checkout متوقف می‌شود؛ هیچ Test/Larastan/Pint/Build سبزی ثبت نشده است.
- Provider credentials واقعی وارد نشده‌اند.
- Bucket CORS/CDN، SMS و Payment acceptance روی Staging اجرا نشده‌اند.
- Refund Provider و Admin financial reconciliation UI هنوز لازم‌اند.
- Seller/Admin Frontend برای Fulfillment، Reviews، Inquiries و Media هنوز لازم است.
- Product detail UI هنوز Review list/submit را نمایش نمی‌دهد.
- Home، Blog و Quiz هنوز باید از Seed به داده زنده/SSR منتقل شوند.
- Frontend uploader و اتصال MediaAsset به Product هنوز لازم است.
- Restore Drill و آزمون خصمانه هنوز اجرا نشده‌اند؛ Runbook و Gate ساخته شده‌اند.
- Staging، Production و Google indexing فعال نشده‌اند.

## وضعیت Merge

همه PRهای `#17` تا `#21` Draft و Stacked هستند. هیچ‌کدام نباید مستقیماً به `main` Merge شوند. ترتیب ادغام بعد از اجرای Gateها:

```text
#17 → integration/rosta-complete-build
#18 → #17
#19 → #18
#20 → #19
#21 → #20
```

سپس زنجیره نهایی روی Runner سالم یا Docker اجرا، خطاها اصلاح و در نهایت به Integration منتقل می‌شود.

## قانون دائمی کسب‌وکار

Rosta فقط دانه کامل می‌فروشد. هیچ Grind selector، Grind option یا Grind state در این انتقال ایجاد نشده است.
