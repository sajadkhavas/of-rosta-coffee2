import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { absoluteUrl, siteConfig } from "@/config/site";

export const Route = createFileRoute("/robots.txt")({
  server: {
    handlers: {
      GET: async () => {
        const lines = siteConfig.allowIndexing
          ? [
              "User-agent: *",
              "Allow: /",
              "Disallow: /api/",
              "Disallow: /admin/",
              "Disallow: /panel/",
              "Disallow: /hub/",
              "Disallow: /auth/",
              "Disallow: /cart",
              "Disallow: /checkout",
              "Disallow: /orders",
              "Disallow: /profile",
              "Disallow: /search",
              "Disallow: /design-system",
              `Sitemap: ${absoluteUrl("/sitemap.xml")}`,
            ]
          : ["User-agent: *", "Disallow: /"];

        return new Response(`${lines.join("\n")}\n`, {
          headers: {
            "Content-Type": "text/plain; charset=utf-8",
            "Cache-Control": "public, max-age=300, stale-while-revalidate=3600",
          },
        });
      },
    },
  },
});
