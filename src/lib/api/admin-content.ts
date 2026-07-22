import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import {
  contentAuthorSchema,
  contentEntrySchema,
  contentSeoSchema,
  type ContentBlock,
  type ContentEntry,
  type ContentRelation,
} from "./content";
import { apiFetch } from "./client";

const identifier = z.string().trim().min(1).max(240);
const nullableText = (max: number) =>
  z.string().trim().max(max).nullable().optional();
const nullableDate = z.string().nullable().optional();

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
    published_at: nullableDate,
    updated_at: nullableDate,
    author: contentAuthorSchema.nullable(),
    seo: contentSeoSchema,
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
    last_hit_at: nullableDate,
  })
  .strict();

const linkReportEntrySchema = z
  .object({
    id: identifier,
    title: z.string().trim().min(1).max(240),
    slug: identifier,
    canonical_path: z.string().startsWith("/").max(500),
    updated_at: nullableDate,
  })
  .strict();

const weakLinkReportEntrySchema = linkReportEntrySchema.extend({
  relations_count: z.number().int().nonnegative(),
});

const brokenRelationSchema = z
  .object({
    relation_id: identifier,
    source: z
      .object({
        id: identifier.nullable(),
        title: z.string().nullable(),
        slug: z.string().nullable(),
        canonical_path: z.string().nullable(),
        status: z.enum(["draft", "review", "published", "archived"]).nullable(),
      })
      .strict(),
    relation_type: z.enum([
      "related",
      "mentions",
      "recommends",
      "compares",
      "primary_topic",
    ]),
    target_type: z.enum([
      "content",
      "product",
      "roastery",
      "origin",
      "brew_method",
      "taste",
    ]),
    target_key: identifier,
    anchor_text: nullableText(300),
    reason: z.enum([
      "missing_target",
      "unpublished_target",
      "wrong_content_type",
    ]),
  })
  .strict();

const contentLinkReportSchema = z
  .object({
    data: z
      .object({
        generated_at: z.string(),
        summary: z
          .object({
            entries_by_status: z
              .object({
                draft: z.number().int().nonnegative(),
                review: z.number().int().nonnegative(),
                published: z.number().int().nonnegative(),
                archived: z.number().int().nonnegative(),
              })
              .strict(),
            total_relations_scanned: z.number().int().nonnegative(),
            relations_truncated: z.boolean(),
            broken_relations: z.number().int().nonnegative(),
            orphaned_entries: z.number().int().nonnegative(),
            weak_outbound_entries: z.number().int().nonnegative(),
          })
          .strict(),
        broken_relations: z.array(brokenRelationSchema).max(250),
        orphaned_entries: z.array(linkReportEntrySchema).max(250),
        weak_outbound_entries: z.array(weakLinkReportEntrySchema).max(250),
      })
      .strict(),
  })
  .passthrough();

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

const authorCollectionSchema = collectionSchema(contentAuthorSchema);
const contentCollectionSchema = collectionSchema(contentSummarySchema);
const redirectCollectionSchema = collectionSchema(redirectSchema);
const authorResourceSchema = z
  .object({ data: contentAuthorSchema })
  .passthrough();
const contentDetailResourceSchema = z
  .object({ data: contentEntrySchema })
  .passthrough();
const redirectResourceSchema = z.object({ data: redirectSchema }).passthrough();

export type AdminContentAuthor = z.infer<typeof contentAuthorSchema>;
export type AdminContentSummary = z.infer<typeof contentSummarySchema>;
export type AdminContentDetail = ContentEntry;
export type AdminSeoRedirect = z.infer<typeof redirectSchema>;
export type AdminContentLinkReport = z.infer<
  typeof contentLinkReportSchema
>["data"];
export type AdminContentStatus = AdminContentSummary["status"];
export type AdminContentType = AdminContentSummary["type"];
export type AdminSchemaType = AdminContentDetail["seo"]["schema_type"];

export interface CreateContentAuthorInput {
  name: string;
  slug: string;
  bio?: string;
  credentials?: string[];
}

export interface ContentRelationInput {
  relation_type: ContentRelation["relation_type"];
  target_type: ContentRelation["target_type"];
  target_key: string;
  anchor_text?: string | null;
  position?: number;
}

export interface CreateContentEntryInput {
  author_id: string;
  type: AdminContentType;
  title: string;
  slug: string;
  canonical_path: string;
  excerpt: string;
  body: ContentBlock[];
  seo_title: string;
  seo_description: string;
  robots_index: boolean;
  robots_follow: boolean;
  og_title?: string | null;
  og_description?: string | null;
  og_media_url?: string | null;
  schema_type: AdminSchemaType;
  keywords: string[];
  relations?: ContentRelationInput[];
}

export interface UpdateContentEntryInput
  extends Partial<Omit<CreateContentEntryInput, "author_id">> {
  expected_content_hash: string;
  author_id?: string | null;
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

export async function getAdminContent(entryId: string): Promise<AdminContentDetail> {
  const payload = contentDetailResourceSchema.parse(
    await apiFetch<unknown>(`/admin/content/${encodeURIComponent(entryId)}`),
  );
  return payload.data;
}

export async function getContentLinkReport(): Promise<AdminContentLinkReport> {
  return contentLinkReportSchema.parse(
    await apiFetch<unknown>("/admin/content-link-report"),
  ).data;
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
): Promise<AdminContentDetail> {
  const payload = contentDetailResourceSchema.parse(
    await apiFetch<unknown>("/admin/content", {
      method: "POST",
      body: { ...input },
    }),
  );
  return payload.data;
}

export async function updateContentEntry(
  entryId: string,
  input: UpdateContentEntryInput,
): Promise<AdminContentDetail> {
  const payload = contentDetailResourceSchema.parse(
    await apiFetch<unknown>(`/admin/content/${encodeURIComponent(entryId)}`, {
      method: "PATCH",
      body: { ...input },
    }),
  );
  return payload.data;
}

export async function setContentStatus(
  entryId: string,
  status: AdminContentStatus,
): Promise<AdminContentDetail> {
  const payload = contentDetailResourceSchema.parse(
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

export function adminContentDetailQueryOptions(entryId: string) {
  return queryOptions({
    queryKey: ["admin", "content", entryId],
    queryFn: () => getAdminContent(entryId),
    staleTime: 0,
    enabled: Boolean(entryId),
  });
}

export function contentLinkReportQueryOptions() {
  return queryOptions({
    queryKey: ["admin", "content-link-report"],
    queryFn: getContentLinkReport,
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
