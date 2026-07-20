import { queryOptions } from "@tanstack/react-query";
import type {
  ApiCollection,
  ApiLinks,
  ApiMeta,
  ApiResource,
  CurrencyCode,
  MediaAsset,
  ProcessingMethod,
  ProductDetail,
  ProductFilters,
  ProductStatus,
  ProductSummary,
  ProductVariant,
  RoastBatchSummary,
  RoastLevel,
  RoasteryDetail,
  RoasterySummary,
  SearchCatalogResult,
  SearchCatalogType,
} from "./contracts";
import { apiFetch, isApiError } from "./client";
import { queryKeys } from "./query-keys";

interface WireMediaSource {
  url: string;
  width: number;
  format: "avif" | "webp" | "jpeg" | "png";
}

interface WireMediaAsset {
  id: string;
  alt: string;
  width: number;
  height: number;
  blur_data_url?: string | null;
  sources: WireMediaSource[];
}

interface WireRoasterySummary {
  id: string;
  name: string;
  slug: string;
  city?: string | null;
  is_verified: boolean;
  logo?: WireMediaAsset | null;
  cover?: WireMediaAsset | null;
  preparation_time?: { min_hours: number; max_hours: number } | null;
  rating?: { value: number; count: number } | null;
}

interface WireRoasteryDetail extends WireRoasterySummary {
  description: string;
  shipping_policy?: string | null;
}

interface WireProductVariant {
  id: string;
  sku: string;
  weight_grams: ProductVariant["weightGrams"];
  price: number;
  compare_at_price?: number | null;
  currency?: CurrencyCode;
  is_available: boolean;
  available_quantity?: number | null;
}

interface WireRoastBatchSummary {
  id: string;
  batch_code: string;
  roasted_at: string;
  available_from?: string | null;
}

interface WireProductSummary {
  id: string;
  name: string;
  slug: string;
  short_description?: string | null;
  origin: { id: string; name: string; country_code?: string | null };
  processing_method: ProcessingMethod;
  roast_level: RoastLevel;
  arabica_percentage: number;
  tasting_notes: string[];
  primary_image?: WireMediaAsset | null;
  roastery: WireRoasterySummary;
  variants: WireProductVariant[];
  latest_roast_batch?: WireRoastBatchSummary | null;
  status: ProductStatus;
}

interface WireProductDetail extends WireProductSummary {
  description: string;
  gallery: WireMediaAsset[];
  brewing_suggestions: string[];
  seo: { title?: string | null; description?: string | null };
}

interface WireSearchResult {
  products: WireProductSummary[];
  roasteries: WireRoasterySummary[];
  suggestions?: string[];
}

export interface CatalogListResult<T> {
  items: T[];
  meta?: ApiMeta;
  links?: ApiLinks;
}

export interface RoasteryListParams {
  page?: number;
  perPage?: number;
}

function mapMedia(value?: WireMediaAsset | null): MediaAsset | null {
  if (!value) return null;
  return {
    id: value.id,
    alt: value.alt,
    width: value.width,
    height: value.height,
    blurDataUrl: value.blur_data_url ?? null,
    sources: value.sources.map((source) => ({ ...source })),
  };
}

function mapRoastery(value: WireRoasterySummary): RoasterySummary {
  return {
    id: value.id,
    name: value.name,
    slug: value.slug,
    city: value.city ?? null,
    isVerified: value.is_verified,
    logo: mapMedia(value.logo),
    cover: mapMedia(value.cover),
    preparationTime: value.preparation_time
      ? { minHours: value.preparation_time.min_hours, maxHours: value.preparation_time.max_hours }
      : null,
    rating: value.rating ? { ...value.rating } : null,
  };
}

function mapRoasteryDetail(value: WireRoasteryDetail): RoasteryDetail {
  return {
    ...mapRoastery(value),
    description: value.description,
    shippingPolicy: value.shipping_policy ?? null,
  };
}

function mapVariant(value: WireProductVariant): ProductVariant {
  return {
    id: value.id,
    sku: value.sku,
    weightGrams: value.weight_grams,
    price: value.price,
    compareAtPrice: value.compare_at_price ?? null,
    currency: value.currency ?? "IRR",
    isAvailable: value.is_available,
    availableQuantity: value.available_quantity ?? null,
  };
}

function mapBatch(value?: WireRoastBatchSummary | null): RoastBatchSummary | null {
  if (!value) return null;
  return {
    id: value.id,
    batchCode: value.batch_code,
    roastedAt: value.roasted_at,
    availableFrom: value.available_from ?? null,
  };
}

function mapProduct(value: WireProductSummary): ProductSummary {
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
    primaryImage: mapMedia(value.primary_image),
    roastery: mapRoastery(value.roastery),
    variants: value.variants.map(mapVariant),
    latestRoastBatch: mapBatch(value.latest_roast_batch),
    status: value.status,
  };
}

function mapProductDetail(value: WireProductDetail): ProductDetail {
  return {
    ...mapProduct(value),
    description: value.description,
    gallery: value.gallery.map(mapMedia).filter((item): item is MediaAsset => Boolean(item)),
    brewingSuggestions: value.brewing_suggestions,
    seo: {
      title: value.seo.title ?? null,
      description: value.seo.description ?? null,
    },
  };
}

function appendArray(search: URLSearchParams, name: string, values?: readonly (string | number)[]) {
  values?.forEach((value) => search.append(`${name}[]`, String(value)));
}

export function productFiltersToSearch(filters: ProductFilters): URLSearchParams {
  const search = new URLSearchParams();
  if (filters.query) search.set("q", filters.query);
  appendArray(search, "origin", filters.origin);
  appendArray(search, "roast_level", filters.roastLevel);
  appendArray(search, "processing_method", filters.processingMethod);
  appendArray(search, "roastery", filters.roastery);
  appendArray(search, "weight", filters.weights);
  if (filters.minPrice !== undefined) search.set("min_price", String(filters.minPrice));
  if (filters.maxPrice !== undefined) search.set("max_price", String(filters.maxPrice));
  if (filters.available !== undefined) search.set("available", filters.available ? "true" : "false");
  if (filters.sort) search.set("sort", filters.sort);
  if (filters.page) search.set("page", String(filters.page));
  if (filters.perPage) search.set("per_page", String(filters.perPage));
  return search;
}

export async function listProducts(
  filters: ProductFilters = {},
): Promise<CatalogListResult<ProductSummary>> {
  const search = productFiltersToSearch(filters);
  const response = await apiFetch<ApiCollection<WireProductSummary>>(
    `/products${search.size ? `?${search.toString()}` : ""}`,
  );
  return { items: response.data.map(mapProduct), meta: response.meta, links: response.links };
}

export async function getProduct(slug: string): Promise<ProductDetail> {
  const response = await apiFetch<ApiResource<WireProductDetail>>(
    `/products/${encodeURIComponent(slug)}`,
  );
  return mapProductDetail(response.data);
}

export async function getRelatedProducts(slug: string): Promise<ProductSummary[]> {
  const response = await apiFetch<ApiResource<WireProductSummary[]>>(
    `/products/${encodeURIComponent(slug)}/related`,
  );
  return response.data.map(mapProduct);
}

export async function listRoasteries(
  params: RoasteryListParams = {},
): Promise<CatalogListResult<RoasterySummary>> {
  const search = new URLSearchParams();
  if (params.page) search.set("page", String(params.page));
  if (params.perPage) search.set("per_page", String(params.perPage));
  const response = await apiFetch<ApiCollection<WireRoasterySummary>>(
    `/roasteries${search.size ? `?${search.toString()}` : ""}`,
  );
  return { items: response.data.map(mapRoastery), meta: response.meta, links: response.links };
}

export async function getRoastery(slug: string): Promise<RoasteryDetail> {
  const response = await apiFetch<ApiResource<WireRoasteryDetail>>(
    `/roasteries/${encodeURIComponent(slug)}`,
  );
  return mapRoasteryDetail(response.data);
}

export async function searchCatalog(
  query: string,
  type: SearchCatalogType = "all",
): Promise<SearchCatalogResult> {
  const search = new URLSearchParams({ q: query, type });
  const response = await apiFetch<ApiResource<WireSearchResult>>(`/search?${search.toString()}`);
  return {
    products: response.data.products.map(mapProduct),
    roasteries: response.data.roasteries.map(mapRoastery),
    suggestions: response.data.suggestions ?? [],
  };
}

function retryPublicQuery(attempt: number, error: unknown) {
  if (isApiError(error) && [404, 422].includes(error.status)) return false;
  return attempt < 2;
}

export const productsQueryOptions = (filters: ProductFilters) =>
  queryOptions({
    queryKey: queryKeys.products.list(filters),
    queryFn: () => listProducts(filters),
    staleTime: 30_000,
    retry: retryPublicQuery,
  });

export const productQueryOptions = (slug: string) =>
  queryOptions({
    queryKey: queryKeys.products.detail(slug),
    queryFn: () => getProduct(slug),
    staleTime: 60_000,
    retry: retryPublicQuery,
  });

export const relatedProductsQueryOptions = (slug: string) =>
  queryOptions({
    queryKey: queryKeys.products.related(slug),
    queryFn: () => getRelatedProducts(slug),
    staleTime: 60_000,
    retry: retryPublicQuery,
  });

export const roasteriesQueryOptions = (params: RoasteryListParams) =>
  queryOptions({
    queryKey: queryKeys.roasteries.list({ ...params }),
    queryFn: () => listRoasteries(params),
    staleTime: 60_000,
    retry: retryPublicQuery,
  });

export const roasteryQueryOptions = (slug: string) =>
  queryOptions({
    queryKey: queryKeys.roasteries.detail(slug),
    queryFn: () => getRoastery(slug),
    staleTime: 60_000,
    retry: retryPublicQuery,
  });

export const searchCatalogQueryOptions = (query: string, type: SearchCatalogType) =>
  queryOptions({
    queryKey: queryKeys.search.results(query, type),
    queryFn: () => searchCatalog(query, type),
    enabled: query.trim().length > 0,
    staleTime: 30_000,
    retry: retryPublicQuery,
  });
