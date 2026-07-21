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

  test("expires stale intents instead of reusing them", () => {
    const storage = new MemoryStorage();
    const fingerprint = buildPaymentFingerprint("order-1");
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

  test("binds the callback payment ID to the expected order", () => {
    const storage = new MemoryStorage();
    savePaymentExpectation("payment-1", "order-1", { storage, now: 10_000 });

    expect(readPaymentExpectation("payment-1", { storage, now: 11_000 })?.orderId).toBe("order-1");
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
      "rosta_payment_expectation_v1",
      JSON.stringify({
        version: 1,
        paymentId: "payment-1",
        orderId: "javascript:alert(1)",
        createdAt: new Date(10_000).toISOString(),
      }),
    );
    expect(readPaymentExpectation("payment-1", { storage, now: 11_000 })).toBeNull();
  });
});
