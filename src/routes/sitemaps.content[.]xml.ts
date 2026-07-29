import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { contentSitemapEntries, sitemapShardResponse } from "@/lib/sitemap";

export const Route = createFileRoute("/sitemaps/content.xml")({
  server: {
    handlers: {
      GET: () => sitemapShardResponse(contentSitemapEntries),
    },
  },
});
