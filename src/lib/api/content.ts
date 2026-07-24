import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";

const identifier = z.string().trim().min(1).max(240);
const text = (max: number) => z.string().trim().min(1).max(max);
const nullableText = (max: number) => z.string().trim().max(max).nullable().optional();
const isoDate = z.string().refine((value) => Number.isFinite(Date.parse(value)));

export const contentAuthorSchema = z
  .object({
    id: identifier,
    name: text(160),
    slug: identifier,
    bio: nullableText(10_000),
    credentials: z.array(text(300)).max(20),
    is_active: z.boolean(),
  })
  .strict();

const paragraphBlock = z.object({ type: z.literal("paragraph"), text: text(6000) }).strict();
const headingBlock = z
  .object({
    type: z.literal("heading"),
    level: z.union([z.literal(2), z.literal(3)]),
    text: text(240),
  })
  .strict();
const listBlock = z
  .object({
    type: z.literal("list"),
    style: z.enum(["ordered", "unordered"]),
    items: z.array(text(1000)).min(1).max(50),
  })
  .strict();
const quoteBlock = z
  .object({
    type: z.literal("quote"),
    text: text(3000),
    citation: nullableText(300),
  })
  .strict();
const calloutBlock = z
  .object({
    type: z.literal("callout"),
    tone: z.enum(["info", "tip", "warning"]),
    text: text(3000),
  })
  .strict();
const faqBlock = z
  .object({
    type: z.literal("faq"),
    items: z
      .array(z.object({ question: text(300), answer: text(3000) }).strict())
      .min(1)
      .max(30),
  })
  .strict();
const productGridBlock = z
  .object({
    type: z.literal("product_grid"),
    product_slugs: z.array(identifier).min(1).max(24),
  })
  .strict();
const roasterySpotlightBlock = z
  .object({
    type: z.literal("roastery_spotlight"),
    roastery_slug: identifier,
  })
  .strict();
const comparisonTableBlock = z
  .object({
    type: z.literal("comparison_table"),
    columns: z.array(text(120)).min(1).max(8),
    rows: z
      .array(z.array(text(1000)).min(1).max(8))
      .min(1)
      .max(50),
  })
  .strict();

export const contentBlockSchema = z
  .discriminatedUnion("type", [
    paragraphBlock,
    headingBlock,
    listBlock,
    quoteBlock,
    calloutBlock,
    faqBlock,
    productGridBlock,
    roasterySpotlightBlock,
    comparisonTableBlock,
  ])
  .superRefine((value, context) => {
    if (value.type !== "comparison_table") return;
    value.rows.forEach((row, index) => {
      if (row.length !== value.columns.length) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["rows", index],
          message: "تعداد سلول‌های جدول با ستون‌ها برابر نیست.",
        });
      }
    });
  });

export const contentRelationSchema = z
  .object({
    id: identifier,
    relation_type: z.enum(["related", "mentions", "recommends", "compares", "primary_topic"]),
    target_type: z.enum(["content", "product", "roastery", "origin", "brew_method", "taste"]),
    target_key: identifier,
    anchor_text: nullableText(300),
    position: z.number().int().min(0).max(1000),
  })
  .strict();

export const contentSeoSchema = z
  .object({
    title: text(240),
    description: nullableText(1000),
    canonical_path: z.string().startsWith("/").max(500),
    robots_index: z.boolean(),
    robots_follow: z.boolean(),
    og_title: text(240),
    og_description: nullableText(1000),
    og_media_url: z.string().url().nullable().optional(),
    schema_type: z.enum(["Article", "BlogPosting", "CollectionPage", "FAQPage", "WebPage"]),
  })
  .strict();

export const contentEntrySchema = z
  .object({
    id: identifier,
    type: z.enum([
      "article",
      "guide",
      "comparison",
      "landing",
      "origin",
      "brew_method",
      "taste",
      "collection",
    ]),
    title: text(240),
    slug: identifier,
    canonical_path: z.string().startsWith("/").max(500),
    excerpt: nullableText(1000),
    status: z.enum(["draft", "review", "published", "archived"]),
    published_at: isoDate.nullable().optional(),
    updated_at: isoDate.nullable().optional(),
    author: contentAuthorSchema.nullable(),
    seo: contentSeoSchema,
    keywords: z.array(text(120)).max(30),
    body: z.array(contentBlockSchema).min(1).max(200),
    content_hash: z.string().regex(/^[a-f0-9]{64}$/),
    relations: z.array(contentRelationSchema).max(100),
    reviewed_by: identifier.nullable().optional(),
  })
  .strict();

const resourceSchema = z.object({ data: contentEntrySchema }).passthrough();
const indexableSchema = z
  .object({
    data: z
      .object({
        items: z
          .array(
            z
              .object({
                path: z.string().startsWith("/").max(500),
                type: z.enum([
                  "article",
                  "guide",
                  "comparison",
                  "landing",
                  "origin",
                  "brew_method",
                  "taste",
                  "collection",
                ]),
                last_modified_at: isoDate.nullable().optional(),
              })
              .strict(),
          )
          .max(500),
        next_cursor: z.string().nullable().optional(),
      })
      .strict(),
  })
  .passthrough();

export type ContentAuthor = z.infer<typeof contentAuthorSchema>;
export type ContentBlock = z.infer<typeof contentBlockSchema>;
export type ContentRelation = z.infer<typeof contentRelationSchema>;
export type ContentEntry = z.infer<typeof contentEntrySchema>;
export type IndexableContentItem = z.infer<typeof indexableSchema>["data"]["items"][number];

export async function getContentByPath(path: string): Promise<ContentEntry> {
  const payload = await apiFetch<unknown>(`/content/resolve?path=${encodeURIComponent(path)}`);
  return resourceSchema.parse(payload).data;
}

export async function getContentBySlug(slug: string): Promise<ContentEntry> {
  const payload = await apiFetch<unknown>(`/content/${encodeURIComponent(slug)}`);
  return resourceSchema.parse(payload).data;
}

export async function listIndexableContent(cursor?: string): Promise<{
  items: IndexableContentItem[];
  nextCursor: string | null;
}> {
  const search = new URLSearchParams({ limit: "500" });
  if (cursor) search.set("cursor", cursor);
  const payload = indexableSchema.parse(await apiFetch<unknown>(`/seo/indexable?${search}`));
  return {
    items: payload.data.items,
    nextCursor: payload.data.next_cursor ?? null,
  };
}

export function contentPathQueryOptions(path: string) {
  return queryOptions({
    queryKey: ["content", "path", path],
    queryFn: () => getContentByPath(path),
    staleTime: 5 * 60 * 1000,
  });
}
