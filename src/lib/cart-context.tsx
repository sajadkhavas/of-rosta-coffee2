import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { getProduct, type Weight } from "@/data/seed";

export interface CartItem {
  productSlug: string;
  weight: Weight;
  qty: number;
  addedAt: number;
}

interface CartContextValue {
  items: CartItem[];
  addItem: (productSlug: string, weight: Weight, qty?: number) => void;
  removeItem: (productSlug: string, weight: Weight) => void;
  updateQty: (productSlug: string, weight: Weight, qty: number) => void;
  clear: () => void;
  itemCount: number;
  subtotal: number;
}

const CartContext = createContext<CartContextValue | null>(null);
const STORAGE_KEY = "rosta_cart";

function sameLine(a: CartItem, slug: string, w: Weight) {
  return a.productSlug === slug && a.weight === w;
}

// Migrate legacy cart data that may contain a stale `grind` field. Merge lines
// that become duplicates after stripping grind.
function sanitize(raw: unknown): CartItem[] {
  if (!Array.isArray(raw)) return [];
  const out: CartItem[] = [];
  for (const entry of raw) {
    if (!entry || typeof entry !== "object") continue;
    const e = entry as Record<string, unknown>;
    const productSlug = typeof e.productSlug === "string" ? e.productSlug : null;
    const weight = typeof e.weight === "number" ? (e.weight as Weight) : null;
    const qty = typeof e.qty === "number" ? e.qty : 1;
    const addedAt = typeof e.addedAt === "number" ? e.addedAt : Date.now();
    if (!productSlug || !weight) continue;
    const idx = out.findIndex((i) => sameLine(i, productSlug, weight));
    if (idx >= 0) {
      out[idx] = { ...out[idx], qty: out[idx].qty + qty };
    } else {
      out.push({ productSlug, weight, qty, addedAt });
    }
  }
  return out;
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
        setItems(sanitize(JSON.parse(raw)));
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
    (productSlug: string, weight: Weight, qty: number = 1) => {
      setItems((prev) => {
        const idx = prev.findIndex((i) => sameLine(i, productSlug, weight));
        if (idx >= 0) {
          const next = prev.slice();
          next[idx] = { ...next[idx], qty: next[idx].qty + qty };
          return next;
        }
        return [...prev, { productSlug, weight, qty, addedAt: Date.now() }];
      });
    },
    [],
  );

  const removeItem = useCallback(
    (productSlug: string, weight: Weight) => {
      setItems((prev) => prev.filter((i) => !sameLine(i, productSlug, weight)));
    },
    [],
  );

  const updateQty = useCallback(
    (productSlug: string, weight: Weight, qty: number) => {
      setItems((prev) => {
        if (qty <= 0) return prev.filter((i) => !sameLine(i, productSlug, weight));
        return prev.map((i) =>
          sameLine(i, productSlug, weight) ? { ...i, qty } : i,
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
