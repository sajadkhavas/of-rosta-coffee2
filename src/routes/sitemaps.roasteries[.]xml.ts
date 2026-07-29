import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { roasterySitemapEntries, sitemapShardResponse } from "@/lib/sitemap";

export const Route = createFileRoute("/sitemaps/roasteries.xml")({
  server: {
    handlers: {
      GET: () => sitemapShardResponse(roasterySitemapEntries),
    },
  },
});
