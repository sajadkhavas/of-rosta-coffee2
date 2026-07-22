# فاز ۱۷ — Release Baseline و آمادگی Staging

## هدف

این فاز کد فعلی را به نسخه‌ای تبدیل می‌کند که بتوان آن را روی Staging اجرا، مشاهده و دیباگ کرد؛ بدون فعال‌شدن ایندکس گوگل و بدون ادعای آماده‌بودن پرداخت، پیامک و عملیات کامل فروشنده.

مرز دائمی کسب‌وکار حفظ می‌شود: رستا فقط دانه کامل می‌فروشد و هیچ Grind state یا انتخاب آسیاب وارد سیستم نمی‌شود.

## وضعیت واقعی

بخش‌های زیر در کد وجود دارند:

- کاتالوگ، روستری، Variant، Roast Batch و موجودی
- OTP و نشست Sanctum
- سبد، Checkout Quote، سفارش و رزرو موجودی
- CMS ساختاریافته و پایه SEO
- PWA، Service Worker و Performance Policy

Blockerهای این فاز:

1. `backend/composer.lock` باید با Composer واقعی تولید و Commit شود.
2. Frontend، Backend و Browser Gate باید در محیطی که Runner سالم دارد اجرا شوند.
3. Staging باید با Indexing خاموش Deploy شود.

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

## اولین اجرا روی سرور توسعه

از ریشه مخزن:

```bash
cp backend/.env.example backend/.env
bash backend/scripts/setup.sh
```

`setup.sh` در صورت نبود Lockfile، آن را داخل Container PHP 8.3 تولید می‌کند و سپس این مراحل را انجام می‌دهد:

- نصب Dependencyها
- تولید APP_KEY
- Migration و Seed
- اجرای `composer check`
- اجرای API، Worker و Scheduler
- Health Check

پس از موفقیت:

```bash
git status --short
git add backend/composer.lock
git commit -m "build: lock Laravel dependencies for Phase 17"
git push origin agent/phase-17-release-baseline-staging
```

Lockfile باید بازبینی و Commit شود؛ تولید مجدد آن هنگام Deploy ممنوع است.

## Gate فرانت و Route Generator

روی ماشینی که Bun و اینترنت Package Registry دارد:

```bash
bun install --frozen-lockfile
bunx @tanstack/router-cli@1.170.16 generate
bun run check
```

بعد از تولید موفق `src/routeTree.gen.ts` باید کنترل شود که حداقل مسیرهای زیر در آن وجود دارند:

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

پس از تأیید Generator:

1. Import در `src/router.tsx` از `routeTree.phase17` به `routeTree.gen` برگردد.
2. `src/routeTree.phase17.ts` حذف شود.
3. `bun run check` دوباره اجرا شود.

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

## استقرار Laravel Staging

از پوشه `backend`:

```bash
bash scripts/deploy-staging.sh
```

اسکریپت موارد زیر را Block می‌کند:

- نبود `composer.lock`
- Secret خالی یا `CHANGE_ME`
- `APP_DEBUG` روشن
- Migration ناموفق
- Health Check ناموفق

API فقط روی Loopback سرور Publish می‌شود و باید از Reverse Proxy دارای TLS به `api-staging.rosta.shop` متصل شود.

## استقرار Frontend Staging

از ریشه مخزن:

```bash
export CLOUDFLARE_API_TOKEN="..."
bash scripts/deploy-staging-frontend.sh
```

این اسکریپت ابتدا `bun run check` را اجرا می‌کند و فقط با Indexing خاموش اجازه Deploy می‌دهد.

## Acceptance Staging

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

تا پیش از عبور این Gate، PR نباید روی `main` Merge شود و Google Indexing نباید فعال گردد.
