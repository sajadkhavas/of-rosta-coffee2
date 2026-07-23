import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";
import { contentAuthorSchema, contentSeoSchema, getContentBySlug, type ContentEntry } from "./content";

const summarySchema = z.object({
  id: z.string().min(1).max(240),
  type: z.enum(["article", "guide", "comparison", "landing", "origin", "brew_method", "taste", "collection"]),
  title: z.string().min(1).max(240),
  slug: z.string().min(1).max(240),
  canonical_path: z.string().startsWith("/").max(500),
  excerpt: z.string().max(1000).nullable().optional(),
  status: z.literal("published"),
  published_at: z.string().nullable().optional(),
  updated_at: z.string().nullable().optional(),
  author: contentAuthorSchema.nullable(),
  seo: contentSeoSchema,
  keywords: z.array(z.string().min(1).max(120)).max(30),
}).strict();
const collectionSchema = z.object({
  data: z.array(summarySchema).max(100),
  meta: z.object({
    current_page: z.number().int().positive().optional(),
    last_page: z.number().int().positive().optional(),
    per_page: z.number().int().positive().max(100).optional(),
    total: z.number().int().nonnegative().optional(),
  }).passthrough().optional(),
}).passthrough();

export type PublicContentSummary = z.infer<typeof summarySchema>;

export async function listPublishedContent(type?: PublicContentSummary["type"], perPage = 24) {
  const search = new URLSearchParams({ per_page: String(Math.min(100, Math.max(1, perPage))), page: "1" });
  if (type) search.set("type", type);
  const payload = collectionSchema.parse(await apiFetch<unknown>(`/content?${search}`));
  return { items: payload.data, total: payload.meta?.total ?? payload.data.length };
}

export const blogIndexQueryOptions = () => queryOptions({
  queryKey: ["public", "content", "blog-index"],
  queryFn: async () => {
    const articles = await listPublishedContent("article", 60);
    return articles.items
      .filter((entry) => entry.canonical_path.startsWith("/blog/"))
      .sort((a, b) => Date.parse(b.published_at || "") - Date.parse(a.published_at || ""));
  },
  staleTime: 5 * 60_000,
});

export const blogEntryQueryOptions = (slug: string) => queryOptions<ContentEntry>({
  queryKey: ["public", "content", "blog", slug],
  queryFn: () => getContentBySlug(slug),
  staleTime: 5 * 60_000,
});
