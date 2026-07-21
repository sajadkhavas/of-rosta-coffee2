import { describe, expect, test } from "bun:test";
import {
  CART_STORAGE_KEY,
  CART_STORAGE_VERSION,
  MAX_CART_STORAGE_BYTES,
  parseStoredCart,
  readCartStorage,
  serializeStoredCart,
  type CartItem,
} from "../../src/lib/cart-storage";

class MemoryStorage {
  private readonly values = new Map<string, string>();

  getItem(key: string) {
    return this.values.get(key) ?? null;
  }

  setItem(key: string, value: string) {
    this.values.set(key, value);
  }

  removeItem(key: string) {
    this.values.delete(key);
  }
}

const item: CartItem = {
  variantId: "variant-250",
  productId: "product-1",
  productSlug: "ethiopia-sample",
  productName: "قهوه اتیوپی",
  productImageUrl: "https://cdn.rosta.shop/coffee.webp",
  roasteryId: "roastery-1",
  roasteryName: "روستری نمونه",
  roasterySlug: "sample-roastery",
  weightGrams: 250,
  unitPriceSnapshot: 500_000,
  quantity: 2,
  addedAt: 1_750_000_000_000,
};

describe("versioned cart persistence", () => {
  test("round-trips a valid versioned single-roastery cart", () => {
    const serialized = serializeStoredCart([item], 1_750_000_000_100);
    const decoded = JSON.parse(serialized);
    expect(decoded.version).toBe(CART_STORAGE_VERSION);
    expect(parseStoredCart(serialized)).toEqual([item]);
  });

  test("rejects oversized, malformed and future-version payloads", () => {
    expect(parseStoredCart("{")).toEqual([]);
    expect(parseStoredCart("x".repeat(MAX_CART_STORAGE_BYTES + 1))).toEqual([]);
    expect(
      parseStoredCart(
        JSON.stringify({ version: 99, updatedAt: Date.now(), items: [item] }),
      ),
    ).toEqual([]);
  });

  test("rejects executable image URLs and invalid business values", () => {
    const unsafe = {
      version: CART_STORAGE_VERSION,
      updatedAt: Date.now(),
      items: [{ ...item, productImageUrl: "javascript:alert(1)" }],
    };
    expect(parseStoredCart(JSON.stringify(unsafe))).toEqual([]);

    const negativePrice = {
      version: CART_STORAGE_VERSION,
      updatedAt: Date.now(),
      items: [{ ...item, unitPriceSnapshot: -1 }],
    };
    expect(parseStoredCart(JSON.stringify(negativePrice))).toEqual([]);
  });

  test("rejects duplicate Variants and cross-roastery carts", () => {
    expect(() => serializeStoredCart([item, { ...item }])).toThrow();
    expect(() =>
      serializeStoredCart([
        item,
        {
          ...item,
          variantId: "variant-500",
          roasteryId: "roastery-2",
          roasterySlug: "other-roastery",
        },
      ]),
    ).toThrow();
  });

  test("migrates a bounded valid v2 array into v3", () => {
    const storage = new MemoryStorage();
    storage.setItem("rosta_cart_v2", JSON.stringify([item]));
    expect(readCartStorage(storage)).toEqual([item]);
    expect(JSON.parse(storage.getItem(CART_STORAGE_KEY) ?? "{}").version).toBe(
      CART_STORAGE_VERSION,
    );
  });

  test("drops invalid legacy entries instead of trusting them", () => {
    const storage = new MemoryStorage();
    storage.setItem(
      "rosta_cart_v2",
      JSON.stringify([
        item,
        { ...item, variantId: "variant-bad", weightGrams: 333 },
        { ...item, variantId: "variant-other", roasteryId: "roastery-2" },
      ]),
    );
    expect(readCartStorage(storage)).toEqual([item]);
  });
});
