import { assertApprovedPaymentRedirect } from "@/config/site";
import { readPaymentExpectation } from "@/lib/transaction-intent";
import type {
  CartQuote,
  CartShipmentGroup,
  OrderDetail,
  PaymentRequestResult,
  ProductSummary,
  ProductVariant,
  RoasterySummary,
} from "./contracts";
import { apiFetch } from "./client";
import {
  parseContract,
  parseOptionalMedia,
  paymentRequestWireSchema,
  resourceSchema,
  type ProductSummaryWire,
  type ProductVariantWire,
  type QuoteWire,
  type RoasterySummaryWire,
} from "./schemas";
import {
  authoritativeOrderDetailWireSchema,
  authoritativeQuoteWireSchema,
} from "./financial-contracts";
import { verifiedPaymentWireSchema } from "./payment-contract";
import { mapOrderDetail } from "./orders";
import {
  isConsistentVerifiedPaid,
  type PaymentExpectationShape,
  type VerifiedPaymentResult,
} from "@/lib/payment-security";

export interface CartApiItem {
  variantId: string;
  quantity: number;
}

function roastery(value: RoasterySummaryWire): RoasterySummary {
  return {
    id: value.id,
    name: value.name,
    slug: value.slug,
    city: value.city ?? null,
    isVerified: value.is_verified,
    logo: parseOptionalMedia(value.logo),
    cover: parseOptionalMedia(value.cover),
    preparationTime: value.preparation_time
      ? {
          minHours: value.preparation_time.min_hours,
          maxHours: value.preparation_time.max_hours,
        }
      : null,
    rating: value.rating ? { ...value.rating } : null,
  };
}

function variant(value: ProductVariantWire): ProductVariant {
  return {
    id: value.id,
    sku: value.sku,
    weightGrams: value.weight_grams,
    price: value.price,
    compareAtPrice: value.compare_at_price ?? null,
    currency: value.currency,
    isAvailable: value.is_available,
    availableQuantity: value.available_quantity ?? null,
  };
}

function product(value: ProductSummaryWire): ProductSummary {
  return {
    id: value.id,
    name: value.name,
    slug: value.slug,
    shortDescription: value.short_description ?? null,
    origin: {
      id: value.origin.id,
      name: value.origin.name,
      countryCode: value.origin.country_code ?? null,
    },
    processingMethod: value.processing_method,
    roastLevel: value.roast_level,
    arabicaPercentage: value.arabica_percentage,
    tastingNotes: value.tasting_notes,
    primaryImage: parseOptionalMedia(value.primary_image),
    roastery: roastery(value.roastery),
    variants: value.variants.map(variant),
    latestRoastBatch: value.latest_roast_batch
      ? {
          id: value.latest_roast_batch.id,
          batchCode: value.latest_roast_batch.batch_code,
          roastedAt: value.latest_roast_batch.roasted_at,
          availableFrom: value.latest_roast_batch.available_from ?? null,
        }
      : null,
    status: value.status,
  };
}

function mapGroup(value: QuoteWire["groups"][number]): CartShipmentGroup {
  return {
    roastery: roastery(value.roastery),
    items: value.items.map((line) => ({
      id: line.id,
      product: product(line.product),
      variant: variant(line.variant),
      quantity: line.quantity,
      lineTotal: line.line_total,
    })),
    subtotal: value.subtotal,
    shippingCost: value.shipping_cost ?? value.shipping_total ?? null,
  };
}

function mapQuote(value: QuoteWire): CartQuote {
  return {
    id: value.id,
    expiresAt: value.expires_at,
    roasteryId: value.roastery_id ?? value.groups[0].roastery.id,
    groups: value.groups.map(mapGroup),
    subtotal: value.subtotal,
    shippingTotal: value.shipping_total,
    discountTotal: value.discount_total,
    grandTotal: value.grand_total,
    currency: value.currency,
    warnings: value.warnings.map((warning) => ({
      code: warning.code,
      message: warning.message,
      cartItemId: warning.cart_item_id,
    })),
  };
}

function itemsPayload(items: CartApiItem[]) {
  if (items.length < 1 || items.length > 100) throw new Error("تعداد اقلام سبد معتبر نیست.");

  const uniqueVariants = new Set<string>();
  return items.map((item) => {
    const variantId = item.variantId.trim();
    if (!variantId || uniqueVariants.has(variantId)) {
      throw new Error("Variant تکراری یا نامعتبر در سبد وجود دارد.");
    }
    if (!Number.isInteger(item.quantity) || item.quantity < 1 || item.quantity > 20) {
      throw new Error("تعداد هر Variant باید بین ۱ تا ۲۰ باشد.");
    }
    uniqueVariants.add(variantId);
    return { variant_id: variantId, quantity: item.quantity };
  });
}

export function createIdempotencyKey(scope: string): string {
  const normalizedScope = scope.trim().replace(/[^a-z0-9_-]/gi, "-").slice(0, 40);
  if (!normalizedScope) throw new Error("محدوده Idempotency نامعتبر است.");

  const cryptoApi = globalThis.crypto;
  if (cryptoApi?.randomUUID) return `rosta-${normalizedScope}-${cryptoApi.randomUUID()}`;
  if (cryptoApi?.getRandomValues) {
    const bytes = cryptoApi.getRandomValues(new Uint8Array(24));
    const entropy = Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
    return `rosta-${normalizedScope}-${entropy}`;
  }
  throw new Error("مرورگر امکان تولید کلید امن را ندارد.");
}

export async function validateCart(items: CartApiItem[]): Promise<CartQuote> {
  const raw = await apiFetch("/cart/validate", {
    method: "POST",
    body: { items: itemsPayload(items) },
  });
  const response = parseContract(
    resourceSchema(authoritativeQuoteWireSchema),
    raw,
    "اعتبارسنجی سبد",
  );
  return mapQuote(response.data);
}

export async function createCheckoutQuote(input: {
  items: CartApiItem[];
  addressId: string;
  couponCode?: string | null;
}): Promise<CartQuote> {
  const addressId = input.addressId.trim();
  if (!addressId) throw new Error("آدرس تحویل معتبر نیست.");

  const couponCode = input.couponCode?.trim().slice(0, 100) || null;
  const raw = await apiFetch("/checkout/quote", {
    method: "POST",
    body: {
      items: itemsPayload(input.items),
      address_id: addressId,
      coupon_code: couponCode,
    },
  });
  const response = parseContract(
    resourceSchema(authoritativeQuoteWireSchema),
    raw,
    "Quote تسویه‌حساب",
  );
  const quote = mapQuote(response.data);
  if (Date.parse(quote.expiresAt) <= Date.now()) {
    throw new Error("Quote دریافت‌شده منقضی شده است.");
  }
  return quote;
}

export async function createOrder(input: {
  quoteId: string;
  idempotencyKey: string;
  notes?: string | null;
}): Promise<OrderDetail> {
  const quoteId = input.quoteId.trim();
  const idempotencyKey = input.idempotencyKey.trim();
  const notes = input.notes?.trim() || null;
  if (!quoteId || !idempotencyKey) throw new Error("شناسه Quote یا Idempotency معتبر نیست.");
  if (idempotencyKey.length > 200) throw new Error("Idempotency Key بیش از حد طولانی است.");
  if (notes && notes.length > 1000) throw new Error("یادداشت سفارش حداکثر ۱۰۰۰ کاراکتر است.");

  const raw = await apiFetch("/orders", {
    method: "POST",
    body: { quote_id: quoteId, idempotency_key: idempotencyKey, notes },
  });
  const response = parseContract(
    resourceSchema(authoritativeOrderDetailWireSchema),
    raw,
    "ایجاد سفارش",
  );
  return mapOrderDetail(response.data);
}

export async function requestPayment(input: {
  orderId: string;
  idempotencyKey: string;
}): Promise<PaymentRequestResult> {
  const orderId = input.orderId.trim();
  const idempotencyKey = input.idempotencyKey.trim();
  if (!orderId || !idempotencyKey) throw new Error("اطلاعات شروع پرداخت معتبر نیست.");

  const raw = await apiFetch("/payments/request", {
    method: "POST",
    body: { order_id: orderId, idempotency_key: idempotencyKey },
  });
  const response = parseContract(resourceSchema(paymentRequestWireSchema), raw, "شروع پرداخت");
  return {
    paymentId: response.data.payment_id,
    redirectUrl: assertApprovedPaymentRedirect(response.data.redirect_url),
  };
}

export async function verifyPayment(
  paymentId: string,
  expectation: PaymentExpectationShape | null = readPaymentExpectation(paymentId),
): Promise<VerifiedPaymentResult> {
  const normalizedPaymentId = paymentId.trim();
  if (!normalizedPaymentId) throw new Error("شناسه پرداخت معتبر نیست.");

  const raw = await apiFetch(`/payments/${encodeURIComponent(normalizedPaymentId)}/verify`, {
    method: "POST",
  });
  const response = parseContract(
    resourceSchema(verifiedPaymentWireSchema),
    raw,
    "تأیید پرداخت",
  );
  const result: VerifiedPaymentResult = {
    paymentId: response.data.payment_id,
    status: response.data.status,
    orderId: response.data.order_id,
    orderStatus: response.data.order_status,
    amount: response.data.amount,
    currency: response.data.currency,
    verifiedAt: response.data.verified_at,
  };

  if (result.paymentId !== normalizedPaymentId) {
    throw new Error("پاسخ Verify متعلق به Payment مورد انتظار نیست.");
  }
  if (result.status === "paid" && !isConsistentVerifiedPaid(result, expectation)) {
    throw new Error("پاسخ پرداخت با سفارش، مبلغ یا Intent این مرورگر سازگار نیست.");
  }

  return result;
}
