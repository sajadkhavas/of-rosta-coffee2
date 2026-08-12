import { expect, test } from "@playwright/test";

test("PS1 keeps Hub HTML private and out of Service Worker caches", async ({ page }) => {
  await page.goto("/");

  const serviceWorkerSupported = await page.evaluate(async () => {
    if (!("serviceWorker" in navigator)) return false;
    await navigator.serviceWorker.register("/sw.js");
    await navigator.serviceWorker.ready;
    return true;
  });
  expect(serviceWorkerSupported).toBe(true);

  const response = await page.goto("/hub/operations");
  expect(response).not.toBeNull();
  const cacheControl = (await response?.headerValue("cache-control")) ?? "";
  expect(cacheControl).toContain("private");
  expect(cacheControl).toContain("no-store");
  expect((await response?.headerValue("pragma")) ?? "").toContain("no-cache");

  const robots = await page.locator('meta[name="robots"]').getAttribute("content");
  expect(robots ?? "").toContain("noindex");
  expect(robots ?? "").toContain("nofollow");

  await page.evaluate(async () => {
    await fetch("/hub/operations", { credentials: "include", cache: "no-store" });
  });

  const leakedHubEntries = await page.evaluate(async () => {
    const leaks: string[] = [];
    for (const cacheName of await caches.keys()) {
      const cache = await caches.open(cacheName);
      for (const request of await cache.keys()) {
        const pathname = new URL(request.url).pathname;
        if (pathname === "/hub" || pathname.startsWith("/hub/")) {
          leaks.push(`${cacheName}:${pathname}`);
        }
      }
    }
    return leaks;
  });
  expect(leakedHubEntries).toEqual([]);
});
