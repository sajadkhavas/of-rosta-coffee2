import { describe, expect, test } from "bun:test";
import { createCartSnapshot, normalizeCartItems } from "@/lib/cart-storage";
import type { ProductSummary, ProductVariant } from "@/lib/api/contracts";

const variant: ProductVariant = {
  id: "variant-a",
  sku: "PACK-250",
  weightGrams: 250,
  price: 2_000_000,
  currency: "IRR",
  isAvailable: true,
};

function product(roasteryId: string, feeAmount: number): ProductSummary {
  return {
    id: `product-${roasteryId}`,
    name: "قهوه تست",
    slug: `coffee-${roasteryId}`,
    origin: { id: "origin", name: "اتیوپی" },
    processingMethod: "washed",
    roastLevel: "medium",
    arabicaPercentage: 100,
    tastingNotes: ["مرکبات"],
    packaging: {
      mode: feeAmount === 0 ? "free" : "fixed",
      feeAmount,
      currency: "IRR",
      isFree: feeAmount === 0,
      label: feeAmount === 0 ? "بسته‌بندی روستری رایگان" : "هزینه بسته‌بندی روستری",
    },
    roastery: {
      id: roasteryId,
      name: `روستری ${roasteryId}`,
      slug: `roastery-${roasteryId}`,
      isVerified: true,
    },
    variants: [variant],
    status: "published",
  };
}

describe("R5D packaging cart contract", () => {
  test("calculates paid packaging per package", () => {
    const item = createCartSnapshot(product("a", 125_000), variant, 3, 1);
    expect(item.packagingFeeAmount * item.quantity).toBe(375_000);
  });

  test("keeps free packaging explicit", () => {
    const item = createCartSnapshot(product("a", 0), variant, 1, 1);
    expect(item.packagingFeeMode).toBe("free");
    expect(item.packagingFeeAmount).toBe(0);
  });

  test("supports multiple roasteries", () => {
    const first = createCartSnapshot(product("a", 0), variant, 1, 1);
    const second = createCartSnapshot(
      product("b", 10_000),
      { ...variant, id: "variant-b", sku: "PACK-B" },
      1,
      2,
    );
    expect(normalizeCartItems([first, second])).toHaveLength(2);
  });
});
