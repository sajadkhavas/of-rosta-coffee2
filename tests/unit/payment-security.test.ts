import { describe, expect, test } from "bun:test";
import {
  isConsistentVerifiedPaid,
  type PaymentExpectationShape,
  type VerifiedPaymentResult,
} from "../../src/lib/payment-security";

const expectation: PaymentExpectationShape = {
  paymentId: "payment-1",
  orderId: "order-1",
  amount: 1_030_000,
  currency: "IRR",
};

const verifiedPaid: VerifiedPaymentResult = {
  paymentId: "payment-1",
  status: "paid",
  orderId: "order-1",
  orderStatus: "processing",
  amount: 1_030_000,
  currency: "IRR",
  verifiedAt: "2026-07-21T10:00:00Z",
};

describe("verified payment security", () => {
  test("accepts only exact paid order and payment truth", () => {
    expect(isConsistentVerifiedPaid(verifiedPaid, expectation)).toBe(true);
  });

  test.each([
    ["payment id", { paymentId: "payment-2" }],
    ["order id", { orderId: "order-2" }],
    ["amount", { amount: verifiedPaid.amount + 1 }],
    ["currency", { currency: "USD" as never }],
    ["payment status", { status: "pending" as const }],
    ["order status", { orderStatus: "awaiting_payment" as const }],
    ["verification time", { verifiedAt: null }],
  ])("rejects a %s mismatch", (_label, patch) => {
    expect(isConsistentVerifiedPaid({ ...verifiedPaid, ...patch }, expectation)).toBe(false);
  });

  test("never accepts a paid result without a local expectation", () => {
    expect(isConsistentVerifiedPaid(verifiedPaid, null)).toBe(false);
  });
});
