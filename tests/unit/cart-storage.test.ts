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
  packagingFeeMode: "free",
  packagingFeeAmount: 0,
  quantity: 2,
  addedAt: 1_750_000_000_000,
};

const legacyItem = (() => {
  const { packagingFeeMode: _mode, packagingFeeAmount: _amount, ...legacy } = item;
  return legacy;
})();

describe("versioned cart persistence", () => {
  test("round-trips a valid versioned marketplace cart", () => {
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
        JSON.stringify({
          version: 99,
          updatedAt: Date.now(),
          items: [item],
        }),
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

    expect(() =>
      serializeStoredCart([{ ...item, packagingFeeMode: "fixed", packagingFeeAmount: 0 }]),
    ).toThrow();
    expect(() =>
      serializeStoredCart([{ ...item, packagingFeeMode: "free", packagingFeeAmount: 1 }]),
    ).toThrow();
  });

  test("rejects duplicate Variants and supports multiple roasteries", () => {
    expect(() => serializeStoredCart([item, { ...item }])).toThrow();

    const marketplaceItems = [
      item,
      {
        ...item,
        variantId: "variant-500",
        productId: "product-2",
        productSlug: "colombia-sample",
        productName: "قهوه کلمبیا",
        roasteryId: "roastery-2",
        roasteryName: "روستری دوم",
        roasterySlug: "other-roastery",
        packagingFeeMode: "fixed" as const,
        packagingFeeAmount: 75_000,
      },
    ];
    expect(parseStoredCart(serializeStoredCart(marketplaceItems))).toEqual(marketplaceItems);
  });

  test("migrates a bounded legacy v2 array into v4 with explicit free packaging", () => {
    const storage = new MemoryStorage();
    storage.setItem("rosta_cart_v2", JSON.stringify([legacyItem]));
    expect(readCartStorage(storage)).toEqual([item]);
    expect(JSON.parse(storage.getItem(CART_STORAGE_KEY) ?? "{}").version).toBe(
      CART_STORAGE_VERSION,
    );
  });

  test("migrates the previous v3 envelope without losing the cart", () => {
    const storage = new MemoryStorage();
    storage.setItem(
      "rosta_cart_v3",
      JSON.stringify({
        version: 3,
        updatedAt: 1_750_000_000_100,
        items: [legacyItem],
      }),
    );
    expect(readCartStorage(storage)).toEqual([item]);
    expect(JSON.parse(storage.getItem(CART_STORAGE_KEY) ?? "{}").version).toBe(4);
  });

  test("drops invalid and duplicate legacy entries instead of trusting them", () => {
    const storage = new MemoryStorage();
    storage.setItem(
      "rosta_cart_v3",
      JSON.stringify({
        version: 3,
        updatedAt: 1_750_000_000_100,
        items: [
          legacyItem,
          { ...legacyItem, variantId: "variant-bad", weightGrams: 333 },
          { ...legacyItem },
          {
            ...legacyItem,
            variantId: "variant-invalid-package",
            packagingFeeMode: "fixed",
            packagingFeeAmount: 0,
          },
        ],
      }),
    );
    expect(readCartStorage(storage)).toEqual([item]);
  });
});
