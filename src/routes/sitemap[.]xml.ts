import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { products, roasteries } from "@/data/seed";
import { blogPosts } from "@/data/blog-posts";

const BASE_URL = "https://rosta.coffee";

const FILTER_ORIGINS_FA = ["اتیوپی", "کلمبیا", "برزیل", "کنیا"];
const FILTER_ROASTS_FA = ["روشن", "متوسط", "تیره"];


export const Route = createFileRoute("/sitemap.xml")({
  server: {
    handlers: {
      GET: async () => {
        type Entry = { path: string; priority: string };
        const entries: Entry[] = [
          { path: "/", priority: "1.0" },
          { path: "/roasteries", priority: "0.8" },
          { path: "/products", priority: "0.9" },
          { path: "/blog", priority: "0.8" },
          { path: "/quiz", priority: "0.7" },
          { path: "/about", priority: "0.5" },
          ...roasteries.map((r) => ({ path: `/roasteries/${r.slug}`, priority: "0.7" })),
          ...products.map((p) => ({ path: `/products/${p.slug}`, priority: "0.8" })),
          ...blogPosts.map((p) => ({ path: `/blog/${p.slug}`, priority: "0.7" })),
          ...FILTER_ORIGINS_FA.map((o) => ({
            path: `/products?origin=${encodeURIComponent(o)}`,
            priority: "0.6",
          })),
          ...FILTER_ROASTS_FA.map((r) => ({
            path: `/products?roast=${encodeURIComponent(r)}`,
            priority: "0.6",
          })),

        ];

        const urls = entries
          .map(
            (e) =>
              `  <url><loc>${BASE_URL}${e.path}</loc><changefreq>weekly</changefreq><priority>${e.priority}</priority></url>`,
          )
          .join("\n");

        const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls}
</urlset>`;

        return new Response(xml, {
          headers: {
            "Content-Type": "application/xml",
            "Cache-Control": "public, max-age=3600",
          },
        });
      },
    },
  },
});
