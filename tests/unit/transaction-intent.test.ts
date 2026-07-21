import { describe, expect, test } from "bun:test";
import {
  buildOrderFingerprint,
  buildPaymentFingerprint,
  getOrCreateTransactionIntent,
  readPaymentExpectation,
  savePaymentExpectation,
  TRANSACTION_INTENT_TTL_MS,
} from "../../src/lib/transaction-intent";

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

const orderInput = {
  quoteId: "quote-1",
  addressId: "address-1",
  couponCode: "WELCOME",
  notes: "تحویل عصر",
  items: [
    { variantId: "variant-b", quantity: 1 },
    { variantId: "variant-a", quantity: 2 },
  ],
};

const paymentInput = { orderId: "order-1", amount: 1_030_000, currency: "IRR" as const };

describe("transaction intents", () => {
  test("keeps one Idempotency key for the same normalized payload", () => {
    const storage = new MemoryStorage();
    const fingerprintA = buildOrderFingerprint(orderInput);
    const fingerprintB = buildOrderFingerprint({
      ...orderInput,
      items: [...orderInput.items].reverse(),
    });
    expect(fingerprintA).toBe(fingerprintB);

    const first = getOrCreateTransactionIntent("order", fingerprintA, {
      storage,
      now: 1_000,
      keyFactory: () => "ORD-abcdefghijklmnopqrstuvwx",
    });
    const second = getOrCreateTransactionIntent("order", fingerprintB, {
      storage,
      now: 2_000,
      keyFactory: () => "ORD-should-not-be-generated-1234",
    });
    expect(second).toBe(first);
  });

  test("rotates the key when the checkout payload changes", () => {
    const storage = new MemoryStorage();
    const first = getOrCreateTransactionIntent("order", buildOrderFingerprint(orderInput), {
      storage,
      now: 1_000,
      keyFactory: () => "ORD-aaaaaaaaaaaaaaaaaaaaaaaa",
    });
    const second = getOrCreateTransactionIntent(
      "order",
      buildOrderFingerprint({ ...orderInput, addressId: "address-2" }),
      {
        storage,
        now: 2_000,
        keyFactory: () => "ORD-bbbbbbbbbbbbbbbbbbbbbbbb",
      },
    );
    expect(second).not.toBe(first);
  });

  test("binds payment Idempotency to order amount and currency", () => {
    expect(buildPaymentFingerprint(paymentInput)).not.toBe(
      buildPaymentFingerprint({ ...paymentInput, amount: paymentInput.amount + 1 }),
    );
    expect(buildPaymentFingerprint(paymentInput)).not.toBe(
      buildPaymentFingerprint({ ...paymentInput, orderId: "order-2" }),
    );
  });

  test("expires stale intents instead of reusing them", () => {
    const storage = new MemoryStorage();
    const fingerprint = buildPaymentFingerprint(paymentInput);
    const first = getOrCreateTransactionIntent("payment", fingerprint, {
      storage,
      now: 1_000,
      keyFactory: () => "PAY-aaaaaaaaaaaaaaaaaaaaaaaa",
    });
    const second = getOrCreateTransactionIntent("payment", fingerprint, {
      storage,
      now: 1_000 + TRANSACTION_INTENT_TTL_MS + 1,
      keyFactory: () => "PAY-bbbbbbbbbbbbbbbbbbbbbbbb",
    });
    expect(second).not.toBe(first);
  });

  test("binds the callback payment ID to exact order amount and currency", () => {
    const storage = new MemoryStorage();
    savePaymentExpectation("payment-1", "order-1", 1_030_000, "IRR", {
      storage,
      now: 10_000,
    });

    const expectation = readPaymentExpectation("payment-1", { storage, now: 11_000 });
    expect(expectation).toMatchObject({
      paymentId: "payment-1",
      orderId: "order-1",
      amount: 1_030_000,
      currency: "IRR",
    });
    expect(readPaymentExpectation("payment-2", { storage, now: 11_000 })).toBeNull();
    expect(
      readPaymentExpectation("payment-1", {
        storage,
        now: 10_000 + TRANSACTION_INTENT_TTL_MS + 1,
      }),
    ).toBeNull();
  });

  test("rejects malformed stored payment expectations", () => {
    const storage = new MemoryStorage();
    storage.setItem(
      "rosta_payment_expectation_v2",
      JSON.stringify({
        version: 2,
        paymentId: "payment-1",
        orderId: "javascript:alert(1)",
        amount: 1_030_000,
        currency: "IRR",
        createdAt: new Date(10_000).toISOString(),
      }),
    );
    expect(readPaymentExpectation("payment-1", { storage, now: 11_000 })).toBeNull();
  });

  test("rejects non-positive payment expectations", () => {
    const storage = new MemoryStorage();
    expect(() =>
      savePaymentExpectation("payment-1", "order-1", 0, "IRR", {
        storage,
        now: 10_000,
      }),
    ).toThrow();
  });
});
