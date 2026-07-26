from pathlib import Path
import json


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    file = Path(path)
    text = file.read_text()
    if old not in text:
        raise SystemExit(f"missing patch anchor in {path}: {old[:140]!r}")
    file.write_text(text.replace(old, new, count))


def replace_between(path: str, start: str, end: str, replacement: str) -> None:
    file = Path(path)
    text = file.read_text()
    start_index = text.find(start)
    end_index = text.find(end, start_index)
    if start_index < 0 or end_index < 0:
        raise SystemExit(f"missing section anchors in {path}: {start!r} / {end!r}")
    file.write_text(text[:start_index] + replacement + text[end_index:])


# Domain contracts
replace(
    "src/lib/api/contracts.ts",
    'export type ProcessingMethod = "washed" | "natural" | "honey" | "other";\n',
    '''export type ProcessingMethod = "washed" | "natural" | "honey" | "other";
export type PackagingFeeMode = "free" | "fixed";

export interface PackagingPolicy {
  mode: PackagingFeeMode;
  feeAmount: number;
  currency: CurrencyCode;
  isFree: boolean;
  label: string;
}
''',
)
replace(
    "src/lib/api/contracts.ts",
    "  tastingNotes: string[];\n  primaryImage?: MediaAsset | null;",
    "  tastingNotes: string[];\n  packaging: PackagingPolicy;\n  primaryImage?: MediaAsset | null;",
)
replace(
    "src/lib/api/contracts.ts",
    '''export interface CartLine {
  id: string;
  product: ProductSummary;
  variant: ProductVariant;
  quantity: number;
  lineTotal: number;
}

export interface CartShipmentGroup {''',
    '''export interface CommerceServiceLine {
  id: string;
  type: string;
  providerType: string;
  packagingFee: number;
  taxAmount: number;
  totalAmount: number;
  currency: CurrencyCode;
  isFree: boolean;
  label?: string | null;
}

export interface CartLine {
  id: string;
  product: ProductSummary;
  variant: ProductVariant;
  quantity: number;
  lineTotal: number;
  services: CommerceServiceLine[];
}

export interface CartShipmentGroup {''',
)
replace(
    "src/lib/api/contracts.ts",
    '''export interface CartShipmentGroup {
  roastery: RoasterySummary;
  items: CartLine[];
  subtotal: number;
  shippingCost?: number | null;
}''',
    '''export interface CartShipmentGroup {
  id: string;
  roastery: RoasterySummary;
  items: CartLine[];
  subtotal: number;
  packagingTotal: number;
  grindingTotal: number;
  shippingCost?: number | null;
  shippingTotal: number;
  discountTotal: number;
  taxTotal: number;
  grandTotal: number;
  currency: CurrencyCode;
}''',
)
replace(
    "src/lib/api/contracts.ts",
    "  subtotal: number;\n  shippingTotal: number;",
    "  subtotal: number;\n  packagingTotal: number;\n  shippingTotal: number;",
    1,
)
replace(
    "src/lib/api/contracts.ts",
    '''export interface OrderLine {
  id: string;
  product: OrderProductLine;
  variant: OrderVariantLine;
  quantity: number;
  lineTotal: number;
}''',
    '''export interface OrderItemServiceSummary {
  id: string;
  type: string;
  providerType: string;
  status: string;
  serviceFee: number;
  packagingFee: number;
  shippingFee: number;
  taxAmount: number;
  totalAmount: number;
  currency: CurrencyCode;
  isFree: boolean;
  label?: string | null;
}

export interface OrderLine {
  id: string;
  product: OrderProductLine;
  variant: OrderVariantLine;
  quantity: number;
  lineTotal: number;
  services: OrderItemServiceSummary[];
}''',
)
replace(
    "src/lib/api/contracts.ts",
    '''export interface SubOrderSummary {
  id: string;
  status: SubOrderStatus;
  roastery: Pick<RoasterySummary, "id" | "name" | "slug">;
  items: OrderLine[];
  subtotal: number;
  shippingTotal: number;
  shipment?: ShipmentSummary | null;
}''',
    '''export interface ShipmentLegSummary {
  id: string;
  routeType: string;
  sequence: number;
  status: string;
  carrier?: string | null;
  trackingCode?: string | null;
  totalAmount: number;
  currency: CurrencyCode;
  plannedAt?: IsoDateTime | null;
  pickedUpAt?: IsoDateTime | null;
  deliveredAt?: IsoDateTime | null;
}

export interface SubOrderSummary {
  id: string;
  status: SubOrderStatus;
  acceptanceStatus: string;
  customerCancellable: boolean;
  roastery: Pick<RoasterySummary, "id" | "name" | "slug">;
  items: OrderLine[];
  subtotal: number;
  packagingTotal: number;
  grindingTotal: number;
  shippingTotal: number;
  discountTotal: number;
  taxTotal: number;
  grandTotal: number;
  currency: CurrencyCode;
  shipment?: ShipmentSummary | null;
  shipmentLegs: ShipmentLegSummary[];
}''',
)
replace(
    "src/lib/api/contracts.ts",
    "  subtotal: number;\n  shippingTotal: number;\n  discountTotal: number;\n}",
    "  subtotal: number;\n  packagingTotal: number;\n  shippingTotal: number;\n  discountTotal: number;\n}",
)

# Wire schemas: products, quotes, and marketplace orders.
product_block = '''export const packagingPolicyWireSchema = z
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

'''
replace_between(
    "src/lib/api/schemas.ts",
    "const productBaseFields = {",
    "export const searchResultWireSchema",
    product_block,
)

quote_block = '''const commerceServiceWireSchema = z
  .object({
    id: identifierSchema,
    type: boundedText(80),
    provider_type: boundedText(80),
    packaging_fee: moneySchema,
    tax_amount: moneySchema,
    total_amount: moneySchema,
    currency: currencySchema,
    is_free: z.boolean(),
    label: nullableText(240),
  })
  .strict()
  .refine((value) => value.is_free === (value.total_amount === 0), "وضعیت رایگان خدمت ناسازگار است.");

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
    const shippingTotal = value.groups.reduce(
      (sum, group) => sum + (group.shipping_total ?? group.shipping_cost ?? 0),
      0,
    );
    const discountTotal = value.groups.reduce((sum, group) => sum + group.discount_total, 0);
    const grandTotal = value.groups.reduce((sum, group) => sum + group.grand_total, 0);
    if (
      groupSubtotal !== value.subtotal ||
      packagingTotal !== value.packaging_total ||
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

'''
replace_between(
    "src/lib/api/schemas.ts",
    "export const cartLineWireSchema",
    "export const orderStatusSchema",
    quote_block,
)

order_block = '''const orderItemServiceWireSchema = z
  .object({
    id: identifierSchema,
    type: boundedText(80),
    provider_type: boundedText(80),
    status: boundedText(80),
    grinding_profile: z.unknown().nullable().optional(),
    service_fee: moneySchema,
    packaging_fee: moneySchema,
    shipping_fee: moneySchema,
    tax_amount: moneySchema,
    total_amount: moneySchema,
    currency: currencySchema,
    is_free: z.boolean(),
    label: nullableText(240),
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

const shipmentLegWireSchema = z
  .object({
    id: identifierSchema,
    route_type: boundedText(100),
    sequence: z.number().int().min(1).max(100),
    status: boundedText(100),
    carrier: nullableText(120),
    tracking_code: nullableText(200),
    total_amount: moneySchema,
    currency: currencySchema,
    planned_at: isoDateTimeSchema.nullable().optional(),
    picked_up_at: isoDateTimeSchema.nullable().optional(),
    delivered_at: isoDateTimeSchema.nullable().optional(),
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

const subOrderWireSchema = z
  .object({
    id: identifierSchema,
    status: subOrderStatusSchema,
    acceptance_status: boundedText(100),
    customer_cancellable: z.boolean(),
    roastery: z
      .object({ id: identifierSchema, name: boundedText(160), slug: slugSchema })
      .strict(),
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
    shipping_total: moneySchema,
    discount_total: moneySchema,
  })
  .strict();

export const createdOrderWireSchema = orderDetailWireSchema;

'''
replace_between(
    "src/lib/api/schemas.ts",
    "const orderLineWireSchema",
    "export const paymentRequestWireSchema",
    order_block,
)

# Financial integrity across all marketplace groups.
Path("src/lib/api/financial-contracts.ts").write_text('''import { z } from "zod";
import { orderDetailWireSchema, orderSummaryWireSchema, quoteWireSchema } from "./schemas";

const ALLOWED_WHOLE_BEAN_WEIGHTS = new Set([50, 100, 250, 500, 1000]);

function addIssue(context: z.RefinementCtx, path: Array<string | number>, message: string): void {
  context.addIssue({ code: z.ZodIssueCode.custom, path, message });
}

export const authoritativeQuoteWireSchema = quoteWireSchema.superRefine((value, context) => {
  for (const [groupIndex, group] of value.groups.entries()) {
    for (const [itemIndex, item] of group.items.entries()) {
      const expectedLineTotal = item.variant.price * item.quantity;
      if (!Number.isSafeInteger(expectedLineTotal) || item.line_total !== expectedLineTotal) {
        addIssue(
          context,
          ["groups", groupIndex, "items", itemIndex, "line_total"],
          "جمع سطر Quote با قیمت Variant و تعداد سازگار نیست.",
        );
      }
    }
  }
});

function validateOrderFinancials(
  value: z.infer<typeof orderSummaryWireSchema> | z.infer<typeof orderDetailWireSchema>,
  context: z.RefinementCtx,
): void {
  for (const [subOrderIndex, subOrder] of value.sub_orders.entries()) {
    const computedSubtotal = subOrder.items.reduce((sum, item, itemIndex) => {
      if (!ALLOWED_WHOLE_BEAN_WEIGHTS.has(item.variant.weight_grams)) {
        addIssue(
          context,
          ["sub_orders", subOrderIndex, "items", itemIndex, "variant", "weight_grams"],
          "وزن Snapshot سفارش خارج از وزن‌های مجاز دانه کامل است.",
        );
      }
      const expectedLineTotal = item.variant.price * item.quantity;
      if (!Number.isSafeInteger(expectedLineTotal) || item.line_total !== expectedLineTotal) {
        addIssue(
          context,
          ["sub_orders", subOrderIndex, "items", itemIndex, "line_total"],
          "جمع سطر سفارش با قیمت Snapshot و تعداد سازگار نیست.",
        );
      }
      return sum + item.line_total;
    }, 0);
    const packagingTotal = subOrder.items.reduce(
      (sum, item) =>
        sum +
        item.services
          .filter((service) => service.type === "packaging")
          .reduce((serviceSum, service) => serviceSum + service.packaging_fee, 0),
      0,
    );
    const expectedGrand =
      subOrder.subtotal +
      subOrder.packaging_total +
      subOrder.grinding_total +
      subOrder.shipping_total +
      subOrder.tax_total -
      subOrder.discount_total;
    if (computedSubtotal !== subOrder.subtotal) {
      addIssue(context, ["sub_orders", subOrderIndex, "subtotal"], "جمع زیرسفارش ناسازگار است.");
    }
    if (packagingTotal !== subOrder.packaging_total) {
      addIssue(
        context,
        ["sub_orders", subOrderIndex, "packaging_total"],
        "جمع بسته‌بندی زیرسفارش ناسازگار است.",
      );
    }
    if (expectedGrand !== subOrder.grand_total) {
      addIssue(context, ["sub_orders", subOrderIndex, "grand_total"], "جمع زیرسفارش ناسازگار است.");
    }
  }

  const childGrand = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.grand_total, 0);
  if (childGrand !== value.grand_total) {
    addIssue(context, ["grand_total"], "جمع سفارش اصلی با زیرسفارش‌ها سازگار نیست.");
  }

  if ("subtotal" in value) {
    const subtotal = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.subtotal, 0);
    const packaging = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.packaging_total, 0);
    const shipping = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.shipping_total, 0);
    const discount = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.discount_total, 0);
    if (
      subtotal !== value.subtotal ||
      packaging !== value.packaging_total ||
      shipping !== value.shipping_total ||
      discount !== value.discount_total
    ) {
      addIssue(context, ["sub_orders"], "جمع مالی سفارش اصلی با زیرسفارش‌ها سازگار نیست.");
    }
  }
}

export const authoritativeOrderSummaryWireSchema =
  orderSummaryWireSchema.superRefine(validateOrderFinancials);
export const authoritativeOrderDetailWireSchema =
  orderDetailWireSchema.superRefine(validateOrderFinancials);

export type AuthoritativeQuoteWire = z.infer<typeof authoritativeQuoteWireSchema>;
export type AuthoritativeOrderSummaryWire = z.infer<typeof authoritativeOrderSummaryWireSchema>;
export type AuthoritativeOrderDetailWire = z.infer<typeof authoritativeOrderDetailWireSchema>;
''')

# Product and quote mappers.
for path in ["src/lib/api/catalog.ts", "src/lib/api/checkout.ts", "src/lib/api/seller-operations.ts"]:
    replace(
        path,
        "    tastingNotes: value.tasting_notes,\n    primaryImage:",
        "    tastingNotes: value.tasting_notes,\n    packaging: {\n      mode: value.packaging.mode,\n      feeAmount: value.packaging.fee_amount,\n      currency: value.packaging.currency,\n      isFree: value.packaging.is_free,\n      label: value.packaging.label,\n    },\n    primaryImage:",
    )

replace(
    "src/lib/api/checkout.ts",
    '''      quantity: line.quantity,
      lineTotal: line.line_total,
    })),
    subtotal: value.subtotal,
    shippingCost: value.shipping_cost ?? value.shipping_total ?? null,
  };''',
    '''      quantity: line.quantity,
      lineTotal: line.line_total,
      services: line.services.map((service) => ({
        id: service.id,
        type: service.type,
        providerType: service.provider_type,
        packagingFee: service.packaging_fee,
        taxAmount: service.tax_amount,
        totalAmount: service.total_amount,
        currency: service.currency,
        isFree: service.is_free,
        label: service.label ?? null,
      })),
    })),
    id: value.id,
    subtotal: value.subtotal,
    packagingTotal: value.packaging_total,
    grindingTotal: value.grinding_total,
    shippingCost: value.shipping_cost ?? value.shipping_total ?? null,
    shippingTotal: value.shipping_total ?? value.shipping_cost ?? 0,
    discountTotal: value.discount_total,
    taxTotal: value.tax_total,
    grandTotal: value.grand_total,
    currency: value.currency,
  };''',
)
replace(
    "src/lib/api/checkout.ts",
    "    roasteryId: value.roastery_id ?? value.groups[0].roastery.id,",
    "    roasteryId: value.roastery_id ?? null,",
)
replace(
    "src/lib/api/checkout.ts",
    "    subtotal: value.subtotal,\n    shippingTotal: value.shipping_total,",
    "    subtotal: value.subtotal,\n    packagingTotal: value.packaging_total,\n    shippingTotal: value.shipping_total,",
)

# Order mapper.
replace(
    "src/lib/api/orders.ts",
    "    quantity: value.quantity,\n    lineTotal: value.line_total,",
    '''    quantity: value.quantity,
    lineTotal: value.line_total,
    services: value.services.map((service) => ({
      id: service.id,
      type: service.type,
      providerType: service.provider_type,
      status: service.status,
      serviceFee: service.service_fee,
      packagingFee: service.packaging_fee,
      shippingFee: service.shipping_fee,
      taxAmount: service.tax_amount,
      totalAmount: service.total_amount,
      currency: service.currency,
      isFree: service.is_free,
      label: service.label ?? null,
    })),''',
)
replace(
    "src/lib/api/orders.ts",
    "    status: value.status,\n    roastery: value.roastery,",
    "    status: value.status,\n    acceptanceStatus: value.acceptance_status,\n    customerCancellable: value.customer_cancellable,\n    roastery: value.roastery,",
)
replace(
    "src/lib/api/orders.ts",
    '''    subtotal: value.subtotal,
    shippingTotal: value.shipping_total,
    shipment: mapShipment(value.shipment),''',
    '''    subtotal: value.subtotal,
    packagingTotal: value.packaging_total,
    grindingTotal: value.grinding_total,
    shippingTotal: value.shipping_total,
    discountTotal: value.discount_total,
    taxTotal: value.tax_total,
    grandTotal: value.grand_total,
    currency: value.currency,
    shipment: mapShipment(value.shipment),
    shipmentLegs: value.shipment_legs.map((leg) => ({
      id: leg.id,
      routeType: leg.route_type,
      sequence: leg.sequence,
      status: leg.status,
      carrier: leg.carrier ?? null,
      trackingCode: leg.tracking_code ?? null,
      totalAmount: leg.total_amount,
      currency: leg.currency,
      plannedAt: leg.planned_at ?? null,
      pickedUpAt: leg.picked_up_at ?? null,
      deliveredAt: leg.delivered_at ?? null,
    })),''',
)
replace(
    "src/lib/api/orders.ts",
    "    subtotal: value.subtotal,\n    shippingTotal: value.shipping_total,",
    "    subtotal: value.subtotal,\n    packagingTotal: value.packaging_total,\n    shippingTotal: value.shipping_total,",
)

# Seller API accepts packaging policy.
replace(
    "src/lib/api/seller-operations.ts",
    "  brewingSuggestions?: string[];\n  seoTitle?: string | null;",
    "  brewingSuggestions?: string[];\n  packagingFeeMode?: \"free\" | \"fixed\";\n  packagingFeeAmount?: number;\n  seoTitle?: string | null;",
)
replace(
    "src/lib/api/seller-operations.ts",
    "    brewing_suggestions: uniqueStrings(input.brewingSuggestions ?? [], 30),\n    seo_title:",
    "    brewing_suggestions: uniqueStrings(input.brewingSuggestions ?? [], 30),\n    packaging_fee_mode: input.packagingFeeMode ?? \"free\",\n    packaging_fee_amount: input.packagingFeeMode === \"fixed\" ? input.packagingFeeAmount ?? 0 : 0,\n    seo_title:",
)

# Multi-roastery cart persistence plus local packaging snapshot.
replace(
    "src/lib/cart-storage.ts",
    'export const CART_STORAGE_KEY = "rosta_cart_v3";\nexport const LEGACY_CART_STORAGE_KEYS = ["rosta_cart", "rosta_cart_v2"] as const;\nexport const CART_STORAGE_VERSION = 3 as const;',
    'export const CART_STORAGE_KEY = "rosta_cart_v4";\nexport const LEGACY_CART_STORAGE_KEYS = ["rosta_cart", "rosta_cart_v2", "rosta_cart_v3"] as const;\nexport const CART_STORAGE_VERSION = 4 as const;',
)
replace(
    "src/lib/cart-storage.ts",
    "    unitPriceSnapshot: z.number().int().nonnegative().max(Number.MAX_SAFE_INTEGER),\n    quantity:",
    "    unitPriceSnapshot: z.number().int().nonnegative().max(Number.MAX_SAFE_INTEGER),\n    packagingFeeMode: z.enum([\"free\", \"fixed\"]),\n    packagingFeeAmount: z.number().int().nonnegative().max(Number.MAX_SAFE_INTEGER),\n    quantity:",
)
# Remove single-roastery validation.
replace(
    "src/lib/cart-storage.ts",
    '''    const variantIds = new Set<string>();
    const roasteryId = value.items[0]?.roasteryId;

    value.items.forEach((item, index) => {''',
    '''    const variantIds = new Set<string>();

    value.items.forEach((item, index) => {''',
)
replace(
    "src/lib/cart-storage.ts",
    '''      variantIds.add(item.variantId);
      if (roasteryId && item.roasteryId !== roasteryId) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["items", index, "roasteryId"],
          message: "سبد شامل چند روستری است.",
        });
      }
    });''',
    '''      variantIds.add(item.variantId);
    });''',
)
replace(
    "src/lib/cart-storage.ts",
    "  let roasteryId: string | undefined;\n\n  for",
    "\n  for",
)
replace(
    "src/lib/cart-storage.ts",
    '''      addedAt:
        Number.isInteger(candidate.addedAt) && Number(candidate.addedAt) > 0
          ? Number(candidate.addedAt)
          : Date.now(),''',
    '''      packagingFeeMode: candidate.packagingFeeMode === "fixed" ? "fixed" : "free",
      packagingFeeAmount:
        candidate.packagingFeeMode === "fixed"
          ? Math.max(0, Number(candidate.packagingFeeAmount ?? 0))
          : 0,
      addedAt:
        Number.isInteger(candidate.addedAt) && Number(candidate.addedAt) > 0
          ? Number(candidate.addedAt)
          : Date.now(),''',
)
replace(
    "src/lib/cart-storage.ts",
    '''    if (variants.has(parsed.data.variantId)) continue;
    if (roasteryId && parsed.data.roasteryId !== roasteryId) continue;

    roasteryId ??= parsed.data.roasteryId;
    variants.add(parsed.data.variantId);''',
    '''    if (variants.has(parsed.data.variantId)) continue;

    variants.add(parsed.data.variantId);''',
)
replace(
    "src/lib/cart-storage.ts",
    "    unitPriceSnapshot: variant.price,\n    quantity:",
    "    unitPriceSnapshot: variant.price,\n    packagingFeeMode: product.packaging.mode,\n    packagingFeeAmount: product.packaging.feeAmount,\n    quantity:",
)

replace(
    "src/lib/cart-context.tsx",
    '''export type CartAddResult =
  | { status: "added" }
  | { status: "requires_reset"; currentRoasteryName: string }
  | { status: "limit_reached" };''',
    '''export type CartAddResult =
  | { status: "added" }
  | { status: "limit_reached" };''',
)
replace(
    "src/lib/cart-context.tsx",
    "  localSubtotal: number;\n  apiItems:",
    "  localSubtotal: number;\n  localPackagingTotal: number;\n  apiItems:",
)
replace(
    "src/lib/cart-context.tsx",
    '''      const currentRoastery = items[0];
      if (currentRoastery && currentRoastery.roasteryId !== input.product.roastery.id) {
        return {
          status: "requires_reset",
          currentRoasteryName: currentRoastery.roasteryName,
        };
      }

''',
    "",
)
replace(
    "src/lib/cart-context.tsx",
    '''  const apiItems = useMemo<CartApiItem[]>(
''',
    '''  const localPackagingTotal = useMemo(
    () => items.reduce((sum, item) => sum + item.packagingFeeAmount * item.quantity, 0),
    [items],
  );
  const apiItems = useMemo<CartApiItem[]>(
''',
)
replace(
    "src/lib/cart-context.tsx",
    "      localSubtotal,\n      apiItems,\n      roasteryId: items[0]?.roasteryId,",
    "      localSubtotal,\n      localPackagingTotal,\n      apiItems,\n      roasteryId: new Set(items.map((item) => item.roasteryId)).size === 1 ? items[0]?.roasteryId : undefined,",
)
replace(
    "src/lib/cart-context.tsx",
    "      localSubtotal,\n      apiItems,",
    "      localSubtotal,\n      localPackagingTotal,\n      apiItems,",
    1,
)

# Product page: no destructive cart reset; show explicit packaging.
replace(
    "src/routes/products.$slug.tsx",
    "  const { addItem, replaceWithItem } = useCart();",
    "  const { addItem } = useCart();",
)
replace(
    "src/routes/products.$slug.tsx",
    '''    if (result.status === "requires_reset") {
      if (
        !window.confirm(`سبد شما شامل محصولات ${result.currentRoasteryName} است. سبد قبلی پاک شود؟`)
      )
        return;
      replaceWithItem(input);
      setNotice("سبد قبلی پاک شد و محصول این روستری جایگزین شد.");
    } else if (result.status === "limit_reached") {''',
    '''    if (result.status === "limit_reached") {''',
)
replace(
    "src/routes/products.$slug.tsx",
    '''            <div className="mt-6 rounded-xl border border-[color:var(--roast)]/40 bg-[color:var(--night)] p-4 text-xs leading-7 text-[color:var(--light)]">
              رستا فقط دانه کامل می‌فروشد. قیمت نهایی در سبد توسط سرور تأیید می‌شود.
            </div>''',
    '''            <div className="mt-6 grid gap-3 rounded-xl border border-[color:var(--roast)]/40 bg-[color:var(--night)] p-4 text-xs leading-7 text-[color:var(--light)]">
              <p>رستا فقط دانه کامل می‌فروشد. قیمت نهایی در سبد توسط سرور تأیید می‌شود.</p>
              <div className="flex items-center justify-between gap-3 border-t border-[color:var(--mid)] pt-3">
                <span>{product.packaging.label}</span>
                <strong className="font-mono text-[color:var(--roast)]">
                  {product.packaging.isFree ? "رایگان" : formatIrr(product.packaging.feeAmount)}
                </strong>
              </div>
            </div>''',
)

# Cart UI: marketplace language and packaging totals.
replace(
    "src/routes/cart.tsx",
    "  const { items, hydrated, apiItems, localSubtotal, updateQuantity, removeItem } = useCart();",
    "  const { items, hydrated, apiItems, localSubtotal, localPackagingTotal, updateQuantity, removeItem } = useCart();",
)
replace(
    "src/routes/cart.tsx",
    "قیمت، موجودی و قانون تک‌روستری بودن سبد توسط سرور رستا بررسی می‌شود.",
    "قیمت، موجودی، بسته‌بندی و گروه‌بندی چندروستری توسط سرور رستا بررسی می‌شود.",
)
replace(
    "src/routes/cart.tsx",
    "<h2 className=\"text-sm font-bold\">{items[0].roasteryName}</h2>",
    "<h2 className=\"text-sm font-bold\">سبد چندروستری رستا</h2>",
)
replace(
    "src/routes/cart.tsx",
    "                تک‌روستری",
    "                {new Set(items.map((item) => item.roasteryId)).size.toLocaleString(\"fa-IR\")} روستری",
)
replace(
    "src/routes/cart.tsx",
    "                          {formatWeight(item.weightGrams)} · دانه کامل",
    "                          {item.roasteryName} · {formatWeight(item.weightGrams)} · دانه کامل",
)
replace(
    "src/routes/cart.tsx",
    '''                        <p className="font-mono text-sm font-bold text-[color:var(--roast)]">
                          {formatIrr(item.unitPriceSnapshot * item.quantity)}
                        </p>''',
    '''                        <p className="font-mono text-sm font-bold text-[color:var(--roast)]">
                          {formatIrr(item.unitPriceSnapshot * item.quantity)}
                        </p>
                        <p className="mt-1 text-[10px] text-[color:var(--light)]">
                          {item.packagingFeeAmount === 0
                            ? "بسته‌بندی رایگان"
                            : `بسته‌بندی ${formatIrr(item.packagingFeeAmount * item.quantity)}`}
                        </p>''',
)
replace(
    "src/routes/cart.tsx",
    '''                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>ارسال</dt>''',
    '''                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>بسته‌بندی روستری</dt>
                  <dd className="font-mono">
                    {quote.packagingTotal === 0 ? "رایگان" : formatIrr(quote.packagingTotal)}
                  </dd>
                </div>
                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>ارسال</dt>''',
)
replace(
    "src/routes/cart.tsx",
    '''                <div className="flex justify-between">
                  <dt>جمع آخرین مشاهده</dt>
                  <dd className="font-mono">{formatIrr(localSubtotal)}</dd>
                </div>''',
    '''                <div className="flex justify-between">
                  <dt>جمع آخرین مشاهده</dt>
                  <dd className="font-mono">{formatIrr(localSubtotal)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt>بسته‌بندی آخرین مشاهده</dt>
                  <dd className="font-mono">
                    {localPackagingTotal === 0 ? "رایگان" : formatIrr(localPackagingTotal)}
                  </dd>
                </div>''',
)

# Checkout UI package line and roastery count.
replace(
    "src/routes/checkout.tsx",
    '''                  <p className="mt-1 text-[color:var(--light)]">
                    {formatWeight(item.weightGrams)} · ×{item.quantity.toLocaleString("fa-IR")}
                  </p>''',
    '''                  <p className="mt-1 text-[color:var(--light)]">
                    {item.roasteryName} · {formatWeight(item.weightGrams)} · ×{item.quantity.toLocaleString("fa-IR")}
                  </p>
                  <p className="mt-1 text-[10px] text-[color:var(--light)]">
                    {item.packagingFeeAmount === 0
                      ? "بسته‌بندی رایگان"
                      : `بسته‌بندی ${formatIrr(item.packagingFeeAmount * item.quantity)}`}
                  </p>''',
)
replace(
    "src/routes/checkout.tsx",
    '''                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>ارسال</dt>''',
    '''                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>بسته‌بندی روستری</dt>
                  <dd className="font-mono">
                    {quote.packagingTotal === 0 ? "رایگان" : formatIrr(quote.packagingTotal)}
                  </dd>
                </div>
                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>ارسال</dt>''',
)
replace(
    "src/routes/checkout.tsx",
    "            <h2 className=\"font-bold\">خلاصه سفارش</h2>",
    "            <h2 className=\"font-bold\">خلاصه سفارش · {quote?.groups.length.toLocaleString(\"fa-IR\") ?? \"—\"} روستری</h2>",
)

# Seller create/update controls.
replace(
    "src/components/seller/SellerOperationsDashboard.tsx",
    "    tastingNotes: \"\",\n  });",
    "    tastingNotes: \"\",\n    packagingFeeMode: \"free\" as \"free\" | \"fixed\",\n    packagingFeeAmount: \"0\",\n  });",
)
replace(
    "src/components/seller/SellerOperationsDashboard.tsx",
    "      tastingNotes: commaList(form.tastingNotes),\n      status: \"draft\",",
    "      tastingNotes: commaList(form.tastingNotes),\n      packagingFeeMode: form.packagingFeeMode,\n      packagingFeeAmount: Number(form.packagingFeeAmount || 0),\n      status: \"draft\",",
)
replace(
    "src/components/seller/SellerOperationsDashboard.tsx",
    '''        <div className="md:col-span-2">
          <TextField
            label="یادداشت‌های طعمی؛ جداشده با ویرگول"''',
    '''        <label className="grid gap-2 text-sm font-bold">
          هزینه بسته‌بندی
          <select
            value={form.packagingFeeMode}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                packagingFeeMode: event.target.value as typeof current.packagingFeeMode,
                packagingFeeAmount: event.target.value === "free" ? "0" : current.packagingFeeAmount,
              }))
            }
            className={fieldClass}
          >
            <option value="free">رایگان</option>
            <option value="fixed">مبلغ ثابت برای هر بسته</option>
          </select>
        </label>
        <TextField
          label="مبلغ هر بسته (ریال)"
          inputMode="numeric"
          disabled={form.packagingFeeMode === "free"}
          value={form.packagingFeeAmount}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              packagingFeeAmount: digits(event.target.value).slice(0, 16),
            }))
          }
        />
        <div className="md:col-span-2">
          <TextField
            label="یادداشت‌های طعمی؛ جداشده با ویرگول"''',
)
replace(
    "src/components/seller/SellerOperationsDashboard.tsx",
    "             {product.origin.name} · {productStatusLabel(product.status)}",
    "             {product.origin.name} · {productStatusLabel(product.status)} · {product.packaging.isFree ? \"بسته‌بندی رایگان\" : `بسته‌بندی ${formatIrr(product.packaging.feeAmount)}`}",
)
# Product packaging update inside ProductOperations.
replace(
    "src/components/seller/SellerOperationsDashboard.tsx",
    "  const [selectedVariantId, setSelectedVariantId] = useState(product.variants[0]?.id ?? \"\");",
    '''  const [selectedVariantId, setSelectedVariantId] = useState(product.variants[0]?.id ?? "");
  const [packagingMode, setPackagingMode] = useState<"free" | "fixed">(product.packaging.mode);
  const [packagingAmount, setPackagingAmount] = useState(String(product.packaging.feeAmount));''',
)
replace(
    "src/components/seller/SellerOperationsDashboard.tsx",
    '''  const stockMutation = useMutation({''',
    '''  const packagingMutation = useMutation({
    mutationFn: () =>
      updateSellerProduct(roastery.id, product.id, {
        packagingFeeMode: packagingMode,
        packagingFeeAmount: packagingMode === "fixed" ? Number(packagingAmount || 0) : 0,
      }),
    onSuccess: async () => {
      await onRefresh();
      pushToast({ title: "هزینه بسته‌بندی محصول ذخیره شد", variant: "success" });
    },
  });
  const stockMutation = useMutation({''',
)
replace(
    "src/components/seller/SellerOperationsDashboard.tsx",
    '''      <div className="mt-6 grid gap-6 xl:grid-cols-3">''',
    '''      {canEditCatalog ? (
        <div className="mt-5 grid gap-3 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 md:grid-cols-[1fr_1fr_auto]">
          <label className="grid gap-2 text-sm font-bold">
            بسته‌بندی محصول
            <select
              value={packagingMode}
              onChange={(event) => {
                const mode = event.target.value as "free" | "fixed";
                setPackagingMode(mode);
                if (mode === "free") setPackagingAmount("0");
              }}
              className={fieldClass}
            >
              <option value="free">رایگان</option>
              <option value="fixed">مبلغ ثابت برای هر بسته</option>
            </select>
          </label>
          <TextField
            label="مبلغ هر بسته (ریال)"
            inputMode="numeric"
            disabled={packagingMode === "free"}
            value={packagingAmount}
            onChange={(event) => setPackagingAmount(digits(event.target.value).slice(0, 16))}
          />
          <Button
            type="button"
            className="self-end"
            loading={packagingMutation.isPending}
            onClick={() => packagingMutation.mutate()}
          >
            ذخیره بسته‌بندی
          </Button>
          {packagingMutation.isError ? (
            <div className="md:col-span-3">
              <Alert variant="danger">{errorMessage(packagingMutation.error)}</Alert>
            </div>
          ) : null}
        </div>
      ) : null}

      <div className="mt-6 grid gap-6 xl:grid-cols-3">''',
)

# Permanent frontend audit and unit coverage.
Path("scripts/audit-r5d-product-packaging.mjs").write_text('''import { readFile, writeFile } from "node:fs/promises";

const paths = {
  contracts: "src/lib/api/contracts.ts",
  schemas: "src/lib/api/schemas.ts",
  financial: "src/lib/api/financial-contracts.ts",
  checkout: "src/lib/api/checkout.ts",
  orders: "src/lib/api/orders.ts",
  cartStorage: "src/lib/cart-storage.ts",
  cartContext: "src/lib/cart-context.tsx",
  product: "src/routes/products.$slug.tsx",
  cart: "src/routes/cart.tsx",
  checkoutRoute: "src/routes/checkout.tsx",
  sellerApi: "src/lib/api/seller-operations.ts",
  sellerUi: "src/components/seller/SellerOperationsDashboard.tsx",
  test: "tests/unit/r5d-packaging.test.ts",
  package: "package.json",
};
const files = Object.fromEntries(
  await Promise.all(Object.entries(paths).map(async ([key, path]) => [key, await readFile(path, "utf8")])),
);
const hasAll = (source, fragments) => fragments.every((fragment) => source.includes(fragment));
const gates = [];
const gate = (name, passed, evidence) => gates.push({ name, passed: Boolean(passed), evidence });
const scripts = JSON.parse(files.package).scripts ?? {};

gate(
  "permanent_gate",
  scripts["audit:r5d"] === "node scripts/audit-r5d-product-packaging.mjs" && scripts.check.includes("audit:r5d"),
  "Frontend check must permanently execute R5D audit.",
);
gate(
  "marketplace_contract",
  hasAll(files.schemas, ["groups: z.array(quoteGroupWireSchema).min(1).max(50)", "packaging_total", "shipment_legs"]) &&
    !files.schemas.includes(".max(1)"),
  "Browser contracts must accept several roastery groups and child shipments.",
);
gate(
  "explicit_packaging",
  hasAll(files.contracts, ["interface PackagingPolicy", "packagingTotal", "packagingFee"]) &&
    hasAll(files.checkout, ["packagingTotal: value.packaging_total", "services: line.services.map"]) &&
    hasAll(files.orders, ["packagingTotal: value.packaging_total", "packagingFee: service.packaging_fee"]),
  "Product, quote and order mappings must keep explicit packaging lines.",
);
gate(
  "multi_roastery_cart",
  files.cartContext.includes('status: "added"') &&
    !files.cartContext.includes('status: "requires_reset"') &&
    !files.cartStorage.includes("سبد شامل چند روستری است"),
  "Adding another roastery must not clear or reject the cart.",
);
gate(
  "customer_surfaces",
  hasAll(files.product, ["product.packaging.label", "product.packaging.feeAmount"]) &&
    hasAll(files.cart, ["quote.packagingTotal", "بسته‌بندی رایگان"]) &&
    hasAll(files.checkoutRoute, ["quote.packagingTotal", "بسته‌بندی روستری"]),
  "Product, cart and checkout must show free or paid packaging.",
);
gate(
  "seller_control",
  hasAll(files.sellerApi, ["packagingFeeMode", "packaging_fee_amount"]) &&
    hasAll(files.sellerUi, ["هزینه بسته‌بندی", "packagingMutation", "ذخیره بسته‌بندی"]),
  "Owner and manager UI must create and update product packaging.",
);
gate(
  "unit_contract",
  hasAll(files.test, ["calculates paid packaging per package", "keeps free packaging explicit", "supports multiple roasteries"]),
  "Unit tests must preserve packaging math and marketplace cart behaviour.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generated_at: new Date().toISOString(),
  passed: failed.length === 0,
  gates,
  failures: failed.map((item) => item.name),
  marker: failed.length === 0 ? "ROSTA_R5D_PRODUCT_PACKAGING_FRONTEND_COMPLETE" : null,
};
await writeFile("r5d-product-packaging-frontend-audit.json", `${JSON.stringify(report, null, 2)}\n`);
if (failed.length) {
  for (const item of failed) console.error(`- ${item.name}: ${item.evidence}`);
  process.exit(1);
}
console.log("ROSTA_R5D_PRODUCT_PACKAGING_FRONTEND_COMPLETE");
''')

Path("tests/unit/r5d-packaging.test.ts").write_text('''import { describe, expect, test } from "bun:test";
import { createCartSnapshot, normalizeCartItems } from "@/lib/cart-storage";
import type { ProductSummary, ProductVariant } from "@/lib/api/contracts";

const variant: ProductVariant = {
  id: "variant-a",
  sku: "PACK-250",
  weightGrams: 250,
  price: 2_000_000,
  currency: "IRR",
  isAvailable: true,
};

function product(roasteryId: string, feeAmount: number): ProductSummary {
  return {
    id: `product-${roasteryId}`,
    name: "قهوه تست",
    slug: `coffee-${roasteryId}`,
    origin: { id: "origin", name: "اتیوپی" },
    processingMethod: "washed",
    roastLevel: "medium",
    arabicaPercentage: 100,
    tastingNotes: ["مرکبات"],
    packaging: {
      mode: feeAmount === 0 ? "free" : "fixed",
      feeAmount,
      currency: "IRR",
      isFree: feeAmount === 0,
      label: feeAmount === 0 ? "بسته‌بندی روستری رایگان" : "هزینه بسته‌بندی روستری",
    },
    roastery: { id: roasteryId, name: `روستری ${roasteryId}`, slug: `roastery-${roasteryId}`, isVerified: true },
    variants: [variant],
    status: "published",
  };
}

describe("R5D packaging cart contract", () => {
  test("calculates paid packaging per package", () => {
    const item = createCartSnapshot(product("a", 125_000), variant, 3, 1);
    expect(item.packagingFeeAmount * item.quantity).toBe(375_000);
  });

  test("keeps free packaging explicit", () => {
    const item = createCartSnapshot(product("a", 0), variant, 1, 1);
    expect(item.packagingFeeMode).toBe("free");
    expect(item.packagingFeeAmount).toBe(0);
  });

  test("supports multiple roasteries", () => {
    const first = createCartSnapshot(product("a", 0), variant, 1, 1);
    const second = createCartSnapshot(
      product("b", 10_000),
      { ...variant, id: "variant-b", sku: "PACK-B" },
      1,
      2,
    );
    expect(normalizeCartItems([first, second])).toHaveLength(2);
  });
});
''')

package_path = Path("package.json")
package = json.loads(package_path.read_text())
package["scripts"]["audit:r5d"] = "node scripts/audit-r5d-product-packaging.mjs"
check = package["scripts"]["check"]
check = check.replace("bun run audit:r5a &&", "bun run audit:r5a && bun run audit:r5d &&")
package["scripts"]["check"] = check
package_path.write_text(json.dumps(package, ensure_ascii=False, indent=2) + "\n")
