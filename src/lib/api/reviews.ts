import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract, resourceSchema } from "./schemas";

const reviewItemSchema = z.object({
  id: z.string().min(1).max(240),
  rating: z.number().int().min(1).max(5),
  title: z.string().max(240).nullable().optional(),
  body: z.string().min(1).max(10_000),
  author: z.string().min(1).max(160),
  is_verified_purchase: z.literal(true),
  created_at: z.string().nullable().optional(),
}).strict();
const publicReviewSchema = z.object({
  summary: z.object({
    count: z.number().int().nonnegative(),
    average: z.number().min(1).max(5).nullable(),
  }).strict(),
  items: z.array(reviewItemSchema).max(100),
}).strict();
const privateReviewSchema = z.object({
  id: z.string().min(1).max(240),
  order_id: z.string().min(1).max(240),
  order_item_id: z.string().min(1).max(240),
  product_id: z.string().min(1).max(240),
  roastery_id: z.string().min(1).max(240),
  rating: z.number().int().min(1).max(5),
  title: z.string().max(240).nullable().optional(),
  body: z.string().min(1).max(10_000),
  status: z.literal("pending"),
  is_verified_purchase: z.literal(true),
  moderated_at: z.string().nullable().optional(),
  moderation_reason: z.string().nullable().optional(),
  created_at: z.string().nullable().optional(),
}).strict();

export type PublicReviewData = z.infer<typeof publicReviewSchema>;
export type PublicReviewItem = z.infer<typeof reviewItemSchema>;

export async function listProductReviews(productSlug: string, limit = 20): Promise<PublicReviewData> {
  const payload = await apiFetch<unknown>(`/products/${encodeURIComponent(productSlug)}/reviews?limit=${Math.min(100, Math.max(1, limit))}`);
  return parseContract(resourceSchema(publicReviewSchema), payload, "نظرهای محصول").data;
}

export async function createVerifiedReview(input: {
  orderItemId: string;
  rating: number;
  title?: string;
  body: string;
}) {
  const payload = await apiFetch<unknown>("/reviews", {
    method: "POST",
    body: {
      order_item_id: input.orderItemId,
      rating: input.rating,
      title: input.title?.trim() || undefined,
      body: input.body.trim(),
    },
  });
  return parseContract(resourceSchema(privateReviewSchema), payload, "ثبت نظر خرید تأییدشده").data;
}

export const productReviewsQueryOptions = (productSlug: string) => queryOptions({
  queryKey: ["public", "products", productSlug, "reviews"],
  queryFn: () => listProductReviews(productSlug, 30),
  staleTime: 60_000,
});
