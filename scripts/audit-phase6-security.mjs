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
  /readPaymentExpectation/,
  "payment verification must be bound to an expected order",
);
requirePattern(
  "src/routes/checkout.tsx",
  /status !== "paid" \|\| !verifyQuery\.data\.consistent/,
  "cart clearing must require consistent verified paid state",
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

const businessContractFiles = [
  "src/lib/api/contracts.ts",
  "src/lib/api/schemas.ts",
  "src/lib/api/checkout.ts",
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
