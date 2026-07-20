import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import type { ProductSummary, ProductVariant } from "@/lib/api/contracts";
import type { CartApiItem } from "@/lib/api/checkout";
import { bestMediaUrl } from "@/lib/catalog-format";

export interface CartItem {
  variantId: string;
  productId: string;
  productSlug: string;
  productName: string;
  productImageUrl?: string | null;
  roasteryId: string;
  roasteryName: string;
  roasterySlug: string;
  weightGrams: ProductVariant["weightGrams"];
  unitPriceSnapshot: number;
  quantity: number;
  addedAt: number;
}

export interface CartAddInput {
  product: ProductSummary;
  variant: ProductVariant;
}

export type CartAddResult =
  | { status: "added" }
  | { status: "requires_reset"; currentRoasteryName: string };

interface CartContextValue {
  items: CartItem[];
  hydrated: boolean;
  addItem: (input: CartAddInput, quantity?: number) => CartAddResult;
  replaceWithItem: (input: CartAddInput, quantity?: number) => void;
  removeItem: (variantId: string) => void;
  updateQuantity: (variantId: string, quantity: number) => void;
  clear: () => void;
  itemCount: number;
  localSubtotal: number;
  apiItems: CartApiItem[];
  roasteryId?: string;
}

const CartContext = createContext<CartContextValue | null>(null);
const STORAGE_KEY = "rosta_cart_v2";
const MAX_QUANTITY = 20;

function clampQuantity(value: number): number {
  if (!Number.isFinite(value)) return 1;
  return Math.max(1, Math.min(MAX_QUANTITY, Math.trunc(value)));
}

function snapshot(input: CartAddInput, quantity = 1): CartItem {
  return {
    variantId: input.variant.id,
    productId: input.product.id,
    productSlug: input.product.slug,
    productName: input.product.name,
    productImageUrl: bestMediaUrl(input.product.primaryImage),
    roasteryId: input.product.roastery.id,
    roasteryName: input.product.roastery.name,
    roasterySlug: input.product.roastery.slug,
    weightGrams: input.variant.weightGrams,
    unitPriceSnapshot: input.variant.price,
    quantity: clampQuantity(quantity),
    addedAt: Date.now(),
  };
}

function sanitize(raw: unknown): CartItem[] {
  if (!Array.isArray(raw)) return [];
  const out: CartItem[] = [];
  for (const entry of raw) {
    if (!entry || typeof entry !== "object") continue;
    const value = entry as Partial<CartItem>;
    if (
      typeof value.variantId !== "string" ||
      typeof value.productId !== "string" ||
      typeof value.productSlug !== "string" ||
      typeof value.productName !== "string" ||
      typeof value.roasteryId !== "string" ||
      typeof value.roasteryName !== "string" ||
      typeof value.roasterySlug !== "string" ||
      typeof value.weightGrams !== "number" ||
      typeof value.unitPriceSnapshot !== "number"
    ) {
      continue;
    }
    if (out.length > 0 && out[0].roasteryId !== value.roasteryId) continue;
    const quantity = clampQuantity(value.quantity ?? 1);
    const existing = out.find((item) => item.variantId === value.variantId);
    if (existing) {
      existing.quantity = clampQuantity(existing.quantity + quantity);
      continue;
    }
    out.push({
      variantId: value.variantId,
      productId: value.productId,
      productSlug: value.productSlug,
      productName: value.productName,
      productImageUrl: value.productImageUrl ?? null,
      roasteryId: value.roasteryId,
      roasteryName: value.roasteryName,
      roasterySlug: value.roasterySlug,
      weightGrams: value.weightGrams as CartItem["weightGrams"],
      unitPriceSnapshot: value.unitPriceSnapshot,
      quantity,
      addedAt: typeof value.addedAt === "number" ? value.addedAt : Date.now(),
    });
  }
  return out;
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<CartItem[]>([]);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      if (raw) setItems(sanitize(JSON.parse(raw)));
      window.localStorage.removeItem("rosta_cart");
    } catch {
      // A disabled storage layer must not break shopping.
    } finally {
      setHydrated(true);
    }
  }, []);

  useEffect(() => {
    if (!hydrated) return;
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
      // Cart remains usable for the current tab when persistence is unavailable.
    }
  }, [hydrated, items]);

  const addItem = useCallback(
    (input: CartAddInput, quantity = 1): CartAddResult => {
      const currentRoastery = items[0];
      if (currentRoastery && currentRoastery.roasteryId !== input.product.roastery.id) {
        return {
          status: "requires_reset",
          currentRoasteryName: currentRoastery.roasteryName,
        };
      }
      setItems((current) => {
        const index = current.findIndex((item) => item.variantId === input.variant.id);
        if (index === -1) return [...current, snapshot(input, quantity)];
        const next = current.slice();
        next[index] = {
          ...next[index],
          unitPriceSnapshot: input.variant.price,
          quantity: clampQuantity(next[index].quantity + quantity),
        };
        return next;
      });
      return { status: "added" };
    },
    [items],
  );

  const replaceWithItem = useCallback((input: CartAddInput, quantity = 1) => {
    setItems([snapshot(input, quantity)]);
  }, []);

  const removeItem = useCallback((variantId: string) => {
    setItems((current) => current.filter((item) => item.variantId !== variantId));
  }, []);

  const updateQuantity = useCallback((variantId: string, quantity: number) => {
    if (quantity <= 0) {
      setItems((current) => current.filter((item) => item.variantId !== variantId));
      return;
    }
    setItems((current) =>
      current.map((item) =>
        item.variantId === variantId
          ? { ...item, quantity: clampQuantity(quantity) }
          : item,
      ),
    );
  }, []);

  const clear = useCallback(() => setItems([]), []);
  const itemCount = useMemo(
    () => items.reduce((sum, item) => sum + item.quantity, 0),
    [items],
  );
  const localSubtotal = useMemo(
    () =>
      items.reduce(
        (sum, item) => sum + item.unitPriceSnapshot * item.quantity,
        0,
      ),
    [items],
  );
  const apiItems = useMemo<CartApiItem[]>(
    () => items.map((item) => ({ variantId: item.variantId, quantity: item.quantity })),
    [items],
  );

  const value = useMemo<CartContextValue>(
    () => ({
      items,
      hydrated,
      addItem,
      replaceWithItem,
      removeItem,
      updateQuantity,
      clear,
      itemCount,
      localSubtotal,
      apiItems,
      roasteryId: items[0]?.roasteryId,
    }),
    [
      items,
      hydrated,
      addItem,
      replaceWithItem,
      removeItem,
      updateQuantity,
      clear,
      itemCount,
      localSubtotal,
      apiItems,
    ],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const context = useContext(CartContext);
  if (!context) throw new Error("useCart must be used within CartProvider");
  return context;
}
