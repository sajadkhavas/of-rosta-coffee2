import { describe, expect, test } from "bun:test";
import { readFileSync } from "node:fs";

describe("R5H contractual fulfilment boundary", () => {
  test("keeps seller actions limited to preparation and handoff", () => {
    const sellerApi = readFileSync("src/lib/api/seller-operations.ts", "utf8");
    expect(sellerApi).toContain('status: "preparing" | "ready_to_ship" | "shipped"');
    expect(sellerApi).not.toContain('status: "accepted" | "rejected"');
    expect(sellerApi).toContain("reportSellerFulfillmentIncident");
  });

  test("exposes admin-only incident resolution and safe customer state", () => {
    const admin = readFileSync("src/lib/api/admin-operations.ts", "utf8");
    const customer = readFileSync("src/routes/orders.$id.tsx", "utf8");
    expect(admin).toContain("resolveAdminFulfillmentIncident");
    expect(admin).toContain('resolution: "resume_fulfillment" | "cancel_and_refund"');
    expect(customer).toContain("بررسی عملیاتی در جریان است");
    expect(customer).not.toContain("resolution_note");
    expect(customer).not.toContain("incident.description");
  });
});
