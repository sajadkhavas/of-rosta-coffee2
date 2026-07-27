import { describe, expect, test } from "bun:test";
import { readFileSync } from "node:fs";

describe("R5I delivery and settlement boundary", () => {
  test("keeps customer delivery final-leg-only and server authoritative", () => {
    const ordersApi = readFileSync("src/lib/api/orders.ts", "utf8");
    const customer = readFileSync("src/routes/orders.$id.tsx", "utf8");
    expect(ordersApi).toContain("/delivery-confirmations");
    expect(ordersApi).toContain('proof_type: "customer_acknowledgement"');
    expect(customer).toContain("تأیید دریافت سفارش");
    expect(customer).toContain("leg.isFinal");
    expect(customer).not.toContain("eligible_at");
    expect(customer).not.toContain("owner_type");
  });

  test("separates roastery payout from Rosta-owned allocations", () => {
    const seller = readFileSync("src/components/seller/SellerOperationsDashboard.tsx", "utf8");
    const admin = readFileSync("src/routes/admin.operations.tsx", "utf8");
    expect(seller).toContain("صورت‌حساب‌ها و پرداخت‌های روستری");
    expect(seller).toContain("در برنامه پرداخت");
    expect(admin).toContain("Batchهای تسویه روستری");
    expect(admin).toContain("هزینه‌های هاب و خدمات متعلق به رستا جدا می‌مانند");
    expect(admin).toContain("ثبت پرداخت موفق");
    expect(admin).toContain("ثبت پرداخت ناموفق");
  });
});
