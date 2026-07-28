# فاز ۱۷ — Release Baseline و آمادگی Staging

## هدف

این فاز کد فعلی را به نسخه‌ای تبدیل می‌کند که بتوان آن را روی Staging اجرا، مشاهده و دیباگ کرد؛ بدون فعال‌شدن ایندکس گوگل و بدون ادعای آماده‌بودن پرداخت، پیامک و عملیات کامل فروشنده.

مرز دائمی حفظ می‌شود: هویت Product/SKU/RoastBatch/Reservation/Stock فقط دانه
کامل است؛ قابلیت‌های بعدی آسیاب را فقط به‌صورت Order Item Service اضافه می‌کنند.

## وضعیت واقعی

بخش‌های زیر در کد وجود دارند:

- کاتالوگ، روستری، Variant، Roast Batch و موجودی
- OTP و نشست Sanctum
- سبد، Checkout Quote، سفارش و رزرو موجودی
- CMS ساختاریافته و پایه SEO
- PWA، Service Worker و Performance Policy

وضعیت فعلی:

1. `backend/composer.lock` و `bun.lock` Commit شده‌اند.
2. Route tree واقعی TanStack تولید و Route tree موقت حذف شده است.
3. ممیزی‌های Source و CI این فاز در lineage یکپارچه قرار دارند.
4. Deployment/Browser acceptance روی Staging و Indexing خاموش هنوز لازم است.

## تغییرات Release Baseline

- CI فرانت از `bun run check` کامل استفاده می‌کند.
- Backend CI بدون `composer.lock` متوقف می‌شود.
- فقط Route محیط‌محور `/robots.txt` باقی مانده است.
- آیکون‌های PWA دارای MIME و محتوای معتبر هستند.
- Native Cursor در Performance Tier حداقلی حفظ می‌شود.
- Canonical و Structured Data صفحات عمومی از `siteConfig` ساخته می‌شوند.
- فرم تماس دیگر موفقیت ساختگی نمایش نمی‌دهد.
- Route Tree موقت فاز ۱۷ تمام Routeهای فعلی را در Runtime ثبت می‌کند.
- Docker Production بر پایه PHP-FPM و Nginx ساخته شده است.
- Worker، Scheduler، MySQL و Redis سرویس‌های مستقل Staging هستند.

## قاعدهٔ Dependency

استقرار فقط از lockfileهای Commit‌شده انجام می‌شود. تولید یا Update dependency
روی Host استقرار ممنوع است و اسکریپت canonical در نبود lockfile متوقف می‌شود.

## Gate فرانت و Route Generator

روی ماشینی که Bun و اینترنت Package Registry دارد:

```bash
bun install --frozen-lockfile
bunx @tanstack/router-cli@1.170.16 generate
bun run check
```

فایل `src/routeTree.gen.ts` اکنون مسیرهای زیر را ثبت می‌کند:

```text
/admin/content
/admin/content-links
/admin/content-edit/$entryId
/guides/$slug
/origins/$slug
/brew/$slug
/tastes/$slug
/collections/$slug
/compare/$slug
/robots.txt
```

`src/router.tsx` از همین فایل تولیدشده استفاده می‌کند و
`src/routeTree.phase17.ts` دیگر وجود ندارد.

## ساخت Environment استیجینگ

```bash
cp .env.staging.example .env.staging
cp backend/.env.staging.example backend/.env.staging
```

تمام `CHANGE_ME`ها باید با Secret واقعی جایگزین شوند. مقادیر زیر الزامی‌اند:

- `APP_KEY`
- `DB_PASSWORD`
- `MYSQL_ROOT_PASSWORD`
- `REDIS_PASSWORD`
- `CLOUDFLARE_API_TOKEN` در Shell استقرار فرانت

Staging باید این مقدار را حفظ کند:

```env
VITE_ALLOW_INDEXING=false
```

## استقرار یکپارچه Staging

Frontend SSR، Laravel، MySQL، Redis، Worker، Scheduler و TLS فقط از مسیر canonical زیر
مستقر می‌شوند:

```bash
bun run staging:deploy
```

این مسیر واحد قبل از استقرار، `composer.lock`، تمام کنترل‌های Frontend/Backend، محیط
Fail-closed، Backup، Migrationهای forward-only و Health Checkها را بررسی می‌کند.
هیچ اسکریپت جداگانه‌ای برای استقرار Frontend یا Backend معتبر نیست.

## Acceptance Staging

Acceptance مستقل پس از استقرار از همین مسیر واحد اجرا می‌شود:

```bash
bun run staging:accept
```

قبل از بستن فاز، موارد زیر باید دستی و خودکار تأیید شوند:

- `/api/v1/health/live` پاسخ ۲۰۰
- `/api/v1/health/ready` پاسخ ۲۰۰ با Database و Redis سالم
- صفحه اصلی، محصولات، روستری‌ها، مقاله و Routeهای محتوایی SSR می‌شوند
- `/robots.txt` روی Staging برابر `Disallow: /` است
- `/sitemap.xml` پاسخ XML معتبر می‌دهد
- مسیرهای Private دارای `no-store` و `noindex` هستند
- Login و Session Refresh با Cookie امن کار می‌کند
- Cart و Quote تا قبل از Payment کار می‌کنند
- Checkout درباره نبود Payment Provider خطای صادقانه می‌دهد
- Mobile، Tablet و Desktop بدون Cursor، Overflow یا Accessibility blocker کار می‌کنند

## Gate خروج

فاز ۱۷ فقط زمانی Ready است که همه موارد زیر برقرار باشند:

```text
backend/composer.lock committed
bun install --frozen-lockfile
bun run check
composer install from lock
composer check
browser audit passed
staging frontend healthy
staging API ready
VITE_ALLOW_INDEXING=false
```

استقرار فقط از SHA ثابت روی `integration/rosta-release-candidate` مجاز است.
تا پیش از پذیرش Staging، Merge/فعال‌سازی Production و Google Indexing ممنوع است.
