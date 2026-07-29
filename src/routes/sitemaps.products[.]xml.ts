import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { productSitemapEntries, sitemapShardResponse } from "@/lib/sitemap";

export const Route = createFileRoute("/sitemaps/products.xml")({
  server: {
    handlers: {
      GET: () => sitemapShardResponse(productSitemapEntries),
    },
  },
});
