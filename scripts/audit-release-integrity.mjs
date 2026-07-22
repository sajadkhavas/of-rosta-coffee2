import { access, readFile, writeFile } from "node:fs/promises";

const paths = {
  package: "package.json",
  manifest: "scripts/create-release-manifest.mjs",
  frontendDeploy: "scripts/deploy-staging-frontend.sh",
  backendDeploy: "backend/scripts/deploy-staging.sh",
  readiness: "backend/app/Console/Commands/BackendReadiness.php",
  backendComposer: "backend/composer.json",
  openApi: "docs/openapi/rosta-v1-commerce-additions.yaml",
  backup: "docs/BACKUP_RESTORE_RUNBOOK.md",
  adversarial: "docs/ADVERSARIAL_RELEASE_ACCEPTANCE.md",
};
const files = {};
for (const [name, path] of Object.entries(paths)) {
  await access(path);
  files[name] = await readFile(path, "utf8");
}

const packageJson = JSON.parse(files.package);
const backendComposer = JSON.parse(files.backendComposer);
const gates = [];
function gate(name, passed, evidence) {
  gates.push({ name, passed: Boolean(passed), evidence });
}

gate(
  "release_manifest_is_permanent",
  packageJson.scripts?.["release:manifest"]?.includes(
    "create-release-manifest.mjs",
  ) && packageJson.scripts?.["release:verify"]?.includes("release:manifest"),
  "Frontend release verification must create a hashed manifest.",
);
gate(
  "artifact_secret_scan",
  files.manifest.includes("PRIVATE KEY") &&
    files.manifest.includes("CLOUDFLARE_API_TOKEN") &&
    files.manifest.includes("sha256") &&
    files.manifest.includes("forbidden release filename"),
  "The release artifact must be hash-listed and scanned for forbidden files and secrets.",
);
gate(
  "deploy_uses_verified_artifact",
  files.frontendDeploy.includes("bun run release:verify") &&
    files.frontendDeploy.includes("release-manifest.json") &&
    files.frontendDeploy.indexOf("bun run release:verify") <
      files.frontendDeploy.indexOf("wrangler deploy"),
  "Staging deploy must verify the artifact before publishing it.",
);
gate(
  "backend_readiness_is_deploy_gate",
  files.backendDeploy.includes("php artisan rosta:readiness --json") &&
    files.readiness.includes("composer_lock") &&
    files.readiness.includes("schema_current") &&
    files.readiness.includes("payment_activation") &&
    files.readiness.includes("media_activation"),
  "Backend deployment must pass machine-readable infrastructure and provider readiness.",
);
gate(
  "commerce_openapi_gate",
  backendComposer.scripts?.["audit:openapi"]?.includes(
    "audit-commerce-openapi-drift.php",
  ) &&
    backendComposer.scripts?.check?.includes("@audit:openapi") &&
    files.openApi.includes("/payments/request:") &&
    files.openApi.includes("/inquiries:") &&
    files.openApi.includes("/media/uploads"),
  "Commerce routes must have a permanent OpenAPI drift gate.",
);
gate(
  "restore_drill_required",
  files.backup.includes("Restore Drill") &&
    files.backup.includes("rosta:readiness") &&
    files.backup.includes("RPO") &&
    files.backup.includes("RTO"),
  "Backup policy must include an isolated restore drill and measurable evidence.",
);
gate(
  "adversarial_acceptance_required",
  [
    "Payment",
    "Inventory",
    "Notification Outbox",
    "Reviews",
    "Media Upload",
    "SSR",
    "Release",
  ].every((section) => files.adversarial.includes(section)),
  "Release acceptance must cover adversarial commerce, privacy, SSR and operations cases.",
);
gate(
  "whole_bean_boundary",
  !Object.values(files).some((content) =>
    /grind[_-]?(selector|state)|grind_option/i.test(content),
  ),
  "Release integrity work must not introduce grind selection or grind state.",
);

const failed = gates.filter((item) => !item.passed);
await writeFile(
  "release-integrity-audit.json",
  `${JSON.stringify(
    {
      generatedAt: new Date().toISOString(),
      marker: "release_integrity=ready",
      passed: failed.length === 0,
      gates,
    },
    null,
    2,
  )}\n`,
);

if (failed.length > 0) {
  console.error("Release integrity audit failed:");
  for (const item of failed) console.error(`- ${item.name}: ${item.evidence}`);
  process.exit(1);
}

console.log(`Release integrity audit passed (${gates.length} gates).`);
