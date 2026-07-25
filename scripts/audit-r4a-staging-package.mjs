import { readFile, writeFile } from "node:fs/promises";

const paths = {
  package: "package.json",
  deployWorkflow: ".github/workflows/staging-deploy.yml",
  rehearsalWorkflow: ".github/workflows/r4-staging-package-ci.yml",
  deploy: "deploy/staging/deploy.sh",
  compose: "deploy/staging/docker-compose.rehearsal.yml",
  rehearsal: "deploy/staging/rehearsal.sh",
  caddy: "deploy/staging/Caddyfile.rehearsal",
  frontendImage: "Dockerfile.staging",
  backendImage: "backend/Dockerfile.production",
  phase22Audit: "scripts/audit-phase22-staging.mjs",
};

const files = Object.fromEntries(
  await Promise.all(
    Object.entries(paths).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);
const packageJson = JSON.parse(files.package);
const gates = [];
const gate = (name, passed, evidence) => gates.push({ name, passed: Boolean(passed), evidence });
const hasAll = (source, fragments) => fragments.every((fragment) => source.includes(fragment));

const scripts = packageJson.scripts ?? {};
const permanentR4aGate =
  scripts["audit:r4a"] === "node scripts/audit-r4a-staging-package.mjs" &&
  scripts.check?.includes("audit:r4a") &&
  scripts["staging:rehearse"] === "ROSTA_ALLOW_REHEARSAL=true bash deploy/staging/rehearsal.sh";
gate(
  "permanent_r4a_gate",
  permanentR4aGate,
  "R4A audit and rehearsal commands must remain in the permanent package contract.",
);

gate(
  "live_deploy_uses_exact_accepted_sha",
  hasAll(files.deployWorkflow, [
    "release_sha:",
    "40-character commit SHA",
    "ref: ${{ inputs.release_sha }}",
    'test "$checked_out" = "${{ inputs.release_sha }}"',
    "git merge-base --is-ancestor",
    "origin/integration/rosta-r-program",
    "runs-on: [self-hosted, linux, x64, rosta-staging]",
  ]) &&
    !files.deployWorkflow.includes("agent/phase-22") &&
    !files.deployWorkflow.includes("release_ref:"),
  "Live staging deployment must accept only an immutable commit already present in the R-program branch.",
);

gate(
  "deploy_never_resolves_dependencies",
  hasAll(files.deploy, [
    "backend/composer.lock is required",
    "composer validate --strict",
    "composer audit --locked",
    "composer install",
    "composer check",
    "40-character commit SHA",
    "backup_database",
    "php artisan migrate --force",
    "acceptance.sh",
  ]) &&
    !files.deploy.includes("composer update") &&
    !files.deploy.includes("ensure_composer_lock"),
  "Deployment must consume the reviewed lock and must never generate or update dependencies on the server.",
);

gate(
  "immutable_images_use_frozen_dependencies",
  hasAll(files.frontendImage, [
    "oven/bun:1.2.22-alpine",
    "bun install --frozen-lockfile",
    "bun run check",
    "node:22-bookworm-slim",
    "USER node",
  ]) &&
    hasAll(files.backendImage, [
      "COPY composer.json composer.lock",
      "composer install",
      "--no-dev",
      "--no-scripts",
      "--classmap-authoritative",
      "php:8.3-fpm-bookworm",
      "USER www-data",
    ]),
  "Frontend and backend staging images must be reproducible and non-root.",
);

gate(
  "rehearsal_stack_is_pinned_and_private",
  hasAll(files.compose, [
    "mysql:8.4",
    "redis:7.4-alpine",
    "minio/minio:RELEASE.",
    "minio/mc:RELEASE.",
    "caddy:2.10.2-alpine",
    "internal: true",
    '"127.0.0.1:18080:8080"',
    '"127.0.0.1:18081:8081"',
    'ROSTA_PAYMENT_ENABLED: "false"',
    'ROSTA_REFUND_ENABLED: "false"',
    'ROSTA_SMS_ENABLED: "false"',
    'ROSTA_MEDIA_UPLOADS_ENABLED: "true"',
    'S3_USE_PATH_STYLE_ENDPOINT: "true"',
    "no-new-privileges:true",
  ]) &&
    !files.compose.match(/image:\s+[^\n]*:latest\b/) &&
    !files.compose.includes('"3306:3306"') &&
    !files.compose.includes('"6379:6379"'),
  "Hosted rehearsal must use pinned infrastructure, private data networks and loopback-only edge ports.",
);

gate(
  "rehearsal_exercises_complete_package",
  hasAll(files.rehearsal, [
    "ROSTA_ALLOW_REHEARSAL",
    'git -C "$ROOT_DIR" rev-parse HEAD',
    "compose config --no-interpolate",
    "compose build --pull api api-web frontend",
    "compose run --rm api php artisan migrate --force",
    "rosta:readiness --strict --json",
    "rosta:staging-acceptance --json",
    "r2_private_round_trip",
    "r2_public_delivery",
    "r2_cors",
    "r2_cleanup",
    "mysqldump",
    "rosta_rehearsal_restore",
    "--no-build --wait",
    "record_release_tag",
    "dependencies-before.sha256",
    "dependencies-after.sha256",
    "git-status-final.txt",
    "ROSTA_R4A_STAGING_PACKAGE_COMPLETE",
  ]) &&
    !files.rehearsal.includes("migrate:rollback") &&
    !files.rehearsal.includes("composer update"),
  "R4A must build, migrate, accept, back up, restore, roll back images and prove a clean immutable workspace.",
);

gate(
  "rehearsal_evidence_is_private",
  hasAll(files.rehearsal, [
    "Ephemeral rehearsal secret entered evidence",
    "Secret-shaped material entered rehearsal evidence",
    "Private, visual or database payload entered rehearsal evidence",
    'rm -f "$BACKUP_FILE"',
    "compose config --no-interpolate",
  ]) &&
    hasAll(files.rehearsalWorkflow, [
      'ROSTA_ALLOW_REHEARSAL: "true"',
      "ROSTA_R4A_STAGING_PACKAGE_COMPLETE",
      "Upload R4A evidence",
      "retention-days: 21",
    ]),
  "No secret, environment, visual trace or database payload may enter uploaded R4A evidence.",
);

gate(
  "edge_is_noindex_and_hardened",
  hasAll(files.caddy, [
    "Strict-Transport-Security",
    "Content-Security-Policy",
    'X-Robots-Tag "noindex, nofollow, noarchive"',
    "X-Content-Type-Options",
    "X-Frame-Options",
    "reverse_proxy api-web:8080",
    "reverse_proxy frontend:3000",
    "reverse_proxy minio:9000",
    'Access-Control-Allow-Origin "http://127.0.0.1:18080"',
  ]),
  "The rehearsal edge must preserve noindex, security headers, API routing and bounded media CORS.",
);

gate(
  "phase22_contract_was_reconciled",
  hasAll(files.phase22Audit, [
    "require_committed_composer_lock",
    "composer audit --locked",
    "release_sha:",
    "origin/integration/rosta-r-program",
    '!files.deploy.includes("composer update")',
    '!files.deploy.includes("ensure_composer_lock")',
  ]),
  "The historical Phase 22 audit must enforce the current immutable R4 deployment contract.",
);

const executableContracts = [
  files.deployWorkflow,
  files.deploy,
  files.compose,
  files.caddy,
  files.frontendImage,
  files.backendImage,
].join("\n");

gate(
  "production_boundaries_remain_disabled",
  !executableContracts.includes('ROSTA_PAYMENT_ENABLED: "true"') &&
    !executableContracts.includes('ROSTA_REFUND_ENABLED: "true"') &&
    !executableContracts.includes('ROSTA_SMS_ENABLED: "true"') &&
    !executableContracts.includes('VITE_ALLOW_INDEXING: "true"') &&
    !executableContracts.match(/^\s*PAYMENT_MERCHANT_ID\s*:/m) &&
    !executableContracts.match(/^\s*KAVENEGAR_API_KEY\s*:/m),
  "R4A cannot activate production money movement, SMS, credentials or indexing.",
);

gate(
  "whole_bean_boundary_preserved",
  !/grind[_-]?(selector|state)|grind_option|grind_preference/i.test(executableContracts),
  "Staging packaging must not introduce any grind selector, state, option or preference.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generated_at: new Date().toISOString(),
  passed: failed.length === 0,
  checked_files: Object.values(paths),
  gates,
  failures: failed.map((item) => item.name),
  marker: failed.length === 0 ? "ROSTA_R4A_STAGING_PACKAGE_CONTRACT_AUDITED" : null,
};

await writeFile("r4a-staging-package-audit.json", `${JSON.stringify(report, null, 2)}\n`);

if (failed.length > 0) {
  console.error("R4A staging package audit failed:");
  for (const item of failed) console.error(`- ${item.name}: ${item.evidence}`);
  process.exit(1);
}

console.log(`R4A staging package audit passed (${gates.length} gates).`);
