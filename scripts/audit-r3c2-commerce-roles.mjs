import { readFile, writeFile } from "node:fs/promises";

const files = {
  workflow: ".github/workflows/browser-acceptance-ci.yml",
  tests: "tests/browser/r3c2-commerce-roles.spec.ts",
  panelLayout: "src/routes/panel.tsx",
  panelIndex: "src/routes/panel.index.tsx",
  panelManage: "src/routes/panel.manage.tsx",
  otpProvider: "backend/app/Providers/AppServiceProvider.php",
  paymentManager: "backend/app/Services/Payments/PaymentProviderManager.php",
  fixture: "backend/database/seeders/RostaAcceptanceSeeder.php",
  package: "package.json",
};

const sources = Object.fromEntries(
  await Promise.all(
    Object.entries(files).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);

const failures = [];
const required = [
  ["workflow", 'APP_ENV: testing', "R3C2 must run only in the Laravel testing environment."],
  ["workflow", 'ROSTA_PAYMENT_ENABLED: "true"', "R3C2 must exercise the non-production payment boundary."],
  ["workflow", "PAYMENT_DRIVER: testing", "R3C2 must select only the testing payment provider."],
  ["workflow", "PAYMENT_CALLBACK_URL: http://127.0.0.1:3000/checkout", "R3C2 payment callback must return to the isolated SSR runtime."],
  ["workflow", "ROSTA_ALLOWED_PAYMENT_REDIRECT_HOSTS: 127.0.0.1", "R3C2 must explicitly allow only the local payment callback host."],
  ["workflow", 'ROSTA_REFUND_ENABLED: "false"', "Refund execution must remain disabled in R3C2."],
  ["workflow", 'ROSTA_SMS_ENABLED: "false"', "Production SMS must remain disabled in R3C2."],
  ["workflow", 'VITE_ALLOW_INDEXING: "false"', "Indexing must remain disabled in R3C2."],
  ["workflow", "ROSTA_R3C2_COMMERCE_ROLES_COMPLETE", "R3C2 CI must emit its completion marker."],
  ["tests", '"/checkout/quote"', "R3C2 must create an authoritative checkout quote."],
  ["tests", '"/orders"', "R3C2 must exercise real order creation."],
  ["tests", '"/payments/request"', "R3C2 must exercise the testing payment provider."],
  ["tests", "idempotencyConflict.status", "R3C2 must prove order idempotency conflicts."],
  ["tests", "consumedQuote.status", "R3C2 must prove stale or consumed quote rejection."],
  ["tests", "foreign-acceptance-roastery", "R3C2 must test a foreign seller scope."],
  ["tests", "invalidDelivered.status", "R3C2 must reject invalid fulfillment transitions."],
  ["tests", '"/reviews"', "R3C2 must submit a verified-purchase review."],
  ["tests", "duplicateReview.status", "R3C2 must reject duplicate reviews."],
  ["tests", '"/admin/reviews/', "R3C2 must moderate the review through an administrator session."],
  ["tests", "rosta:acceptance-otp", "All R3C2 roles must authenticate through the real one-time CLI bridge."],
  ["panelLayout", "Outlet", "The seller panel parent must render nested seller routes."],
  ["panelIndex", 'createFileRoute("/panel/")', "Daily seller operations must live on the panel index route."],
  ["panelManage", 'createFileRoute("/panel/manage")', "Catalog management must remain an independently rendered nested route."],
  ["otpProvider", "environment('testing')", "The expanded OTP rate must remain testing-only."],
  ["otpProvider", "otpRequestsPerMinute = $acceptanceOtp ? 12 : 3", "Production OTP throttling must remain unchanged."],
  ["paymentManager", "'testing' => ! app()->environment('production')", "The testing payment provider must remain forbidden in production."],
  ["fixture", "foreign-acceptance-roastery", "The unowned scope fixture must be deterministic."],
  ["package", '"audit:r3c2"', "Package scripts must expose the permanent R3C2 audit."],
];

for (const [file, fragment, message] of required) {
  if (!sources[file].includes(fragment)) failures.push(message);
}

const forbidden = [
  ["workflow", "PAYMENT_DRIVER: zarinpal"],
  ["workflow", "PAYMENT_MERCHANT_ID:"],
  ["workflow", "KAVENEGAR_API_KEY:"],
  ["workflow", 'ROSTA_REFUND_ENABLED: "true"'],
  ["workflow", 'ROSTA_SMS_ENABLED: "true"'],
  ["workflow", 'VITE_ALLOW_INDEXING: "true"'],
  ["tests", "page.route("],
  ["tests", "context.addCookies("],
  ["tests", "fixed_otp"],
  ["tests", "migrate:fresh"],
  ["fixture", "order_id"],
  ["fixture", "payment_id"],
  ["panelLayout", "SellerOperationsDashboard"],
];

for (const [file, fragment] of forbidden) {
  if (sources[file].includes(fragment)) {
    failures.push(`R3C2 contract contains forbidden fragment in ${files[file]}: ${fragment}`);
  }
}

const report = {
  passed: failures.length === 0,
  checked_files: Object.values(files),
  failures,
  marker: failures.length === 0 ? "ROSTA_R3C2_COMMERCE_ROLES_CONTRACT_AUDITED" : null,
};

await writeFile(
  "r3c2-commerce-roles-audit.json",
  `${JSON.stringify(report, null, 2)}\n`,
  "utf8",
);

if (failures.length > 0) {
  console.error("R3C2 commerce and roles acceptance audit failed:");
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log("R3C2 commerce and roles acceptance audit passed.");
