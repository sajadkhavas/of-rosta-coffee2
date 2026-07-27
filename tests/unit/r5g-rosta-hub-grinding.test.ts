import { describe, expect, test } from "bun:test";
import { buildCartItemsPayload } from "@/lib/api/checkout";

// Provider, fee, zone, capacity, route and settlement stay Laravel-authoritative.
describe("R5G Rosta Hub grinding request boundary", () => {
  test("sends only the selected profile on the existing whole-bean line", () => {
    const payload = buildCartItemsPayload([
      {
        variantId: "whole-bean-250",
        quantity: 2,
        grindingProfileId: "profile-v60-v2",
      },
    ]);

    expect(payload).toEqual([
      {
        variant_id: "whole-bean-250",
        quantity: 2,
        grinding_profile_id: "profile-v60-v2",
      },
    ]);
    expect(payload[0]).not.toHaveProperty("provider_type");
    expect(payload[0]).not.toHaveProperty("provider_hub_id");
    expect(payload[0]).not.toHaveProperty("service_fee");
    expect(payload[0]).not.toHaveProperty("zone");
    expect(payload[0]).not.toHaveProperty("settlement_owner");
  });
});
