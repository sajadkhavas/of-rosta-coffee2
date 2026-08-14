import { expect, test, type Page } from "@playwright/test";
import { spawnSync } from "node:child_process";

const pendingOtpKey = "rosta.pending-otp.v1";

function consumeAcceptanceOtp(challengeId: string): string {
  if (!/^[0-9A-HJKMNP-TV-Z]{26}$/i.test(challengeId)) {
    throw new Error("PS5B received an invalid OTP challenge identifier.");
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

  throw new Error("The one-time PS5B OTP was not delivered before the bounded deadline.");
}

async function loginSeller(page: Page): Promise<void> {
  await page.goto("/auth/?mode=login&redirect=%2Fpanel%2Forganization");
  await page.getByLabel("شماره موبایل").fill("09120000002");
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
  await page.waitForURL((url) => url.pathname === "/panel/organization");
}

test("PS5B seller organization is private, uncached, keyboard-labeled and mobile-safe", async ({
  page,
}) => {
  await loginSeller(page);

  const response = await page.goto("/panel/organization");
  expect(response).not.toBeNull();
  expect((await response?.headerValue("cache-control")) ?? "").toContain("private");
  expect((await response?.headerValue("cache-control")) ?? "").toContain("no-store");
  expect((await response?.headerValue("pragma")) ?? "").toContain("no-cache");
  expect((await page.locator('meta[name="robots"]').getAttribute("content")) ?? "").toContain(
    "noindex",
  );

  await expect(
    page.getByRole("heading", { name: "اعضا، دسترسی و دسترس‌پذیری روستری" }),
  ).toBeVisible();
  await expect(page.getByLabel("روستری فعال")).toBeVisible();
  await expect(page.getByLabel("کد دعوت ۶۴ کاراکتری")).toBeVisible();
  await expect(page.getByRole("heading", { name: "ساعات هفتگی و استثناها" })).toBeVisible();

  const serviceWorkerSupported = await page.evaluate(async () => {
    if (!("serviceWorker" in navigator)) return false;
    await navigator.serviceWorker.register("/sw.js");
    await navigator.serviceWorker.ready;
    return true;
  });
  expect(serviceWorkerSupported).toBe(true);
  await page.evaluate(async () => {
    await fetch("/panel/organization", { credentials: "include", cache: "no-store" });
  });
  const leakedEntries = await page.evaluate(async () => {
    const leaks: string[] = [];
    for (const cacheName of await caches.keys()) {
      const cache = await caches.open(cacheName);
      for (const request of await cache.keys()) {
        if (new URL(request.url).pathname === "/panel/organization") {
          leaks.push(`${cacheName}:${request.url}`);
        }
      }
    }
    return leaks;
  });
  expect(leakedEntries).toEqual([]);

  const unnamedControls = await page.locator("button, input, select, textarea").evaluateAll((nodes) =>
    nodes
      .filter((node) => {
        const element = node as HTMLElement;
        const id = element.getAttribute("id");
        const label = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
        const wrapped = element.closest("label");
        const name = element.getAttribute("aria-label") ?? element.getAttribute("aria-labelledby");
        const buttonText = element.tagName === "BUTTON" ? element.textContent?.trim() : "";
        return !label && !wrapped && !name && !buttonText;
      })
      .map((node) => (node as HTMLElement).outerHTML.slice(0, 160)),
  );
  expect(unnamedControls).toEqual([]);

  await page.keyboard.press("Tab");
  expect(await page.evaluate(() => document.activeElement !== document.body)).toBe(true);

  await page.setViewportSize({ width: 390, height: 844 });
  const mobileOverflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  );
  expect(mobileOverflow).toBeLessThanOrEqual(2);
});
