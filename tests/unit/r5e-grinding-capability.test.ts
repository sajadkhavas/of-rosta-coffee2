import { describe, expect, test } from "bun:test";
import { grindingCapabilityWireSchema, mapGrindingCapability } from "@/lib/api/grinding-capability";

const validCapability = {
  availability: "available" as const,
  is_available: true,
  is_active: true,
  fee_mode: "free" as const,
  fee_amount: 0,
  currency: "IRR" as const,
  is_free: true,
  label: "آسیاب روستری رایگان",
  preparation_minutes: 30,
  capacity_per_day: 80,
  supported_weights: [250, 500] as const,
  profiles: [
    {
      id: "profile-v60",
      code: "v60",
      version: 1,
      public_name: "V60",
      brew_method: "v60",
    },
  ],
};

describe("R5E grinding capability contract", () => {
  test("accepts an explicit free roastery capability", () => {
    const parsed = grindingCapabilityWireSchema.parse(validCapability);
    const capability = mapGrindingCapability(parsed);

    expect(capability.isAvailable).toBe(true);
    expect(capability.isFree).toBe(true);
    expect(capability.feeAmount).toBe(0);
    expect(capability.supportedWeights).toEqual([250, 500]);
    expect(capability.profiles[0]?.publicName).toBe("V60");
  });

  test("rejects inconsistent free money", () => {
    const result = grindingCapabilityWireSchema.safeParse({
      ...validCapability,
      fee_amount: 100_000,
    });

    expect(result.success).toBe(false);
  });

  test("rejects an available capability without profiles", () => {
    const result = grindingCapabilityWireSchema.safeParse({
      ...validCapability,
      profiles: [],
    });

    expect(result.success).toBe(false);
  });

  test("keeps grinding outside product and inventory identity", () => {
    const capability = mapGrindingCapability(grindingCapabilityWireSchema.parse(validCapability));

    expect(capability).not.toHaveProperty("variantId");
    expect(capability).not.toHaveProperty("sku");
    expect(capability).not.toHaveProperty("stockOnHand");
  });
});
