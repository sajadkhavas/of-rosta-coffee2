import type {
  ApiResource,
  CartQuote,
  CartShipmentGroup,
  CurrencyCode,
  OrderDetail,
  PaymentRequestResult,
  PaymentStatus,
  ProductSummary,
  ProductVariant,
  RoasterySummary,
} from "./contracts";
import { apiFetch } from "./client";

export interface CartApiItem {
  variantId: string;
  quantity: number;
}

interface WireMedia {
  id?: string;
  alt?: string;
  width?: number;
  height?: number;
  blur_data_url?: string | null;
  sources?: Array<{
    url: string;
    width?: number;
    format?: "avif" | "webp" | "jpeg" | "png";
  }>;
}

interface WireVariant {
  id: string;
  sku?: string;
  weight_grams: ProductVariant["weightGrams"];
  price: number;
  compare_at_price?: number | null;
  currency?: CurrencyCode;
  is_available?: boolean;
  available_quantity?: number | null;
}

interface WireRoastery {
  id: string;
  name: string;
  slug?: string;
  city?: string | null;
  is_verified?: boolean;
  logo?: WireMedia | null;
  cover?: WireMedia | null;
}

interface WireProduct {
  id: string;
  name: string;
  slug: string;
  short_description?: string | null;
  origin?: { id?: string; name?: string; country_code?: string | null };
  processing_method?: ProductSummary["processingMethod"];
  roast_level?: ProductSummary["roastLevel"];
  arabica_percentage?: number;
  tasting_notes?: string[];
  primary_image?: WireMedia | null;
  roastery?: WireRoastery;
  variants?: WireVariant[];
  status?: ProductSummary["status"];
}

interface WireCartLine {
  id: string;
  product: WireProduct;
  variant: WireVariant;
  quantity: number;
  line_total: number;
}

interface WireQuote {
  id: string;
  expires_at: string;
  roastery_id?: string | null;
  groups?: Array<{
    roastery: WireRoastery;
    items: WireCartLine[];
    subtotal: number;
    shipping_cost?: number | null;
    shipping_total?: number | null;
  }>;
  subtotal: number;
  shipping_total: number;
  discount_total: number;
  grand_total: number;
  currency?: CurrencyCode;
  warnings?: Array<{ code?: string; message?: string; cart_item_id?: string }>;
}

interface WireOrder {
  id: string;
  order_number: string;
  status: OrderDetail["status"];
  placed_at?: string | null;
  subtotal?: number;
  shipping_total?: number;
  discount_total?: number;
  grand_total: number;
  currency?: CurrencyCode;
  address?: null;
  sub_orders?: [];
}

interface WirePaymentRequest {
  payment_id: string;
  redirect_url: string;
}

interface WirePaymentVerify {
  status: PaymentStatus;
  order_id: string;
}

function media(value?: WireMedia | null) {
  if (!value) return null;
  return {
    id: value.id ?? value.sources?.[0]?.url ?? "media",
    alt: value.alt ?? "",
    width: value.width ?? value.sources?.[0]?.width ?? 1,
    height: value.height ?? value.width ?? 1,
    blurDataUrl: value.blur_data_url ?? null,
    sources: (value.sources ?? []).map((source) => ({
      url: source.url,
      width: source.width ?? value.width ?? 1,
      format: source.format ?? "jpeg",
    })),
  };
}

function roastery(value: WireRoastery): RoasterySummary {
  return {
    id: value.id,
    name: value.name,
    slug: value.slug ?? value.id,
    city: value.city ?? null,
    isVerified: Boolean(value.is_verified),
    logo: media(value.logo),
    cover: media(value.cover),
  };
}

function variant(value: WireVariant): ProductVariant {
  return {
    id: value.id,
    sku: value.sku ?? value.id,
    weightGrams: value.weight_grams,
    price: value.price,
    compareAtPrice: value.compare_at_price ?? null,
    currency: value.currency ?? "IRR",
    isAvailable: value.is_available ?? true,
    availableQuantity: value.available_quantity ?? null,
  };
}

function product(value: WireProduct): ProductSummary {
  const mappedRoastery = value.roastery
    ? roastery(value.roastery)
    : { id: "unknown", name: "روستری", slug: "unknown", isVerified: false };
  return {
    id: value.id,
    name: value.name,
    slug: value.slug,
    shortDescription: value.short_description ?? null,
    origin: {
      id: value.origin?.id ?? "unknown",
      name: value.origin?.name ?? "نامشخص",
      countryCode: value.origin?.country_code ?? null,
    },
    processingMethod: value.processing_method ?? "other",
    roastLevel: value.roast_level ?? "medium",
    arabicaPercentage: value.arabica_percentage ?? 0,
    tastingNotes: value.tasting_notes ?? [],
    primaryImage: media(value.primary_image),
    roastery: mappedRoastery,
    variants: (value.variants ?? []).map(variant),
    status: value.status ?? "published",
  };
}

function mapGroup(value: NonNullable<WireQuote["groups"]>[number]): CartShipmentGroup {
  return {
    roastery: roastery(value.roastery),
    items: value.items.map((line) => ({
      id: line.id,
      product: product({ ...line.product, roastery: line.product.roastery ?? value.roastery }),
      variant: variant(line.variant),
      quantity: line.quantity,
      lineTotal: line.line_total,
    })),
    subtotal: value.subtotal,
    shippingCost: value.shipping_cost ?? value.shipping_total ?? null,
  };
}

function mapQuote(value: WireQuote): CartQuote {
  return {
    id: value.id,
    expiresAt: value.expires_at,
    roasteryId: value.roastery_id ?? value.groups?.[0]?.roastery.id ?? null,
    groups: (value.groups ?? []).map(mapGroup),
    subtotal: value.subtotal,
    shippingTotal: value.shipping_total,
    discountTotal: value.discount_total,
    grandTotal: value.grand_total,
    currency: value.currency ?? "IRR",
    warnings: (value.warnings ?? []).map((warning) => ({
      code: warning.code ?? "cart.warning",
      message: warning.message ?? "سبد نیاز به بررسی دارد.",
      cartItemId: warning.cart_item_id,
    })),
  };
}

function itemsPayload(items: CartApiItem[]) {
  return items.map((item) => ({ variant_id: item.variantId, quantity: item.quantity }));
}

export function createIdempotencyKey(scope: string): string {
  const uuid = globalThis.crypto?.randomUUID?.();
  const entropy = uuid ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  return `rosta-${scope}-${entropy}`;
}

export function assertSafePaymentRedirect(value: string): string {
  const url = new URL(value);
  const isLocalHttp = url.protocol === "http:" && ["localhost", "127.0.0.1"].includes(url.hostname);
  if (url.protocol !== "https:" && !isLocalHttp) {
    throw new Error("آدرس انتقال درگاه معتبر نیست.");
  }
  return url.toString();
}

export async function validateCart(items: CartApiItem[]): Promise<CartQuote> {
  const response = await apiFetch<ApiResource<WireQuote>>("/cart/validate", {
    method: "POST",
    body: { items: itemsPayload(items) },
  });
  return mapQuote(response.data);
}

export async function createCheckoutQuote(input: {
  items: CartApiItem[];
  addressId: string;
  couponCode?: string | null;
}): Promise<CartQuote> {
  const response = await apiFetch<ApiResource<WireQuote>>("/checkout/quote", {
    method: "POST",
    body: {
      items: itemsPayload(input.items),
      address_id: input.addressId,
      coupon_code: input.couponCode?.trim() || null,
    },
  });
  return mapQuote(response.data);
}

export async function createOrder(input: {
  quoteId: string;
  idempotencyKey: string;
  notes?: string | null;
}): Promise<OrderDetail> {
  const response = await apiFetch<ApiResource<WireOrder>>("/orders", {
    method: "POST",
    body: {
      quote_id: input.quoteId,
      idempotency_key: input.idempotencyKey,
      notes: input.notes?.trim() || null,
    },
  });
  return {
    id: response.data.id,
    orderNumber: response.data.order_number,
    status: response.data.status,
    placedAt: response.data.placed_at ?? null,
    subtotal: response.data.subtotal ?? 0,
    shippingTotal: response.data.shipping_total ?? 0,
    discountTotal: response.data.discount_total ?? 0,
    grandTotal: response.data.grand_total,
    currency: response.data.currency ?? "IRR",
    address: null,
    subOrders: [],
  };
}

export async function requestPayment(input: {
  orderId: string;
  idempotencyKey: string;
}): Promise<PaymentRequestResult> {
  const response = await apiFetch<ApiResource<WirePaymentRequest>>("/payments/request", {
    method: "POST",
    body: { order_id: input.orderId, idempotency_key: input.idempotencyKey },
  });
  return {
    paymentId: response.data.payment_id,
    redirectUrl: assertSafePaymentRedirect(response.data.redirect_url),
  };
}

export async function verifyPayment(paymentId: string): Promise<{
  status: PaymentStatus;
  orderId: string;
}> {
  const response = await apiFetch<ApiResource<WirePaymentVerify>>(
    `/payments/${encodeURIComponent(paymentId)}/verify`,
    { method: "POST" },
  );
  return { status: response.data.status, orderId: response.data.order_id };
}
