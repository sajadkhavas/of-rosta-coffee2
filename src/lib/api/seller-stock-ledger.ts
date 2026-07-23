import { z } from "zod";
import { apiFetch } from "./client";
import { collectionSchema, parseContract } from "./schemas";

const identifier = z.string().trim().min(1).max(240);
const stockLedgerEntrySchema = z
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
      "reservation_release",
    ]),
    created_at: z
      .string()
      .refine((value) => Number.isFinite(Date.parse(value)))
      .nullable()
      .optional(),
  })
  .strict();

export type SellerStockLedgerEntry = z.infer<typeof stockLedgerEntrySchema>;

export async function listAuthoritativeStockLedger(
  roasteryId: string,
  variantId: string,
): Promise<SellerStockLedgerEntry[]> {
  const response = parseContract(
    collectionSchema(stockLedgerEntrySchema),
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/variants/${encodeURIComponent(variantId)}/stock-ledger?per_page=100`,
    ),
    "دفتر authoritative موجودی",
  );
  return response.data;
}
