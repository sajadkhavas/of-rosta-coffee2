import { z } from "zod";
import type { RoasteryDetail } from "./contracts";
import { apiFetch } from "./client";
import {
  parseContract,
  parseOptionalMedia,
  resourceSchema,
  roasteryDetailWireSchema,
} from "./schemas";

export interface CreateSellerRoasteryInput {
  name: string;
  slug: string;
  city?: string;
  description?: string;
  shippingPolicy?: string;
  preparationMinHours?: number | null;
  preparationMaxHours?: number | null;
}

const responseSchema = resourceSchema(roasteryDetailWireSchema);
type Wire = z.infer<typeof roasteryDetailWireSchema>;

export async function createSellerRoastery(
  input: CreateSellerRoasteryInput,
): Promise<RoasteryDetail> {
  const response = parseContract(
    responseSchema,
    await apiFetch<unknown>("/seller/roasteries", {
      method: "POST",
      body: {
        name: input.name.trim(),
        slug: input.slug.trim(),
        city: input.city?.trim() || null,
        description: input.description?.trim() || "",
        shipping_policy: input.shippingPolicy?.trim() || null,
        preparation_min_hours: input.preparationMinHours ?? null,
        preparation_max_hours: input.preparationMaxHours ?? null,
      },
    }),
    "ایجاد روستری فروشنده",
  );
  return mapRoastery(response.data);
}

function mapRoastery(value: Wire): RoasteryDetail {
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
