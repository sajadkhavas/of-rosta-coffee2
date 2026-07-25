import { z } from "zod";

const paginationUrl = z.string().trim().max(2_000).nullable();

const paginationLinkSchema = z
  .object({
    url: paginationUrl,
    label: z.string().max(500),
    active: z.boolean(),
  })
  .strict();

const paginationMetaSchema = z
  .object({
    current_page: z.number().int().min(1).optional(),
    from: z.number().int().min(1).nullable().optional(),
    last_page: z.number().int().min(1).optional(),
    links: z.array(paginationLinkSchema).max(100).optional(),
    path: z.string().trim().max(2_000).optional(),
    per_page: z.number().int().min(1).max(100).optional(),
    to: z.number().int().min(1).nullable().optional(),
    total: z.number().int().nonnegative().optional(),
  })
  .strict();

const paginationLinksSchema = z
  .object({
    first: paginationUrl.optional(),
    last: paginationUrl.optional(),
    prev: paginationUrl.optional(),
    next: paginationUrl.optional(),
  })
  .strict();

export function laravelCollectionSchema<T extends z.ZodTypeAny>(item: T) {
  return z
    .object({
      data: z.array(item).max(500),
      meta: paginationMetaSchema.optional(),
      links: paginationLinksSchema.optional(),
    })
    .passthrough();
}
