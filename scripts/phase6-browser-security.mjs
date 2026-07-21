import { mkdir, writeFile } from "node:fs/promises";
import { chromium } from "playwright";

const baseURL = process.env.AUDIT_BASE_URL ?? "http://127.0.0.1:4173";
const outputDir = process.env.AUDIT_OUTPUT_DIR ?? "browser-audit";
const CART_KEY = "rosta_cart_v3";
const EXPECTATION_KEY = "rosta_payment_expectation_v2";
const paymentId = "payment-1";
const orderId = "order-1";
const amount = 2_650_000;

const cartItem = {
  variantId: "variant-250",
  productId: "product-1",
  productSlug: "ethiopia-yirgacheffe",
  productName: "اتیوپی یرگاچف",
  productImageUrl: null,
  roasteryId: "roastery-1",
  roasteryName: "روستری امکان",
  roasterySlug: "emkan",
  weightGrams: 250,
  unitPriceSnapshot: 2_450_000,
  quantity: 1,
  addedAt: Date.now(),
};

const cartEnvelope = {
  version: 3,
  updatedAt: Date.now(),
  items: [cartItem],
};

const expectation = {
  version: 2,
  paymentId,
  orderId,
  amount,
  currency: "IRR",
  createdAt: new Date().toISOString(),
};

const currentUser = {
  id: "user-1",
  mobile: "09123456789",
  name: "سجاد",
  email: "sajad@example.com",
  roles: ["customer"],
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

async function mockApi(context, verifyPayload) {
  await context.route("http://127.0.0.1:8000/api/v1/**", async (route) => {
    const request = route.request();
    if (request.method() === "OPTIONS") {
      return route.fulfill({
        status: 204,
        headers: {
          "Access-Control-Allow-Origin": baseURL,
          "Access-Control-Allow-Credentials": "true",
          "Access-Control-Allow-Headers":
            "Content-Type, X-Requested-With, X-XSRF-TOKEN",
          "Access-Control-Allow-Methods":
            "GET,POST,PATCH,DELETE,OPTIONS",
        },
      });
    }

    const url = new URL(request.url());
    const path = url.pathname.replace(/^\/api\/v1/, "");
    if (path === "/me") return json(route, { data: currentUser });
    if (path === `/payments/${paymentId}/verify`) {
      return json(route, { data: verifyPayload });
    }
    return json(
      route,
      {
        error: {
          code: "request.not_found",
          message: "Mock endpoint not found",
          request_id: "request-browser-security",
        },
      },
      404,
    );
  });
}

async function runScenario(browser, scenario) {
  const context = await browser.newContext({
    viewport: scenario.viewport,
    locale: "fa-IR",
    colorScheme: "dark",
    serviceWorkers: "block",
  });
  await context.addInitScript(
    ({ cartKey, cart, expectationKey, paymentExpectation }) => {
      localStorage.setItem(cartKey, JSON.stringify(cart));
      if (paymentExpectation) {
        sessionStorage.setItem(
          expectationKey,
          JSON.stringify(paymentExpectation),
        );
      } else {
        sessionStorage.removeItem(expectationKey);
      }
    },
    {
      cartKey: CART_KEY,
      cart: cartEnvelope,
      expectationKey: EXPECTATION_KEY,
      paymentExpectation: scenario.expectation,
    },
  );
  await mockApi(context, scenario.verifyPayload);

  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  await page.goto(
    `${baseURL}/checkout?payment_id=${paymentId}&status=paid&order_id=${orderId}`,
    {
      waitUntil: "domcontentloaded",
      timeout: 30_000,
    },
  );
  await page.waitForTimeout(1_000);

  const visibleText = await page.locator("main").innerText();
  const storedCart = await page.evaluate((key) => {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : null;
  }, CART_KEY);
  const cartCount = Array.isArray(storedCart?.items)
    ? storedCart.items.length
    : -1;
  const expectedTextFound = visibleText.includes(scenario.expectedText);
  const cartMatches = cartCount === scenario.expectedCartCount;
  const passed = expectedTextFound && cartMatches && errors.length === 0;

  const result = {
    name: scenario.name,
    passed,
    expectedText: scenario.expectedText,
    expectedTextFound,
    expectedCartCount: scenario.expectedCartCount,
    actualCartCount: cartCount,
    pageErrors: errors,
  };

  if (!passed) {
    await page.screenshot({
      path: `${outputDir}/screenshots/phase6-${scenario.name}.png`,
      fullPage: true,
    });
  }
  await context.close();
  return result;
}

await mkdir(`${outputDir}/screenshots`, { recursive: true });
const browser = await chromium.launch({ headless: true });

const validVerification = {
  payment_id: paymentId,
  status: "paid",
  order_id: orderId,
  order_status: "processing",
  amount,
  currency: "IRR",
  verified_at: new Date().toISOString(),
};

const scenarios = [
  {
    name: "forged-query-without-intent",
    viewport: { width: 390, height: 844 },
    expectation: null,
    verifyPayload: validVerification,
    expectedText: "تأیید پرداخت انجام نشد",
    expectedCartCount: 1,
  },
  {
    name: "mismatched-paid-amount",
    viewport: { width: 1440, height: 1000 },
    expectation,
    verifyPayload: { ...validVerification, amount: amount + 1 },
    expectedText: "تأیید پرداخت انجام نشد",
    expectedCartCount: 1,
  },
  {
    name: "consistent-verified-paid",
    viewport: { width: 390, height: 844 },
    expectation,
    verifyPayload: validVerification,
    expectedText: "پرداخت با موفقیت تأیید شد",
    expectedCartCount: 0,
  },
];

const results = [];
try {
  for (const scenario of scenarios) {
    results.push(await runScenario(browser, scenario));
  }
} finally {
  await browser.close();
}

const report = {
  generatedAt: new Date().toISOString(),
  baseURL,
  results,
  passed: results.every((result) => result.passed),
};
await writeFile(
  `${outputDir}/phase6-browser-security.json`,
  `${JSON.stringify(report, null, 2)}\n`,
  "utf8",
);

if (!report.passed) {
  console.error(JSON.stringify(report, null, 2));
  process.exit(1);
}
console.log("Phase 6 adversarial payment browser acceptance passed.");
