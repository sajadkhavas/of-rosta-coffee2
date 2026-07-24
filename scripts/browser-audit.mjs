import { mkdir, writeFile } from "node:fs/promises";
import { chromium } from "playwright";
import axe from "axe-core";

const baseURL = process.env.AUDIT_BASE_URL ?? "http://127.0.0.1:4173";
const outputDir = process.env.AUDIT_OUTPUT_DIR ?? "browser-audit";

const routes = [
  "/",
  "/products",
  "/products/ethiopia-yirgacheffe",
  "/roasteries",
  "/roasteries/emkan",
  "/quiz",
  "/search?q=%D8%A7%D8%AA%DB%8C%D9%88%D9%BE%DB%8C",
  "/cart",
  "/checkout",
  "/auth?mode=login",
  "/auth?mode=register",
  "/auth?mode=recover",
  "/auth/verify",
  "/forbidden",
  "/profile",
  "/orders",
  "/orders/order-1",
  "/about",
  "/contact",
  "/blog",
  "/privacy",
  "/terms",
  "/design-system",
];

const screenshotRoutes = new Set([
  "/",
  "/products",
  "/products/ethiopia-yirgacheffe",
  "/roasteries",
  "/profile",
  "/orders/order-1",
  "/design-system",
]);

const viewports = [
  { name: "mobile", width: 390, height: 844, isMobile: true, hasTouch: true },
  { name: "tablet", width: 768, height: 1024, isMobile: false, hasTouch: true },
  { name: "desktop", width: 1440, height: 1000, isMobile: false, hasTouch: false },
];

const media = (id, alt, width = 900, height = 900) => ({
  id,
  alt,
  width,
  height,
  blur_data_url: null,
  sources: [
    {
      url: `https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=${width}&q=75`,
      width,
      format: "jpeg",
    },
  ],
});

const roastery = {
  id: "roastery-1",
  name: "روستری امکان",
  slug: "emkan",
  city: "تهران",
  is_verified: true,
  logo: media("logo-1", "لوگوی روستری امکان", 240, 240),
  cover: media("cover-1", "کاور روستری امکان", 1400, 700),
  preparation_time: { min_hours: 12, max_hours: 36 },
  rating: { value: 4.8, count: 126 },
};

const product = {
  id: "product-1",
  name: "اتیوپی یرگاچف",
  slug: "ethiopia-yirgacheffe",
  short_description: "دانه کامل قهوه با عطر گل، مرکبات و چای سیاه.",
  origin: { id: "ethiopia", name: "اتیوپی", country_code: "ET" },
  processing_method: "washed",
  roast_level: "light",
  arabica_percentage: 100,
  tasting_notes: ["گل یاس", "برگاموت", "چای سیاه"],
  primary_image: media("product-1-main", "دانه قهوه اتیوپی یرگاچف"),
  roastery,
  variants: [
    {
      id: "variant-250",
      sku: "EMK-ETH-250",
      weight_grams: 250,
      price: 2450000,
      compare_at_price: null,
      currency: "IRR",
      is_available: true,
      available_quantity: 12,
    },
    {
      id: "variant-500",
      sku: "EMK-ETH-500",
      weight_grams: 500,
      price: 4700000,
      compare_at_price: null,
      currency: "IRR",
      is_available: false,
      available_quantity: 0,
    },
  ],
  latest_roast_batch: {
    id: "batch-1",
    batch_code: "EMK-1405-04-29",
    roasted_at: "2026-07-20T08:00:00+03:30",
    available_from: "2026-07-21T08:00:00+03:30",
  },
  status: "published",
};

const productDetail = {
  ...product,
  description: "این قهوه تک‌خاستگاه با اسیدیته شفاف و شیرینی طبیعی برای دم‌آوری فیلتر مناسب است.",
  gallery: [
    media("product-1-main", "دانه قهوه اتیوپی یرگاچف"),
    media("product-1-detail", "نمای نزدیک دانه‌های قهوه"),
  ],
  brewing_suggestions: ["V60", "کمکس", "اروپرس"],
  seo: {
    title: "خرید قهوه اتیوپی یرگاچف از روستری امکان | رستا",
    description: "دانه کامل قهوه اتیوپی تازه‌رست با موجودی و تاریخ رست واقعی.",
  },
};

const order = {
  id: "order-1",
  order_number: "R-1405-0001",
  status: "processing",
  placed_at: "2026-07-20T10:00:00+03:30",
  grand_total: 2650000,
  currency: "IRR",
  subtotal: 2450000,
  shipping_total: 200000,
  discount_total: 0,
  address: {
    id: "address-1",
    title: "خانه",
    recipient_name: "سجاد",
    recipient_mobile: "09123456789",
    province: "تهران",
    city: "تهران",
    address_line: "خیابان نمونه، کوچه نمونه، پلاک ۱",
    postal_code: "1234567890",
    is_default: true,
  },
  sub_orders: [
    {
      id: "sub-order-1",
      status: "preparing",
      roastery: { id: roastery.id, name: roastery.name, slug: roastery.slug },
      items: [
        {
          id: "line-1",
          product: {
            id: product.id,
            name: product.name,
            slug: product.slug,
            primary_image: product.primary_image,
          },
          variant: {
            id: "variant-250",
            sku: "EMK-ETH-250",
            weight_grams: 250,
            price: 2450000,
            currency: "IRR",
          },
          quantity: 1,
          line_total: 2450000,
        },
      ],
      subtotal: 2450000,
      shipping_total: 200000,
      shipment: {
        id: "shipment-1",
        carrier: "پست پیشتاز",
        tracking_code: "123456789012345678901234",
        status: "pending",
        shipped_at: null,
        delivered_at: null,
      },
    },
  ],
};

function json(route, body, status = 200) {
  return route.fulfill({
    status,
    contentType: "application/json; charset=utf-8",
    headers: {
      "Access-Control-Allow-Origin": baseURL,
      "Access-Control-Allow-Credentials": "true",
    },
    body: JSON.stringify(body),
  });
}

async function mockApi(context) {
  await context.route("http://127.0.0.1:8000/api/v1/**", async (route) => {
    const request = route.request();
    if (request.method() === "OPTIONS") {
      return route.fulfill({
        status: 204,
        headers: {
          "Access-Control-Allow-Origin": baseURL,
          "Access-Control-Allow-Credentials": "true",
          "Access-Control-Allow-Headers": "Content-Type, X-Requested-With, X-XSRF-TOKEN",
          "Access-Control-Allow-Methods": "GET,POST,PATCH,DELETE,OPTIONS",
        },
      });
    }

    const url = new URL(request.url());
    const path = url.pathname.replace(/^\/api\/v1/, "");

    if (path === "/products") {
      return json(route, {
        data: [product],
        meta: { current_page: 1, last_page: 1, per_page: 24, total: 1 },
        links: { first: null, last: null, prev: null, next: null },
      });
    }
    if (path === `/products/${product.slug}/related`) {
      return json(route, { data: [product] });
    }
    if (path === `/products/${product.slug}`) {
      return json(route, { data: productDetail });
    }
    if (path === "/roasteries") {
      return json(route, {
        data: [roastery],
        meta: { current_page: 1, last_page: 1, per_page: 18, total: 1 },
        links: { first: null, last: null, prev: null, next: null },
      });
    }
    if (path === `/roasteries/${roastery.slug}`) {
      return json(route, {
        data: {
          ...roastery,
          description: "روستری امکان با تمرکز بر قهوه‌های تک‌خاستگاه و رست روشن فعالیت می‌کند.",
          shipping_policy: "ارسال سفارش‌ها پس از آماده‌سازی و ثبت تاریخ رست انجام می‌شود.",
        },
      });
    }
    if (path === "/search") {
      return json(route, {
        data: { products: [product], roasteries: [roastery], suggestions: ["اتیوپی", "رست روشن"] },
      });
    }
    if (path === "/me") {
      return json(route, {
        data: {
          id: "user-1",
          mobile: "09123456789",
          name: "سجاد",
          email: "sajad@example.com",
          roles: ["customer"],
        },
      });
    }
    if (path === "/me/addresses") {
      return json(route, { data: [order.address] });
    }
    if (path === "/orders") {
      return json(route, {
        data: [order],
        meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 },
        links: { first: null, last: null, prev: null, next: null },
      });
    }
    if (path === `/orders/${order.id}`) {
      return json(route, { data: order });
    }
    if (path === "/auth/otp/request") {
      return json(
        route,
        {
          data: {
            request_id: "00000000-0000-4000-8000-000000000001",
            expires_in: 300,
            retry_after: 60,
          },
        },
        202,
      );
    }
    if (path === "/auth/otp/verify") {
      return json(route, {
        data: {
          id: "user-1",
          mobile: "09123456789",
          name: "سجاد",
          email: null,
          roles: ["customer"],
        },
      });
    }
    if (path === "/auth/logout") return route.fulfill({ status: 204 });

    return json(route, { error: { code: "not_found", message: "Mock endpoint not found" } }, 404);
  });
}

await mkdir(`${outputDir}/screenshots`, { recursive: true });
const browser = await chromium.launch({ headless: true });
const report = {
  generatedAt: new Date().toISOString(),
  baseURL,
  routeCount: routes.length,
  viewportCount: viewports.length,
  auditedCases: 0,
  results: [],
  pwa: null,
  reducedMotion: null,
  summary: {},
};

for (const viewport of viewports) {
  const context = await browser.newContext({
    viewport: { width: viewport.width, height: viewport.height },
    isMobile: viewport.isMobile,
    hasTouch: viewport.hasTouch,
    locale: "fa-IR",
    colorScheme: "dark",
    reducedMotion: "no-preference",
    serviceWorkers: "allow",
  });
  await mockApi(context);

  for (const routePath of routes) {
    const page = await context.newPage();
    const consoleMessages = [];
    const pageErrors = [];
    page.on("console", (message) => {
      if (["error", "warning"].includes(message.type())) {
        consoleMessages.push({ type: message.type(), text: message.text() });
      }
    });
    page.on("pageerror", (error) => pageErrors.push(error.message));

    let status = null;
    let navigationError = null;
    try {
      const response = await page.goto(`${baseURL}${routePath}`, {
        waitUntil: "domcontentloaded",
        timeout: 30_000,
      });
      status = response?.status() ?? null;
      await page.waitForTimeout(700);
    } catch (error) {
      navigationError = error instanceof Error ? error.message : String(error);
    }

    const dom = navigationError
      ? null
      : await page.evaluate(() => {
          const visible = (element) => {
            const style = window.getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            return (
              style.display !== "none" &&
              style.visibility !== "hidden" &&
              rect.width > 0 &&
              rect.height > 0
            );
          };
          const overflowElements = [...document.querySelectorAll("body *")]
            .filter(visible)
            .filter((element) => {
              const rect = element.getBoundingClientRect();
              return rect.right > window.innerWidth + 2 || rect.left < -2;
            })
            .slice(0, 12)
            .map((element) => ({
              tag: element.tagName.toLowerCase(),
              id: element.id || null,
              text: (element.textContent ?? "").trim().replace(/\s+/g, " ").slice(0, 100),
              rect: {
                x: Math.round(element.getBoundingClientRect().x),
                width: Math.round(element.getBoundingClientRect().width),
                right: Math.round(element.getBoundingClientRect().right),
              },
            }));
          return {
            title: document.title,
            lang: document.documentElement.lang,
            dir: document.documentElement.dir,
            h1Count: document.querySelectorAll("h1").length,
            mainCount: document.querySelectorAll("main").length,
            horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 2,
            overflowElements,
          };
        });

    let axeViolations = [];
    if (!navigationError) {
      await page.addScriptTag({ content: axe.source });
      axeViolations = await page.evaluate(async () => {
        const result = await window.axe.run(document, {
          runOnly: {
            type: "tag",
            values: ["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"],
          },
        });
        return result.violations
          .filter((violation) => ["critical", "serious"].includes(violation.impact))
          .map((violation) => ({
            id: violation.id,
            impact: violation.impact,
            help: violation.help,
            targets: violation.nodes.slice(0, 5).map((node) => node.target),
          }));
      });
    }

    if (screenshotRoutes.has(routePath)) {
      const slug =
        routePath === "/"
          ? "home"
          : routePath.replace(/^\//, "").replaceAll("/", "--").replaceAll("?", "--");
      await page.screenshot({
        path: `${outputDir}/screenshots/${viewport.name}--${slug}.png`,
        fullPage: true,
        animations: "disabled",
      });
    }

    report.results.push({
      viewport: viewport.name,
      route: routePath,
      status,
      navigationError,
      pageErrors,
      consoleMessages,
      dom,
      axeViolations,
    });
    report.auditedCases += 1;
    await page.close();
  }
  await context.close();
}

{
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
    colorScheme: "dark",
    reducedMotion: "reduce",
    serviceWorkers: "block",
  });
  await mockApi(context);
  const page = await context.newPage();
  await page.goto(`${baseURL}/`, {
    waitUntil: "domcontentloaded",
    timeout: 30_000,
  });
  await page.waitForTimeout(800);
  report.reducedMotion = await page.evaluate(() => ({
    preference: window.matchMedia("(prefers-reduced-motion: reduce)").matches,
    customCursorPresent: Boolean(document.getElementById("rosta-cursor")),
    cursorEnhanced: document.documentElement.classList.contains("cursor-enhanced"),
    canvasCount: document.querySelectorAll("canvas").length,
  }));
  await context.close();
}

{
  const context = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    colorScheme: "dark",
    serviceWorkers: "allow",
  });
  await mockApi(context);
  const page = await context.newPage();
  await page.goto(`${baseURL}/`, {
    waitUntil: "domcontentloaded",
    timeout: 30_000,
  });
  await page.waitForTimeout(1800);
  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(800);

  const browserState = await page.evaluate(async () => {
    const registration =
      "serviceWorker" in navigator ? await navigator.serviceWorker.getRegistration() : null;
    const manifestResponse = await fetch("/manifest.json");
    const manifest = manifestResponse.ok ? await manifestResponse.json() : null;
    const cachedRequests = [];
    if ("caches" in window) {
      for (const key of await caches.keys()) {
        const cache = await caches.open(key);
        for (const request of await cache.keys()) cachedRequests.push(request.url);
      }
    }
    return {
      serviceWorkerSupported: "serviceWorker" in navigator,
      controllerPresent: Boolean(navigator.serviceWorker?.controller),
      activeState: registration?.active?.state ?? null,
      manifestStatus: manifestResponse.status,
      manifest,
      cacheKeys: "caches" in window ? await caches.keys() : [],
      cachedRequests,
    };
  });

  let offlineFallback = null;
  try {
    await context.setOffline(true);
    await page.goto(`${baseURL}/browser-audit-offline-probe`, {
      waitUntil: "domcontentloaded",
      timeout: 12_000,
    });
    offlineFallback = await page.evaluate(() => ({
      title: document.title,
      bodyText: document.body.innerText.trim().replace(/\s+/g, " ").slice(0, 300),
    }));
  } catch (error) {
    offlineFallback = {
      error: error instanceof Error ? error.message : String(error),
    };
  } finally {
    await context.setOffline(false);
  }

  report.pwa = { browserState, offlineFallback };
  await context.close();
}

await browser.close();

const navigationFailures = report.results.filter(
  (item) => item.navigationError || (item.status != null && item.status >= 500),
);
const pageErrors = report.results.flatMap((item) =>
  item.pageErrors.map((error) => ({ ...item, error })),
);
const hydrationWarnings = report.results.flatMap((item) =>
  item.consoleMessages
    .filter((message) => /hydration|did not match|server rendered html/i.test(message.text))
    .map((message) => ({
      viewport: item.viewport,
      route: item.route,
      ...message,
    })),
);
const horizontalOverflow = report.results
  .filter((item) => item.dom?.horizontalOverflow)
  .map((item) => ({
    viewport: item.viewport,
    route: item.route,
    elements: item.dom.overflowElements,
  }));
const axeViolations = report.results.flatMap((item) =>
  item.axeViolations.map((violation) => ({
    viewport: item.viewport,
    route: item.route,
    ...violation,
  })),
);
const privatePrefixes = [
  "/api/",
  "/auth",
  "/cart",
  "/checkout",
  "/profile",
  "/orders",
  "/forbidden",
];
const privateCacheLeaks = (report.pwa?.browserState?.cachedRequests ?? []).filter((requestUrl) => {
  const pathname = new URL(requestUrl).pathname;
  return privatePrefixes.some((prefix) => pathname.startsWith(prefix));
});

report.summary = {
  auditedCases: report.auditedCases,
  navigationFailures,
  pageErrors,
  hydrationWarnings,
  horizontalOverflow,
  axeViolations,
  privateCacheLeaks,
};

const failures = [];
if (navigationFailures.length) failures.push(`navigation failures: ${navigationFailures.length}`);
if (pageErrors.length) failures.push(`page errors: ${pageErrors.length}`);
if (hydrationWarnings.length) failures.push(`hydration warnings: ${hydrationWarnings.length}`);
if (horizontalOverflow.length)
  failures.push(`horizontal overflow cases: ${horizontalOverflow.length}`);
if (axeViolations.length) failures.push(`serious/critical Axe findings: ${axeViolations.length}`);
if (privateCacheLeaks.length) failures.push(`private cache leaks: ${privateCacheLeaks.length}`);
if (!report.pwa?.browserState?.serviceWorkerSupported) failures.push("service worker unsupported");
if (!report.pwa?.browserState?.controllerPresent)
  failures.push("service worker controller missing");
if (report.pwa?.browserState?.activeState !== "activated")
  failures.push("service worker not activated");
if (report.pwa?.browserState?.manifestStatus !== 200) failures.push("manifest not available");
if (report.pwa?.offlineFallback?.error) failures.push("offline fallback failed");
if (report.reducedMotion?.customCursorPresent || report.reducedMotion?.cursorEnhanced) {
  failures.push("custom cursor active with reduced motion");
}
if ((report.reducedMotion?.canvasCount ?? 0) > 0)
  failures.push("canvas active with reduced motion");

await writeFile(`${outputDir}/report.json`, `${JSON.stringify(report, null, 2)}\n`);
await writeFile(
  `${outputDir}/summary.md`,
  `# Browser audit\n\n- Routes: ${routes.length}\n- Viewports: ${viewports.length}\n- Cases: ${report.auditedCases}\n- Navigation failures: ${navigationFailures.length}\n- Page errors: ${pageErrors.length}\n- Hydration warnings: ${hydrationWarnings.length}\n- Overflow cases: ${horizontalOverflow.length}\n- Serious/critical Axe findings: ${axeViolations.length}\n- Private cache leaks: ${privateCacheLeaks.length}\n- PWA controller: ${report.pwa?.browserState?.controllerPresent ? "yes" : "no"}\n- Offline fallback: ${report.pwa?.offlineFallback?.error ? "failed" : "passed"}\n`,
);

if (failures.length) {
  console.error(`Browser audit failed:\n- ${failures.join("\n- ")}`);
  process.exit(1);
}

console.log(`Browser audit passed: ${report.auditedCases} cases, no critical failures.`);
