import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { absoluteUrl } from "@/config/site";
import { blogPosts } from "@/data/blog-posts";
import { listIndexableContent } from "@/lib/api/content";
import { listProducts, listRoasteries } from "@/lib/api/catalog";

interface SitemapEntry {
  path: string;
  priority: string;
  changefreq: "daily" | "weekly" | "monthly";
  lastmod?: string | null;
}

const MAX_CATALOG_PAGES = 100;
const MAX_CONTENT_CURSOR_PAGES = 100;

function xmlEscape(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

async function productEntries(): Promise<SitemapEntry[]> {
  const entries: SitemapEntry[] = [];
  for (let page = 1; page <= MAX_CATALOG_PAGES; page += 1) {
    const result = await listProducts({ page, perPage: 100, sort: "newest" });
    entries.push(
      ...result.items
        .filter((product) => product.status === "published")
        .map((product) => ({
          path: `/products/${product.slug}`,
          priority: "0.8",
          changefreq: "weekly" as const,
          lastmod: product.latestRoastBatch?.roastedAt ?? null,
        })),
    );

    const lastPage = result.meta?.last_page ?? page;
    if (page >= lastPage || result.items.length === 0) break;
  }
  return entries;
}

async function roasteryEntries(): Promise<SitemapEntry[]> {
  const entries: SitemapEntry[] = [];
  for (let page = 1; page <= MAX_CATALOG_PAGES; page += 1) {
    const result = await listRoasteries({ page, perPage: 100 });
    entries.push(
      ...result.items.map((roastery) => ({
        path: `/roasteries/${roastery.slug}`,
        priority: "0.7",
        changefreq: "weekly" as const,
      })),
    );

    const lastPage = result.meta?.last_page ?? page;
    if (page >= lastPage || result.items.length === 0) break;
  }
  return entries;
}

async function structuredContentEntries(): Promise<SitemapEntry[]> {
  const entries: SitemapEntry[] = [];
  let cursor: string | undefined;

  for (let page = 0; page < MAX_CONTENT_CURSOR_PAGES; page += 1) {
    const result = await listIndexableContent(cursor);
    entries.push(
      ...result.items.map((item) => ({
        path: item.path,
        priority: item.type === "landing" || item.type === "collection" ? "0.8" : "0.7",
        changefreq: item.type === "landing" || item.type === "collection" ? "weekly" as const : "monthly" as const,
        lastmod: item.last_modified_at ?? null,
      })),
    );

    if (!result.nextCursor || result.nextCursor === cursor) break;
    cursor = result.nextCursor;
  }

  return entries;
}

async function dynamicEntries(): Promise<SitemapEntry[]> {
  const results = await Promise.allSettled([
    productEntries(),
    roasteryEntries(),
    structuredContentEntries(),
  ]);

  return results.flatMap((result) =>
    result.status === "fulfilled" ? result.value : [],
  );
}

export const Route = createFileRoute("/sitemap.xml")({
  server: {
    handlers: {
      GET: async () => {
        const staticEntries: SitemapEntry[] = [
          { path: "/", priority: "1.0", changefreq: "weekly" },
          { path: "/products", priority: "0.9", changefreq: "daily" },
          { path: "/roasteries", priority: "0.8", changefreq: "weekly" },
          { path: "/blog", priority: "0.7", changefreq: "weekly" },
          { path: "/about", priority: "0.5", changefreq: "monthly" },
          { path: "/contact", priority: "0.4", changefreq: "monthly" },
          { path: "/terms", priority: "0.3", changefreq: "monthly" },
          { path: "/privacy", priority: "0.3", changefreq: "monthly" },
          ...blogPosts.map((post) => ({
            path: `/blog/${post.slug}`,
            priority: "0.6",
            changefreq: "monthly" as const,
            lastmod: post.publishedAt,
          })),
        ];

        const entries = [
          ...staticEntries,
          ...(await dynamicEntries()),
        ];
        const uniqueEntries = Array.from(
          new Map(entries.map((entry) => [entry.path, entry])).values(),
        );

        const urls = uniqueEntries
          .map((entry) => {
            const timestamp = entry.lastmod ? new Date(entry.lastmod) : null;
            const lastmod = timestamp && Number.isFinite(timestamp.getTime())
              ? `<lastmod>${xmlEscape(timestamp.toISOString())}</lastmod>`
              : "";
            return `  <url><loc>${xmlEscape(absoluteUrl(entry.path))}</loc>${lastmod}<changefreq>${entry.changefreq}</changefreq><priority>${entry.priority}</priority></url>`;
          })
          .join("\n");

        const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls}
</urlset>`;

        return new Response(xml, {
          headers: {
            "Content-Type": "application/xml; charset=utf-8",
            "Cache-Control": "public, max-age=900, stale-while-revalidate=3600",
          },
        });
      },
    },
  },
});
