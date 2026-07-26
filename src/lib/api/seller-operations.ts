import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import type {
  ApiLinks,
  ApiMeta,
  MediaAsset,
  OrderDetail,
  OrderSummary,
  ProductDetail,
  ProductSummary,
  ProductVariant,
  RoastBatchSummary,
  RoasteryDetail,
} from "./contracts";
import { apiFetch } from "./client";
import {
  collectionSchema,
  mediaAssetSchema,
  parseContract,
  parseOptionalMedia,
  productSummaryWireSchema,
  resourceSchema,
  roastBatchWireSchema,
  roasteryDetailWireSchema,
  type ProductSummaryWire,
  type ProductVariantWire,
  type RoastBatchWire,
  type RoasteryDetailWire,
} from "./schemas";
import {
  authoritativeOrderDetailWireSchema,
  authoritativeOrderSummaryWireSchema,
} from "./financial-contracts";
import { mapOrderDetail, mapOrderSummary } from "./orders";

const identifier = z.string().trim().min(1).max(240);
const isoDate = z.string().refine((value) => Number.isFinite(Date.parse(value)));
const sellerRoleSchema = z.enum([
  "roastery_owner",
  "roastery_manager",
  "roastery_staff",
  "administrator",
]);

const sellerRoasteryWireSchema = roasteryDetailWireSchema.extend({
  status: z.enum(["pending", "verified", "suspended", "rejected"]),
  access_roles: z.array(sellerRoleSchema).min(1).max(4),
});

const sellerRoasteryListSchema = z
  .object({
    data: z.object({ items: z.array(sellerRoasteryWireSchema).max(500) }).strict(),
  })
  .passthrough();

const sellerProductDetailWireSchema = productSummaryWireSchema
  .extend({
    description: z.string().trim().max(50_000),
    gallery: z.array(z.unknown()).max(30),
    brewing_suggestions: z.array(z.string().trim().min(1).max(500)).max(30),
    seo: z
      .object({
        title: z.string().trim().max(180).nullable().optional(),
        description: z.string().trim().max(500).nullable().optional(),
      })
      .strict(),
  })
  .strict();

const originSchema = z
  .object({
    id: identifier,
    name: z.string().trim().min(1).max(160),
    slug: z.string().trim().min(1).max(180),
    country_code: z.string().trim().min(2).max(3).nullable().optional(),
  })
  .strict();

const stockLedgerSchema = z
  .object({
    id: identifier,
    variant_id: identifier,
    roast_batch_id: identifier.nullable().optional(),
    delta: z.number().int().min(-1_000_000).max(1_000_000),
    balance_after: z.number().int().min(0).max(1_000_000_000),
    reason: z.enum([
      "opening",
      "purchase",
      "correction",
      "damage",
      "expiry",
      "return",
      "reservation",
      "release",
      "sale",
    ]),
    created_at: isoDate.nullable().optional(),
  })
  .strict();

const uploadIntentSchema = z
  .object({
    upload_id: identifier,
    upload_url: z.string().url().max(4_000),
    method: z.literal("PUT"),
    headers: z.record(z.string(), z.string()),
    object_key: z.string().trim().min(1).max(512),
    expires_at: isoDate,
  })
  .strict();

const sellerRoasteryResourceSchema = resourceSchema(roasteryDetailWireSchema);
const sellerProductResourceSchema = resourceSchema(sellerProductDetailWireSchema);
const variantResourceSchema = resourceSchema(productSummaryWireSchema.shape.variants.element);
const roastBatchResourceSchema = resourceSchema(roastBatchWireSchema);
const stockLedgerResourceSchema = resourceSchema(stockLedgerSchema);
const mediaResourceSchema = resourceSchema(mediaAssetSchema);
const uploadIntentResourceSchema = resourceSchema(uploadIntentSchema);

export type SellerRoasteryStatus = z.infer<typeof sellerRoasteryWireSchema.shape.status>;
export type SellerAccessRole = z.infer<typeof sellerRoleSchema>;
export type StockReason = z.infer<typeof stockLedgerSchema.shape.reason>;
export type StockLedgerEntry = z.infer<typeof stockLedgerSchema>;
export type SellerOrigin = z.infer<typeof originSchema>;

export interface SellerRoastery extends RoasteryDetail {
  status: SellerRoasteryStatus;
  accessRoles: SellerAccessRole[];
}

export interface SellerList<T> {
  items: T[];
  meta?: ApiMeta;
  links?: ApiLinks;
}

export interface UpsertProductInput {
  originId: string;
  primaryMediaId?: string | null;
  name: string;
  slug: string;
  shortDescription?: string | null;
  description?: string;
  processingMethod: "washed" | "natural" | "honey" | "other";
  roastLevel: "light" | "medium" | "dark";
  arabicaPercentage: number;
  tastingNotes: string[];
  brewingSuggestions?: string[];
  packagingFeeMode?: "free" | "fixed";
  packagingFeeAmount?: number;
  seoTitle?: string | null;
  seoDescription?: string | null;
  status?: "draft" | "review" | "archived";
  galleryMediaIds?: string[];
}

export interface UpsertVariantInput {
  sku: string;
  weightGrams: 50 | 100 | 250 | 500 | 1000;
  price: number;
  compareAtPrice?: number | null;
  isActive?: boolean;
}

export interface CreateRoastBatchInput {
  batchCode: string;
  roastedAt: string;
  availableFrom?: string | null;
  isActive?: boolean;
}

export interface AdjustStockInput {
  delta: number;
  reason: Exclude<StockReason, "reservation" | "release" | "sale">;
  roastBatchId?: string | null;
  idempotencyKey: string;
}

export interface FulfillmentInput {
  status: "accepted" | "rejected" | "preparing" | "ready_to_ship" | "shipped" | "delivered";
  reason?: string;
  carrier?: string;
  trackingCode?: string;
  internalNote?: string;
}

export interface UploadMediaInput {
  file: File;
  alt: string;
}

function mapRoastery(value: RoasteryDetailWire): RoasteryDetail {
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
    description: value.description,
    shippingPolicy: value.shipping_policy ?? null,
  };
}

function mapVariant(value: ProductVariantWire): ProductVariant {
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

function mapBatch(value?: RoastBatchWire | null): RoastBatchSummary | null {
  if (!value) return null;
  return {
    id: value.id,
    batchCode: value.batch_code,
    roastedAt: value.roasted_at,
    availableFrom: value.available_from ?? null,
  };
}

function mapProduct(value: ProductSummaryWire): ProductSummary {
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
    packaging: {
      mode: value.packaging.mode,
      feeAmount: value.packaging.fee_amount,
      currency: value.packaging.currency,
      isFree: value.packaging.is_free,
      label: value.packaging.label,
    },
    primaryImage: parseOptionalMedia(value.primary_image),
    roastery: {
      id: value.roastery.id,
      name: value.roastery.name,
      slug: value.roastery.slug,
      city: value.roastery.city ?? null,
      isVerified: value.roastery.is_verified,
      logo: parseOptionalMedia(value.roastery.logo),
      cover: parseOptionalMedia(value.roastery.cover),
      preparationTime: value.roastery.preparation_time
        ? {
            minHours: value.roastery.preparation_time.min_hours,
            maxHours: value.roastery.preparation_time.max_hours,
          }
        : null,
      rating: value.roastery.rating ? { ...value.roastery.rating } : null,
    },
    variants: value.variants.map(mapVariant),
    latestRoastBatch: mapBatch(value.latest_roast_batch),
    status: value.status,
  };
}

function mapProductDetail(value: z.infer<typeof sellerProductDetailWireSchema>): ProductDetail {
  return {
    ...mapProduct(value),
    description: value.description,
    gallery: value.gallery
      .map(parseOptionalMedia)
      .filter((item): item is MediaAsset => Boolean(item)),
    brewingSuggestions: value.brewing_suggestions,
    seo: {
      title: value.seo.title ?? null,
      description: value.seo.description ?? null,
    },
  };
}

function productBody(input: UpsertProductInput) {
  return {
    origin_id: input.originId.trim(),
    primary_media_id: input.primaryMediaId || null,
    name: input.name.trim(),
    slug: input.slug.trim(),
    short_description: input.shortDescription?.trim() || null,
    description: input.description?.trim() || "",
    processing_method: input.processingMethod,
    roast_level: input.roastLevel,
    arabica_percentage: input.arabicaPercentage,
    tasting_notes: uniqueStrings(input.tastingNotes, 30),
    brewing_suggestions: uniqueStrings(input.brewingSuggestions ?? [], 30),
    packaging_fee_mode: input.packagingFeeMode ?? "free",
    packaging_fee_amount: input.packagingFeeMode === "fixed" ? (input.packagingFeeAmount ?? 0) : 0,
    seo_title: input.seoTitle?.trim() || null,
    seo_description: input.seoDescription?.trim() || null,
    status: input.status ?? "draft",
    gallery_media_ids: [...new Set(input.galleryMediaIds ?? [])].slice(0, 30),
  };
}

export async function listSellerRoasteries(): Promise<SellerRoastery[]> {
  const response = sellerRoasteryListSchema.parse(await apiFetch<unknown>("/seller/roasteries"));
  return response.data.items.map((value) => ({
    ...mapRoastery(value),
    status: value.status,
    accessRoles: value.access_roles,
  }));
}

export async function getSellerRoastery(roasteryId: string): Promise<RoasteryDetail> {
  const response = parseContract(
    sellerRoasteryResourceSchema,
    await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}`),
    "جزئیات روستری فروشنده",
  );
  return mapRoastery(response.data);
}

export async function listSellerOrigins(): Promise<SellerList<SellerOrigin>> {
  const response = parseContract(
    collectionSchema(originSchema),
    await apiFetch<unknown>("/seller/origins?per_page=100"),
    "خاستگاه‌های پنل فروشنده",
  );
  return { items: response.data, meta: response.meta, links: response.links };
}

export async function listSellerProducts(roasteryId: string): Promise<SellerList<ProductSummary>> {
  const response = parseContract(
    collectionSchema(productSummaryWireSchema),
    await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/products`),
    "محصولات پنل فروشنده",
  );
  return {
    items: response.data.map(mapProduct),
    meta: response.meta,
    links: response.links,
  };
}

export async function getSellerProduct(
  roasteryId: string,
  productId: string,
): Promise<ProductDetail> {
  const response = parseContract(
    sellerProductResourceSchema,
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/products/${encodeURIComponent(productId)}`,
    ),
    "جزئیات محصول فروشنده",
  );
  return mapProductDetail(response.data);
}

export async function createSellerProduct(
  roasteryId: string,
  input: UpsertProductInput,
): Promise<ProductDetail> {
  const response = parseContract(
    sellerProductResourceSchema,
    await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/products`, {
      method: "POST",
      body: productBody(input),
    }),
    "ایجاد محصول فروشنده",
  );
  return mapProductDetail(response.data);
}

export async function updateSellerProduct(
  roasteryId: string,
  productId: string,
  input: Partial<UpsertProductInput>,
): Promise<ProductDetail> {
  const body = Object.fromEntries(
    Object.entries(
      productBody({
        originId: input.originId ?? "",
        name: input.name ?? "",
        slug: input.slug ?? "",
        processingMethod: input.processingMethod ?? "washed",
        roastLevel: input.roastLevel ?? "medium",
        arabicaPercentage: input.arabicaPercentage ?? 100,
        tastingNotes: input.tastingNotes ?? [],
        ...input,
      }),
    ).filter(([key]) => {
      const sourceKey = {
        origin_id: "originId",
        primary_media_id: "primaryMediaId",
        name: "name",
        slug: "slug",
        short_description: "shortDescription",
        description: "description",
        processing_method: "processingMethod",
        roast_level: "roastLevel",
        arabica_percentage: "arabicaPercentage",
        tasting_notes: "tastingNotes",
        brewing_suggestions: "brewingSuggestions",
        packaging_fee_mode: "packagingFeeMode",
        packaging_fee_amount: "packagingFeeAmount",
        seo_title: "seoTitle",
        seo_description: "seoDescription",
        status: "status",
        gallery_media_ids: "galleryMediaIds",
      }[key] as keyof UpsertProductInput;
      return Object.prototype.hasOwnProperty.call(input, sourceKey);
    }),
  );
  const response = parseContract(
    sellerProductResourceSchema,
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/products/${encodeURIComponent(productId)}`,
      { method: "PATCH", body },
    ),
    "ویرایش محصول فروشنده",
  );
  return mapProductDetail(response.data);
}

export async function createSellerVariant(
  roasteryId: string,
  productId: string,
  input: UpsertVariantInput,
): Promise<ProductVariant> {
  const response = parseContract(
    variantResourceSchema,
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/products/${encodeURIComponent(productId)}/variants`,
      {
        method: "POST",
        body: {
          sku: input.sku.trim(),
          weight_grams: input.weightGrams,
          price: input.price,
          compare_at_price: input.compareAtPrice ?? null,
          is_active: input.isActive ?? true,
        },
      },
    ),
    "ایجاد وزن محصول",
  );
  return mapVariant(response.data);
}

export async function updateSellerVariant(
  roasteryId: string,
  productId: string,
  variantId: string,
  input: Partial<UpsertVariantInput>,
): Promise<ProductVariant> {
  const body: Record<string, unknown> = {};
  if (input.sku !== undefined) body.sku = input.sku.trim();
  if (input.weightGrams !== undefined) body.weight_grams = input.weightGrams;
  if (input.price !== undefined) body.price = input.price;
  if (input.compareAtPrice !== undefined) body.compare_at_price = input.compareAtPrice;
  if (input.isActive !== undefined) body.is_active = input.isActive;
  const response = parseContract(
    variantResourceSchema,
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/products/${encodeURIComponent(productId)}/variants/${encodeURIComponent(variantId)}`,
      { method: "PATCH", body },
    ),
    "ویرایش وزن محصول",
  );
  return mapVariant(response.data);
}

export async function listSellerRoastBatches(
  roasteryId: string,
  productId: string,
): Promise<SellerList<RoastBatchSummary>> {
  const response = parseContract(
    collectionSchema(roastBatchWireSchema),
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/products/${encodeURIComponent(productId)}/roast-batches`,
    ),
    "بچ‌های رست محصول",
  );
  return {
    items: response.data.map((batch) => mapBatch(batch) as RoastBatchSummary),
    meta: response.meta,
    links: response.links,
  };
}

export async function createSellerRoastBatch(
  roasteryId: string,
  productId: string,
  input: CreateRoastBatchInput,
): Promise<RoastBatchSummary> {
  const response = parseContract(
    roastBatchResourceSchema,
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/products/${encodeURIComponent(productId)}/roast-batches`,
      {
        method: "POST",
        body: {
          batch_code: input.batchCode.trim(),
          roasted_at: input.roastedAt,
          available_from: input.availableFrom || null,
          is_active: input.isActive ?? true,
        },
      },
    ),
    "ثبت بچ رست",
  );
  return mapBatch(response.data) as RoastBatchSummary;
}

export async function listStockLedger(
  roasteryId: string,
  variantId: string,
): Promise<SellerList<StockLedgerEntry>> {
  const response = parseContract(
    collectionSchema(stockLedgerSchema),
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/variants/${encodeURIComponent(variantId)}/stock-ledger`,
    ),
    "دفتر موجودی وزن محصول",
  );
  return { items: response.data, meta: response.meta, links: response.links };
}

export async function adjustSellerStock(
  roasteryId: string,
  variantId: string,
  input: AdjustStockInput,
): Promise<StockLedgerEntry> {
  const response = parseContract(
    stockLedgerResourceSchema,
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/variants/${encodeURIComponent(variantId)}/stock-adjustments`,
      {
        method: "POST",
        body: {
          delta: input.delta,
          reason: input.reason,
          roast_batch_id: input.roastBatchId || null,
          idempotency_key: input.idempotencyKey,
        },
      },
    ),
    "اصلاح موجودی",
  );
  return response.data;
}

export async function listSellerOrders(roasteryId: string): Promise<SellerList<OrderSummary>> {
  const response = parseContract(
    collectionSchema(authoritativeOrderSummaryWireSchema.or(authoritativeOrderDetailWireSchema)),
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/orders?per_page=100`,
    ),
    "صف سفارش‌های روستری",
  );
  return {
    items: response.data.map(mapOrderSummary),
    meta: response.meta,
    links: response.links,
  };
}

export async function getSellerOrder(roasteryId: string, orderId: string): Promise<OrderDetail> {
  const response = parseContract(
    resourceSchema(authoritativeOrderDetailWireSchema),
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/orders/${encodeURIComponent(orderId)}`,
    ),
    "جزئیات سفارش روستری",
  );
  return mapOrderDetail(response.data);
}

export async function transitionSellerOrder(
  roasteryId: string,
  orderId: string,
  input: FulfillmentInput,
): Promise<OrderDetail> {
  const response = parseContract(
    resourceSchema(authoritativeOrderDetailWireSchema),
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/orders/${encodeURIComponent(orderId)}/fulfillment`,
      {
        method: "PATCH",
        body: {
          status: input.status,
          reason: input.reason?.trim() || null,
          carrier: input.carrier?.trim() || null,
          tracking_code: input.trackingCode?.trim() || null,
          internal_note: input.internalNote?.trim() || null,
        },
      },
    ),
    "تغییر وضعیت سفارش روستری",
  );
  return mapOrderDetail(response.data);
}

export async function listSellerMedia(roasteryId: string): Promise<SellerList<MediaAsset>> {
  const response = parseContract(
    collectionSchema(mediaAssetSchema),
    await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/media`),
    "رسانه‌های روستری",
  );
  return {
    items: response.data.map((item) => parseOptionalMedia(item) as MediaAsset),
    meta: response.meta,
    links: response.links,
  };
}

export async function uploadSellerMedia(
  roasteryId: string,
  input: UploadMediaInput,
): Promise<MediaAsset> {
  const checksum = await sha256Hex(input.file);
  const intent = parseContract(
    uploadIntentResourceSchema,
    await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/media/uploads`, {
      method: "POST",
      body: {
        filename: input.file.name,
        mime_type: input.file.type,
        size_bytes: input.file.size,
        checksum_sha256: checksum,
      },
    }),
    "ایجاد مجوز آپلود رسانه",
  ).data;

  const uploadResponse = await fetch(intent.upload_url, {
    method: intent.method,
    headers: intent.headers,
    body: input.file,
  });
  if (!uploadResponse.ok) {
    throw new Error(`آپلود مستقیم رسانه ناموفق بود (${uploadResponse.status}).`);
  }

  const dimensions = await imageDimensions(input.file);
  const completed = parseContract(
    mediaResourceSchema,
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/media/uploads/${encodeURIComponent(intent.upload_id)}/complete`,
      {
        method: "POST",
        body: {
          alt: input.alt.trim(),
          width: dimensions.width,
          height: dimensions.height,
          blur_data_url: null,
        },
      },
    ),
    "تکمیل ثبت رسانه",
  );
  const media = parseOptionalMedia(completed.data);
  if (!media) throw new Error("رسانه ثبت شد اما قرارداد پاسخ معتبر نبود.");
  return media;
}

export const sellerRoasteriesQueryOptions = () =>
  queryOptions({
    queryKey: ["seller", "roasteries"],
    queryFn: listSellerRoasteries,
    staleTime: 30_000,
  });

export const sellerProductsQueryOptions = (roasteryId: string) =>
  queryOptions({
    queryKey: ["seller", "roasteries", roasteryId, "products"],
    queryFn: () => listSellerProducts(roasteryId),
    enabled: Boolean(roasteryId),
    staleTime: 20_000,
  });

export const sellerOrdersQueryOptions = (roasteryId: string) =>
  queryOptions({
    queryKey: ["seller", "roasteries", roasteryId, "orders"],
    queryFn: () => listSellerOrders(roasteryId),
    enabled: Boolean(roasteryId),
    staleTime: 10_000,
  });

function uniqueStrings(values: string[], max: number): string[] {
  return [...new Set(values.map((value) => value.trim()).filter(Boolean))].slice(0, max);
}

async function sha256Hex(file: File): Promise<string> {
  if (!globalThis.crypto?.subtle) {
    throw new Error("مرورگر امکان محاسبه امن SHA-256 را ندارد.");
  }
  const digest = await globalThis.crypto.subtle.digest("SHA-256", await file.arrayBuffer());
  return Array.from(new Uint8Array(digest))
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}

async function imageDimensions(file: File): Promise<{ width: number; height: number }> {
  const url = URL.createObjectURL(file);
  try {
    const image = new Image();
    await new Promise<void>((resolve, reject) => {
      image.onload = () => resolve();
      image.onerror = () => reject(new Error("ابعاد تصویر قابل خواندن نیست."));
      image.src = url;
    });
    if (!image.naturalWidth || !image.naturalHeight) {
      throw new Error("ابعاد تصویر معتبر نیست.");
    }
    return { width: image.naturalWidth, height: image.naturalHeight };
  } finally {
    URL.revokeObjectURL(url);
  }
}
