import { queryOptions } from "@tanstack/react-query";
import type {
  ApiLinks,
  ApiMeta,
  MediaAsset,
  ProductDetail,
  ProductFilters,
  ProductSummary,
  ProductVariant,
  RoastBatchSummary,
  RoasteryDetail,
  RoasterySummary,
  SearchCatalogResult,
  SearchCatalogType,
} from "./contracts";
import { apiFetch, isApiError } from "./client";
import { queryKeys } from "./query-keys";
import {
  collectionSchema,
  parseContract,
  parseOptionalMedia,
  productDetailWireSchema,
  publicProductSummaryWireSchema,
  resourceSchema,
  roasteryDetailWireSchema,
  roasterySummaryWireSchema,
  searchResultWireSchema,
  type ProductDetailWire,
  type ProductSummaryWire,
  type ProductVariantWire,
  type RoastBatchWire,
  type RoasteryDetailWire,
  type RoasterySummaryWire,
} from "./schemas";

export interface CatalogListResult<T> {
  items: T[];
  meta?: ApiMeta;
  links?: ApiLinks;
}

export interface RoasteryListParams {
  page?: number;
  perPage?: number;
}

function mapRoastery(value: RoasterySummaryWire): RoasterySummary {
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

function mapRoasteryDetail(value: RoasteryDetailWire): RoasteryDetail {
  return {
    ...mapRoastery(value),
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
    primaryImage: parseOptionalMedia(value.primary_image),
    roastery: mapRoastery(value.roastery),
    variants: value.variants.map(mapVariant),
    latestRoastBatch: mapBatch(value.latest_roast_batch),
    status: value.status,
  };
}

function mapProductDetail(value: ProductDetailWire): ProductDetail {
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

function boundedInteger(value: number | undefined, min: number, max: number): number | undefined {
  if (value === undefined || !Number.isFinite(value)) return undefined;
  return Math.min(max, Math.max(min, Math.trunc(value)));
}

function boundedStrings(values: string[] | undefined, maxItems = 20): string[] | undefined {
  if (!values?.length) return undefined;
  return [...new Set(values.map((value) => value.trim()).filter(Boolean))].slice(0, maxItems);
}

function appendArray(search: URLSearchParams, name: string, values?: readonly (string | number)[]) {
  values?.forEach((value) => search.append(`${name}[]`, String(value)));
}

export function productFiltersToSearch(filters: ProductFilters): URLSearchParams {
  const search = new URLSearchParams();
  const query = filters.query?.trim().slice(0, 120);
  const origins = boundedStrings(filters.origin);
  const roasteries = boundedStrings(filters.roastery);
  const minPrice = boundedInteger(filters.minPrice, 0, Number.MAX_SAFE_INTEGER);
  const maxPrice = boundedInteger(filters.maxPrice, 0, Number.MAX_SAFE_INTEGER);
  const page = boundedInteger(filters.page, 1, 10_000);
  const perPage = boundedInteger(filters.perPage, 1, 100);

  if (query) search.set("q", query);
  appendArray(search, "origin", origins);
  appendArray(search, "roast_level", filters.roastLevel?.slice(0, 3));
  appendArray(search, "processing_method", filters.processingMethod?.slice(0, 4));
  appendArray(search, "roastery", roasteries);
  appendArray(search, "weight", filters.weights?.slice(0, 5));
  if (minPrice !== undefined) search.set("min_price", String(minPrice));
  if (maxPrice !== undefined && (minPrice === undefined || maxPrice >= minPrice)) {
    search.set("max_price", String(maxPrice));
  }
  if (filters.available !== undefined) {
    search.set("available", filters.available ? "true" : "false");
  }
  if (filters.sort) search.set("sort", filters.sort);
  if (page) search.set("page", String(page));
  if (perPage) search.set("per_page", String(perPage));
  return search;
}

export async function listProducts(
  filters: ProductFilters = {},
): Promise<CatalogListResult<ProductSummary>> {
  const search = productFiltersToSearch(filters);
  const raw = await apiFetch(`/products${search.size ? `?${search.toString()}` : ""}`);
  const response = parseContract(
    collectionSchema(publicProductSummaryWireSchema),
    raw,
    "فهرست محصولات",
  );
  return {
    items: response.data.map(mapProduct),
    meta: response.meta,
    links: response.links,
  };
}

export async function getProduct(slug: string): Promise<ProductDetail> {
  const raw = await apiFetch(`/products/${encodeURIComponent(slug)}`);
  const response = parseContract(resourceSchema(productDetailWireSchema), raw, "جزئیات محصول");
  return mapProductDetail(response.data);
}

export async function getRelatedProducts(slug: string): Promise<ProductSummary[]> {
  const raw = await apiFetch(`/products/${encodeURIComponent(slug)}/related`);
  const response = parseContract(
    resourceSchema(publicProductSummaryWireSchema.array().max(24)),
    raw,
    "محصولات مرتبط",
  );
  return response.data.map(mapProduct);
}

export async function listRoasteries(
  params: RoasteryListParams = {},
): Promise<CatalogListResult<RoasterySummary>> {
  const search = new URLSearchParams();
  const page = boundedInteger(params.page, 1, 10_000);
  const perPage = boundedInteger(params.perPage, 1, 100);
  if (page) search.set("page", String(page));
  if (perPage) search.set("per_page", String(perPage));

  const raw = await apiFetch(`/roasteries${search.size ? `?${search.toString()}` : ""}`);
  const response = parseContract(
    collectionSchema(roasterySummaryWireSchema),
    raw,
    "فهرست روستری‌ها",
  );
  return {
    items: response.data.map(mapRoastery),
    meta: response.meta,
    links: response.links,
  };
}

export async function getRoastery(slug: string): Promise<RoasteryDetail> {
  const raw = await apiFetch(`/roasteries/${encodeURIComponent(slug)}`);
  const response = parseContract(resourceSchema(roasteryDetailWireSchema), raw, "جزئیات روستری");
  return mapRoasteryDetail(response.data);
}

export async function searchCatalog(
  query: string,
  type: SearchCatalogType = "all",
): Promise<SearchCatalogResult> {
  const normalizedQuery = query.trim().slice(0, 120);
  if (!normalizedQuery) {
    return { products: [], roasteries: [], suggestions: [] };
  }

  const search = new URLSearchParams({ q: normalizedQuery, type });
  const raw = await apiFetch(`/search?${search.toString()}`);
  const response = parseContract(resourceSchema(searchResultWireSchema), raw, "جستجوی کاتالوگ");
  return {
    products: response.data.products.map(mapProduct),
    roasteries: response.data.roasteries.map(mapRoastery),
    suggestions: response.data.suggestions ?? [],
  };
}

function retryPublicQuery(attempt: number, error: unknown) {
  if (isApiError(error) && [404, 422, 429].includes(error.status)) return false;
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
