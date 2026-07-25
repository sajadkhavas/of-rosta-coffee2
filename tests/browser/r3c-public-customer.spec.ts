import { expect, test, type Page } from "@playwright/test";
import { spawnSync } from "node:child_process";
import { createRequire } from "node:module";
import { readFileSync } from "node:fs";

const require = createRequire(import.meta.url);
const axeSource = readFileSync(require.resolve("axe-core/axe.min.js"), "utf8");
const pendingOtpKey = "rosta.pending-otp.v1";

interface AxeNode {
  target: string[];
  html: string;
  failureSummary?: string;
}

interface AxeViolation {
  id: string;
  impact: string | null;
  help: string;
  nodes: AxeNode[];
}

async function expectNoSeriousAccessibilityViolations(page: Page): Promise<void> {
  await page.addScriptTag({ content: axeSource });
  const violations = await page.evaluate(async () => {
    const axe = (window as unknown as {
      axe: {
        run: (
          context: Document,
          options: { resultTypes: string[] },
        ) => Promise<{ violations: AxeViolation[] }>;
      };
    }).axe;
    const result = await axe.run(document, { resultTypes: ["violations"] });
    return result.violations.filter((violation) =>
      violation.impact === "serious" || violation.impact === "critical",
    );
  });

  expect(
    violations.map(({ id, impact, help, nodes }) => ({
      id,
      impact,
      help,
      nodes: nodes.map(({ target, html, failureSummary }) => ({
        target,
        html,
        failureSummary,
      })),
    })),
  ).toEqual([]);
}

function consumeAcceptanceOtp(challengeId: string): string {
  if (!/^[0-9A-HJKMNP-TV-Z]{26}$/i.test(challengeId)) {
    throw new Error("Browser acceptance received an invalid OTP challenge identifier.");
  }

  for (let attempt = 0; attempt < 30; attempt += 1) {
    const result = spawnSync(
      "php",
      ["artisan", "rosta:acceptance-otp", challengeId, "--no-ansi"],
      {
        cwd: "backend",
        encoding: "utf8",
        env: process.env,
        stdio: ["ignore", "pipe", "pipe"],
      },
    );

    const output = result.stdout.trim();
    if (result.status === 0 && /^\d{6}$/.test(output)) return output;
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 250);
  }

  throw new Error("The one-time acceptance OTP was not delivered before the bounded deadline.");
}

test.describe("R3C public browser journeys", () => {
  test("public content, live catalog and metadata render through production SSR", async ({ page }) => {
    const home = await page.goto("/");
    expect(home?.status()).toBe(200);
    await expect(page.getByRole("heading", { name: "قهوه‌ای که زنده است" })).toBeVisible();
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/i);
    await expectNoSeriousAccessibilityViolations(page);

    const products = await page.goto("/products");
    expect(products?.status()).toBe(200);
    await expect(page.getByText("اتیوپی سیدامو دانه کامل").first()).toBeVisible();
    const canonical = page.locator('link[rel="canonical"]');
    await expect(canonical).toHaveCount(1);
    await expect(canonical).toHaveAttribute("href", /\/products/);

    const product = await page.goto("/products/ethiopia-sidamo-whole-bean");
    expect(product?.status()).toBe(200);
    await expect(page.getByRole("heading", { name: "اتیوپی سیدامو دانه کامل" })).toBeVisible();
    await expect(page.getByText(/۱۰۰|100/).first()).toBeVisible();

    const roasteries = await page.goto("/roasteries");
    expect(roasteries?.status()).toBe(200);
    await expect(page.getByText("روستری رستا").first()).toBeVisible();

    const blog = await page.goto("/blog");
    expect(blog?.status()).toBe(200);
    await expect(page.locator("main")).toBeVisible();

    const quiz = await page.goto("/quiz");
    expect(quiz?.status()).toBe(200);
    await expect(page.locator("main")).toBeVisible();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/products");
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
    await expectNoSeriousAccessibilityViolations(page);
  });
});

test.describe("R3C real customer session", () => {
  test("random OTP creates a secure cookie session and hostile redirect is contained", async ({ page }) => {
    await page.goto("/auth/?mode=login&redirect=%2F%2Fevil.example");
    await page.getByLabel("شماره موبایل").fill("09123456789");
    await page.getByRole("button", { name: "دریافت کد ورود" }).click();
    await page.waitForURL(/\/auth\/verify/);

    const challengeId = await page.evaluate((storageKey) => {
      const raw = sessionStorage.getItem(storageKey);
      if (!raw) return null;
      const value = JSON.parse(raw) as { requestId?: unknown };
      return typeof value.requestId === "string" ? value.requestId : null;
    }, pendingOtpKey);
    expect(challengeId).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/i);

    const code = consumeAcceptanceOtp(challengeId as string);
    await page.getByLabel("کد شش‌رقمی").fill(code);
    await page.getByRole("button", { name: "تأیید و ادامه" }).click();

    await page.waitForURL(/\/profile(?:\?|$)/);
    expect(new URL(page.url()).pathname).toBe("/profile");
    await expect(page.getByRole("heading", { name: "حساب کاربری" })).toBeVisible();
    await expect(page.getByText("نشست امن مبتنی بر Cookie؛ بدون ذخیره Token در مرورگر.")).toBeVisible();
    await expect(page.getByText("خانه پذیرش")).toBeVisible();

    const me = await page.evaluate(async () => {
      const response = await fetch("http://127.0.0.1:8000/api/v1/me", {
        credentials: "include",
        headers: { Accept: "application/json" },
      });
      return {
        status: response.status,
        body: await response.json(),
      };
    });
    expect(me.status).toBe(200);
    expect(JSON.stringify(me.body)).toContain("customer");
    await expectNoSeriousAccessibilityViolations(page);
  });
});
