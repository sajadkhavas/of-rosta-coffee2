import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract } from "./schemas";

const threshold = z.union([z.literal(5000), z.literal(10000), z.literal(20000), z.literal(50000)]);
const tier = z
  .object({
    id: z.string().min(1),
    min_weight_grams: threshold,
    unit_price: z.number().int().positive(),
    is_active: z.boolean(),
  })
  .strict();
const responseSchema = z
  .object({ data: z.object({ items: z.array(tier).max(4) }).strict() })
  .passthrough();
export type WholesaleThreshold = z.infer<typeof threshold>;
export interface WholesaleTier {
  id?: string;
  minWeightGrams: WholesaleThreshold;
  unitPrice: number;
  isActive: boolean;
}
function path(roasteryId: string, productId: string, variantId: string) {
  return `/seller/roasteries/${encodeURIComponent(roasteryId)}/products/${encodeURIComponent(productId)}/variants/${encodeURIComponent(variantId)}/wholesale-tiers`;
}
export async function getWholesaleTiers(
  roasteryId: string,
  productId: string,
  variantId: string,
): Promise<WholesaleTier[]> {
  const response = parseContract(
    responseSchema,
    await apiFetch<unknown>(path(roasteryId, productId, variantId)),
    "قیمت عمده",
  );
  return response.data.items.map((item) => ({
    id: item.id,
    minWeightGrams: item.min_weight_grams,
    unitPrice: item.unit_price,
    isActive: item.is_active,
  }));
}
export async function replaceWholesaleTiers(
  roasteryId: string,
  productId: string,
  variantId: string,
  tiers: WholesaleTier[],
): Promise<WholesaleTier[]> {
  const response = parseContract(
    responseSchema,
    await apiFetch<unknown>(path(roasteryId, productId, variantId), {
      method: "PUT",
      body: {
        tiers: tiers.map((item) => ({
          min_weight_grams: item.minWeightGrams,
          unit_price: item.unitPrice,
          is_active: item.isActive,
        })),
      },
    }),
    "ذخیره قیمت عمده",
  );
  return response.data.items.map((item) => ({
    id: item.id,
    minWeightGrams: item.min_weight_grams,
    unitPrice: item.unit_price,
    isActive: item.is_active,
  }));
}
