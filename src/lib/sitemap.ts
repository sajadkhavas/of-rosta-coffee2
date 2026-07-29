import { absoluteUrl } from "@/config/site";
import { listProducts, listRoasteries } from "@/lib/api/catalog";
import { listIndexableContent } from "@/lib/api/content";

export interface SitemapEntry {
  path: string;
  priority: string;
  changefreq: "daily" | "weekly" | "monthly";
  lastmod?: string | null;
}

export const MAX_SITEMAP_URLS = 50_000;
export const SITEMAP_SHARDS = [
  "/sitemaps/static.xml",
  "/sitemaps/products.xml",
  "/sitemaps/roasteries.xml",
  "/sitemaps/content.xml",
] as const;

const CATALOG_PAGE_SIZE = 100;
const CONTENT_PAGE_SIZE = 500;
const MAX_CATALOG_PAGES = MAX_SITEMAP_URLS / CATALOG_PAGE_SIZE;
const MAX_CONTENT_CURSOR_PAGES = MAX_SITEMAP_URLS / CONTENT_PAGE_SIZE;
const XML_HEADERS = {
  "Content-Type": "application/xml; charset=utf-8",
  "Cache-Control": "public, max-age=900, stale-while-revalidate=3600",
};

export const STATIC_SITEMAP_ENTRIES: SitemapEntry[] = [
  { path: "/", priority: "1.0", changefreq: "weekly" },
  { path: "/products", priority: "0.9", changefreq: "daily" },
  { path: "/roasteries", priority: "0.8", changefreq: "weekly" },
  { path: "/blog", priority: "0.7", changefreq: "weekly" },
  { path: "/about", priority: "0.5", changefreq: "monthly" },
  { path: "/contact", priority: "0.4", changefreq: "monthly" },
  { path: "/terms", priority: "0.3", changefreq: "monthly" },
  { path: "/privacy", priority: "0.3", changefreq: "monthly" },
];

function xmlEscape(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

function uniqueEntries(entries: SitemapEntry[]): SitemapEntry[] {
  const unique = Array.from(new Map(entries.map((entry) => [entry.path, entry])).values());
  if (unique.length > MAX_SITEMAP_URLS) {
    throw new Error(`Sitemap shard exceeds ${MAX_SITEMAP_URLS} URLs.`);
  }
  return unique;
}

export function renderSitemap(entries: SitemapEntry[]): string {
  const urls = uniqueEntries(entries)
    .map((entry) => {
      const timestamp = entry.lastmod ? new Date(entry.lastmod) : null;
      const lastmod =
        timestamp && Number.isFinite(timestamp.getTime())
          ? `<lastmod>${xmlEscape(timestamp.toISOString())}</lastmod>`
          : "";
      return `  <url><loc>${xmlEscape(absoluteUrl(entry.path))}</loc>${lastmod}<changefreq>${entry.changefreq}</changefreq><priority>${entry.priority}</priority></url>`;
    })
    .join("\n");

  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls}
</urlset>`;
}

export function renderSitemapIndex(paths: readonly string[] = SITEMAP_SHARDS): string {
  const items = paths
    .map((path) => `  <sitemap><loc>${xmlEscape(absoluteUrl(path))}</loc></sitemap>`)
    .join("\n");
  return `<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${items}
</sitemapindex>`;
}

export function sitemapIndexResponse(): Response {
  return new Response(renderSitemapIndex(), { headers: XML_HEADERS });
}

export async function sitemapShardResponse(
  loader: () => Promise<SitemapEntry[]> | SitemapEntry[],
): Promise<Response> {
  try {
    return new Response(renderSitemap(await loader()), { headers: XML_HEADERS });
  } catch (error) {
    console.error("Sitemap shard generation failed.", error);
    return new Response("Sitemap source is temporarily unavailable.", {
      status: 503,
      headers: {
        "Content-Type": "text/plain; charset=utf-8",
        "Cache-Control": "no-store",
        "Retry-After": "300",
      },
    });
  }
}

export async function productSitemapEntries(): Promise<SitemapEntry[]> {
  const entries: SitemapEntry[] = [];

  for (let page = 1; page <= MAX_CATALOG_PAGES; page += 1) {
    const result = await listProducts({ page, perPage: CATALOG_PAGE_SIZE, sort: "newest" });
    if (result.meta?.last_page === undefined && result.items.length >= CATALOG_PAGE_SIZE) {
      throw new Error("Product sitemap pagination metadata is missing.");
    }
    const lastPage = result.meta?.last_page ?? page;
    if ((result.meta?.total ?? 0) > MAX_SITEMAP_URLS || lastPage > MAX_CATALOG_PAGES) {
      throw new Error("Product sitemap requires another shard.");
    }

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

    if (page >= lastPage || result.items.length === 0) return uniqueEntries(entries);
  }

  throw new Error("Product sitemap pagination did not terminate.");
}

export async function roasterySitemapEntries(): Promise<SitemapEntry[]> {
  const entries: SitemapEntry[] = [];

  for (let page = 1; page <= MAX_CATALOG_PAGES; page += 1) {
    const result = await listRoasteries({ page, perPage: CATALOG_PAGE_SIZE });
    if (result.meta?.last_page === undefined && result.items.length >= CATALOG_PAGE_SIZE) {
      throw new Error("Roastery sitemap pagination metadata is missing.");
    }
    const lastPage = result.meta?.last_page ?? page;
    if ((result.meta?.total ?? 0) > MAX_SITEMAP_URLS || lastPage > MAX_CATALOG_PAGES) {
      throw new Error("Roastery sitemap requires another shard.");
    }

    entries.push(
      ...result.items
        .filter((roastery) => roastery.isVerified)
        .map((roastery) => ({
          path: `/roasteries/${roastery.slug}`,
          priority: "0.7",
          changefreq: "weekly" as const,
        })),
    );

    if (page >= lastPage || result.items.length === 0) return uniqueEntries(entries);
  }

  throw new Error("Roastery sitemap pagination did not terminate.");
}

export async function contentSitemapEntries(): Promise<SitemapEntry[]> {
  const entries: SitemapEntry[] = [];
  let cursor: string | undefined;

  for (let page = 0; page < MAX_CONTENT_CURSOR_PAGES; page += 1) {
    const result = await listIndexableContent(cursor);
    entries.push(
      ...result.items.map((item) => ({
        path: item.path,
        priority: item.type === "landing" || item.type === "collection" ? "0.8" : "0.7",
        changefreq:
          item.type === "landing" || item.type === "collection"
            ? ("weekly" as const)
            : ("monthly" as const),
        lastmod: item.last_modified_at ?? null,
      })),
    );

    if (!result.nextCursor || result.nextCursor === cursor) return uniqueEntries(entries);
    cursor = result.nextCursor;
  }

  throw new Error("Content sitemap requires another shard.");
}
