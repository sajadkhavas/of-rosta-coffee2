import { z } from "zod";
import type { ProductSummary, ProductVariant } from "@/lib/api/contracts";
import { bestMediaUrl } from "@/lib/catalog-format";

export const CART_STORAGE_KEY = "rosta_cart_v3";
export const LEGACY_CART_STORAGE_KEYS = ["rosta_cart", "rosta_cart_v2"] as const;
export const CART_STORAGE_VERSION = 3 as const;
export const MAX_CART_ITEMS = 50;
export const MAX_CART_QUANTITY = 20;
export const MAX_CART_STORAGE_BYTES = 64 * 1024;

const ID_PATTERN = /^[A-Za-z0-9._:-]{1,200}$/;
const SLUG_PATTERN = /^[A-Za-z0-9\u0600-\u06ff](?:[A-Za-z0-9\u0600-\u06ff_-]{0,178}[A-Za-z0-9\u0600-\u06ff])?$/;
const LOCAL_HOSTS = new Set(["localhost", "127.0.0.1", "[::1]"]);

function isSafeImageUrl(value: string): boolean {
  if (value.startsWith("/")) return !value.startsWith("//") && !value.includes("\\");
  try {
    const url = new URL(value);
    return (
      url.protocol === "https:" ||
      (url.protocol === "http:" && LOCAL_HOSTS.has(url.hostname.toLowerCase()))
    );
  } catch {
    return false;
  }
}

const idSchema = z.string().trim().regex(ID_PATTERN);
const slugSchema = z.string().trim().regex(SLUG_PATTERN);
const textSchema = (maximum: number) => z.string().trim().min(1).max(maximum);
const weightSchema = z.union([
  z.literal(50),
  z.literal(100),
  z.literal(250),
  z.literal(500),
  z.literal(1000),
]);

export const cartItemSchema = z
  .object({
    variantId: idSchema,
    productId: idSchema,
    productSlug: slugSchema,
    productName: textSchema(240),
    productImageUrl: z.string().max(2_000).refine(isSafeImageUrl).nullable().optional(),
    roasteryId: idSchema,
    roasteryName: textSchema(160),
    roasterySlug: slugSchema,
    weightGrams: weightSchema,
    unitPriceSnapshot: z.number().int().nonnegative().max(Number.MAX_SAFE_INTEGER),
    quantity: z.number().int().min(1).max(MAX_CART_QUANTITY),
    addedAt: z.number().int().positive().max(4_102_444_800_000),
  })
  .strict();

export interface CartItem extends z.infer<typeof cartItemSchema> {}

const cartEnvelopeSchema = z
  .object({
    version: z.literal(CART_STORAGE_VERSION),
    updatedAt: z.number().int().positive().max(4_102_444_800_000),
    items: z.array(cartItemSchema).max(MAX_CART_ITEMS),
  })
  .strict()
  .superRefine((value, context) => {
    const variantIds = new Set<string>();
    const roasteryId = value.items[0]?.roasteryId;

    value.items.forEach((item, index) => {
      if (variantIds.has(item.variantId)) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["items", index, "variantId"],
          message: "Variant تکراری است.",
        });
      }
      variantIds.add(item.variantId);
      if (roasteryId && item.roasteryId !== roasteryId) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["items", index, "roasteryId"],
          message: "سبد شامل چند روستری است.",
        });
      }
    });
  });

interface StorageLike {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
  removeItem(key: string): void;
}

function byteLength(value: string): number {
  return typeof TextEncoder !== "undefined" ? new TextEncoder().encode(value).byteLength : value.length * 2;
}

function clampQuantity(value: number): number {
  if (!Number.isFinite(value)) return 1;
  return Math.max(1, Math.min(MAX_CART_QUANTITY, Math.trunc(value)));
}

function normalizeLegacyItems(raw: unknown): CartItem[] {
  if (!Array.isArray(raw)) return [];
  const items: CartItem[] = [];
  const variants = new Set<string>();
  let roasteryId: string | undefined;

  for (const entry of raw.slice(0, MAX_CART_ITEMS)) {
    if (!entry || typeof entry !== "object") continue;
    const candidate = entry as Record<string, unknown>;
    const parsed = cartItemSchema.safeParse({
      ...candidate,
      quantity: clampQuantity(Number(candidate.quantity ?? 1)),
      addedAt:
        Number.isInteger(candidate.addedAt) && Number(candidate.addedAt) > 0
          ? Number(candidate.addedAt)
          : Date.now(),
    });
    if (!parsed.success) continue;
    if (variants.has(parsed.data.variantId)) continue;
    if (roasteryId && parsed.data.roasteryId !== roasteryId) continue;

    roasteryId ??= parsed.data.roasteryId;
    variants.add(parsed.data.variantId);
    items.push(parsed.data);
  }

  return items;
}

export function parseStoredCart(raw: string | null): CartItem[] {
  if (!raw || byteLength(raw) > MAX_CART_STORAGE_BYTES) return [];
  try {
    const decoded: unknown = JSON.parse(raw);
    const envelope = cartEnvelopeSchema.safeParse(decoded);
    if (envelope.success) return envelope.data.items;
    return normalizeLegacyItems(decoded);
  } catch {
    return [];
  }
}

export function serializeStoredCart(items: CartItem[], now = Date.now()): string {
  const envelope = cartEnvelopeSchema.parse({
    version: CART_STORAGE_VERSION,
    updatedAt: now,
    items,
  });
  const serialized = JSON.stringify(envelope);
  if (byteLength(serialized) > MAX_CART_STORAGE_BYTES) {
    throw new Error("حجم سبد از محدوده امن بیشتر است.");
  }
  return serialized;
}

export function readCartStorage(storage: StorageLike): CartItem[] {
  const current = storage.getItem(CART_STORAGE_KEY);
  if (current) return parseStoredCart(current);

  for (const key of LEGACY_CART_STORAGE_KEYS) {
    const legacy = storage.getItem(key);
    if (!legacy) continue;
    const items = parseStoredCart(legacy);
    try {
      storage.setItem(CART_STORAGE_KEY, serializeStoredCart(items));
    } catch {
      // The in-memory cart remains usable if migration persistence is restricted.
    }
    return items;
  }
  return [];
}

export function writeCartStorage(storage: StorageLike, items: CartItem[]): void {
  storage.setItem(CART_STORAGE_KEY, serializeStoredCart(items));
}

export function removeLegacyCartStorage(storage: StorageLike): void {
  for (const key of LEGACY_CART_STORAGE_KEYS) storage.removeItem(key);
}

export function createCartSnapshot(
  product: ProductSummary,
  variant: ProductVariant,
  quantity = 1,
  now = Date.now(),
): CartItem {
  const image = bestMediaUrl(product.primaryImage);
  return cartItemSchema.parse({
    variantId: variant.id,
    productId: product.id,
    productSlug: product.slug,
    productName: product.name,
    productImageUrl: image && isSafeImageUrl(image) ? image : null,
    roasteryId: product.roastery.id,
    roasteryName: product.roastery.name,
    roasterySlug: product.roastery.slug,
    weightGrams: variant.weightGrams,
    unitPriceSnapshot: variant.price,
    quantity: clampQuantity(quantity),
    addedAt: now,
  });
}

export function normalizeCartItems(items: CartItem[]): CartItem[] {
  return cartEnvelopeSchema.parse({
    version: CART_STORAGE_VERSION,
    updatedAt: Date.now(),
    items: items.slice(0, MAX_CART_ITEMS),
  }).items;
}
