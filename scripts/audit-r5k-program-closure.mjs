import { access, readFile, writeFile } from "node:fs/promises";

const paths = {
  package: "package.json",
  phases: "docs/PHASES.md",
  closure: "docs/r5/R5K_PROGRAM_CLOSURE.md",
  workflow: ".github/workflows/staging-deploy.yml",
  sellerResource: "backend/app/Http/Resources/OrderResource.php",
  sellerController: "backend/app/Http/Controllers/Seller/SellerOrderController.php",
  sellerPage: "src/components/seller/SellerOperationsDashboard.tsx",
  frontendEnvironment: ".env.staging.example",
  backendEnvironment: "backend/.env.staging.example",
};

const canonicalDeployFiles = [
  "deploy/staging/docker-compose.yml",
  "deploy/staging/deploy.sh",
  "deploy/staging/acceptance.sh",
  "deploy/staging/backup.sh",
  "deploy/staging/rollback.sh",
  "deploy/staging/restore-backup.sh",
];

const retiredFiles = [
  "backend/docker-compose.staging.yml",
  "backend/scripts/deploy-staging.sh",
  "scripts/deploy-staging-frontend.sh",
  "scripts/audit-release-integrity.mjs",
  "scripts/audit-seo-foundation.mjs",
  "src/data/mock-orders.ts",
];

async function exists(path) {
  try {
    await access(path);
    return true;
  } catch {
    return false;
  }
}

const files = Object.fromEntries(
  await Promise.all(
    Object.entries(paths).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);
const packageJson = JSON.parse(files.package);
const gates = [];
const gate = (name, passed, evidence) => gates.push({ name, passed: Boolean(passed), evidence });
const hasAll = (source, fragments) => fragments.every((fragment) => source.includes(fragment));

const phaseRegister = files.phases.split("## Evidence matrix")[0];
const phaseRows = [...phaseRegister.matchAll(/^\| C([0-9]) \|/gm)].map((match) => match[1]);
gate(
  "exactly_ten_canonical_phases",
  phaseRows.join(",") === "0,1,2,3,4,5,6,7,8,9",
  "docs/PHASES.md must define exactly C0 through C9 in execution order.",
);

gate(
  "single_release_path",
  hasAll(files.phases, [
    "integration/rosta-r5-marketplace",
    "integration/rosta-release-candidate",
    "main` is not a staging source",
  ]) &&
    hasAll(files.closure, [
      "program/r5k-program-closure",
      "integration/rosta-r5-marketplace",
      "integration/rosta-release-candidate",
    ]),
  "The program and closure documents must define one integration-to-release path.",
);

gate(
  "permanent_r5k_gate",
  packageJson.scripts?.["audit:r5k"] === "node scripts/audit-r5k-program-closure.mjs" &&
    packageJson.scripts?.check?.includes("audit:r5k"),
  "audit:r5k must remain in the default frontend check chain.",
);

gate(
  "canonical_deploy_only",
  (await Promise.all(canonicalDeployFiles.map(exists))).every(Boolean) &&
    !(await Promise.all(retiredFiles.map(exists))).some(Boolean),
  "Only deploy/staging may contain executable staging lifecycle entrypoints.",
);

gate(
  "immutable_release_candidate_lineage",
  hasAll(files.workflow, [
    "release_sha:",
    "ref: ${{ inputs.release_sha }}",
    "origin/integration/rosta-release-candidate",
  ]) &&
    !files.workflow.includes("origin/integration/rosta-r-program") &&
    !files.workflow.includes("agent/phase-22"),
  "Staging must accept only an immutable SHA frozen on the release-candidate branch.",
);

gate(
  "seller_hub_least_privilege",
  hasAll(files.sellerResource, [
    "routeIs('api.v1.seller.*')",
    "'awaiting_receipt'",
    "route_type === 'rosta_hub_to_customer'",
  ]) &&
    hasAll(files.sellerController, ["hub.operation.", "hub.operation.receive"]) &&
    hasAll(files.sellerPage, [
      'data-testid="seller-hub-handoff-status"',
      "roastery_to_rosta_hub",
      "hubOperation.receivedAt",
    ]) &&
    !files.sellerPage.includes("hubOperation.readyAt") &&
    !files.sellerPage.includes("hubOperation.handedOffAt"),
  "Seller surfaces may expose only inbound handoff and Hub receipt.",
);

const environmentContract = `${files.frontendEnvironment}\n${files.backendEnvironment}`;
gate(
  "external_activation_remains_disabled",
  hasAll(environmentContract, [
    "VITE_ALLOW_INDEXING=false",
    "ROSTA_PAYMENT_ENABLED=false",
    "ROSTA_REFUND_ENABLED=false",
    "ROSTA_SMS_ENABLED=false",
  ]) &&
    !environmentContract.includes("ROSTA_PAYMENT_ENABLED=true") &&
    !environmentContract.includes("ROSTA_REFUND_ENABLED=true") &&
    !environmentContract.includes("ROSTA_SMS_ENABLED=true"),
  "R5K must not enable production providers, money movement or indexing.",
);

gate(
  "runtime_boundary_is_honest",
  hasAll(files.phases, [
    "Work that belongs on staging",
    '"accepted": true',
    "Real payment, refund execution, SMS, production money movement and Google indexing remain disabled",
  ]) &&
    hasAll(files.closure, [
      "Runtime staging completion is intentionally separate",
      '"accepted": true',
    ]),
  "Program closure must not claim server runtime acceptance.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generated_at: new Date().toISOString(),
  passed: failed.length === 0,
  gates,
  failures: failed.map((item) => item.name),
  marker: failed.length === 0 ? "ROSTA_R5K_PROGRAM_CLOSURE_COMPLETE" : null,
};

await writeFile("r5k-program-closure-audit.json", `${JSON.stringify(report, null, 2)}\n`);

if (failed.length) {
  console.error(
    `R5K program closure audit failed:\n- ${failed.map((item) => item.evidence).join("\n- ")}`,
  );
  process.exit(1);
}

console.log("ROSTA_R5K_PROGRAM_CLOSURE_COMPLETE");
