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
import {
  CART_STORAGE_KEY,
  MAX_CART_ITEMS,
  MAX_CART_QUANTITY,
  createCartSnapshot,
  normalizeCartItems,
  parseStoredCart,
  readCartStorage,
  removeLegacyCartStorage,
  writeCartStorage,
  type CartItem,
} from "@/lib/cart-storage";

export type { CartItem } from "@/lib/cart-storage";

export interface CartAddInput {
  product: ProductSummary;
  variant: ProductVariant;
}

export type CartAddResult =
  | { status: "added" }
  | { status: "requires_reset"; currentRoasteryName: string }
  | { status: "limit_reached" };

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

function clampQuantity(value: number): number {
  if (!Number.isFinite(value)) return 1;
  return Math.max(1, Math.min(MAX_CART_QUANTITY, Math.trunc(value)));
}

function safelyNormalize(items: CartItem[]): CartItem[] {
  try {
    return normalizeCartItems(items);
  } catch {
    return [];
  }
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<CartItem[]>([]);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    try {
      const loaded = readCartStorage(window.localStorage);
      setItems(loaded);
      removeLegacyCartStorage(window.localStorage);
    } catch {
      // A disabled or corrupted storage layer must not break shopping.
    } finally {
      setHydrated(true);
    }
  }, []);

  useEffect(() => {
    if (!hydrated) return;
    try {
      writeCartStorage(window.localStorage, items);
    } catch {
      // Cart remains usable for the current tab when persistence is unavailable.
    }
  }, [hydrated, items]);

  useEffect(() => {
    const synchronizeAcrossTabs = (event: StorageEvent) => {
      if (event.storageArea !== window.localStorage || event.key !== CART_STORAGE_KEY) return;
      setItems(parseStoredCart(event.newValue));
    };
    window.addEventListener("storage", synchronizeAcrossTabs);
    return () => window.removeEventListener("storage", synchronizeAcrossTabs);
  }, []);

  const addItem = useCallback(
    (input: CartAddInput, quantity = 1): CartAddResult => {
      const currentRoastery = items[0];
      if (currentRoastery && currentRoastery.roasteryId !== input.product.roastery.id) {
        return {
          status: "requires_reset",
          currentRoasteryName: currentRoastery.roasteryName,
        };
      }

      const existing = items.find((item) => item.variantId === input.variant.id);
      if (!existing && items.length >= MAX_CART_ITEMS) return { status: "limit_reached" };

      setItems((current) => {
        const index = current.findIndex((item) => item.variantId === input.variant.id);
        if (index === -1) {
          return safelyNormalize([
            ...current,
            createCartSnapshot(input.product, input.variant, quantity),
          ]);
        }
        const next = current.slice();
        next[index] = {
          ...next[index],
          productName: input.product.name,
          roasteryName: input.product.roastery.name,
          unitPriceSnapshot: input.variant.price,
          quantity: clampQuantity(next[index].quantity + quantity),
        };
        return safelyNormalize(next);
      });
      return { status: "added" };
    },
    [items],
  );

  const replaceWithItem = useCallback((input: CartAddInput, quantity = 1) => {
    setItems([createCartSnapshot(input.product, input.variant, quantity)]);
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
      safelyNormalize(
        current.map((item) =>
          item.variantId === variantId
            ? { ...item, quantity: clampQuantity(quantity) }
            : item,
        ),
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
