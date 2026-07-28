# گزارش نهایی ممیزی و انتقال Cooci به Rosta

تاریخ ممیزی اولیه: 2026-07-22

به‌روزرسانی نهایی: 2026-07-28

## نتیجه

کد دو مخزن `sajadkhavas/cooci` و
`sajadkhavas/winimi-bakery-backend` با Rosta مقایسه شد. انتقال کپی مستقیم نبود؛
الگوهای قابل‌اعتماد برای Domain چندروستری Rosta بازطراحی و در lineage
`integration/rosta-r5-marketplace` یکپارچه شدند.

Rosta در Auth/session، کاتالوگ چندروستری، RoastBatch، Quote و Reservation،
CMS ساختاریافته و SEO مرجع باقی ماند. مدل‌های Bakery، lifecycle تک‌فروشنده،
Credentialها و هر مدل آسیاب در Product/Inventory منتقل نشدند.

## بسته‌های تحویل‌شده

### A — Payment و Notification Outbox

- Payment Attempt مستقل و idempotent
- Providerهای Disabled، Testing و Zarinpal با Verify امن
- مصرف اتمیک موجودی/رزرو و مرز `requires_review`
- Outbox رمزگذاری‌شده، Retry، stale recovery و قالب نسخه‌دار

### B — Fulfilment

- Transition service واحد با scope روستری و حفاظت IDOR
- Tracking، status history، note خصوصی و restock دقیقاً یک‌بار
- تصمیم نهایی R5H: تعهد خودکار پس از پرداخت و Incident به‌جای رد فروشنده

### C — Reviews و Support

- Review خرید تأییدشده با Moderation
- Public summary فقط از رکورد Approved
- Inquiry پایدار با Honeypot، HMAC IP، rate limit و duplicate window

### D — Media/Object Storage

- Upload Intent محدود به User/Roastery
- Object key سروری، presigned PUT و checksum/MIME/size اجباری
- تکمیل idempotent، CDN policy و cleanup خودکار

### E — Release Integrity و Recovery

- readiness و OpenAPI drift
- Release manifest و secret scan
- deployment اتمیک، rollback و backup/restore runbook
- ماتریس تست خصمانه و بستهٔ واحد Staging

## وضعیت نهایی

این پنج بسته source-complete و در رجیستر ده‌فازی `docs/PHASES.md` ثبت شده‌اند.
فایل‌های lock و route tree در مخزن وجود دارند و Gateهای دائمی به زنجیرهٔ بررسی
متصل‌اند. شاخه‌ها و PRهای `#17` تا `#21` مسیر فعال Merge نیستند و پس از ایجاد
Release Candidate باید به‌عنوان superseded بسته شوند.

## مواردی که عمداً خارج از Source باقی می‌مانند

- Server/DNS/ACME/APP_KEY/MySQL/Redis/R2 inputs
- اجرای Runtime acceptance، browser E2E و restore drill روی Staging
- Credential و تأیید رسمی Payment، Refund و SMS
- Production money movement
- Production media activation و Google indexing

## قانون دائمی کسب‌وکار

Product، SKU، RoastBatch، Reservation و Stock فقط هویت دانهٔ کامل دارند.
Grinding فقط به‌صورت Order Item Service قیمت‌گذاری و snapshot می‌شود و هیچ‌گاه
Variant یا بعد موجودی نیست.
