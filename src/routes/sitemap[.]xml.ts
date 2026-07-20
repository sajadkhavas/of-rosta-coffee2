import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { blogPosts } from "@/data/blog-posts";
import { listProducts, listRoasteries } from "@/lib/api/catalog";
import { absoluteUrl } from "@/config/site";

interface SitemapEntry {
  path: string;
  priority: string;
  changefreq: "daily" | "weekly" | "monthly";
  lastmod?: string | null;
}

function xmlEscape(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

async function catalogEntries(): Promise<SitemapEntry[]> {
  const [productsResult, roasteriesResult] = await Promise.allSettled([
    listProducts({ page: 1, perPage: 100, sort: "newest" }),
    listRoasteries({ page: 1, perPage: 100 }),
  ]);

  const products =
    productsResult.status === "fulfilled"
      ? productsResult.value.items.filter((product) => product.status === "published")
      : [];
  const roasteries =
    roasteriesResult.status === "fulfilled" ? roasteriesResult.value.items : [];

  return [
    ...products.map((product) => ({
      path: `/products/${product.slug}`,
      priority: "0.8",
      changefreq: "weekly" as const,
      lastmod: product.latestRoastBatch?.roastedAt ?? null,
    })),
    ...roasteries.map((roastery) => ({
      path: `/roasteries/${roastery.slug}`,
      priority: "0.7",
      changefreq: "weekly" as const,
    })),
  ];
}

export const Route = createFileRoute("/sitemap.xml")({
  server: {
    handlers: {
      GET: async () => {
        const staticEntries: SitemapEntry[] = [
          { path: "/", priority: "1.0", changefreq: "weekly" },
          { path: "/products", priority: "0.9", changefreq: "daily" },
          { path: "/roasteries", priority: "0.8", changefreq: "weekly" },
          { path: "/blog", priority: "0.8", changefreq: "weekly" },
          { path: "/quiz", priority: "0.7", changefreq: "monthly" },
          { path: "/about", priority: "0.5", changefreq: "monthly" },
          { path: "/contact", priority: "0.4", changefreq: "monthly" },
          { path: "/terms", priority: "0.3", changefreq: "monthly" },
          { path: "/privacy", priority: "0.3", changefreq: "monthly" },
          ...blogPosts.map((post) => ({
            path: `/blog/${post.slug}`,
            priority: "0.7",
            changefreq: "monthly" as const,
          })),
        ];

        let dynamicEntries: SitemapEntry[] = [];
        try {
          dynamicEntries = await catalogEntries();
        } catch {
          // The sitemap remains valid with static routes when the Laravel API is temporarily unavailable.
        }

        const uniqueEntries = Array.from(
          new Map(
            [...staticEntries, ...dynamicEntries].map((entry) => [entry.path, entry]),
          ).values(),
        );

        const urls = uniqueEntries
          .map((entry) => {
            const lastmod = entry.lastmod
              ? `<lastmod>${xmlEscape(new Date(entry.lastmod).toISOString())}</lastmod>`
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
