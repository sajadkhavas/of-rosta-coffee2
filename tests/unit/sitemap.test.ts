import { describe, expect, test } from "bun:test";
import {
  MAX_SITEMAP_URLS,
  renderSitemap,
  renderSitemapIndex,
  sitemapShardResponse,
} from "../../src/lib/sitemap";

describe("sitemap infrastructure", () => {
  test("renders an index that advertises every bounded shard", () => {
    const xml = renderSitemapIndex();
    expect(xml).toContain("<sitemapindex");
    expect(xml).toContain("https://rosta.shop/sitemaps/static.xml");
    expect(xml).toContain("https://rosta.shop/sitemaps/products.xml");
    expect(xml).toContain("https://rosta.shop/sitemaps/roasteries.xml");
    expect(xml).toContain("https://rosta.shop/sitemaps/content.xml");
  });

  test("escapes, deduplicates and emits valid absolute URL entries", () => {
    const xml = renderSitemap([
      { path: "/products/a&b", priority: "0.8", changefreq: "weekly" },
      { path: "/products/a&b", priority: "0.8", changefreq: "weekly" },
    ]);
    expect(xml).toContain("<urlset");
    expect(xml).toContain("https://rosta.shop/products/a&amp;b");
    expect(xml.match(/<url>/g)?.length).toBe(1);
  });

  test("rejects an oversized shard instead of publishing an incomplete sitemap", () => {
    const entries = Array.from({ length: MAX_SITEMAP_URLS + 1 }, (_, index) => ({
      path: `/products/${index}`,
      priority: "0.8",
      changefreq: "weekly" as const,
    }));
    expect(() => renderSitemap(entries)).toThrow();
  });

  test("returns a visible 503 when an upstream sitemap source fails", async () => {
    const response = await sitemapShardResponse(async () => {
      throw new Error("catalog unavailable");
    });
    expect(response.status).toBe(503);
    expect(response.headers.get("cache-control")).toBe("no-store");
    expect(response.headers.get("retry-after")).toBe("300");
  });
});
