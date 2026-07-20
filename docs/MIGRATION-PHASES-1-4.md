# انتقال فازهای ۱ تا ۴ به پروژه اصلی رستا

تاریخ: ۲۰۲۶-۰۷-۲۰

## هدف

پروژه `of-rosta-coffee2` منبع اصلی متصل به Lovable است. قابلیت‌هایی که قبلاً روی مخزن آزمایشی `remix-of-of-rosta-coffee2` توسعه داده شده بودند، بدون حذف صفحات و طراحی اورجینال Lovable به این مخزن منتقل شدند.

شاخه انتقال:

- `agent/migrate-phases-1-4`
- Pull Request: `#1`
- Base: `main`

## فاز ۱ — پایداری و زیرساخت

- نسخه‌های اصلی Runtime و وابستگی‌ها Pin شدند.
- وابستگی‌های UI اورجینال Lovable و Radix حفظ شدند.
- TypeScript، ESLint، Build و Bundle Budget در CI قرار گرفتند.
- Preview تولیدی بر مبنای Cloudflare Worker حفظ شد.
- Environmentهای `VITE_SITE_URL`، `VITE_API_URL` و `VITE_ALLOW_INDEXING` تعریف شدند.
- API Client مشترک با `credentials: include` و XSRF Header اضافه شد.
- فونت‌های Vazirmatn، Playfair Display و DM Mono به‌صورت Self-host استفاده می‌شوند.
- PWA، Service Worker، Offline Fallback و مرزبندی Cache صفحات خصوصی اضافه شدند.

## فاز ۲ — Design System

- Primitiveهای RTL برای Button، Field، Feedback، Empty State و Skeleton اضافه شدند.
- Dialog و Drawer مبتنی بر `<dialog>` اضافه شدند.
- Form Summary به Fieldهای دارای خطا لینک می‌شود.
- Toast دارای Live Region اضافه شد.
- Route زنده و `noindex` در `/design-system` اضافه شد.
- Tokenهای برند رستا و تایپوگرافی پروژه اصلی حفظ شدند.

## فاز ۳ — هویت و حساب مشتری

- ورود، ثبت‌نام و بازیابی Passwordless OTP اضافه شد.
- State کوتاه‌عمر OTP فقط در `sessionStorage` نگه‌داری می‌شود.
- Access Token یا Refresh Token در Web Storage ذخیره نمی‌شود.
- Redirect داخلی امن و جلوگیری از Open Redirect اضافه شد.
- Guard مشترک حساب برای Profile و Orders اضافه شد.
- خطاهای 401، 403، 422، Network و 5xx مرزبندی شدند.
- Profile، ویرایش مشخصات و Address Book به Laravel API متصل شدند.
- Orders List و Order Detail به API متصل شدند.
- Sub-order، اقلام، آدرس، Shipment، کد رهگیری و Timeline نمایش داده می‌شوند.
- Logout، پاک‌سازی Query Cache و Routeهای خصوصی `noindex` شدند.

## فاز ۴ — کاتالوگ و جستجو

- Product List، Product Detail و Related Products Adapter اضافه شدند.
- Roastery List و Roastery Detail Adapter اضافه شدند.
- Search Adapter با Debounce و URL State اضافه شد.
- Responseهای Backend از `snake_case` به Contractهای `camelCase` تبدیل می‌شوند.
- Routeهای عملیاتی Products، Roasteries و Search از Seed جدا شدند.
- Filter، Sort و Pagination کاتالوگ مبتنی بر URL هستند.
- قیمت، وزن، Media، تاریخ رست و موجودی Variant از API خوانده می‌شوند.
- Variant ناموجود قابل انتخاب نیست.
- Product، Organization، ItemList و FAQ JSON-LD داده‌محور اضافه شدند.
- Sitemap محصولات منتشرشده و روستری‌ها را با Fail-safe از API دریافت می‌کند.
- Search همیشه `noindex,follow` است.

## قانون ثابت محصول

- تمام محصولات فقط **دانه کامل قهوه** هستند.
- هیچ Grind Selector یا فیلد آسیاب در Product، Variant، Cart، Checkout و Order وجود ندارد.

## مرز فاز ۵

سبد فعلی فقط Preview محلی توسعه است. موارد زیر در فاز ۵ انجام می‌شوند:

- اتصال Variant واقعی به Cart
- محدودسازی قطعی سبد به یک روستری در Context و Backend
- `POST /cart/validate` و Checkout Quote سمت سرور
- رزرو و کسر اتمیک موجودی
- ایجاد Order واقعی
- Split Payment
- Payment Callback و Verify
- صفحات پرداخت موفق، ناموفق و بازپرداخت

تا قبل از فاز ۵، Checkout هیچ شماره سفارش تصادفی، پرداخت یا موفقیت ساختگی تولید نمی‌کند.
