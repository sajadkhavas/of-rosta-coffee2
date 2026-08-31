import { expect, test, type Page, type TestInfo } from "@playwright/test";

interface SeoEvidence {
  route: string;
  status: number;
  title: string;
  description: string | null;
  canonical: string | null;
  robots: string | null;
  xRobotsTag: string | null;
  cacheControl: string | null;
}

const publicSeoRoutes = [
  { route: "/", canonical: "https://rosta.shop/" },
  { route: "/products", canonical: "https://rosta.shop/products" },
  { route: "/about", canonical: "https://rosta.shop/about" },
];

async function attachEvidence(testInfo: TestInfo, name: string, evidence: unknown): Promise<void> {
  await testInfo.attach(name, {
    body: Buffer.from(`${JSON.stringify(evidence, null, 2)}\n`, "utf8"),
    contentType: "application/json",
  });
}

async function collectSeoEvidence(page: Page, route: string): Promise<SeoEvidence> {
  const response = await page.goto(route, { waitUntil: "networkidle" });
  expect(response, `${route} must return an HTTP response`).not.toBeNull();
  const headers = response ? response.headers() : {};

  return {
    route,
    status: response?.status() ?? 0,
    title: await page.title(),
    description: await page.locator('meta[name="description"]').getAttribute("content"),
    canonical: await page.locator('link[rel="canonical"]').getAttribute("href"),
    robots: await page.locator('meta[name="robots"]').getAttribute("content"),
    xRobotsTag: headers["x-robots-tag"] ?? null,
    cacheControl: headers["cache-control"] ?? null,
  };
}

test.describe.configure({ mode: "serial" });

test("PS8A proves production-indexable public SSR metadata and canonical policy", async ({
  page,
}, testInfo) => {
  const runtimeErrors: string[] = [];
  page.on("pageerror", (error) => runtimeErrors.push(`pageerror:${error.message}`));
  page.on("console", (message) => {
    if (message.type() === "error") runtimeErrors.push(`console:${message.text()}`);
  });

  const evidence: SeoEvidence[] = [];
  for (const expected of publicSeoRoutes) {
    const actual = await collectSeoEvidence(page, expected.route);
    expect(actual.status, `${expected.route} must render successfully`).toBe(200);
    expect(
      actual.title.trim().length,
      `${expected.route} must expose a meaningful title`,
    ).toBeGreaterThan(8);
    expect(
      actual.description?.trim().length ?? 0,
      `${expected.route} must expose a meaningful meta description`,
    ).toBeGreaterThan(40);
    expect(actual.canonical, `${expected.route} canonical must target the production origin`).toBe(
      expected.canonical,
    );
    expect(actual.robots ?? "", `${expected.route} must remain indexable`).not.toContain("noindex");
    expect(
      actual.xRobotsTag ?? "",
      `${expected.route} must not receive an HTTP noindex policy`,
    ).not.toContain("noindex");
    await expect(page.locator("html")).toHaveAttribute("lang", "fa");
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
    evidence.push(actual);
  }

  expect(
    runtimeErrors.filter((message) => /hydration|uncaught|unhandled|error/i.test(message)),
    "PS8A public SEO surfaces must not emit hydration/page/runtime errors",
  ).toEqual([]);

  await attachEvidence(testInfo, "ps8a-public-seo-evidence.json", evidence);
});

test("PS8A proves filtered catalog and private surfaces remain fail-closed to indexing", async ({
  page,
}, testInfo) => {
  const evidence: SeoEvidence[] = [];

  const filteredCatalog = await collectSeoEvidence(page, "/products?q=espresso");
  expect(filteredCatalog.status).toBe(200);
  expect(filteredCatalog.robots).toContain("noindex");
  expect(filteredCatalog.canonical).toBe("https://rosta.shop/products");
  evidence.push(filteredCatalog);

  const auth = await collectSeoEvidence(page, "/auth");
  expect(auth.status).toBe(200);
  expect(auth.robots).toContain("noindex");
  expect(auth.xRobotsTag).toContain("noindex");
  expect(auth.cacheControl).toContain("private");
  expect(auth.cacheControl).toContain("no-store");
  evidence.push(auth);

  await attachEvidence(testInfo, "ps8a-indexing-boundary-evidence.json", evidence);
});

test("PS8A proves robots, manifest, security headers and 404 crawl safety on production SSR", async ({
  page,
}, testInfo) => {
  const robotsResponse = await page.request.get("/robots.txt");
  expect(robotsResponse.status()).toBe(200);
  expect(robotsResponse.headers()["content-type"]).toContain("text/plain");
  const robots = await robotsResponse.text();
  expect(robots).toContain("User-agent: *");
  expect(robots).toContain("Allow: /");
  expect(robots).toContain("Disallow: /admin/");
  expect(robots).toContain("Disallow: /auth/");
  expect(robots).toContain("Disallow: /checkout");
  expect(robots).toContain("Sitemap: https://rosta.shop/sitemap.xml");
  expect(robots).not.toContain("Disallow: /\n");

  const manifestResponse = await page.request.get("/manifest.json");
  expect(manifestResponse.status()).toBe(200);
  const manifest = (await manifestResponse.json()) as {
    name?: string;
    start_url?: string;
    display?: string;
  };
  expect(manifest.name?.trim().length ?? 0).toBeGreaterThan(0);
  expect(manifest.start_url).toBe("/");
  expect(manifest.display).toBeTruthy();

  const homeResponse = await page.request.get("/");
  const homeHeaders = homeResponse.headers();
  expect(homeHeaders["content-security-policy"]).toContain("frame-ancestors 'none'");
  expect(homeHeaders["x-content-type-options"]).toBe("nosniff");
  expect(homeHeaders["x-frame-options"]).toBe("DENY");
  expect(homeHeaders["referrer-policy"]).toBe("strict-origin-when-cross-origin");

  const missingResponse = await page.request.get("/__ps8a_missing_route__");
  expect(missingResponse.status()).toBe(404);
  expect(missingResponse.headers()["x-robots-tag"]).toContain("noindex");

  await attachEvidence(testInfo, "ps8a-edge-seo-evidence.json", {
    robots,
    manifest,
    homeSecurityHeaders: {
      contentSecurityPolicy: homeHeaders["content-security-policy"],
      xContentTypeOptions: homeHeaders["x-content-type-options"],
      xFrameOptions: homeHeaders["x-frame-options"],
      referrerPolicy: homeHeaders["referrer-policy"],
    },
    missingRoute: {
      status: missingResponse.status(),
      xRobotsTag: missingResponse.headers()["x-robots-tag"] ?? null,
    },
  });
});
