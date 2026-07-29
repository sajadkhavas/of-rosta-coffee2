import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { STATIC_SITEMAP_ENTRIES, sitemapShardResponse } from "@/lib/sitemap";

export const Route = createFileRoute("/sitemaps/static.xml")({
  server: {
    handlers: {
      GET: () => sitemapShardResponse(() => STATIC_SITEMAP_ENTRIES),
    },
  },
});
