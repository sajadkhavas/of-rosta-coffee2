import { z } from "zod";
import type { MediaAsset } from "./contracts";

const CONTROL_OR_BACKSLASH = /[\\\u0000-\u001f\u007f]/;
const LOCAL_HOSTS = new Set(["localhost", "127.0.0.1", "[::1]"]);

function isSafeAssetUrl(value: string): boolean {
  if (CONTROL_OR_BACKSLASH.test(value) || value.startsWith("//")) return false;
  if (value.startsWith("/")) return true;

  try {
    const url = new URL(value);
    return url.protocol === "https:" || (url.protocol === "http:" && LOCAL_HOSTS.has(url.hostname));
  } catch {
    return false;
  }
}

const boundedText = (max = 500) => z.string().trim().min(1).max(max);
const nullableText = (max = 500) => z.string().trim().max(max).nullable().optional();
const identifierSchema = boundedText(200).refine((value) => !CONTROL_OR_BACKSLASH.test(value), "شناسه نامعتبر است.");
const slugSchema = boundedText(180).refine(
  (value) => !CONTROL_OR_BACKSLASH.test(value) && !value.includes("/") && value !== "." && value !== "..",
  "Slug نامعتبر است.",
);
const isoDateTimeSchema = z.string().refine((value) => Number.isFinite(Date.parse(value)), "زمان ISO نامعتبر است.");
const moneySchema = z.number().int().nonnegative().safe();
const currencySchema = z.literal("IRR");
const mobileSchema = z.string().regex(/^09\d{9}$/);
const safeHttpUrlSchema = z.string().refine(isSafeAssetUrl, "URL رسانه ناامن است.");

export class ApiContractError extends Error {
  readonly context: string;
  readonly issues: z.ZodIssue[];

  constructor(context: string, error: z.ZodError) {
    super(`پاسخ سرویس در بخش «${context}» با قرارداد رستا سازگار نیست.`, { cause: error });
    this.name = "ApiContractError";
    this.context = context;
    this.issues = error.issues;
  }
}

export function parseContract<T>(schema: z.ZodType<T>, value: unknown, context: string): T {
  const result = schema.safeParse(value);
  if (!result.success) throw new ApiContractError(context, result.error);
  return result.data;
}

export function resourceSchema<T extends z.ZodTypeAny>(data: T) {
  return z.object({ data }).passthrough();
}

export function collectionSchema<T extends z.ZodTypeAny>(item: T) {
  return z
    .object({
      data: z.array(item).max(500),
      meta: z
        .object({
          current_page: z.number().int().min(1).optional(),
          last_page: z.number().int().min(1).optional(),
          per_page: z.number().int().min(1).max(100).optional(),
          total: z.number().int().nonnegative().optional(),
        })
        .strict()
        .optional(),
      links: z
        .object({
          first: z.string().nullable().optional(),
          last: z.string().nullable().optional(),
          prev: z.string().nullable().optional(),
          next: z.string().nullable().optional(),
        })
        .strict()
        .optional(),
    })
    .passthrough();
}

export const authUserSchema = z
  .object({
    id: identifierSchema,
    mobile: mobileSchema,
    name: nullableText(120),
    email: z.string().trim().email().max(254).nullable().optional(),
    roles: z.array(boundedText(80)).max(20),
  })
  .strict();

export const otpRequestResultSchema = z
  .object({
    request_id: identifierSchema,
    expires_in: z.number().int().min(30).max(900),
    retry_after: z.number().int().min(0).max(900),
  })
  .strict();

export const addressWireSchema = z
  .object({
    id: identifierSchema,
    title: nullableText(80),
    recipient_name: boundedText(120),
    recipient_mobile: mobileSchema,
    province: boundedText(120),
    city: boundedText(120),
    address_line: boundedText(1000),
    postal_code: z.string().regex(/^\d{10}$/).nullable().optional(),
    is_default: z.boolean(),
  })
  .strict();

export const mediaAssetSchema = z
  .object({
    id: identifierSchema,
    alt: z.string().trim().max(300),
    width: z.number().int().positive().max(20_000),
    height: z.number().int().positive().max(20_000),
    blur_data_url: z
      .string()
      .max(250_000)
      .refine((value) => value.startsWith("data:image/") || isSafeAssetUrl(value), "Blur URL نامعتبر است.")
      .nullable()
      .optional(),
    sources: z
      .array(
        z
          .object({
            url: safeHttpUrlSchema,
            width: z.number().int().positive().max(20_000),
            format: z.enum(["avif", "webp", "jpeg", "png"]),
          })
          .strict(),
      )
      .min(1)
      .max(12),
  })
  .strict();

export function parseOptionalMedia(value: unknown): MediaAsset | null {
  if (value === null || value === undefined) return null;
  const parsed = mediaAssetSchema.safeParse(value);
  if (!parsed.success) return null;
  return {
    id: parsed.data.id,
    alt: parsed.data.alt,
    width: parsed.data.width,
    height: parsed.data.height,
    blurDataUrl: parsed.data.blur_data_url ?? null,
    sources: parsed.data.sources,
  };
}

export const roasterySummaryWireSchema = z
  .object({
    id: identifierSchema,
    name: boundedText(160),
    slug: slugSchema,
    city: nullableText(120),
    is_verified: z.boolean(),
    logo: z.unknown().nullable().optional(),
    cover: z.unknown().nullable().optional(),
    preparation_time: z
      .object({ min_hours: z.number().int().nonnegative().max(720), max_hours: z.number().int().nonnegative().max(720) })
      .strict()
      .refine((value) => value.max_hours >= value.min_hours, "بازه آماده‌سازی نامعتبر است.")
      .nullable()
      .optional(),
    rating: z
      .object({ value: z.number().min(0).max(5), count: z.number().int().nonnegative() })
      .strict()
      .nullable()
      .optional(),
  })
  .strict();

export const roasteryDetailWireSchema = roasterySummaryWireSchema.extend({
  description: z.string().trim().max(20_000),
  shipping_policy: nullableText(10_000),
});

export const productVariantWireSchema = z
  .object({
    id: identifierSchema,
    sku: boundedText(120),
    weight_grams: z.union([z.literal(50), z.literal(100), z.literal(250), z.literal(500), z.literal(1000)]),
    price: moneySchema,
    compare_at_price: moneySchema.nullable().optional(),
    currency: currencySchema,
    is_available: z.boolean(),
    available_quantity: z.number().int().nonnegative().max(1_000_000).nullable().optional(),
  })
  .strict()
  .superRefine((value, context) => {
    if (value.compare_at_price !== null && value.compare_at_price !== undefined && value.compare_at_price < value.price) {
      context.addIssue({ code: z.ZodIssueCode.custom, path: ["compare_at_price"], message: "قیمت مقایسه‌ای کمتر از قیمت فروش است." });
    }
    if (value.is_available && value.available_quantity === 0) {
      context.addIssue({ code: z.ZodIssueCode.custom, path: ["available_quantity"], message: "Variant موجود نمی‌تواند موجودی صفر داشته باشد." });
    }
  });

export const roastBatchWireSchema = z
  .object({
    id: identifierSchema,
    batch_code: boundedText(120),
    roasted_at: isoDateTimeSchema,
    available_from: isoDateTimeSchema.nullable().optional(),
  })
  .strict();

const productBaseFields = {
  id: identifierSchema,
  name: boundedText(240),
  slug: slugSchema,
  short_description: nullableText(1000),
  origin: z
    .object({ id: identifierSchema, name: boundedText(160), country_code: z.string().trim().min(2).max(3).nullable().optional() })
    .strict(),
  processing_method: z.enum(["washed", "natural", "honey", "other"]),
  roast_level: z.enum(["light", "medium", "dark"]),
  arabica_percentage: z.number().int().min(0).max(100),
  tasting_notes: z.array(boundedText(100)).max(30),
  primary_image: z.unknown().nullable().optional(),
  roastery: roasterySummaryWireSchema,
  variants: z.array(productVariantWireSchema).min(1).max(30),
  latest_roast_batch: roastBatchWireSchema.nullable().optional(),
  status: z.enum(["draft", "review", "published", "archived"]),
};

export const productSummaryWireSchema = z.object(productBaseFields).strict();
export const publicProductSummaryWireSchema = productSummaryWireSchema.refine(
  (value) => value.status === "published",
  "محصول عمومی باید published باشد.",
);
export const productDetailWireSchema = z
  .object({
    ...productBaseFields,
    description: z.string().trim().max(50_000),
    gallery: z.array(z.unknown()).max(30),
    brewing_suggestions: z.array(boundedText(500)).max(30),
    seo: z.object({ title: nullableText(180), description: nullableText(500) }).strict(),
  })
  .strict()
  .refine((value) => value.status === "published", "محصول عمومی باید published باشد.");

export const searchResultWireSchema = z
  .object({
    products: z.array(publicProductSummaryWireSchema).max(100),
    roasteries: z.array(roasterySummaryWireSchema).max(100),
    suggestions: z.array(boundedText(200)).max(20).optional(),
  })
  .strict();

export const cartLineWireSchema = z
  .object({
    id: identifierSchema,
    product: productSummaryWireSchema,
    variant: productVariantWireSchema,
    quantity: z.number().int().min(1).max(20),
    line_total: moneySchema,
  })
  .strict()
  .refine((value) => value.product.variants.some((variant) => variant.id === value.variant.id), "Variant داخل محصول Quote وجود ندارد.");

export const quoteWireSchema = z
  .object({
    id: identifierSchema,
    expires_at: isoDateTimeSchema,
    roastery_id: identifierSchema.nullable().optional(),
    groups: z
      .array(
        z
          .object({
            roastery: roasterySummaryWireSchema,
            items: z.array(cartLineWireSchema).min(1).max(100),
            subtotal: moneySchema,
            shipping_cost: moneySchema.nullable().optional(),
            shipping_total: moneySchema.nullable().optional(),
          })
          .strict(),
      )
      .min(1)
      .max(1),
    subtotal: moneySchema,
    shipping_total: moneySchema,
    discount_total: moneySchema,
    grand_total: moneySchema,
    currency: currencySchema,
    warnings: z
      .array(
        z
          .object({ code: boundedText(160), message: boundedText(1000), cart_item_id: identifierSchema.optional() })
          .strict(),
      )
      .max(100),
  })
  .strict()
  .superRefine((value, context) => {
    const group = value.groups[0];
    const groupRoasteryId = group.roastery.id;
    if (value.roastery_id && value.roastery_id !== groupRoasteryId) {
      context.addIssue({ code: z.ZodIssueCode.custom, path: ["roastery_id"], message: "روستری Quote ناسازگار است." });
    }
    if (group.items.some((item) => item.product.roastery.id !== groupRoasteryId)) {
      context.addIssue({ code: z.ZodIssueCode.custom, path: ["groups"], message: "Quote شامل چند روستری است." });
    }
    const expectedGrandTotal = value.subtotal + value.shipping_total - value.discount_total;
    if (expectedGrandTotal !== value.grand_total) {
      context.addIssue({ code: z.ZodIssueCode.custom, path: ["grand_total"], message: "جمع نهایی Quote ناسازگار است." });
    }
  });

export const orderStatusSchema = z.enum([
  "draft",
  "awaiting_payment",
  "paid",
  "processing",
  "partially_shipped",
  "shipped",
  "partially_delivered",
  "delivered",
  "partially_cancelled",
  "cancelled",
  "refunded",
]);

export const subOrderStatusSchema = z.enum([
  "pending_acceptance",
  "accepted",
  "rejected",
  "preparing",
  "ready_to_ship",
  "shipped",
  "delivered",
  "cancelled",
  "refund_pending",
  "refunded",
]);

const orderLineWireSchema = z
  .object({
    id: identifierSchema,
    product: z
      .object({ id: identifierSchema, name: boundedText(240), slug: slugSchema, primary_image: z.unknown().nullable().optional() })
      .strict(),
    variant: z
      .object({
        id: identifierSchema,
        sku: boundedText(120),
        weight_grams: z.number().int().positive().max(100_000),
        price: moneySchema,
        currency: currencySchema,
      })
      .strict(),
    quantity: z.number().int().min(1).max(20),
    line_total: moneySchema,
  })
  .strict();

const shipmentWireSchema = z
  .object({
    id: identifierSchema,
    carrier: nullableText(120),
    tracking_code: nullableText(200),
    status: boundedText(100),
    shipped_at: isoDateTimeSchema.nullable().optional(),
    delivered_at: isoDateTimeSchema.nullable().optional(),
  })
  .strict();

const subOrderWireSchema = z
  .object({
    id: identifierSchema,
    status: subOrderStatusSchema,
    roastery: z.object({ id: identifierSchema, name: boundedText(160), slug: slugSchema }).strict(),
    items: z.array(orderLineWireSchema).min(1).max(100),
    subtotal: moneySchema,
    shipping_total: moneySchema,
    shipment: shipmentWireSchema.nullable().optional(),
  })
  .strict();

const orderBaseFields = {
  id: identifierSchema,
  order_number: boundedText(120),
  status: orderStatusSchema,
  placed_at: isoDateTimeSchema.nullable().optional(),
  grand_total: moneySchema,
  currency: currencySchema,
  sub_orders: z.array(subOrderWireSchema).min(1).max(1),
};

export const orderSummaryWireSchema = z.object(orderBaseFields).strict();
export const orderDetailWireSchema = z
  .object({
    ...orderBaseFields,
    address: addressWireSchema.nullable(),
    subtotal: moneySchema,
    shipping_total: moneySchema,
    discount_total: moneySchema,
  })
  .strict()
  .refine(
    (value) => value.subtotal + value.shipping_total - value.discount_total === value.grand_total,
    "جمع سفارش ناسازگار است.",
  );

export const createdOrderWireSchema = z
  .object({
    id: identifierSchema,
    order_number: boundedText(120),
    status: orderStatusSchema,
    placed_at: isoDateTimeSchema.nullable().optional(),
    subtotal: moneySchema,
    shipping_total: moneySchema,
    discount_total: moneySchema,
    grand_total: moneySchema,
    currency: currencySchema,
    address: addressWireSchema.nullable(),
    sub_orders: z.array(subOrderWireSchema).max(1),
  })
  .strict()
  .refine(
    (value) => value.subtotal + value.shipping_total - value.discount_total === value.grand_total,
    "جمع سفارش ایجادشده ناسازگار است.",
  );

export const paymentRequestWireSchema = z
  .object({ payment_id: identifierSchema, redirect_url: z.string().url().max(2000) })
  .strict();

export const paymentVerifyWireSchema = z
  .object({
    status: z.enum(["pending", "paid", "failed", "cancelled", "refunded"]),
    order_id: identifierSchema,
  })
  .strict();

export type AddressWire = z.infer<typeof addressWireSchema>;
export type RoasterySummaryWire = z.infer<typeof roasterySummaryWireSchema>;
export type RoasteryDetailWire = z.infer<typeof roasteryDetailWireSchema>;
export type ProductVariantWire = z.infer<typeof productVariantWireSchema>;
export type RoastBatchWire = z.infer<typeof roastBatchWireSchema>;
export type ProductSummaryWire = z.infer<typeof productSummaryWireSchema>;
export type ProductDetailWire = z.infer<typeof productDetailWireSchema>;
export type SearchResultWire = z.infer<typeof searchResultWireSchema>;
export type QuoteWire = z.infer<typeof quoteWireSchema>;
export type OrderSummaryWire = z.infer<typeof orderSummaryWireSchema>;
export type OrderDetailWire = z.infer<typeof orderDetailWireSchema>;
