# وضعیت انتقال معماری Cooci به Rosta

آخرین به‌روزرسانی: 2026-07-28

## نتیجه

Packageهای A تا E انتقال Cooci در lineage یکپارچهٔ
`integration/rosta-r5-marketplace` پیاده‌سازی و با ممیزی‌های دائمی پروژه پوشش
داده شده‌اند. شاخه‌ها و PRهای قدیمی فقط سابقهٔ توسعه‌اند و مسیر اجرایی نیستند.
رجیستر فعلی فازها و Commitهای شاهد در `docs/PHASES.md` نگهداری می‌شود.

## Packageهای تکمیل‌شده

| Package | خروجی نهایی |
| --- | --- |
| A — Payment/Outbox | Payment Attempt، Providerهای fail-closed، Verify امن، Notification Outbox و Retry |
| B — Fulfilment | State machine، scope روستری، Tracking، History و Restock دقیقاً یک‌بار |
| C — Reviews/Support | خرید تأییدشده، Moderation، Inquiry پایدار و کنترل‌های سوءاستفاده |
| D — Media | Upload intent امضاشده، checksum/MIME/size، R2/CDN و cleanup |
| E — Release | Readiness، contract drift، release integrity، backup/restore و staging package |

تصمیم R5H جایگزین پذیرش/رد دستی فروشنده شده است: پرداخت موفق SubOrder را متعهد
می‌کند و ناتوانی فروشنده به‌صورت Incident با رسیدگی ادمین مدیریت می‌شود.

## مرزهای دائمی

- هویت Product/SKU/RoastBatch/Reservation/Stock فقط دانهٔ کامل است.
- آسیاب فقط Order Item Service است و هرگز بعد موجودی نیست.
- Laravel مرجع قیمت، موجودی، پرداخت، وضعیت و تسویه است.
- دادهٔ Provider و اطلاعات عملیاتی خصوصی وارد Browser contract نمی‌شود.
- Production payment/refund/SMS/indexing تا پذیرش Staging غیرفعال می‌ماند.

## موارد باقی‌مانده خارج از Source

- ساخت و Freeze شاخهٔ `integration/rosta-release-candidate` پس از R5K
- نصب Secretها، DNS، R2 و دسترسی سرور Staging
- اجرای گزارش پذیرش Runtime و Restore Drill روی Staging
- تأیید رسمی Providerها و فعال‌سازی جداگانهٔ Production
- فعال‌سازی Google indexing فقط بعد از تأیید Launch
