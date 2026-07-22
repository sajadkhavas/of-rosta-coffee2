# Phase 16 — PWA, performance and frontend release readiness

Marker: `phase16_pwa_performance=ready`

## هدف

بستن لایه تجربه و عملکرد فرانت رستا پیش از ورود به سرور، بدون تغییر دامنه محصول یا اضافه‌کردن قابلیت تجاری جدید. این فاز باید تضمین کند طراحی فعلی روی موبایل، تبلت و دسکتاپ با شبکه و سخت‌افزارهای متفاوت رفتار قابل‌پیش‌بینی دارد.

## سیاست Motion

- GSAP و Lenis فقط بعد از Idle و به‌صورت Dynamic Import بارگیری می‌شوند.
- `prefers-reduced-motion`، Data Saver، نوع اتصال، حافظه، تعداد هسته و نوع Pointer سطح تجربه را تعیین می‌کنند.
- سطح `minimal` هیچ Smooth Scroll، Cursor سفارشی یا Motion سنگین ندارد.
- سطح `balanced` فقط Revealهای ضروری را اجرا می‌کند.
- سطح `full` تجربه کامل برند را فعال می‌کند.
- تمام Tickerها، Listenerها، MutationObserverها، Cursor DOM و ScrollTriggerها در تغییر Route پاک‌سازی می‌شوند.
- Split Text با DOM امن ساخته می‌شود و از `innerHTML` استفاده نمی‌کند.

## سیاست PWA و Cache

- Navigation همیشه از شبکه و با `no-store` دریافت می‌شود؛ در نبود شبکه فقط صفحه آفلاین نمایش داده می‌شود.
- API، Admin، Auth، Cart، Checkout، Orders، Panel و Profile هرگز وارد Cache سرویس‌ورکر نمی‌شوند.
- Assetهای Hashشده Cache-first و Media/Fontها Stale-while-revalidate هستند.
- Cache رسانه سقف تعداد دارد و نسخه‌های قدیمی هنگام Activate حذف می‌شوند.
- Service Worker هر ساعت، هنگام بازگشت اینترنت و هنگام بازگشت Tab بررسی می‌شود.
- نسخه جدید فقط با تأیید کاربر فعال می‌شود تا وسط Checkout برنامه عوض نشود.

## Core Web Vitals

رستا بدون Dependency جدید این معیارها را ثبت می‌کند:

- LCP
- CLS
- INP
- FCP
- TTFB

هر Metric از طریق رویداد `rosta:web-vital` در مرورگر منتشر می‌شود. ارسال شبکه‌ای فقط وقتی فعال است که `VITE_PERFORMANCE_ENDPOINT` تنظیم شده باشد؛ ارسال بدون Credential و با Fail-silent انجام می‌شود.

## Bundle Contract

- Chunkهای Motion، Three.js، Charts، TanStack و React از Application جدا هستند.
- هر JavaScript chunk هم بودجه Raw و هم بودجه Gzip دارد.
- `bundle-report.json` سهم Feature categoryها را ثبت می‌کند.
- Phase 16 audit جزو `bun run check` است و حذف تصادفی هرکدام از مرزها Build را متوقف می‌کند.

## Reconnect و صداقت UI

- قطع و بازگشت اتصال به کاربر اعلام می‌شود.
- عملیات خرید در حالت آفلاین موفق نمایش داده نمی‌شود.
- React Query پس از بازگشت اتصال Refetch می‌کند، روی Focus درخواست اضافی نمی‌زند و فقط خطاهای موقت شبکه/سرور را با سقف محدود Retry می‌کند.

## مرز تجاری دائمی

- تمام محصولات فقط دانه کامل هستند.
- این فاز هیچ Grind selector، Grind state یا منطق آسیاب اضافه نمی‌کند.
- Laravel همچنان مرجع قیمت، موجودی، سفارش، پرداخت و انتشار است.

## Gateهای نهایی روی سرور

Source scope این فاز پیش از ورود به سرور بسته می‌شود. پذیرش Runtime باید روی Toolchain واقعی انجام شود:

1. `bun install --frozen-lockfile`
2. `bun run check`
3. `bun run check:all`
4. Production SSR preview
5. Lighthouse روی موبایل و دسکتاپ
6. آزمایش Install/Update/Offline PWA
7. Browser audit سه Viewport با Network throttling و Reduced Motion
8. ثبت Baseline واقعی LCP/CLS/INP و Bundle report

تا زمانی که این Gateهای Runtime واقعاً اجرا نشده‌اند، PR باید Draft بماند و ادعای امتیاز Lighthouse یا صفر باگ Runtime نباید ثبت شود.
