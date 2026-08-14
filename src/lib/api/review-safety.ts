import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract, resourceSchema } from "./schemas";

const id = z.string().min(1).max(240);
const replySchema = z.object({ id, review_id: id, body: z.string().min(1).max(5_000), status: z.enum(["visible", "hidden", "rejected"]), updated_at: z.string().nullable().optional() }).strict();
const sellerReviewSchema = z.object({
  id, product_id: id, rating: z.number().int().min(1).max(5), title: z.string().max(240).nullable().optional(), body: z.string().min(1).max(10_000),
  status: z.enum(["pending", "approved", "rejected"]), is_verified_purchase: z.boolean(), created_at: z.string().nullable().optional(), reply: replySchema.nullable().optional(),
}).strict();
const reportSchema = z.object({
  id, review_id: id, reason: z.enum(["spam", "harassment", "hate", "personal_data", "fraud", "off_topic", "other"]), evidence: z.string().max(500).nullable().optional(),
  status: z.enum(["open", "reviewing", "resolved", "dismissed"]), created_at: z.string().nullable().optional(), moderated_at: z.string().nullable().optional(), resolution_reason: z.string().max(500).nullable().optional(),
}).strict();
const listSellerSchema = z.object({ items: z.array(sellerReviewSchema).max(100) }).strict();
const listReportSchema = z.object({ items: z.array(reportSchema).max(100) }).strict();

export type SellerReviewSafety = z.infer<typeof sellerReviewSchema>;
export type ReviewAbuseReport = z.infer<typeof reportSchema>;
export type ReviewAbuseStatus = ReviewAbuseReport["status"];

export async function listSellerReviews(roasteryId: string): Promise<SellerReviewSafety[]> {
  return parseContract(resourceSchema(listSellerSchema), await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/reviews`), "نظرهای فروشنده").data.items;
}
export async function saveSellerReply(roasteryId: string, reviewId: string, body: string) {
  return parseContract(resourceSchema(replySchema), await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/reviews/${encodeURIComponent(reviewId)}/reply`, { method: "PUT", body: { body: body.trim() } }), "پاسخ فروشنده").data;
}
export async function listAdminReviewReports(status: ReviewAbuseStatus = "open"): Promise<ReviewAbuseReport[]> {
  return parseContract(resourceSchema(listReportSchema), await apiFetch<unknown>(`/admin/review-reports?status=${encodeURIComponent(status)}`), "صف گزارش نظرها").data.items;
}
export async function moderateReviewReport(reportId: string, status: ReviewAbuseStatus, resolutionReason?: string) {
  return parseContract(resourceSchema(reportSchema), await apiFetch<unknown>(`/admin/review-reports/${encodeURIComponent(reportId)}`, { method: "PATCH", body: { status, resolution_reason: resolutionReason?.trim() || null } }), "بررسی گزارش نظر").data;
}
export async function moderateSellerReply(replyId: string, status: "visible" | "hidden" | "rejected", reason?: string) {
  return parseContract(resourceSchema(replySchema), await apiFetch<unknown>(`/admin/review-replies/${encodeURIComponent(replyId)}`, { method: "PATCH", body: { status, reason: reason?.trim() || null } }), "Moderation پاسخ فروشنده").data;
}
