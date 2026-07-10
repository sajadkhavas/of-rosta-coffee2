import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { getProduct, type Grind, type Weight } from "@/data/seed";

export interface CartItem {
  productSlug: string;
  weight: Weight;
  grind: Grind;
  qty: number;
  addedAt: number;
}

interface CartContextValue {
  items: CartItem[];
  addItem: (productSlug: string, weight: Weight, grind: Grind, qty?: number) => void;
  removeItem: (productSlug: string, weight: Weight, grind: Grind) => void;
  updateQty: (productSlug: string, weight: Weight, grind: Grind, qty: number) => void;
  clear: () => void;
  itemCount: number;
  subtotal: number;
}

const CartContext = createContext<CartContextValue | null>(null);
const STORAGE_KEY = "rosta_cart";

function sameLine(a: CartItem, slug: string, w: Weight, g: Grind) {
  return a.productSlug === slug && a.weight === w && a.grind === g;
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<CartItem[]>([]);
  const [hydrated, setHydrated] = useState(false);

  // Load once on mount (client only)
  useEffect(() => {
    try {
      if (typeof window === "undefined") return;
      const raw = window.localStorage.getItem(STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw) as CartItem[];
        if (Array.isArray(parsed)) setItems(parsed);
      }
    } catch {
      /* ignore */
    } finally {
      setHydrated(true);
    }
  }, []);

  // Persist after hydration
  useEffect(() => {
    if (!hydrated) return;
    try {
      if (typeof window === "undefined") return;
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
      /* ignore */
    }
  }, [items, hydrated]);

  const addItem = useCallback(
    (productSlug: string, weight: Weight, grind: Grind, qty: number = 1) => {
      setItems((prev) => {
        const idx = prev.findIndex((i) => sameLine(i, productSlug, weight, grind));
        if (idx >= 0) {
          const next = prev.slice();
          next[idx] = { ...next[idx], qty: next[idx].qty + qty };
          return next;
        }
        return [...prev, { productSlug, weight, grind, qty, addedAt: Date.now() }];
      });
    },
    [],
  );

  const removeItem = useCallback(
    (productSlug: string, weight: Weight, grind: Grind) => {
      setItems((prev) => prev.filter((i) => !sameLine(i, productSlug, weight, grind)));
    },
    [],
  );

  const updateQty = useCallback(
    (productSlug: string, weight: Weight, grind: Grind, qty: number) => {
      setItems((prev) => {
        if (qty <= 0) return prev.filter((i) => !sameLine(i, productSlug, weight, grind));
        return prev.map((i) =>
          sameLine(i, productSlug, weight, grind) ? { ...i, qty } : i,
        );
      });
    },
    [],
  );

  const clear = useCallback(() => setItems([]), []);

  const itemCount = useMemo(() => items.reduce((s, i) => s + i.qty, 0), [items]);
  const subtotal = useMemo(
    () =>
      items.reduce((sum, i) => {
        const p = getProduct(i.productSlug);
        if (!p) return sum;
        return sum + p.prices[i.weight] * i.qty;
      }, 0),
    [items],
  );

  const value = useMemo<CartContextValue>(
    () => ({ items, addItem, removeItem, updateQty, clear, itemCount, subtotal }),
    [items, addItem, removeItem, updateQty, clear, itemCount, subtotal],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart must be used within CartProvider");
  return ctx;
}
