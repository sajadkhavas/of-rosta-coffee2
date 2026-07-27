import { expect, test, type Browser, type Page } from "@playwright/test";
import { randomUUID } from "node:crypto";
import { spawnSync } from "node:child_process";

const pendingOtpKey = "rosta.pending-otp.v1";
const apiBase = "http://127.0.0.1:8000/api/v1";
type JsonRecord = Record<string, unknown>;

interface ApiResult {
  status: number;
  body: unknown;
}

function record(value: unknown, label: string): JsonRecord {
  expect(value, `${label} must be an object`).not.toBeNull();
  expect(typeof value, `${label} must be an object`).toBe("object");
  expect(Array.isArray(value), `${label} must not be an array`).toBe(false);
  return value as JsonRecord;
}

function array(value: unknown, label: string): unknown[] {
  expect(Array.isArray(value), `${label} must be an array`).toBe(true);
  return value as unknown[];
}

function text(value: unknown, label: string): string {
  expect(typeof value, `${label} must be a string`).toBe("string");
  expect((value as string).length, `${label} must not be empty`).toBeGreaterThan(0);
  return value as string;
}

function number(value: unknown, label: string): number {
  expect(typeof value, `${label} must be a number`).toBe("number");
  return value as number;
}

function data(result: ApiResult): unknown {
  return record(result.body, "API response").data;
}

async function api(
  page: Page,
  path: string,
  method: "GET" | "POST" | "PATCH" | "DELETE" = "GET",
  body?: unknown,
): Promise<ApiResult> {
  return page.evaluate(
    async ({ base, path, method, body }) => {
      const headers: Record<string, string> = { Accept: "application/json" };
      const xsrfCookie = document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="));
      if (body !== undefined) headers["Content-Type"] = "application/json";
      if (method !== "GET" && xsrfCookie) {
        headers["X-XSRF-TOKEN"] = decodeURIComponent(xsrfCookie.slice("XSRF-TOKEN=".length));
      }

      const response = await fetch(`${base}${path}`, {
        method,
        credentials: "include",
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
      });
      const raw = await response.text();
      let payload: unknown = null;
      if (raw !== "") {
        try {
          payload = JSON.parse(raw);
        } catch {
          payload = raw;
        }
      }
      return { status: response.status, body: payload };
    },
    { base: apiBase, path, method, body },
  );
}

function consumeAcceptanceOtp(challengeId: string): string {
  if (!/^[0-9A-HJKMNP-TV-Z]{26}$/i.test(challengeId)) {
    throw new Error("R3C2 received an invalid OTP challenge identifier.");
  }

  for (let attempt = 0; attempt < 30; attempt += 1) {
    const result = spawnSync("php", ["artisan", "rosta:acceptance-otp", challengeId, "--no-ansi"], {
      cwd: "backend",
      encoding: "utf8",
      env: process.env,
      stdio: ["ignore", "pipe", "pipe"],
    });
    const output = result.stdout.trim();
    if (result.status === 0 && /^\d{6}$/.test(output)) return output;
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 250);
  }
  throw new Error("The one-time R3C2 OTP was not delivered before the bounded deadline.");
}

async function login(page: Page, mobile: string, redirect: string): Promise<JsonRecord> {
  await page.goto(`/auth/?mode=login&redirect=${encodeURIComponent(redirect)}`);
  await page.getByLabel("شماره موبایل").fill(mobile);
  await page.getByRole("button", { name: "دریافت کد ورود" }).click();
  await page.waitForURL(/\/auth\/verify/);

  const challengeId = await page.evaluate((storageKey) => {
    const raw = sessionStorage.getItem(storageKey);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as { requestId?: unknown };
    return typeof parsed.requestId === "string" ? parsed.requestId : null;
  }, pendingOtpKey);
  expect(challengeId).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/i);

  await page.getByLabel("کد شش‌رقمی").fill(consumeAcceptanceOtp(challengeId as string));
  await page.getByRole("button", { name: "تأیید و ادامه" }).click();
  await page.waitForURL((url) => url.pathname === redirect);

  const me = await api(page, "/me");
  expect(me.status).toBe(200);
  return record(data(me), "authenticated user");
}

async function rolePage(browser: Browser, mobile: string, redirect: string) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const user = await login(page, mobile, redirect);
  return { context, page, user };
}

test.describe.configure({ mode: "serial" });

test("R3C2 completes commerce, scoped seller, administrator and adversarial journeys", async ({
  browser,
}) => {
  const customer = await rolePage(browser, "09123456789", "/profile");
  expect(array(customer.user.roles, "customer roles")).toContain("customer");
  expect((await api(customer.page, "/seller/roasteries")).status).toBe(403);
  expect((await api(customer.page, "/admin/roasteries?status=verified&per_page=100")).status).toBe(
    403,
  );

  const productResponse = await api(customer.page, "/products/ethiopia-sidamo-whole-bean");
  expect(productResponse.status).toBe(200);
  const product = record(data(productResponse), "product");
  const productId = text(product.id, "product id");
  const variants = array(product.variants, "product variants").map((item) =>
    record(item, "product variant"),
  );
  const selectedVariant = variants.find(
    (item) => number(item.weight_grams, "variant weight") === 100,
  );
  expect(selectedVariant).toBeDefined();
  const variantId = text(selectedVariant?.id, "100g variant id");

  const addressesResponse = await api(customer.page, "/me/addresses");
  expect(addressesResponse.status).toBe(200);
  const addresses = array(data(addressesResponse), "customer addresses").map((item) =>
    record(item, "customer address"),
  );
  const addressId = text(addresses[0].id, "address id");

  const quoteResponse = await api(customer.page, "/checkout/quote", "POST", {
    items: [{ variant_id: variantId, quantity: 1 }],
    address_id: addressId,
    coupon_code: null,
  });
  expect(quoteResponse.status).toBe(200);
  const quoteId = text(record(data(quoteResponse), "checkout quote").id, "quote id");

  const orderKey = `r3c2-order-${randomUUID()}`;
  const orderRequest = { quote_id: quoteId, idempotency_key: orderKey, notes: "R3C2 acceptance" };
  const orderResponse = await api(customer.page, "/orders", "POST", orderRequest);
  expect(orderResponse.status).toBe(201);
  const order = record(data(orderResponse), "created order");
  const orderId = text(order.id, "order id");
  const subOrders = array(order.sub_orders, "sub orders").map((item) => record(item, "sub order"));
  const orderItems = array(subOrders[0].items, "order items").map((item) =>
    record(item, "order item"),
  );
  const orderItemId = text(orderItems[0].id, "order item id");

  const replayedOrder = await api(customer.page, "/orders", "POST", orderRequest);
  expect([200, 201]).toContain(replayedOrder.status);
  expect(text(record(data(replayedOrder), "replayed order").id, "replayed order id")).toBe(orderId);

  const idempotencyConflict = await api(customer.page, "/orders", "POST", {
    ...orderRequest,
    notes: "conflicting payload",
  });
  expect(idempotencyConflict.status).toBe(409);

  const consumedQuote = await api(customer.page, "/orders", "POST", {
    quote_id: quoteId,
    idempotency_key: `r3c2-consumed-${randomUUID()}`,
    notes: "stale quote replay",
  });
  expect(consumedQuote.status).toBe(409);

  const paymentKey = `r3c2-payment-${randomUUID()}`;
  const paymentRequest = { order_id: orderId, idempotency_key: paymentKey };
  const paymentResponse = await api(customer.page, "/payments/request", "POST", paymentRequest);
  expect(paymentResponse.status).toBe(201);
  const payment = record(data(paymentResponse), "payment attempt");
  const paymentId = text(payment.payment_id, "payment id");
  const paymentRedirect = text(payment.redirect_url, "payment redirect");
  expect(new URL(paymentRedirect).hostname).toBe("127.0.0.1");

  const replayedPayment = await api(customer.page, "/payments/request", "POST", paymentRequest);
  expect([200, 201]).toContain(replayedPayment.status);
  expect(
    text(record(data(replayedPayment), "replayed payment").payment_id, "replayed payment id"),
  ).toBe(paymentId);

  const callbackResponse = await customer.page.goto(paymentRedirect);
  expect(callbackResponse?.status()).toBe(200);
  expect(new URL(customer.page.url()).pathname).toBe("/checkout");

  const verification = await api(
    customer.page,
    `/payments/${encodeURIComponent(paymentId)}/verify`,
    "POST",
  );
  expect(verification.status).toBe(200);
  expect(text(record(data(verification), "payment verification").status, "payment status")).toBe(
    "paid",
  );
  const paidOrder = await api(customer.page, `/orders/${encodeURIComponent(orderId)}`);
  expect(paidOrder.status).toBe(200);
  expect(text(record(data(paidOrder), "paid order").status, "paid order status")).toBe("paid");

  const seller = await rolePage(browser, "09120000002", "/panel/manage");
  expect(array(seller.user.roles, "seller roles")).toContain("roastery_owner");
  await expect(
    seller.page.getByRole("heading", { name: "ویرایش اطلاعات و کاتالوگ" }),
  ).toBeVisible();

  const sellerRoasteriesResponse = await api(seller.page, "/seller/roasteries");
  expect(sellerRoasteriesResponse.status).toBe(200);
  const sellerRoasteries = array(
    record(data(sellerRoasteriesResponse), "seller roastery envelope").items,
    "seller roasteries",
  ).map((item) => record(item, "seller roastery"));
  expect(sellerRoasteries).toHaveLength(1);
  const ownedRoasteryId = text(sellerRoasteries[0].id, "owned roastery id");

  const publicRoasteriesResponse = await api(customer.page, "/roasteries?per_page=100");
  expect(publicRoasteriesResponse.status).toBe(200);
  const publicRoasteries = array(data(publicRoasteriesResponse), "public roasteries").map((item) =>
    record(item, "public roastery"),
  );
  const foreignRoastery = publicRoasteries.find(
    (item) => item.slug === "foreign-acceptance-roastery",
  );
  expect(foreignRoastery).toBeDefined();
  const foreignRoasteryId = text(foreignRoastery?.id, "foreign roastery id");

  expect([403, 404]).toContain(
    (await api(seller.page, `/seller/roasteries/${encodeURIComponent(foreignRoasteryId)}`)).status,
  );
  expect((await api(seller.page, "/admin/roasteries?status=verified&per_page=100")).status).toBe(
    403,
  );
  expect((await api(seller.page, `/orders/${encodeURIComponent(orderId)}`)).status).toBe(404);
  expect(
    (await api(seller.page, `/payments/${encodeURIComponent(paymentId)}/verify`, "POST")).status,
  ).toBe(404);

  const sellerOrdersResponse = await api(
    seller.page,
    `/seller/roasteries/${encodeURIComponent(ownedRoasteryId)}/orders?per_page=100`,
  );
  expect(sellerOrdersResponse.status).toBe(200);
  const sellerOrders = array(data(sellerOrdersResponse), "seller orders").map((item) =>
    record(item, "seller order"),
  );
  expect(sellerOrders.some((item) => item.id === orderId)).toBe(true);

  const fulfillmentPath = `/seller/roasteries/${encodeURIComponent(ownedRoasteryId)}/orders/${encodeURIComponent(orderId)}/fulfillment`;
  const invalidDelivered = await api(seller.page, fulfillmentPath, "PATCH", {
    status: "delivered",
  });
  expect(invalidDelivered.status).toBe(409);

  const manualAcceptance = await api(seller.page, fulfillmentPath, "PATCH", {
    status: "accepted",
  });
  expect(manualAcceptance.status).toBe(422);

  for (const transition of [
    { status: "preparing" },
    { status: "ready_to_ship" },
    { status: "shipped", carrier: "پست", tracking_code: "R3C2TRACK001" },
  ]) {
    expect((await api(seller.page, fulfillmentPath, "PATCH", transition)).status).toBe(200);
  }

  const sellerDelivery = await api(seller.page, fulfillmentPath, "PATCH", { status: "delivered" });
  expect(sellerDelivery.status).toBe(409);

  const administrator = await rolePage(browser, "09120000001", "/admin/operations");
  expect(array(administrator.user.roles, "administrator roles")).toContain("administrator");
  await expect(
    administrator.page.getByRole("heading", { name: "نظارت کاتالوگ، تحویل، تسویه و سلامت عملیات" }),
  ).toBeVisible();
  const adminDelivery = await api(
    administrator.page,
    `/admin/orders/${encodeURIComponent(orderId)}/fulfillment`,
    "PATCH",
    { status: "delivered" },
  );
  expect(adminDelivery.status).toBe(200);

  const deliveredOrder = await api(customer.page, `/orders/${encodeURIComponent(orderId)}`);
  expect(deliveredOrder.status).toBe(200);
  const deliveredOrderData = record(data(deliveredOrder), "delivered order");
  expect(text(deliveredOrderData.status, "delivered status")).toBe("delivered");
  const deliveredSubOrder = record(
    array(deliveredOrderData.sub_orders, "delivered sub-orders")[0],
    "delivered sub-order",
  );
  const deliveryState = record(deliveredSubOrder.delivery, "delivery state");
  expect(text(deliveryState.settlement_state, "settlement state")).toBe("dispute_hold");
  expect(text(deliveryState.dispute_window_ends_at, "dispute window").length).toBeGreaterThan(10);

  const reviewResponse = await api(customer.page, "/reviews", "POST", {
    order_item_id: orderItemId,
    rating: 5,
    title: "پذیرش R3C2",
    body: "این نظر فقط پس از خرید و تحویل واقعی در پذیرش یکپارچه ثبت شده است.",
  });
  expect(reviewResponse.status).toBe(201);
  const review = record(data(reviewResponse), "created review");
  const reviewId = text(review.id, "review id");
  expect(review.is_verified_purchase).toBe(true);
  expect(review.status).toBe("pending");

  const duplicateReview = await api(customer.page, "/reviews", "POST", {
    order_item_id: orderItemId,
    rating: 4,
    body: "این ارسال تکراری باید به صورت قطعی رد شود.",
  });
  expect(duplicateReview.status).toBe(409);

  const adminRoasteries = await api(
    administrator.page,
    "/admin/roasteries?status=verified&per_page=100",
  );
  expect(adminRoasteries.status).toBe(200);
  const adminRoasteryItems = array(
    record(data(adminRoasteries), "admin roastery envelope").items,
    "admin roasteries",
  ).map((item) => record(item, "admin roastery"));
  expect(adminRoasteryItems.some((item) => item.id === ownedRoasteryId)).toBe(true);
  expect(adminRoasteryItems.some((item) => item.id === foreignRoasteryId)).toBe(true);

  const pendingReviews = await api(
    administrator.page,
    "/admin/reviews?status=pending&per_page=100",
  );
  expect(pendingReviews.status).toBe(200);
  const pendingItems = array(
    record(data(pendingReviews), "pending review envelope").items,
    "pending reviews",
  ).map((item) => record(item, "pending review"));
  expect(pendingItems.some((item) => item.id === reviewId)).toBe(true);

  const approvedReview = await api(
    administrator.page,
    `/admin/reviews/${encodeURIComponent(reviewId)}`,
    "PATCH",
    { status: "approved", reason: "R3C2 verified-purchase acceptance" },
  );
  expect(approvedReview.status).toBe(200);
  expect(record(data(approvedReview), "approved review").status).toBe("approved");

  expect((await api(administrator.page, `/orders/${encodeURIComponent(orderId)}`)).status).toBe(
    404,
  );
  expect(
    (
      await api(administrator.page, "/admin/reviews/01INVALIDR3C2REVIEW00000000", "PATCH", {
        status: "approved",
      })
    ).status,
  ).toBe(404);

  const publicReviews = await api(
    customer.page,
    `/products/${encodeURIComponent(text(product.slug, "product slug"))}/reviews`,
  );
  expect(publicReviews.status).toBe(200);
  const publicReviewItems = array(
    record(data(publicReviews), "public review data").items,
    "public review items",
  ).map((item) => record(item, "public review"));
  expect(
    publicReviewItems.some((item) => item.id === reviewId && item.is_verified_purchase === true),
  ).toBe(true);

  expect(productId).not.toBe("");
  await Promise.all([
    customer.context.close(),
    seller.context.close(),
    administrator.context.close(),
  ]);
});
