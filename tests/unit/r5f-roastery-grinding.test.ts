import { describe, expect, test } from "bun:test";
import { buildCartItemsPayload } from "@/lib/api/checkout";

describe("R5F roastery grinding selection", () => {
  test("serializes the grinding service while preserving whole-bean identity", () => {
    const payload = buildCartItemsPayload([
      {
        variantId: "variant-250",
        quantity: 2,
        grindingProfileId: "profile-v60-v2",
      },
    ]);

    expect(payload).toEqual([
      {
        variant_id: "variant-250",
        quantity: 2,
        grinding_profile_id: "profile-v60-v2",
      },
    ]);
    expect(payload[0]).not.toHaveProperty("sku");
    expect(payload[0]).not.toHaveProperty("weight_grams");
    expect(payload[0]).not.toHaveProperty("stock");
  });

  test("keeps whole beans as the default when no grinding profile is selected", () => {
    const payload = buildCartItemsPayload([
      {
        variantId: "variant-500",
        quantity: 1,
        grindingProfileId: null,
      },
    ]);

    expect(payload[0]?.grinding_profile_id).toBeNull();
  });

  test("rejects an unsafe grinding profile identifier", () => {
    expect(() =>
      buildCartItemsPayload([
        {
          variantId: "variant-250",
          quantity: 1,
          grindingProfileId: "x".repeat(201),
        },
      ]),
    ).toThrow("شناسه پروفایل آسیاب معتبر نیست");
  });
});
