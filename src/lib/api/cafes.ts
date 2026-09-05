import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract, resourceSchema } from "./schemas";

const cafeStatus = z.enum(["pending", "verified", "suspended", "rejected"]);
const cafeSchema = z
  .object({
    id: z.string().min(1),
    name: z.string().min(1).max(160),
    slug: z.string().min(1).max(180),
    status: cafeStatus,
    is_verified: z.boolean(),
    city: z.string().min(1).max(120),
    address: z.string().min(1).max(1000),
    latitude: z.number().nullable(),
    longitude: z.number().nullable(),
    phone: z.string().nullable(),
    website_url: z.string().nullable(),
    instagram_handle: z.string().nullable(),
    description: z.string().nullable(),
    opening_hours: z.record(z.string(), z.unknown()).or(z.array(z.unknown())),
    amenities: z.array(z.string()).max(50),
    verified_at: z.string().nullable(),
    distance_km: z.number().nonnegative().nullable().optional(),
    membership_role: z.enum(["owner", "manager"]).optional(),
  })
  .strict();
const listSchema = z
  .object({
    data: z
      .object({ items: z.array(cafeSchema).max(200), pagination: z.unknown().optional() })
      .passthrough(),
  })
  .passthrough();

export type Cafe = z.infer<typeof cafeSchema>;
export type CafeStatus = z.infer<typeof cafeStatus>;
export interface CafeInput {
  name: string;
  slug?: string;
  city: string;
  address: string;
  latitude?: number | null;
  longitude?: number | null;
  phone?: string | null;
  websiteUrl?: string | null;
  instagramHandle?: string | null;
  description?: string | null;
}
function body(input: CafeInput) {
  return {
    name: input.name.trim(),
    slug: input.slug?.trim() || undefined,
    city: input.city.trim(),
    address: input.address.trim(),
    latitude: input.latitude ?? null,
    longitude: input.longitude ?? null,
    phone: input.phone?.trim() || null,
    website_url: input.websiteUrl?.trim() || null,
    instagram_handle: input.instagramHandle?.trim() || null,
    description: input.description?.trim() || null,
  };
}
export async function listCafes(
  params: { city?: string; lat?: number; lng?: number; radiusKm?: number } = {},
): Promise<Cafe[]> {
  const search = new URLSearchParams();
  if (params.city) search.set("city", params.city);
  if (params.lat !== undefined && params.lng !== undefined) {
    search.set("lat", String(params.lat));
    search.set("lng", String(params.lng));
  }
  if (params.radiusKm !== undefined) search.set("radius_km", String(params.radiusKm));
  const response = parseContract(
    listSchema,
    await apiFetch<unknown>(`/cafes${search.size ? `?${search}` : ""}`),
    "فهرست کافه‌ها",
  );
  return response.data.items;
}
export async function getCafe(slug: string): Promise<Cafe> {
  const response = parseContract(
    resourceSchema(cafeSchema),
    await apiFetch<unknown>(`/cafes/${encodeURIComponent(slug)}`),
    "صفحه کافه",
  );
  return response.data;
}
export async function applyCafe(input: CafeInput): Promise<Cafe> {
  const response = parseContract(
    resourceSchema(cafeSchema),
    await apiFetch<unknown>("/cafes/apply", { method: "POST", body: body(input) }),
    "ثبت درخواست کافه",
  );
  return response.data;
}
export async function listMyCafes(): Promise<Cafe[]> {
  const response = parseContract(listSchema, await apiFetch<unknown>("/me/cafes"), "کافه‌های من");
  return response.data.items;
}
export async function updateCafe(id: string, input: CafeInput): Promise<Cafe> {
  const response = parseContract(
    resourceSchema(cafeSchema),
    await apiFetch<unknown>(`/me/cafes/${encodeURIComponent(id)}`, {
      method: "PATCH",
      body: body(input),
    }),
    "ویرایش کافه",
  );
  return response.data;
}
export async function listAdminCafes(status: CafeStatus = "pending"): Promise<Cafe[]> {
  const response = parseContract(
    listSchema,
    await apiFetch<unknown>(`/admin/cafes?status=${status}&per_page=100`),
    "بررسی کافه‌ها",
  );
  return response.data.items;
}
export async function setCafeStatus(
  id: string,
  status: CafeStatus,
  reviewNote?: string,
): Promise<Cafe> {
  const response = parseContract(
    resourceSchema(cafeSchema),
    await apiFetch<unknown>(`/admin/cafes/${encodeURIComponent(id)}/status`, {
      method: "PATCH",
      body: { status, review_note: reviewNote?.trim() || null },
    }),
    "تغییر وضعیت کافه",
  );
  return response.data;
}
export const myCafesQueryOptions = () =>
  queryOptions({ queryKey: ["cafes", "mine"], queryFn: listMyCafes, staleTime: 30_000 });
