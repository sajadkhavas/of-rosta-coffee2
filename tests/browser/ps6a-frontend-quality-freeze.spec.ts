import { expect, test, type Page, type TestInfo } from "@playwright/test";
import { createHash } from "node:crypto";
import { createRequire } from "node:module";
import { readFileSync } from "node:fs";

const require = createRequire(import.meta.url);
const axeSource = readFileSync(require.resolve("axe-core/axe.min.js"), "utf8");

interface AxeViolation {
  id: string;
  impact: string | null;
  help: string;
  nodes: Array<{ target: string[]; html: string; failureSummary?: string }>;
}

interface RuntimeEvidence {
  route: string;
  viewport: string;
  status: number;
  scrollWidth: number;
  innerWidth: number;
  ttfbMs: number;
  domContentLoadedMs: number;
  loadMs: number;
  screenshotSha256: string;
}

const publicRoutes = [
  "/",
  "/products",
  "/products/ethiopia-sidamo-whole-bean",
  "/roasteries",
  "/blog",
  "/quiz",
  "/about",
];

async function seriousAccessibilityViolations(page: Page): Promise<AxeViolation[]> {
  await page.addScriptTag({ content: axeSource });
  return page.evaluate(async () => {
    const axe = (
      window as unknown as {
        axe: {
          run: (
            context: Document,
            options: { resultTypes: string[] },
          ) => Promise<{ violations: AxeViolation[] }>;
        };
      }
    ).axe;

    const result = await axe.run(document, { resultTypes: ["violations"] });
    return result.violations.filter(
      (violation) => violation.impact === "serious" || violation.impact === "critical",
    );
  });
}

async function collectEvidence(page: Page, route: string, viewport: string): Promise<RuntimeEvidence> {
  const response = await page.goto(route, { waitUntil: "networkidle" });
  expect(response, `${route} must return an HTTP response`).not.toBeNull();
  expect(response?.status(), `${route} must render successfully`).toBe(200);
  await expect(page.locator("main").first()).toBeVisible();
  await expect(page.locator("html")).toHaveAttribute("dir", "rtl");

  const violations = await seriousAccessibilityViolations(page);
  expect(
    violations.map(({ id, impact, help, nodes }) => ({
      id,
      impact,
      help,
      nodes: nodes.map(({ target, html, failureSummary }) => ({ target, html, failureSummary })),
    })),
    `${route} must have zero serious/critical Axe violations`,
  ).toEqual([]);

  const runtime = await page.evaluate(() => {
    const navigation = performance.getEntriesByType("navigation")[0] as PerformanceNavigationTiming;
    return {
      scrollWidth: document.documentElement.scrollWidth,
      innerWidth: window.innerWidth,
      ttfbMs: Math.max(0, navigation.responseStart - navigation.requestStart),
      domContentLoadedMs: Math.max(0, navigation.domContentLoadedEventEnd - navigation.startTime),
      loadMs: Math.max(0, navigation.loadEventEnd - navigation.startTime),
      bodyText: document.body.innerText,
    };
  });

  expect(runtime.scrollWidth, `${route} must not overflow horizontally`).toBeLessThanOrEqual(
    runtime.innerWidth + 1,
  );
  expect(runtime.ttfbMs, `${route} local integrated TTFB regression`).toBeLessThan(5_000);
  expect(runtime.domContentLoadedMs, `${route} local integrated DCL regression`).toBeLessThan(15_000);
  expect(runtime.loadMs, `${route} local integrated load regression`).toBeLessThan(20_000);

  expect(runtime.bodyText).not.toMatch(/Bearer\s+[A-Za-z0-9._~+/=-]{12,}/i);
  expect(runtime.bodyText).not.toMatch(/(?:api[_-]?key|client[_-]?secret|private[_-]?key)\s*[:=]/i);

  await page.keyboard.press("Tab");
  const focusTag = await page.evaluate(() => document.activeElement?.tagName ?? "");
  expect(focusTag, `${route} keyboard navigation must leave document body`).not.toBe("BODY");

  const screenshot = await page.screenshot({ fullPage: true, animations: "disabled" });
  const screenshotSha256 = createHash("sha256").update(screenshot).digest("hex");

  return {
    route,
    viewport,
    status: response?.status() ?? 0,
    scrollWidth: runtime.scrollWidth,
    innerWidth: runtime.innerWidth,
    ttfbMs: Math.round(runtime.ttfbMs),
    domContentLoadedMs: Math.round(runtime.domContentLoadedMs),
    loadMs: Math.round(runtime.loadMs),
    screenshotSha256,
  };
}

async function attachEvidence(testInfo: TestInfo, evidence: RuntimeEvidence[]): Promise<void> {
  await testInfo.attach("ps6a-visual-performance-index.json", {
    body: Buffer.from(`${JSON.stringify(evidence, null, 2)}\n`, "utf8"),
    contentType: "application/json",
  });
}

test.describe.configure({ mode: "serial" });

test("PS6A freezes public desktop quality without hydration, accessibility, overflow or leak regressions", async ({
  page,
}, testInfo) => {
  const runtimeErrors: string[] = [];
  let activeRoute = "bootstrap";
  page.on("pageerror", (error) => runtimeErrors.push(`${activeRoute}:pageerror:${error.message}`));
  page.on("console", (message) => {
    if (message.type() === "error") runtimeErrors.push(`${activeRoute}:console:${message.text()}`);
  });

  const evidence: RuntimeEvidence[] = [];
  for (const route of publicRoutes) {
    activeRoute = route;
    evidence.push(await collectEvidence(page, route, "1440x1000"));
  }

  expect(
    runtimeErrors.filter((message) => /hydration|uncaught|unhandled|error/i.test(message)),
    "PS6A public surfaces must not emit hydration/page/runtime errors",
  ).toEqual([]);

  await attachEvidence(testInfo, evidence);
});

test("PS6A freezes representative mobile quality and deterministic visual evidence", async ({
  page,
}, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const evidence: RuntimeEvidence[] = [];

  for (const route of ["/", "/products", "/quiz", "/about"]) {
    evidence.push(await collectEvidence(page, route, "390x844"));
  }

  await attachEvidence(testInfo, evidence);
});
