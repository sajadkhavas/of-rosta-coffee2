import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const root = process.cwd();
const read = (path) => readFileSync(resolve(root, path), "utf8");
const failures = [];

function requirePattern(path, pattern, message) {
  const content = read(path);
  if (!pattern.test(content)) failures.push(`${path}: ${message}`);
}

function forbidPattern(path, pattern, message) {
  const content = read(path);
  if (pattern.test(content)) failures.push(`${path}: ${message}`);
}

for (const path of [
  "src/lib/api/identity.ts",
  "src/lib/api/catalog.ts",
  "src/lib/api/orders.ts",
  "src/lib/api/checkout.ts",
]) {
  requirePattern(path, /parseContract\s*\(/, "production API payloads must be runtime validated");
  requirePattern(path, /resourceSchema|collectionSchema/, "API envelopes must be runtime validated");
}

requirePattern(
  "src/config/site.ts",
  /paymentRedirectHosts/,
  "payment redirect host allowlist is required",
);
requirePattern(
  "src/config/site.ts",
  /assertApprovedPaymentRedirect/,
  "approved payment redirect predicate is required",
);
forbidPattern(
  "src/lib/api/checkout.ts",
  /Math\.random\s*\(/,
  "transaction identifiers must not use Math.random",
);
requirePattern(
  "src/lib/transaction-intent.ts",
  /buildOrderFingerprint/,
  "order Idempotency must be bound to the checkout payload",
);
requirePattern(
  "src/lib/transaction-intent.ts",
  /amount:\s*number[\s\S]*currency:\s*CurrencyCode/,
  "payment expectation must be bound to amount and currency",
);
requirePattern(
  "src/lib/transaction-intent.ts",
  /buildPaymentFingerprint[\s\S]*amount[\s\S]*currency/,
  "payment Idempotency must be bound to order amount and currency",
);
requirePattern(
  "src/lib/api/payment-contract.ts",
  /payment_id[\s\S]*order_status[\s\S]*amount[\s\S]*currency[\s\S]*verified_at/,
  "payment verification must expose complete server truth",
);
requirePattern(
  "src/lib/payment-security.ts",
  /result\.paymentId === expectation\.paymentId[\s\S]*result\.orderId === expectation\.orderId[\s\S]*result\.amount === expectation\.amount[\s\S]*result\.currency === expectation\.currency/,
  "paid success must require exact payment, order, amount and currency consistency",
);
requirePattern(
  "src/routes/checkout.tsx",
  /status !== "paid" \|\| !verifyQuery\.data\?*\.consistent|status !== "paid" \|\| !verifyQuery\.data\.consistent/,
  "cart clearing must require consistent verified paid state",
);
requirePattern(
  "src/routes/checkout.tsx",
  /order\.grandTotal !== quote\.grandTotal \|\| order\.currency !== quote\.currency/,
  "created order totals must match the checkout quote",
);
requirePattern(
  "src/lib/cart-storage.ts",
  /CART_STORAGE_VERSION = 3/,
  "cart persistence must be explicitly versioned",
);
requirePattern(
  "src/lib/cart-storage.ts",
  /MAX_CART_STORAGE_BYTES = 64 \* 1024/,
  "cart persistence must have a byte limit",
);
requirePattern(
  "src/lib/cart-context.tsx",
  /addEventListener\("storage"/,
  "cart changes must synchronize across tabs",
);
forbidPattern(
  "src/lib/cart-context.tsx",
  /setItem\("rosta_cart(?:_v2)?"/,
  "legacy unversioned cart keys must never be written",
);

const serviceWorker = read("public/sw.js");
const installHandler = serviceWorker.match(
  /self\.addEventListener\("install",[\s\S]*?\n}\);/,
)?.[0];
if (!installHandler) failures.push("public/sw.js: install handler is missing");
if (installHandler?.includes("skipWaiting")) {
  failures.push("public/sw.js: install must not force an update during an active transaction");
}
if (!serviceWorker.includes('event.data?.type === "ROSTA_SKIP_WAITING"')) {
  failures.push("public/sw.js: explicit update activation message is required");
}
if (!serviceWorker.includes('request.destination === "script"')) {
  failures.push("public/sw.js: executable assets need an explicit network-first policy");
}
if (!serviceWorker.includes('"/api"')) {
  failures.push("public/sw.js: the complete same-origin API path must bypass caches");
}
requirePattern(
  "src/components/ServiceWorkerRegistration.tsx",
  /navigator\.serviceWorker\.controller/,
  "PWA update prompts must only appear for controlled clients",
);
requirePattern(
  "src/components/ServiceWorkerRegistration.tsx",
  /ROSTA_SKIP_WAITING/,
  "PWA updates must require explicit activation",
);

for (const header of [
  "Content-Security-Policy",
  "X-Content-Type-Options",
  "Referrer-Policy",
  "Permissions-Policy",
  "Strict-Transport-Security",
]) {
  requirePattern("src/server.ts", new RegExp(header), `${header} response header is required`);
}
requirePattern(
  "src/server.ts",
  /private, no-store/,
  "private customer routes must be marked no-store",
);
requirePattern(
  "src/routes/__root.tsx",
  /rosta:session-expired[\s\S]*removeQueries/,
  "expired protected sessions must clear private query caches",
);
requirePattern(
  "src/lib/api/identity.ts",
  /suppressSessionExpiryEvent:\s*true/,
  "authentication bootstrap must not create expiry refetch loops",
);

const businessContractFiles = [
  "src/lib/api/contracts.ts",
  "src/lib/api/schemas.ts",
  "src/lib/api/checkout.ts",
  "src/lib/api/payment-contract.ts",
  "src/lib/cart-storage.ts",
  "src/lib/transaction-intent.ts",
];
for (const path of businessContractFiles) {
  forbidPattern(
    path,
    /\bgrind(?:Type|_type|Setting|_setting)?\b/i,
    "grind state is forbidden because Rosta sells whole beans only",
  );
}

if (failures.length) {
  console.error("Phase 6 security audit failed:\n");
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log("Phase 6 security audit passed.");
