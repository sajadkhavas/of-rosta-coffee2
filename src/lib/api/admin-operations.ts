import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract, resourceSchema } from "./schemas";

const id = z.string().trim().min(1).max(240);
const date = z.string().nullable().optional();
const paginationSchema = z
  .object({
    current_page: z.number().int().positive(),
    last_page: z.number().int().positive(),
    per_page: z.number().int().positive().max(100),
    total: z.number().int().nonnegative(),
  })
  .strict();
const collection = <T extends z.ZodTypeAny>(item: T) =>
  z
    .object({
      data: z.object({ items: z.array(item).max(500), pagination: paginationSchema }).strict(),
    })
    .passthrough();

const roasteryStatusSchema = z.enum(["pending", "verified", "suspended", "rejected"]);
const productStatusSchema = z.enum(["draft", "review", "published", "archived"]);
const reviewStatusSchema = z.enum(["pending", "approved", "rejected"]);
const inquiryStatusSchema = z.enum(["new", "in_progress", "resolved", "closed", "spam"]);
const notificationStatusSchema = z.enum(["pending", "processing", "sent", "failed"]);

const mediaSchema = z
  .object({
    id,
    alt: z.string().max(300).nullable().optional(),
    width: z.number().int().positive().nullable().optional(),
    height: z.number().int().positive().nullable().optional(),
    blur_data_url: z.string().nullable().optional(),
    sources: z.array(z.record(z.string(), z.unknown())).max(20).optional(),
  })
  .passthrough();

const roasterySchema = z
  .object({
    id,
    name: z.string().trim().min(1).max(160),
    slug: z.string().trim().min(1).max(180),
    city: z.string().max(120).nullable().optional(),
    description: z.string().max(20_000),
    shipping_policy: z.string().max(10_000).nullable().optional(),
    status: roasteryStatusSchema,
    is_verified: z.boolean(),
    preparation_time: z
      .object({
        min_hours: z.number().int().nonnegative().max(720),
        max_hours: z.number().int().nonnegative().max(720),
      })
      .strict()
      .nullable()
      .optional(),
    logo: mediaSchema.nullable().optional(),
    cover: mediaSchema.nullable().optional(),
    verified_at: date,
    updated_at: date,
  })
  .strict();

const productSchema = z
  .object({
    id,
    name: z.string().trim().min(1).max(240),
    slug: z.string().trim().min(1).max(180),
    short_description: z.string().max(1000).nullable().optional(),
    processing_method: z.string().trim().min(1).max(80),
    roast_level: z.string().trim().min(1).max(80),
    arabica_percentage: z.number().int().min(0).max(100),
    tasting_notes: z.array(z.string().max(100)).max(30),
    status: productStatusSchema,
    origin: z.object({ id, name: z.string().min(1).max(160) }).passthrough(),
    roastery: z.object({ id, name: z.string().min(1).max(160), slug: z.string() }).passthrough(),
    variants: z
      .array(
        z
          .object({
            id,
            sku: z.string().max(120),
            weight_grams: z.union([
              z.literal(50),
              z.literal(100),
              z.literal(250),
              z.literal(500),
              z.literal(1000),
            ]),
            price: z.number().int().nonnegative(),
            currency: z.literal("IRR"),
            is_available: z.boolean(),
            available_quantity: z.number().int().nonnegative().nullable().optional(),
          })
          .passthrough(),
      )
      .max(5),
    updated_at: date,
  })
  .passthrough();

const reviewSchema = z
  .object({
    id,
    order_id: id,
    order_item_id: id,
    product_id: id,
    roastery_id: id,
    rating: z.number().int().min(1).max(5),
    title: z.string().max(240).nullable().optional(),
    body: z.string().min(1).max(10_000),
    status: reviewStatusSchema,
    is_verified_purchase: z.literal(true),
    moderated_at: date,
    moderation_reason: z.string().max(1000).nullable().optional(),
    created_at: date,
  })
  .strict();

const inquirySchema = z
  .object({
    id,
    type: z.enum([
      "support",
      "order_issue",
      "roastery_onboarding",
      "corporate_purchase",
      "content_correction",
      "privacy_request",
    ]),
    name: z.string().min(1).max(160),
    mobile: z.string().max(32).nullable().optional(),
    email: z.string().email().max(254).nullable().optional(),
    order_number: z.string().max(120).nullable().optional(),
    message: z.string().min(1).max(5000),
    status: inquiryStatusSchema,
    user_id: id.nullable().optional(),
    assigned_to: id.nullable().optional(),
    resolved_at: date,
    created_at: date,
  })
  .strict();

const auditSchema = z
  .object({
    id,
    action: z.string().min(1).max(240),
    actor: z.object({ id, name: z.string().nullable().optional() }).strict().nullable(),
    auditable_type: z.string().max(240),
    auditable_id: id.nullable().optional(),
    request_id: z.string().max(240).nullable().optional(),
    metadata: z.record(z.string(), z.unknown()),
    created_at: date,
  })
  .strict();

const fulfillmentIncidentStatusSchema = z.enum(["open", "resolved"]);
const fulfillmentIncidentSchema = z
  .object({
    id,
    order_id: id,
    order_number: z.string().max(120).nullable().optional(),
    sub_order_id: id,
    roastery: z.object({ id, name: z.string().max(160).nullable() }).strict(),
    sub_order_status: z.string().max(64).nullable().optional(),
    sla_status: z.string().max(64).nullable().optional(),
    preparation_due_at: date,
    handoff_due_at: date,
    status: fulfillmentIncidentStatusSchema,
    code: z.string().min(1).max(64),
    severity: z.enum(["medium", "high", "critical"]),
    description: z.string().min(1).max(2000),
    resolution: z.enum(["resume_fulfillment", "cancel_and_refund"]).nullable().optional(),
    resolution_note: z.string().max(2000).nullable().optional(),
    refund_attempt_id: id.nullable().optional(),
    reported_at: z.string().min(1),
    resolved_at: date,
  })
  .strict();

const notificationSchema = z
  .object({
    id,
    user_id: id.nullable().optional(),
    order_id: id.nullable().optional(),
    sub_order_id: id.nullable().optional(),
    channel: z.string().max(40),
    destination_hint: z.string().max(300),
    template_key: z.string().max(240),
    status: notificationStatusSchema,
    provider: z.string().max(120).nullable().optional(),
    provider_message_id: z.string().max(300).nullable().optional(),
    attempts: z.number().int().nonnegative(),
    last_error: z.string().max(500).nullable().optional(),
    available_at: date,
    processing_at: date,
    sent_at: date,
    failed_at: date,
    created_at: date,
  })
  .strict();

export type AdminRoastery = z.infer<typeof roasterySchema>;
export type AdminProduct = z.infer<typeof productSchema>;
export type AdminReview = z.infer<typeof reviewSchema>;
export type AdminInquiry = z.infer<typeof inquirySchema>;
export type AdminAudit = z.infer<typeof auditSchema>;
export type AdminNotification = z.infer<typeof notificationSchema>;
export type AdminFulfillmentIncident = z.infer<typeof fulfillmentIncidentSchema>;
export type AdminRoasteryStatus = z.infer<typeof roasteryStatusSchema>;
export type AdminProductStatus = z.infer<typeof productStatusSchema>;
export type AdminReviewStatus = z.infer<typeof reviewStatusSchema>;
export type AdminInquiryStatus = z.infer<typeof inquiryStatusSchema>;
export type AdminNotificationStatus = z.infer<typeof notificationStatusSchema>;
export type AdminFulfillmentIncidentStatus = z.infer<typeof fulfillmentIncidentStatusSchema>;

async function list<T>(path: string, schema: z.ZodType<T>, label: string): Promise<T[]> {
  return parseContract(collection(schema), await apiFetch(path), label).data.items;
}

export const listAdminRoasteries = (status: AdminRoasteryStatus) =>
  list(`/admin/roasteries?status=${status}&per_page=100`, roasterySchema, "روستری‌های ادمین");
export const listAdminProducts = (status: AdminProductStatus) =>
  list(`/admin/products?status=${status}&per_page=100`, productSchema, "محصولات ادمین");
export const listAdminReviews = (status: AdminReviewStatus) =>
  list(`/admin/reviews?status=${status}&per_page=100`, reviewSchema, "نظرات ادمین");
export const listAdminInquiries = (status: AdminInquiryStatus) =>
  list(`/admin/inquiries?status=${status}&per_page=100`, inquirySchema, "درخواست‌های پشتیبانی");
export const listAdminAudits = (action = "") =>
  list(
    `/admin/operations/audits?per_page=100${action ? `&action=${encodeURIComponent(action)}` : ""}`,
    auditSchema,
    "گزارش ممیزی",
  );
export const listAdminNotifications = (status: AdminNotificationStatus) =>
  list(
    `/admin/operations/notifications?status=${status}&per_page=100`,
    notificationSchema,
    "صف اعلان‌ها",
  );
export const listAdminFulfillmentIncidents = (status: AdminFulfillmentIncidentStatus) =>
  list(
    `/admin/fulfillment-incidents?status=${status}&per_page=100`,
    fulfillmentIncidentSchema,
    "Incidentهای آماده‌سازی",
  );

export async function resolveAdminFulfillmentIncident(
  incidentId: string,
  input: {
    resolution: "resume_fulfillment" | "cancel_and_refund";
    note: string;
    extendSlaHours?: number;
  },
) {
  return parseContract(
    resourceSchema(z.unknown()),
    await apiFetch(`/admin/fulfillment-incidents/${encodeURIComponent(incidentId)}/resolve`, {
      method: "POST",
      body: {
        resolution: input.resolution,
        note: input.note.trim(),
        extend_sla_hours:
          input.resolution === "resume_fulfillment" ? (input.extendSlaHours ?? 0) : null,
      },
    }),
    "تعیین تکلیف Incident آماده‌سازی",
  ).data;
}

export async function setRoasteryStatus(value: string, status: AdminRoasteryStatus) {
  return parseContract(
    resourceSchema(z.unknown()),
    await apiFetch(`/admin/roasteries/${encodeURIComponent(value)}/status`, {
      method: "PATCH",
      body: { status },
    }),
    "تغییر وضعیت روستری",
  ).data;
}
export async function setProductStatus(value: string, status: AdminProductStatus) {
  return parseContract(
    resourceSchema(z.unknown()),
    await apiFetch(`/admin/products/${encodeURIComponent(value)}/status`, {
      method: "PATCH",
      body: { status },
    }),
    "تغییر وضعیت محصول",
  ).data;
}
export async function moderateReview(
  value: string,
  status: Exclude<AdminReviewStatus, "pending">,
  reason?: string,
) {
  return parseContract(
    resourceSchema(reviewSchema),
    await apiFetch(`/admin/reviews/${encodeURIComponent(value)}`, {
      method: "PATCH",
      body: { status, reason: reason?.trim() || undefined },
    }),
    "مدیریت نظر",
  ).data;
}
export async function setInquiryStatus(value: string, status: AdminInquiryStatus) {
  return parseContract(
    resourceSchema(inquirySchema),
    await apiFetch(`/admin/inquiries/${encodeURIComponent(value)}`, {
      method: "PATCH",
      body: { status },
    }),
    "تغییر وضعیت درخواست",
  ).data;
}

export const adminRoasteriesQuery = (status: AdminRoasteryStatus) =>
  queryOptions({
    queryKey: ["admin", "operations", "roasteries", status],
    queryFn: () => listAdminRoasteries(status),
    staleTime: 15_000,
  });
export const adminProductsQuery = (status: AdminProductStatus) =>
  queryOptions({
    queryKey: ["admin", "operations", "products", status],
    queryFn: () => listAdminProducts(status),
    staleTime: 15_000,
  });
export const adminReviewsQuery = (status: AdminReviewStatus) =>
  queryOptions({
    queryKey: ["admin", "operations", "reviews", status],
    queryFn: () => listAdminReviews(status),
    staleTime: 10_000,
  });
export const adminInquiriesQuery = (status: AdminInquiryStatus) =>
  queryOptions({
    queryKey: ["admin", "operations", "inquiries", status],
    queryFn: () => listAdminInquiries(status),
    staleTime: 10_000,
  });
export const adminAuditsQuery = (action = "") =>
  queryOptions({
    queryKey: ["admin", "operations", "audits", action],
    queryFn: () => listAdminAudits(action),
    staleTime: 10_000,
  });
export const adminNotificationsQuery = (status: AdminNotificationStatus) =>
  queryOptions({
    queryKey: ["admin", "operations", "notifications", status],
    queryFn: () => listAdminNotifications(status),
    staleTime: 10_000,
  });
export const adminFulfillmentIncidentsQuery = (status: AdminFulfillmentIncidentStatus) =>
  queryOptions({
    queryKey: ["admin", "operations", "fulfillment-incidents", status],
    queryFn: () => listAdminFulfillmentIncidents(status),
    staleTime: 5_000,
  });
