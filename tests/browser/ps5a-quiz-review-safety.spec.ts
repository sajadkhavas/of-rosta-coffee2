import { expect, test } from "@playwright/test";
import { createRequire } from "node:module";
import { readFileSync } from "node:fs";

const require = createRequire(import.meta.url);
const axeSource = readFileSync(require.resolve("axe-core/axe.min.js"), "utf8");

async function expectAccessible(page: import("@playwright/test").Page) {
  await page.addScriptTag({ content: axeSource });
  const violations = await page.evaluate(async () => {
    const axe = (
      window as unknown as {
        axe: {
          run: (
            context: Document,
          ) => Promise<{ violations: Array<{ impact: string | null; id: string }> }>;
        };
      }
    ).axe;
    const result = await axe.run(document);
    return result.violations
      .filter((item) => item.impact === "serious" || item.impact === "critical")
      .map((item) => item.id);
  });
  expect(violations).toEqual([]);
}

test.describe("PS5A persisted quiz journey", () => {
  test("guest completes server quiz without persisting answers in LocalStorage", async ({
    page,
  }) => {
    const response = await page.goto("/quiz");
    expect(response?.status()).toBe(200);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/i);
    await expect(
      page.getByRole("heading", { name: "با چه دستگاهی قهوه درست می‌کنی؟" }),
    ).toBeVisible();

    for (const choice of ["اسپرسوساز خانگی", "متوسط", "کلاسیک و قابل‌اعتماد", "شکلاتی و کارامل"]) {
      await page.getByRole("button", { name: choice }).click();
      await page.getByRole("button", { name: "بعدی" }).click();
    }
    await page.getByRole("button", { name: "تازه شروع کرده‌ام" }).click();
    await page.getByRole("button", { name: "نمایش پیشنهادهای زنده" }).click();

    await expect(
      page.getByRole("heading", { name: "قهوه‌های هماهنگ با پاسخ‌های تو" }),
    ).toBeVisible();
    await expect(page.getByRole("button", { name: "ذخیره در حساب من" })).toBeVisible();
    const stored = await page.evaluate(() =>
      Object.fromEntries(Object.keys(localStorage).map((key) => [key, localStorage.getItem(key)])),
    );
    expect(JSON.stringify(stored)).not.toContain("brew_method");
    expect(JSON.stringify(stored)).not.toContain("chocolate");
    expect(JSON.stringify(stored)).not.toContain("espresso");

    await page.keyboard.press("Tab");
    await expectAccessible(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByText("حریم خصوصی و حساب")).toBeVisible();
    await page.getByRole("button", { name: "حذف نتیجه مهمان" }).click();
    await expect(page.getByText("نتیجه مهمان حذف شد.")).toBeVisible();
  });
});
