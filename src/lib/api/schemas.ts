import { z } from "zod";
import type { MediaAsset } from "./contracts";

function hasControlOrBackslash(value: string): boolean {
  for (const character of value) {
    const code = character.charCodeAt(0);
    if (character === "\\" || code <= 0x1f || code === 0x7f) return true;
  }
  return false;
}
const LOCAL_HOSTS = new Set(["localhost", "127.0.0.1", "[::1]"]);

function isSafeAssetUrl(value: string): boolean {
  if (hasControlOrBackslash(value) || value.startsWith("//")) return false;
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
const identifierSchema = boundedText(200).refine(
  (value) => !hasControlOrBackslash(value),
  "شناسه نامعتبر است.",
);
const slugSchema = boundedText(180).refine(
  (value) =>
    !hasControlOrBackslash(value) && !value.includes("/") && value !== "." && value !== "..",
  "Slug نامعتبر است.",
);
const isoDateTimeSchema = z
  .string()
  .refine((value) => Number.isFinite(Date.parse(value)), "زمان ISO نامعتبر است.");
const moneySchema = z.number().int().nonnegative().safe();
const currencySchema = z.literal("IRR");
const mobileSchema = z.string().regex(/^09\d{9}$/);
const safeHttpUrlSchema = z.string().refine(isSafeAssetUrl, "URL رسانه ناامن است.");

export class ApiContractError extends Error {
  readonly context: string;
  readonly issues: z.ZodIssue[];

  constructor(context: string, error: z.ZodError) {
    super(`پاسخ سرویس در بخش «${context}» با قرارداد رستا سازگار نیست.`, {
      cause: error,
    });
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
    postal_code: z
      .string()
      .regex(/^\d{10}$/)
      .nullable()
      .optional(),
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
      .refine(
        (value) => value.startsWith("data:image/") || isSafeAssetUrl(value),
        "Blur URL نامعتبر است.",
      )
      .nullable()
      .optional(),
    variant_version: z.string().trim().min(1).max(32).nullable().optional(),
    sources: z
      .array(
        z
          .object({
            url: safeHttpUrlSchema,
            width: z.number().int().positive().max(20_000),
            height: z.number().int().positive().max(20_000).optional(),
            format: z.enum(["avif", "webp", "jpeg", "png"]),
            size_bytes: z.number().int().positive().max(50_000_000).optional(),
            checksum_sha256: z
              .string()
              .regex(/^[a-f0-9]{64}$/)
              .optional(),
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
    variantVersion: parsed.data.variant_version ?? null,
    sources: parsed.data.sources.map((source) => ({
      url: source.url,
      width: source.width,
      height: source.height,
      format: source.format,
      sizeBytes: source.size_bytes,
      checksumSha256: source.checksum_sha256,
    })),
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
      .object({
        min_hours: z.number().int().nonnegative().max(720),
        max_hours: z.number().int().nonnegative().max(720),
      })
      .strict()
      .refine((value) => value.max_hours >= value.min_hours, "بازه آماده‌سازی نامعتبر است.")
      .nullable()
      .optional(),
    rating: z
      .object({
        value: z.number().min(0).max(5),
        count: z.number().int().nonnegative(),
      })
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
    weight_grams: z.union([
      z.literal(50),
      z.literal(100),
      z.literal(250),
      z.literal(500),
      z.literal(1000),
    ]),
    price: moneySchema,
    compare_at_price: moneySchema.nullable().optional(),
    currency: currencySchema,
    is_available: z.boolean(),
    available_quantity: z.number().int().nonnegative().max(1_000_000).nullable().optional(),
  })
  .strict()
  .superRefine((value, context) => {
    if (
      value.compare_at_price !== null &&
      value.compare_at_price !== undefined &&
      value.compare_at_price < value.price
    ) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["compare_at_price"],
        message: "قیمت مقایسه‌ای کمتر از قیمت فروش است.",
      });
    }
    if (value.is_available && value.available_quantity === 0) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["available_quantity"],
        message: "Variant موجود نمی‌تواند موجودی صفر داشته باشد.",
      });
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

export const packagingPolicyWireSchema = z
  .object({
    mode: z.enum(["free", "fixed"]),
    fee_amount: moneySchema,
    currency: currencySchema,
    is_free: z.boolean(),
    label: boundedText(240),
  })
  .strict()
  .superRefine((value, context) => {
    if (value.is_free !== (value.fee_amount === 0)) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["is_free"],
        message: "وضعیت رایگان بسته‌بندی با مبلغ آن سازگار نیست.",
      });
    }
    if (value.mode === "free" && value.fee_amount !== 0) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["fee_amount"],
        message: "بسته‌بندی رایگان باید مبلغ صفر داشته باشد.",
      });
    }
    if (value.mode === "fixed" && value.fee_amount === 0) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["fee_amount"],
        message: "بسته‌بندی ثابت باید مبلغ مثبت داشته باشد.",
      });
    }
  });

const productBaseFields = {
  id: identifierSchema,
  name: boundedText(240),
  slug: slugSchema,
  short_description: nullableText(1000),
  origin: z
    .object({
      id: identifierSchema,
      name: boundedText(160),
      country_code: z.string().trim().min(2).max(3).nullable().optional(),
    })
    .strict(),
  processing_method: z.enum(["washed", "natural", "honey", "other"]),
  roast_level: z.enum(["light", "medium", "dark"]),
  arabica_percentage: z.number().int().min(0).max(100),
  tasting_notes: z.array(boundedText(100)).max(30),
  packaging: packagingPolicyWireSchema,
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

const grindingProfileSelectionWireSchema = z
  .object({
    id: identifierSchema,
    code: boundedText(100),
    version: z.number().int().min(1).max(65_535),
    name: boundedText(160),
    brew_method: boundedText(100),
  })
  .strict();

const hubOperationWireSchema = z
  .object({
    status: boundedText(80),
    label: boundedText(240),
    received_at: isoDateTimeSchema.nullable().optional(),
    ready_at: isoDateTimeSchema.nullable().optional(),
    handed_off_at: isoDateTimeSchema.nullable().optional(),
  })
  .strict();

const commerceServiceWireSchema = z
  .object({
    id: identifierSchema,
    type: boundedText(80),
    provider_type: boundedText(80),
    grinding_profile: grindingProfileSelectionWireSchema.nullable(),
    service_fee: moneySchema,
    packaging_fee: moneySchema,
    tax_amount: moneySchema,
    total_amount: moneySchema,
    currency: currencySchema,
    is_free: z.boolean(),
    label: nullableText(240),
    hub_operation: hubOperationWireSchema.nullable().optional(),
  })
  .strict()
  .refine(
    (value) => value.is_free === (value.total_amount === 0),
    "وضعیت رایگان خدمت ناسازگار است.",
  );

export const cartLineWireSchema = z
  .object({
    id: identifierSchema,
    product: productSummaryWireSchema,
    variant: productVariantWireSchema,
    quantity: z.number().int().min(1).max(20),
    line_total: moneySchema,
    services: z.array(commerceServiceWireSchema).max(20),
  })
  .strict()
  .refine(
    (value) => value.product.variants.some((variant) => variant.id === value.variant.id),
    "Variant داخل محصول Quote وجود ندارد.",
  );

const quoteGroupWireSchema = z
  .object({
    id: identifierSchema,
    roastery: roasterySummaryWireSchema,
    items: z.array(cartLineWireSchema).min(1).max(100),
    subtotal: moneySchema,
    packaging_total: moneySchema,
    grinding_total: moneySchema,
    shipping_cost: moneySchema.nullable().optional(),
    shipping_total: moneySchema.nullable().optional(),
    discount_total: moneySchema,
    tax_total: moneySchema,
    grand_total: moneySchema,
    currency: currencySchema,
  })
  .strict();

export const quoteWireSchema = z
  .object({
    id: identifierSchema,
    expires_at: isoDateTimeSchema,
    roastery_id: identifierSchema.nullable().optional(),
    groups: z.array(quoteGroupWireSchema).min(1).max(50),
    subtotal: moneySchema,
    packaging_total: moneySchema,
    grinding_total: moneySchema,
    shipping_total: moneySchema,
    discount_total: moneySchema,
    grand_total: moneySchema,
    currency: currencySchema,
    warnings: z
      .array(
        z
          .object({
            code: boundedText(160),
            message: boundedText(1000),
            cart_item_id: identifierSchema.optional(),
          })
          .strict(),
      )
      .max(100),
  })
  .strict()
  .superRefine((value, context) => {
    const singleRoasteryId = value.groups.length === 1 ? value.groups[0].roastery.id : null;
    if ((value.roastery_id ?? null) !== singleRoasteryId) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["roastery_id"],
        message: "شناسه روستری سفارش اصلی با تعداد گروه‌ها سازگار نیست.",
      });
    }

    for (const [groupIndex, group] of value.groups.entries()) {
      if (group.items.some((item) => item.product.roastery.id !== group.roastery.id)) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["groups", groupIndex, "items"],
          message: "آیتمی به روستری گروه دیگری تعلق دارد.",
        });
      }
      const itemSubtotal = group.items.reduce((sum, item) => sum + item.line_total, 0);
      const packaging = group.items.reduce(
        (sum, item) =>
          sum +
          item.services
            .filter((service) => service.type === "packaging")
            .reduce((serviceSum, service) => serviceSum + service.packaging_fee, 0),
        0,
      );
      const grinding = group.items.reduce(
        (sum, item) =>
          sum +
          item.services
            .filter((service) => service.type === "grinding")
            .reduce((serviceSum, service) => serviceSum + service.service_fee, 0),
        0,
      );
      const shipping = group.shipping_total ?? group.shipping_cost ?? 0;
      const expectedGrand =
        group.subtotal +
        group.packaging_total +
        group.grinding_total +
        shipping +
        group.tax_total -
        group.discount_total;
      if (itemSubtotal !== group.subtotal) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["groups", groupIndex, "subtotal"],
          message: "جمع اقلام گروه Quote ناسازگار است.",
        });
      }
      if (packaging !== group.packaging_total) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["groups", groupIndex, "packaging_total"],
          message: "جمع بسته‌بندی گروه Quote ناسازگار است.",
        });
      }
      if (grinding !== group.grinding_total) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["groups", groupIndex, "grinding_total"],
          message: "جمع آسیاب گروه Quote ناسازگار است.",
        });
      }
      if (expectedGrand !== group.grand_total) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["groups", groupIndex, "grand_total"],
          message: "جمع نهایی گروه Quote ناسازگار است.",
        });
      }
    }

    const groupSubtotal = value.groups.reduce((sum, group) => sum + group.subtotal, 0);
    const packagingTotal = value.groups.reduce((sum, group) => sum + group.packaging_total, 0);
    const grindingTotal = value.groups.reduce((sum, group) => sum + group.grinding_total, 0);
    const shippingTotal = value.groups.reduce(
      (sum, group) => sum + (group.shipping_total ?? group.shipping_cost ?? 0),
      0,
    );
    const discountTotal = value.groups.reduce((sum, group) => sum + group.discount_total, 0);
    const grandTotal = value.groups.reduce((sum, group) => sum + group.grand_total, 0);
    if (
      groupSubtotal !== value.subtotal ||
      packagingTotal !== value.packaging_total ||
      grindingTotal !== value.grinding_total ||
      shippingTotal !== value.shipping_total ||
      discountTotal !== value.discount_total ||
      grandTotal !== value.grand_total
    ) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["groups"],
        message: "جمع گروه‌های Quote با سفارش اصلی سازگار نیست.",
      });
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
  "awaiting_payment",
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

const orderItemServiceWireSchema = z
  .object({
    id: identifierSchema,
    type: boundedText(80),
    provider_type: boundedText(80),
    status: boundedText(80),
    grinding_profile: grindingProfileSelectionWireSchema.nullable(),
    service_fee: moneySchema,
    packaging_fee: moneySchema,
    shipping_fee: moneySchema,
    tax_amount: moneySchema,
    total_amount: moneySchema,
    currency: currencySchema,
    is_free: z.boolean(),
    label: nullableText(240),
    hub_operation: hubOperationWireSchema.nullable().optional(),
  })
  .strict();

const orderLineWireSchema = z
  .object({
    id: identifierSchema,
    product: z
      .object({
        id: identifierSchema,
        name: boundedText(240),
        slug: slugSchema,
        primary_image: z.unknown().nullable().optional(),
      })
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
    services: z.array(orderItemServiceWireSchema).max(20),
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

const shipmentDeliveryConfirmationWireSchema = z
  .object({
    source: z.enum(["customer", "administrator", "carrier"]),
    proof_type: boundedText(64),
    confirmed_at: isoDateTimeSchema,
  })
  .strict();

const shipmentLegWireSchema = z
  .object({
    id: identifierSchema,
    route_type: boundedText(100),
    sequence: z.number().int().min(1).max(100),
    is_final: z.boolean(),
    status: boundedText(100),
    carrier: nullableText(120),
    tracking_code: nullableText(200),
    total_amount: moneySchema,
    currency: currencySchema,
    planned_at: isoDateTimeSchema.nullable().optional(),
    picked_up_at: isoDateTimeSchema.nullable().optional(),
    delivered_at: isoDateTimeSchema.nullable().optional(),
    delivery_confirmation: shipmentDeliveryConfirmationWireSchema.nullable().optional(),
  })
  .strict();

const orderEventWireSchema = z
  .object({
    id: identifierSchema,
    sub_order_id: identifierSchema.nullable().optional(),
    type: boundedText(160),
    previous_state: nullableText(160),
    next_state: nullableText(160),
    title: boundedText(300),
    description: nullableText(1000),
    occurred_at: isoDateTimeSchema,
  })
  .strict();

const fulfillmentIncidentWireSchema = z
  .object({
    id: identifierSchema,
    status: z.enum(["open", "resolved"]),
    code: boundedText(64),
    severity: z.enum(["medium", "high", "critical"]),
    resolution: z.enum(["resume_fulfillment", "cancel_and_refund"]).nullable(),
    reported_at: isoDateTimeSchema,
    resolved_at: isoDateTimeSchema.nullable().optional(),
  })
  .strict();

const fulfillmentCommitmentWireSchema = z
  .object({
    acceptance_mode: z.enum(["awaiting_payment", "automatic_contractual"]),
    committed_at: isoDateTimeSchema.nullable().optional(),
    preparation_due_at: isoDateTimeSchema.nullable().optional(),
    handoff_due_at: isoDateTimeSchema.nullable().optional(),
    sla_status: boundedText(64),
    is_breached: z.boolean(),
  })
  .strict();

const deliveryWireSchema = z
  .object({
    confirmed_at: isoDateTimeSchema.nullable().optional(),
    dispute_window_ends_at: isoDateTimeSchema.nullable().optional(),
    customer_can_confirm: z.boolean(),
    settlement_state: z.enum(["not_delivered", "dispute_hold", "blocked", "released"]),
    settlement_hold_code: nullableText(64),
    settlement_released_at: isoDateTimeSchema.nullable().optional(),
  })
  .strict();

const subOrderWireSchema = z
  .object({
    id: identifierSchema,
    status: subOrderStatusSchema,
    acceptance_status: boundedText(100),
    customer_cancellable: z.boolean(),
    fulfillment: fulfillmentCommitmentWireSchema,
    delivery: deliveryWireSchema,
    incidents: z.array(fulfillmentIncidentWireSchema).max(20),
    roastery: z.object({ id: identifierSchema, name: boundedText(160), slug: slugSchema }).strict(),
    items: z.array(orderLineWireSchema).min(1).max(100),
    subtotal: moneySchema,
    packaging_total: moneySchema,
    grinding_total: moneySchema,
    shipping_total: moneySchema,
    discount_total: moneySchema,
    tax_total: moneySchema,
    grand_total: moneySchema,
    currency: currencySchema,
    shipment: shipmentWireSchema.nullable().optional(),
    shipment_legs: z.array(shipmentLegWireSchema).max(100),
  })
  .strict();

const orderBaseFields = {
  id: identifierSchema,
  order_number: boundedText(120),
  status: orderStatusSchema,
  placed_at: isoDateTimeSchema.nullable().optional(),
  grand_total: moneySchema,
  currency: currencySchema,
  sub_orders: z.array(subOrderWireSchema).min(1).max(50),
  events: z.array(orderEventWireSchema).max(500),
};

export const orderSummaryWireSchema = z.object(orderBaseFields).strict();
export const orderDetailWireSchema = z
  .object({
    ...orderBaseFields,
    address: addressWireSchema.nullable(),
    subtotal: moneySchema,
    packaging_total: moneySchema,
    grinding_total: moneySchema,
    shipping_total: moneySchema,
    discount_total: moneySchema,
  })
  .strict();

export const createdOrderWireSchema = orderDetailWireSchema;

export const paymentRequestWireSchema = z
  .object({
    payment_id: identifierSchema,
    redirect_url: z.string().url().max(2000),
  })
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
