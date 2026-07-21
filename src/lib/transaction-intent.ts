import type { CurrencyCode } from "@/lib/api/contracts";

export type TransactionIntentKind = "order" | "payment";

interface StorageLike {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
  removeItem(key: string): void;
}

interface StoredTransactionIntent {
  version: 1;
  kind: TransactionIntentKind;
  fingerprint: string;
  idempotencyKey: string;
  createdAt: string;
}

export interface PaymentExpectation {
  version: 2;
  paymentId: string;
  orderId: string;
  amount: number;
  currency: CurrencyCode;
  createdAt: string;
}

const INTENT_TTL_MS = 30 * 60 * 1000;
const MAX_STORED_INTENT_LENGTH = 4_096;
const IDEMPOTENCY_KEY_PATTERN = /^[A-Za-z0-9:_-]{24,160}$/;
const SAFE_ID_PATTERN = /^[A-Za-z0-9._:-]{1,200}$/;

const intentStorageKey = (kind: TransactionIntentKind) => `rosta_${kind}_intent_v1`;
const PAYMENT_EXPECTATION_KEY = "rosta_payment_expectation_v2";
const LEGACY_PAYMENT_EXPECTATION_KEY = "rosta_payment_expectation_v1";

const getSessionStorage = (): StorageLike | null => {
  if (typeof window === "undefined") return null;
  try {
    return window.sessionStorage;
  } catch {
    return null;
  }
};

const normalizeText = (value: unknown, maximum = 500) =>
  typeof value === "string" ? value.trim().slice(0, maximum) : "";

const hashFingerprintSource = (source: string) => {
  let hash = 0x811c9dc5;
  for (let index = 0; index < source.length; index += 1) {
    hash ^= source.charCodeAt(index);
    hash = Math.imul(hash, 0x01000193);
  }
  return (hash >>> 0).toString(16).padStart(8, "0");
};

const randomHex = (length: number) => {
  const cryptoApi = globalThis.crypto;
  if (!cryptoApi?.getRandomValues) throw new Error("Secure random generator is unavailable.");
  const bytes = cryptoApi.getRandomValues(new Uint8Array(Math.ceil(length / 2)));
  return [...bytes]
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("")
    .slice(0, length);
};

export const createTransactionIdempotencyKey = (prefix: string) => {
  const safePrefix = prefix.toUpperCase().replace(/[^A-Z0-9_-]/g, "").slice(0, 12) || "TXN";
  return `${safePrefix}-${Date.now().toString(36)}-${randomHex(32)}`;
};

export const buildOrderFingerprint = ({
  quoteId,
  addressId,
  couponCode,
  notes,
  items,
}: {
  quoteId: string;
  addressId: string;
  couponCode?: string | null;
  notes?: string | null;
  items: Array<{ variantId: string; quantity: number }>;
}) => {
  const normalizedItems = items
    .map((item) => ({
      variantId: normalizeText(item.variantId, 200),
      quantity: Number.isFinite(item.quantity)
        ? Math.max(1, Math.min(20, Math.trunc(item.quantity)))
        : 1,
    }))
    .sort((left, right) => left.variantId.localeCompare(right.variantId, "en"));

  const source = JSON.stringify({
    quoteId: normalizeText(quoteId, 200),
    addressId: normalizeText(addressId, 200),
    couponCode: normalizeText(couponCode, 100),
    notes: normalizeText(notes, 1_000),
    items: normalizedItems,
  });

  return `order:${hashFingerprintSource(source)}`;
};

export const buildPaymentFingerprint = ({
  orderId,
  amount,
  currency,
}: {
  orderId: string;
  amount: number;
  currency: CurrencyCode;
}) =>
  `payment:${hashFingerprintSource(
    JSON.stringify({
      orderId: normalizeText(orderId, 200),
      amount: Number.isSafeInteger(amount) && amount > 0 ? amount : 0,
      currency,
    }),
  )}`;

const parseIntent = (
  raw: string | null,
  kind: TransactionIntentKind,
  now: number,
): StoredTransactionIntent | null => {
  if (!raw || raw.length > MAX_STORED_INTENT_LENGTH) return null;

  try {
    const value: unknown = JSON.parse(raw);
    if (!value || typeof value !== "object") return null;
    const candidate = value as Partial<StoredTransactionIntent>;
    const createdAt = Date.parse(candidate.createdAt || "");

    if (
      candidate.version !== 1 ||
      candidate.kind !== kind ||
      typeof candidate.fingerprint !== "string" ||
      candidate.fingerprint.length > 128 ||
      typeof candidate.idempotencyKey !== "string" ||
      !IDEMPOTENCY_KEY_PATTERN.test(candidate.idempotencyKey) ||
      !Number.isFinite(createdAt) ||
      createdAt > now + 60_000 ||
      now - createdAt > INTENT_TTL_MS
    ) {
      return null;
    }

    return candidate as StoredTransactionIntent;
  } catch {
    return null;
  }
};

export const getOrCreateTransactionIntent = (
  kind: TransactionIntentKind,
  fingerprint: string,
  options: {
    storage?: StorageLike | null;
    now?: number;
    keyFactory?: (prefix: string) => string;
  } = {},
) => {
  const storage = options.storage === undefined ? getSessionStorage() : options.storage;
  const now = options.now ?? Date.now();
  const existing = parseIntent(storage?.getItem(intentStorageKey(kind)) ?? null, kind, now);

  if (existing?.fingerprint === fingerprint) return existing.idempotencyKey;

  const idempotencyKey = (options.keyFactory ?? createTransactionIdempotencyKey)(
    kind === "order" ? "ORD" : "PAY",
  );
  if (!IDEMPOTENCY_KEY_PATTERN.test(idempotencyKey)) {
    throw new Error("Generated Idempotency key is invalid.");
  }

  const intent: StoredTransactionIntent = {
    version: 1,
    kind,
    fingerprint: fingerprint.slice(0, 128),
    idempotencyKey,
    createdAt: new Date(now).toISOString(),
  };

  try {
    storage?.setItem(intentStorageKey(kind), JSON.stringify(intent));
  } catch {
    // The in-memory key remains valid for this request when storage is restricted.
  }

  return idempotencyKey;
};

export const clearTransactionIntent = (
  kind: TransactionIntentKind,
  storage: StorageLike | null = getSessionStorage(),
) => {
  try {
    storage?.removeItem(intentStorageKey(kind));
  } catch {
    // Restricted storage must not block a completed transaction.
  }
};

export const savePaymentExpectation = (
  paymentId: string,
  orderId: string,
  amount: number,
  currency: CurrencyCode,
  options: { storage?: StorageLike | null; now?: number } = {},
): PaymentExpectation => {
  const normalizedPaymentId = normalizeText(paymentId, 200);
  const normalizedOrderId = normalizeText(orderId, 200);
  if (!SAFE_ID_PATTERN.test(normalizedPaymentId) || !SAFE_ID_PATTERN.test(normalizedOrderId)) {
    throw new Error("Payment expectation identifiers are invalid.");
  }
  if (!Number.isSafeInteger(amount) || amount <= 0 || currency !== "IRR") {
    throw new Error("Payment expectation amount or currency is invalid.");
  }

  const storage = options.storage === undefined ? getSessionStorage() : options.storage;
  const expectation: PaymentExpectation = {
    version: 2,
    paymentId: normalizedPaymentId,
    orderId: normalizedOrderId,
    amount,
    currency,
    createdAt: new Date(options.now ?? Date.now()).toISOString(),
  };

  try {
    storage?.removeItem(LEGACY_PAYMENT_EXPECTATION_KEY);
    storage?.setItem(PAYMENT_EXPECTATION_KEY, JSON.stringify(expectation));
  } catch {
    // The caller still has the returned expectation for the current navigation.
  }
  return expectation;
};

export const readPaymentExpectation = (
  paymentId: string,
  options: { storage?: StorageLike | null; now?: number } = {},
): PaymentExpectation | null => {
  const storage = options.storage === undefined ? getSessionStorage() : options.storage;
  const raw = storage?.getItem(PAYMENT_EXPECTATION_KEY) ?? null;
  if (!raw || raw.length > MAX_STORED_INTENT_LENGTH) return null;

  try {
    const candidate = JSON.parse(raw) as Partial<PaymentExpectation>;
    const createdAt = Date.parse(candidate.createdAt || "");
    const now = options.now ?? Date.now();
    if (
      candidate.version !== 2 ||
      candidate.paymentId !== paymentId ||
      typeof candidate.orderId !== "string" ||
      !SAFE_ID_PATTERN.test(candidate.paymentId || "") ||
      !SAFE_ID_PATTERN.test(candidate.orderId) ||
      !Number.isSafeInteger(candidate.amount) ||
      (candidate.amount ?? 0) <= 0 ||
      candidate.currency !== "IRR" ||
      !Number.isFinite(createdAt) ||
      createdAt > now + 60_000 ||
      now - createdAt > INTENT_TTL_MS
    ) {
      return null;
    }
    return candidate as PaymentExpectation;
  } catch {
    return null;
  }
};

export const clearPaymentExpectation = (storage: StorageLike | null = getSessionStorage()) => {
  const remove = () => {
    try {
      storage?.removeItem(PAYMENT_EXPECTATION_KEY);
      storage?.removeItem(LEGACY_PAYMENT_EXPECTATION_KEY);
    } catch {
      // Restricted storage must not block the verified result page.
    }
  };

  if (typeof window === "undefined") {
    remove();
    return;
  }

  // Keep the expectation available for the current paid-result render. Removing it
  // synchronously would change the verify query key and trigger a second verification.
  window.addEventListener("pagehide", remove, { once: true });
};

export const TRANSACTION_INTENT_TTL_MS = INTENT_TTL_MS;
