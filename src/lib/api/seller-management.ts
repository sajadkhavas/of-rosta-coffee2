import { apiFetch } from "./client";
import type { RoasteryDetail } from "./contracts";
import {
  parseContract,
  parseOptionalMedia,
  resourceSchema,
  roasteryDetailWireSchema,
  type RoasteryDetailWire,
} from "./schemas";

export interface UpdateSellerRoasteryInput {
  name?: string;
  slug?: string;
  city?: string | null;
  description?: string;
  shippingPolicy?: string | null;
  preparationMinHours?: number | null;
  preparationMaxHours?: number | null;
  logoMediaId?: string | null;
  coverMediaId?: string | null;
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
      ? { minHours: value.preparation_time.min_hours, maxHours: value.preparation_time.max_hours }
      : null,
    rating: value.rating ? { ...value.rating } : null,
    description: value.description,
    shippingPolicy: value.shipping_policy ?? null,
  };
}

export async function updateSellerRoastery(
  roasteryId: string,
  input: UpdateSellerRoasteryInput,
): Promise<RoasteryDetail> {
  const body: Record<string, unknown> = {};
  if (input.name !== undefined) body.name = input.name.trim();
  if (input.slug !== undefined) body.slug = input.slug.trim();
  if (input.city !== undefined) body.city = input.city?.trim() || null;
  if (input.description !== undefined) body.description = input.description.trim();
  if (input.shippingPolicy !== undefined)
    body.shipping_policy = input.shippingPolicy?.trim() || null;
  if (input.preparationMinHours !== undefined)
    body.preparation_min_hours = input.preparationMinHours;
  if (input.preparationMaxHours !== undefined)
    body.preparation_max_hours = input.preparationMaxHours;
  if (input.logoMediaId !== undefined) body.logo_media_id = input.logoMediaId;
  if (input.coverMediaId !== undefined) body.cover_media_id = input.coverMediaId;

  const response = parseContract(
    resourceSchema(roasteryDetailWireSchema),
    await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}`, {
      method: "PATCH",
      body,
    }),
    "ویرایش اطلاعات روستری",
  );
  return mapRoastery(response.data);
}

export function mediaUrl(media?: { sources?: Array<{ url: string }> } | null): string | null {
  return media?.sources?.[0]?.url ?? null;
}
