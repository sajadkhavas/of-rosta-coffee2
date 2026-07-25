import { readFile, writeFile } from "node:fs/promises";

const files = {
  package: "package.json",
  finalWorkflow: ".github/workflows/r3-final-gate.yml",
  browserWorkflow: ".github/workflows/browser-acceptance-ci.yml",
  runtimeWorkflow: ".github/workflows/fullstack-integration-ci.yml",
  backendComposer: "backend/composer.json",
  browserTests: "tests/browser/r3c2-commerce-roles.spec.ts",
};

const sources = Object.fromEntries(
  await Promise.all(
    Object.entries(files).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);
const packageJson = JSON.parse(sources.package);
const failures = [];

const required = [
  ["finalWorkflow", "mysql:8.4", "R3D final acceptance must use MySQL 8.4."],
  ["finalWorkflow", "redis:7.4-alpine", "R3D final acceptance must use Redis 7.4."],
  ["finalWorkflow", "First clean frontend install", "R3D must perform the first clean frontend installation."],
  ["finalWorkflow", "Second clean frontend install", "R3D must prove frontend dependency reproducibility twice."],
  ["finalWorkflow", "First clean backend install", "R3D must perform the first clean backend installation."],
  ["finalWorkflow", "Second clean backend install", "R3D must prove backend dependency reproducibility twice."],
  ["finalWorkflow", "bun run check", "R3D must run the complete frontend permanent gate."],
  ["finalWorkflow", "composer check", "R3D must run the complete backend permanent gate."],
  ["finalWorkflow", "rosta:acceptance-fixtures", "R3D must seed deterministic acceptance fixtures."],
  ["finalWorkflow", "bun run test:browser", "R3D must run the real browser journeys."],
  ["finalWorkflow", "all_surfaces_integrated=ready", "R3D must emit the authoritative final marker."],
  ["finalWorkflow", "APP_ENV: testing", "R3D must remain in the testing application environment."],
  ["finalWorkflow", "PAYMENT_DRIVER: testing", "R3D may use only the testing payment provider."],
  ["finalWorkflow", 'ROSTA_REFUND_ENABLED: "false"', "Refund execution must remain disabled in R3D."],
  ["finalWorkflow", 'ROSTA_SMS_ENABLED: "false"', "Production SMS must remain disabled in R3D."],
  ["finalWorkflow", 'VITE_ALLOW_INDEXING: "false"', "Indexing must remain disabled in R3D."],
  ["browserWorkflow", "ROSTA_R3C2_COMMERCE_ROLES_COMPLETE", "R3D requires the R3C2 browser marker."],
  ["runtimeWorkflow", "ROSTA_R3B_INTEGRATED_RUNTIME_COMPLETE", "R3D requires the R3B runtime marker."],
  ["backendComposer", '"audit:r3c2"', "R3D requires the backend R3C2 permanent audit."],
  ["browserTests", "foreign-acceptance-roastery", "R3D requires adversarial seller scope acceptance."],
];

for (const [file, fragment, message] of required) {
  if (!sources[file].includes(fragment)) failures.push(message);
}

if (packageJson.scripts?.["audit:r3d"] !== "node scripts/audit-r3d-final-integration.mjs") {
  failures.push("Package scripts must expose the permanent R3D audit.");
}
if (!packageJson.scripts?.check?.includes("audit:r3d")) {
  failures.push("The R3D audit must remain in the default frontend check chain.");
}

const forbidden = [
  ["finalWorkflow", "APP_ENV: production"],
  ["finalWorkflow", "PAYMENT_DRIVER: zarinpal"],
  ["finalWorkflow", "PAYMENT_MERCHANT_ID:"],
  ["finalWorkflow", "KAVENEGAR_API_KEY:"],
  ["finalWorkflow", 'ROSTA_REFUND_ENABLED: "true"'],
  ["finalWorkflow", 'ROSTA_SMS_ENABLED: "true"'],
  ["finalWorkflow", 'VITE_ALLOW_INDEXING: "true"'],
  ["finalWorkflow", "deploy/staging"],
  ["finalWorkflow", "deploy/production"],
];
for (const [file, fragment] of forbidden) {
  if (sources[file].toLowerCase().includes(fragment.toLowerCase())) {
    failures.push(`R3D contract contains forbidden fragment in ${files[file]}: ${fragment}`);
  }
}

const report = {
  passed: failures.length === 0,
  checked_files: Object.values(files),
  failures,
  marker: failures.length === 0 ? "ROSTA_R3D_FINAL_CONTRACT_AUDITED" : null,
};
await writeFile("r3d-final-integration-audit.json", `${JSON.stringify(report, null, 2)}\n`);

if (failures.length > 0) {
  console.error("R3D final integration contract audit failed:");
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log("R3D final integration contract audit passed.");
