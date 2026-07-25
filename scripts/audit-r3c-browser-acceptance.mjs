import { readFile, writeFile } from "node:fs/promises";

const files = {
  config: "playwright.config.ts",
  tests: "tests/browser/r3c-public-customer.spec.ts",
  workflow: ".github/workflows/browser-acceptance-ci.yml",
  package: "package.json",
};

const sources = Object.fromEntries(
  await Promise.all(
    Object.entries(files).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);

const failures = [];
const required = [
  ["config", "workers: 1", "Browser acceptance must serialize journeys against the shared fixture database."],
  ["config", 'trace: "off"', "Browser traces must remain disabled to avoid OTP capture."],
  ["config", 'screenshot: "off"', "Screenshots must remain disabled to avoid OTP capture."],
  ["config", 'video: "off"', "Videos must remain disabled to avoid OTP capture."],
  ["tests", "rosta.pending-otp.v1", "Customer acceptance must obtain the real challenge identifier from browser session state."],
  ["tests", "rosta:acceptance-otp", "Customer acceptance must consume OTP only through the non-HTTP CLI bridge."],
  ["tests", "%2F%2Fevil.example", "Customer acceptance must test the encoded hostile redirect payload."],
  ["tests", "expectNoSeriousAccessibilityViolations", "Browser acceptance must include accessibility checks."],
  ["tests", "credentials: \"include\"", "Customer acceptance must prove the credentialed cookie session."],
  ["workflow", "mysql:8.4", "Browser CI must use MySQL 8.4."],
  ["workflow", "redis:7.4-alpine", "Browser CI must use Redis 7.4."],
  ["workflow", "SMS_DRIVER: acceptance", "Browser CI must select the dedicated acceptance OTP driver."],
  ["workflow", "queue:work redis --queue=notifications", "Browser CI must process the real OTP notification queue."],
  ["workflow", "playwright install --with-deps chromium", "Browser CI must install the pinned Chromium runtime."],
  ["workflow", "ROSTA_R3C_BROWSER_ACCEPTANCE_COMPLETE", "Browser CI must emit the R3C marker."],
  ["package", '"audit:r3c"', "Package scripts must expose the permanent R3C audit."],
];

for (const [file, fragment, message] of required) {
  if (!sources[file].includes(fragment)) failures.push(message);
}

const forbidden = [
  ["config", 'trace: "on"'],
  ["config", 'screenshot: "only-on-failure"'],
  ["config", 'video: "retain-on-failure"'],
  ["workflow", 'ROSTA_SMS_ENABLED: "true"'],
  ["workflow", 'VITE_ALLOW_INDEXING: "true"'],
  ["tests", "page.route("],
  ["tests", "context.addCookies("],
  ["tests", "fixed_otp"],
];
for (const [file, fragment] of forbidden) {
  if (sources[file].includes(fragment)) {
    failures.push(`R3C browser contract contains forbidden fragment in ${files[file]}: ${fragment}`);
  }
}

if (sources.workflow.includes('ROSTA_PAYMENT_ENABLED: "true"')) {
  if (!sources.workflow.includes("PAYMENT_DRIVER: testing")) {
    failures.push("Enabled R3C payment acceptance must use only the testing provider.");
  }
  if (!sources.workflow.includes("APP_ENV: testing")) {
    failures.push("Enabled R3C payment acceptance must remain in the testing environment.");
  }
  if (!sources.workflow.includes('ROSTA_REFUND_ENABLED: "false"')) {
    failures.push("Refund execution must remain disabled during browser acceptance.");
  }
}

const report = {
  passed: failures.length === 0,
  checked_files: Object.values(files),
  failures,
  marker: failures.length === 0 ? "ROSTA_R3C_BROWSER_CONTRACT_AUDITED" : null,
};

await writeFile(
  "r3c-browser-audit.json",
  `${JSON.stringify(report, null, 2)}\n`,
  "utf8",
);

if (failures.length > 0) {
  console.error("R3C browser acceptance contract audit failed:");
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log("R3C browser acceptance contract audit passed.");
