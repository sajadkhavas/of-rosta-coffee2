import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { products, roasteries } from "@/data/seed";

const BASE_URL = "https://rosta.coffee";

export const Route = createFileRoute("/sitemap.xml")({
  server: {
    handlers: {
      GET: async () => {
        const staticPaths = ["/", "/roasteries", "/products", "/about"];
        const roasteryPaths = roasteries.map((r) => `/roasteries/${r.slug}`);
        const productPaths = products.map((p) => `/products/${p.slug}`);
        const paths = [...staticPaths, ...roasteryPaths, ...productPaths];

        const urls = paths
          .map(
            (p) =>
              `  <url><loc>${BASE_URL}${p}</loc><changefreq>weekly</changefreq><priority>${p === "/" ? "1.0" : "0.7"}</priority></url>`,
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
