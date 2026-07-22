import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import type { ContentBlock } from "./content";
import { apiFetch } from "./client";

const identifier = z.string().trim().min(1).max(240);
const nullableText = (max: number) =>
  z.string().trim().max(max).nullable().optional();

const authorSchema = z
  .object({
    id: identifier,
    name: z.string().trim().min(1).max(160),
    slug: identifier,
    bio: nullableText(10_000),
    credentials: z.array(z.string().trim().min(1).max(300)).max(20),
    is_active: z.boolean(),
  })
  .strict();

const contentSummarySchema = z
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
    title: z.string().trim().min(1).max(240),
    slug: identifier,
    canonical_path: z.string().startsWith("/").max(500),
    excerpt: nullableText(1000),
    status: z.enum(["draft", "review", "published", "archived"]),
    published_at: z.string().nullable().optional(),
    updated_at: z.string().nullable().optional(),
    author: authorSchema.nullable(),
    seo: z
      .object({
        title: z.string().trim().min(1).max(240),
        description: nullableText(1000),
        canonical_path: z.string().startsWith("/").max(500),
        robots_index: z.boolean(),
        robots_follow: z.boolean(),
        og_title: z.string().trim().min(1).max(240),
        og_description: nullableText(1000),
        og_media_url: z.string().url().nullable().optional(),
        schema_type: z.enum([
          "Article",
          "BlogPosting",
          "CollectionPage",
          "FAQPage",
          "WebPage",
        ]),
      })
      .strict(),
    keywords: z.array(z.string().trim().min(1).max(120)).max(30),
  })
  .passthrough();

const redirectSchema = z
  .object({
    id: identifier,
    source_path: z.string().startsWith("/").max(500),
    destination_path: z.string().startsWith("/").max(500),
    status_code: z.union([z.literal(301), z.literal(308)]),
    is_active: z.boolean(),
    hits: z.number().int().nonnegative(),
    last_hit_at: z.string().nullable().optional(),
  })
  .strict();

function collectionSchema<T extends z.ZodTypeAny>(item: T) {
  return z
    .object({
      data: z.array(item).max(500),
      meta: z
        .object({
          current_page: z.number().int().positive().optional(),
          last_page: z.number().int().positive().optional(),
          per_page: z.number().int().positive().max(100).optional(),
          total: z.number().int().nonnegative().optional(),
        })
        .passthrough()
        .optional(),
    })
    .passthrough();
}

const authorCollectionSchema = collectionSchema(authorSchema);
const contentCollectionSchema = collectionSchema(contentSummarySchema);
const redirectCollectionSchema = collectionSchema(redirectSchema);
const authorResourceSchema = z.object({ data: authorSchema }).passthrough();
const contentResourceSchema = z
  .object({ data: contentSummarySchema })
  .passthrough();
const redirectResourceSchema = z.object({ data: redirectSchema }).passthrough();

export type AdminContentAuthor = z.infer<typeof authorSchema>;
export type AdminContentSummary = z.infer<typeof contentSummarySchema>;
export type AdminSeoRedirect = z.infer<typeof redirectSchema>;
export type AdminContentStatus = AdminContentSummary["status"];

export interface CreateContentAuthorInput {
  name: string;
  slug: string;
  bio?: string;
  credentials?: string[];
}

export interface CreateContentEntryInput {
  author_id: string;
  type: AdminContentSummary["type"];
  title: string;
  slug: string;
  canonical_path: string;
  excerpt: string;
  body: ContentBlock[];
  seo_title: string;
  seo_description: string;
  robots_index: boolean;
  robots_follow: boolean;
  schema_type:
    | "Article"
    | "BlogPosting"
    | "CollectionPage"
    | "FAQPage"
    | "WebPage";
  keywords: string[];
}

export interface CreateSeoRedirectInput {
  source_path: string;
  destination_path: string;
  status_code: 301 | 308;
}

export async function listAdminContent(): Promise<AdminContentSummary[]> {
  const payload = contentCollectionSchema.parse(
    await apiFetch<unknown>("/admin/content?per_page=100"),
  );
  return payload.data;
}

export async function listContentAuthors(): Promise<AdminContentAuthor[]> {
  const payload = authorCollectionSchema.parse(
    await apiFetch<unknown>("/admin/content-authors?per_page=100"),
  );
  return payload.data;
}

export async function listSeoRedirects(): Promise<AdminSeoRedirect[]> {
  const payload = redirectCollectionSchema.parse(
    await apiFetch<unknown>("/admin/seo-redirects?per_page=100"),
  );
  return payload.data;
}

export async function createContentAuthor(
  input: CreateContentAuthorInput,
): Promise<AdminContentAuthor> {
  const payload = authorResourceSchema.parse(
    await apiFetch<unknown>("/admin/content-authors", {
      method: "POST",
      body: { ...input },
    }),
  );
  return payload.data;
}

export async function createContentEntry(
  input: CreateContentEntryInput,
): Promise<AdminContentSummary> {
  const payload = contentResourceSchema.parse(
    await apiFetch<unknown>("/admin/content", {
      method: "POST",
      body: { ...input },
    }),
  );
  return payload.data;
}

export async function setContentStatus(
  entryId: string,
  status: AdminContentStatus,
): Promise<AdminContentSummary> {
  const payload = contentResourceSchema.parse(
    await apiFetch<unknown>(
      `/admin/content/${encodeURIComponent(entryId)}/status`,
      {
        method: "PATCH",
        body: { status },
      },
    ),
  );
  return payload.data;
}

export async function createSeoRedirect(
  input: CreateSeoRedirectInput,
): Promise<AdminSeoRedirect> {
  const payload = redirectResourceSchema.parse(
    await apiFetch<unknown>("/admin/seo-redirects", {
      method: "POST",
      body: { ...input },
    }),
  );
  return payload.data;
}

export function adminContentQueryOptions() {
  return queryOptions({
    queryKey: ["admin", "content"],
    queryFn: listAdminContent,
    staleTime: 30_000,
  });
}

export function contentAuthorsQueryOptions() {
  return queryOptions({
    queryKey: ["admin", "content-authors"],
    queryFn: listContentAuthors,
    staleTime: 60_000,
  });
}

export function seoRedirectsQueryOptions() {
  return queryOptions({
    queryKey: ["admin", "seo-redirects"],
    queryFn: listSeoRedirects,
    staleTime: 30_000,
  });
}
