import { readFile, writeFile } from "node:fs/promises";

const files = {
  workflow: ".github/workflows/fullstack-integration-ci.yml",
  runtime: "scripts/accept-r3b-runtime.mjs",
  package: "package.json",
};

const sources = Object.fromEntries(
  await Promise.all(
    Object.entries(files).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);

const failures = [];
const required = [
  ["workflow", "mysql:8.4", "Integrated CI must use MySQL 8.4."],
  ["workflow", "redis:7.4-alpine", "Integrated CI must use Redis 7.4."],
  ["workflow", 'node-version: "22"', "Integrated CI must pin Node 22."],
  ["workflow", "bun-version: 1.2.22", "Integrated CI must pin Bun 1.2.22."],
  ["workflow", 'php-version: "8.3"', "Integrated CI must pin PHP 8.3."],
  ["workflow", "rosta:acceptance-fixtures --json", "Integrated CI must seed the R3A fixture contract."],
  ["workflow", "php artisan serve --host=127.0.0.1 --port=8000", "Integrated CI must boot Laravel."],
  ["workflow", "node .output/server/index.mjs", "Integrated CI must boot the production SSR output."],
  ["workflow", "ROSTA_INTERNAL_API_URL", "SSR must use the explicit internal Laravel URL."],
  ["workflow", 'ROSTA_PAYMENT_ENABLED: "false"', "Production payment activation must remain disabled."],
  ["workflow", 'ROSTA_REFUND_ENABLED: "false"', "Refund execution must remain disabled."],
  ["workflow", 'ROSTA_SMS_ENABLED: "false"', "SMS activation must remain disabled."],
  ["workflow", 'VITE_ALLOW_INDEXING: "false"', "Indexing must remain disabled."],
  ["workflow", "route-tree.diff", "Generated route tree drift must be preserved as evidence."],
  ["workflow", "unexpected_status", "Integrated CI must reject every unexpected workspace change."],
  ["workflow", "git checkout -- src/routeTree.gen.ts", "Only the generated route tree may be restored after evidence capture."],
  ["runtime", "/sanctum/csrf-cookie", "Runtime acceptance must verify the CSRF cookie boundary."],
  ["runtime", 'method: "OPTIONS"', "Runtime acceptance must verify CORS preflight."],
  ["runtime", "unauthenticated_session_boundary", "Runtime acceptance must reject unauthenticated protected access."],
  ["runtime", "ssr_product_detail", "Runtime acceptance must prove an API-backed SSR detail route."],
  ["runtime", "ROSTA_R3B_INTEGRATED_RUNTIME_COMPLETE", "Runtime acceptance must emit the R3B marker."],
  ["package", '"audit:r3b"', "Package scripts must expose the permanent R3B audit."],
];

for (const [file, fragment, message] of required) {
  if (!sources[file].includes(fragment)) failures.push(message);
}

const forbidden = [
  "staging:deploy",
  "ROSTA_PAYMENT_ENABLED: \"true\"",
  "ROSTA_REFUND_ENABLED: \"true\"",
  "ROSTA_SMS_ENABLED: \"true\"",
  "VITE_ALLOW_INDEXING: \"true\"",
  "fixed_otp",
  "otp_code",
];
for (const fragment of forbidden) {
  if (sources.workflow.includes(fragment) || sources.runtime.includes(fragment)) {
    failures.push(`R3B runtime contract contains forbidden fragment: ${fragment}`);
  }
}

const report = {
  passed: failures.length === 0,
  checked_files: Object.values(files),
  failures,
  marker: failures.length === 0 ? "ROSTA_R3B_RUNTIME_CONTRACT_AUDITED" : null,
};

await writeFile(
  "r3b-integration-audit.json",
  `${JSON.stringify(report, null, 2)}\n`,
  "utf8",
);

if (failures.length > 0) {
  console.error("R3B integrated runtime contract audit failed:");
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log("R3B integrated runtime contract audit passed.");
