import { z } from "zod";

const MAX_TEXT = 10_000;
const MAX_ARRAY = 100;
const moneySchema = z.number().int().min(0).max(Number.MAX_SAFE_INTEGER);
const boundedText = (max = MAX_TEXT) => z.string().trim().min(1).max(max);
const nullableText = (max = MAX_TEXT) => boundedText(max).nullable().optional();
const identifierSchema = z
  .string()
  .trim()
  .min(1)
  .max(200)
  .regex(/^[A-Za-z0-9._:-]+$/);
const slugSchema = z
  .string()
  .trim()
  .min(1)
  .max(180)
  .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/);
const currencySchema = z.literal("IRR");
const isoDateTimeSchema = z.string().refine(
  (value) => Number.isFinite(Date.parse(value)),
  "زمان ISO نامعتبر است.",
);

export const mediaSourceWireSchema = z
  .object({
    url: z.string().url().max(2_000),
    width: z.number().int().positive().max(20_000),
    format: z.enum(["avif", "webp", "jpeg", "png"]),
  })
  .strict();

export const mediaWireSchema = z
  .object({
    id: identifierSchema,
    alt: boundedText(300),
    width: z.number().int().positive().max(20_000),
    height: z.number().int().positive().max(20_000),
    blur_data_url: nullableText(250_000),
    sources: z.array(mediaSourceWireSchema).min(1).max(12),
  })
  .strict();

export const paginationWireSchema = z
  .object({
    current_page: z.number().int().min(1),
    last_page: z.number().int().min(1),
    per_page: z.number().int().min(1).max(100),
    total: z.number().int().min(0),
  })
  .strict();

export const originWireSchema = z
  .object({
    id: identifierSchema,
    name: boundedText(160),
    country_code: z.string().trim().min(2).max(3).nullable().optional(),
  })
  .strict();

export const roasteryWireSchema = z
  .object({
    id: identifierSchema,
    name: boundedText(160),
    slug: slugSchema,
    city: nullableText(160),
    is_verified: z.boolean(),
    logo: mediaWireSchema.nullable().optional(),
    cover: mediaWireSchema.nullable().optional(),
    preparation_time: z
      .object({
        min_hours: z.number().int().min(0).max(720),
        max_hours: z.number().int().min(0).max(720),
      })
      .strict()
      .nullable()
      .optional(),
    rating: z
      .object({
        average: z.number().min(0).max(5),
        count: z.number().int().min(0),
      })
      .strict()
      .nullable()
      .optional(),
  })
  .strict();

export const roastBatchWireSchema = z
  .object({
    id: identifierSchema,
    batch_code: boundedText(120),
    roasted_at: isoDateTimeSchema,
    available_from: isoDateTimeSchema.nullable().optional(),
  })
  .strict();

export const productVariantWireSchema = z
  .object({
    id: identifierSchema,
    sku: boundedText(120),
    weight_grams: z.number().int().positive().max(100_000),
    price: moneySchema,
    compare_at_price: moneySchema.nullable().optional(),
    currency: currencySchema,
    is_available: z.boolean(),
    available_quantity: z.number().int().min(0).nullable().optional(),
  })
  .strict();

export const productSummaryWireSchema = z
  .object({
    id: identifierSchema,
    name: boundedText(240),
    slug: slugSchema,
    short_description: nullableText(1_000),
    origin: originWireSchema,
    processing_method: boundedText(100),
    roast_level: boundedText(100),
    arabica_percentage: z.number().int().min(0).max(100),
    tasting_notes: z.array(boundedText(160)).max(30),
    primary_image: mediaWireSchema.nullable().optional(),
    roastery: roasteryWireSchema,
    variants: z.array(productVariantWireSchema).min(1).max(20),
    latest_roast_batch: roastBatchWireSchema.nullable().optional(),
    status: z.enum(["draft", "review", "published", "archived"]),
  })
  .strict();

export const productDetailWireSchema = productSummaryWireSchema.extend({
  description: nullableText(20_000),
  brewing_suggestions: z.array(boundedText(300)).max(30),
  media: z.array(mediaWireSchema).max(30),
});

export const productListWireSchema = z
  .object({
    items: z.array(productSummaryWireSchema).max(100),
    pagination: paginationWireSchema,
  })
  .strict();

export const roasteryDetailWireSchema = roasteryWireSchema.extend({
  description: nullableText(20_000),
  products: z.array(productSummaryWireSchema).max(100),
});

export const roasteryListWireSchema = z
  .object({
    items: z.array(roasteryWireSchema).max(100),
    pagination: paginationWireSchema,
  })
  .strict();

export const cartValidationItemWireSchema = z
  .object({
    variant_id: identifierSchema,
    requested_quantity: z.number().int().min(1).max(20),
    available_quantity: z.number().int().min(0),
    is_available: z.boolean(),
    unit_price: moneySchema,
    currency: currencySchema,
  })
  .strict();

export const cartValidationWireSchema = z
  .object({
    valid: z.boolean(),
    roastery_id: identifierSchema.nullable(),
    subtotal: moneySchema,
    currency: currencySchema,
    items: z.array(cartValidationItemWireSchema).max(MAX_ARRAY),
    warnings: z
      .array(
        z
          .object({
            code: boundedText(160),
            message: boundedText(1_000),
          })
          .strict(),
      )
      .max(MAX_ARRAY),
  })
  .strict();

export const addressWireSchema = z
  .object({
    id: identifierSchema,
    title: nullableText(120),
    recipient_name: boundedText(160),
    recipient_mobile: boundedText(32),
    province: boundedText(160),
    city: boundedText(160),
    address_line: boundedText(2_000),
    postal_code: nullableText(32),
    is_default: z.boolean(),
  })
  .strict();

export const userWireSchema = z
  .object({
    id: identifierSchema,
    name: nullableText(160),
    mobile: boundedText(32),
    email: z.string().email().max(254).nullable().optional(),
    roles: z.array(boundedText(120)).max(50),
  })
  .strict();

export const authSessionWireSchema = z
  .object({
    id: identifierSchema,
    expires_at: isoDateTimeSchema,
    last_seen_at: isoDateTimeSchema.nullable().optional(),
    created_at: isoDateTimeSchema,
    current: z.boolean(),
  })
  .strict();

export const quoteLineWireSchema = z
  .object({
    id: identifierSchema,
    product: productSummaryWireSchema,
    variant: productVariantWireSchema,
    quantity: z.number().int().min(1).max(20),
    line_total: moneySchema,
  })
  .strict();

export const quoteGroupWireSchema = z
  .object({
    roastery: roasteryWireSchema,
    items: z.array(quoteLineWireSchema).min(1).max(100),
    subtotal: moneySchema,
    shipping_cost: moneySchema.nullable().optional(),
    shipping_total: moneySchema.nullable().optional(),
  })
  .strict();

export const quoteWireSchema = z
  .object({
    id: identifierSchema,
    expires_at: isoDateTimeSchema,
    roastery_id: identifierSchema.nullable().optional(),
    groups: z.array(quoteGroupWireSchema).min(1).max(1),
    subtotal: moneySchema,
    shipping_total: moneySchema,
    discount_total: moneySchema,
    grand_total: moneySchema,
    currency: currencySchema,
    address: addressWireSchema.nullable(),
    warnings: z
      .array(
        z
          .object({
            code: boundedText(160),
            message: boundedText(1_000),
          })
          .strict(),
      )
      .max(MAX_ARRAY),
  })
  .strict()
  .superRefine((value, context) => {
    const groupRoasteryId = value.groups[0]?.roastery.id;
    if (
      !groupRoasteryId ||
      value.groups.some((group) => group.roastery.id !== groupRoasteryId) ||
      value.groups.some((group) =>
        group.items.some((item) => item.product.roastery.id !== groupRoasteryId),
      )
    ) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["groups"],
        message: "Quote شامل چند روستری است.",
      });
    }
    const expectedGrandTotal =
      value.subtotal + value.shipping_total - value.discount_total;
    if (expectedGrandTotal !== value.grand_total) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["grand_total"],
        message: "جمع نهایی Quote ناسازگار است.",
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
  "refund_pending",
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
    roastery: z
      .object({
        id: identifierSchema,
        name: boundedText(160),
        slug: slugSchema,
      })
      .strict(),
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
    (value) =>
      value.subtotal + value.shipping_total - value.discount_total ===
      value.grand_total,
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
  .strict();

export const paymentRequestWireSchema = z
  .object({
    payment_id: identifierSchema,
    redirect_url: z.string().url().max(2_000),
  })
  .strict();

export const contentPageSummaryWireSchema = z
  .object({
    id: identifierSchema,
    slug: slugSchema,
    title: boundedText(240),
    excerpt: nullableText(1_000),
    status: z.enum(["draft", "published", "archived"]),
    published_at: isoDateTimeSchema.nullable().optional(),
    updated_at: isoDateTimeSchema.nullable().optional(),
  })
  .strict();

export const contentPageDetailWireSchema = contentPageSummaryWireSchema.extend({
  description: nullableText(20_000),
  blocks: z.array(z.unknown()).max(200),
  seo: z.unknown().nullable().optional(),
});

export const contentListWireSchema = z
  .object({
    items: z.array(contentPageSummaryWireSchema).max(100),
    pagination: paginationWireSchema,
  })
  .strict();

export const resourceSchema = <T extends z.ZodTypeAny>(data: T) =>
  z.object({ data }).strict();

export const listResourceSchema = <T extends z.ZodTypeAny>(item: T) =>
  z
    .object({
      data: z.array(item).max(100),
      meta: paginationWireSchema,
    })
    .strict();

export type ProductSummaryWire = z.infer<typeof productSummaryWireSchema>;
export type ProductDetailWire = z.infer<typeof productDetailWireSchema>;
export type ProductVariantWire = z.infer<typeof productVariantWireSchema>;
export type RoasterySummaryWire = z.infer<typeof roasteryWireSchema>;
export type QuoteWire = z.infer<typeof quoteWireSchema>;
export type OrderSummaryWire = z.infer<typeof orderSummaryWireSchema>;
export type OrderDetailWire = z.infer<typeof orderDetailWireSchema>;
