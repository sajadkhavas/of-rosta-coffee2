import { access, readFile, writeFile } from "node:fs/promises";

async function exists(path) {
  try {
    await access(path);
    return true;
  } catch {
    return false;
  }
}

const paths = {
  package: "package.json",
  dockerfile: "Dockerfile.staging",
  dockerignore: ".dockerignore",
  compose: "deploy/staging/docker-compose.yml",
  caddy: "deploy/staging/Caddyfile",
  lib: "deploy/staging/lib.sh",
  deploy: "deploy/staging/deploy.sh",
  deployWorkflow: ".github/workflows/staging-deploy.yml",
  acceptance: "deploy/staging/acceptance.sh",
  backup: "deploy/staging/backup.sh",
  rollback: "deploy/staging/rollback.sh",
  restore: "deploy/staging/restore-backup.sh",
  backendEnvironment: "backend/.env.staging.example",
  frontendEnvironment: ".env.staging.example",
  stagingCommand: "backend/app/Console/Commands/StagingAcceptance.php",
  siteConfig: "src/config/site.ts",
};

const missing = [];
const files = {};
for (const [key, path] of Object.entries(paths)) {
  if (!(await exists(path))) {
    missing.push(path);
    continue;
  }
  files[key] = await readFile(path, "utf8");
}

const packageJson = files.package ? JSON.parse(files.package) : {};
const gates = [];
const gate = (name, passed, evidence) => gates.push({ name, passed: Boolean(passed), evidence });
const hasAll = (source, tokens) =>
  typeof source === "string" && tokens.every((token) => source.includes(token));

const composePorts = files.compose?.match(/ports:\s*[\s\S]*?(?=\n\s{2}\w|\nnetworks:)/g) ?? [];

gate(
  "phase22_files_present",
  missing.length === 0,
  missing.length === 0
    ? "All staging deployment, acceptance, backup and rollback files exist."
    : `Missing files: ${missing.join(", ")}`,
);

gate(
  "permanent_phase22_gate",
  packageJson.scripts?.["audit:phase22"] === "node scripts/audit-phase22-staging.mjs" &&
    packageJson.scripts?.check?.includes("audit:phase22"),
  "Phase 22 audit must stay in the default frontend check chain.",
);

gate(
  "frontend_image_is_deterministic",
  hasAll(files.dockerfile, [
    "oven/bun:",
    "bun install --frozen-lockfile",
    "bun run typecheck",
    "bun run audit:phase22",
    "bun run build",
    "USER node",
    "HEALTHCHECK",
  ]),
  "Staging frontend image must use a frozen Bun lock, validate before build and run as non-root.",
);

gate(
  "docker_context_excludes_secrets",
  hasAll(files.dockerignore, [".env", ".env.*", "backend/.env", ".staging-state", ".git"]),
  "Docker build context must exclude host secrets, state, VCS metadata and generated dependencies.",
);

gate(
  "compose_has_complete_private_stack",
  hasAll(files.compose, [
    "mysql:8.4",
    "redis:7.4-alpine",
    "api:",
    "worker:",
    "scheduler:",
    "api-web:",
    "frontend:",
    "edge:",
    "internal: true",
    "no-new-privileges:true",
    "ROSTA_INTERNAL_API_URL",
  ]) &&
    !files.compose?.includes('"3306:3306"') &&
    !files.compose?.includes('"6379:6379"') &&
    composePorts.some((block) => block.includes('"80:80"') && block.includes('"443:443"')),
  "Only the Caddy edge may publish host ports; MySQL, Redis and PHP remain private.",
);

gate(
  "tls_noindex_and_security_headers",
  hasAll(files.caddy, [
    "Strict-Transport-Security",
    "Content-Security-Policy",
    'X-Robots-Tag "noindex, nofollow, noarchive"',
    "STAGING_MEDIA_DOMAIN",
    "reverse_proxy frontend:3000",
    "reverse_proxy api-web:8080",
  ]),
  "Staging edge must provide TLS, noindex and explicit security headers for frontend and API.",
);

gate(
  "environment_is_fail_closed",
  hasAll(files.frontendEnvironment, [
    "VITE_ALLOW_INDEXING=false",
    "STAGING_SITE_DOMAIN=",
    "STAGING_API_DOMAIN=",
    "STAGING_MEDIA_DOMAIN=",
  ]) &&
    hasAll(files.backendEnvironment, [
      "APP_ENV=staging",
      "APP_DEBUG=false",
      "ROSTA_PAYMENT_ENABLED=false",
      "ROSTA_REFUND_ENABLED=false",
      "ROSTA_SMS_ENABLED=false",
      "ROSTA_MEDIA_UPLOADS_ENABLED=true",
      "ROSTA_MEDIA_UPLOAD_DISK=s3",
      "SESSION_SECURE_COOKIE=true",
      "SESSION_ENCRYPT=true",
    ]),
  "Staging keeps money and SMS disabled while requiring secure cookies and real R2 acceptance.",
);

gate(
  "environment_paths_are_resolved_before_source",
  hasAll(files.lib, [
    "ROSTA_FRONTEND_ENV_PATH",
    "ROSTA_BACKEND_ENV_PATH",
    'export ROSTA_FRONTEND_ENV_FILE="$ROSTA_FRONTEND_ENV_PATH"',
    'export ROSTA_BACKEND_ENV_FILE="$ROSTA_BACKEND_ENV_PATH"',
  ]),
  "Host environment file paths must remain absolute and cannot be overridden by sourced values.",
);

gate(
  "deploy_is_guarded_and_evidenced",
  hasAll(files.deploy, [
    "flock -n",
    "require_committed_composer_lock",
    "composer validate --strict",
    "composer audit --locked",
    "composer install",
    "composer check",
    "backend/composer.lock is required",
    "backup_database",
    "php artisan migrate --force",
    "acceptance.sh",
    "record_release_tag",
  ]) &&
    !files.deploy.includes("composer update") &&
    !files.deploy.includes("ensure_composer_lock"),
  "Deployment must serialize execution, require the reviewed lock, back up, migrate, accept and record the release.",
);

gate(
  "deployment_uses_accepted_immutable_sha",
  hasAll(files.deployWorkflow, [
    "release_sha:",
    "40-character commit SHA",
    "ref: ${{ inputs.release_sha }}",
    "git merge-base --is-ancestor",
    "origin/integration/rosta-release-candidate",
  ]) &&
    !files.deployWorkflow.includes("release_ref:") &&
    !files.deployWorkflow.includes("agent/phase-22"),
  "Staging deployment must check out an exact SHA already frozen on the release-candidate branch.",
);

gate(
  "real_infrastructure_acceptance",
  hasAll(files.stagingCommand, [
    "select version() as version",
    "getMigrationFiles",
    "Redis::setex",
    "Queue::connection('redis')->size",
    "Storage::disk",
    "->put($objectKey, $payload)",
    "->get($objectKey)",
    "->delete($objectKey)",
    "Access-Control-Allow-Origin",
  ]),
  "Backend acceptance must perform real MySQL, migration, Redis, queue, R2 and CORS round trips.",
);

gate(
  "external_acceptance_is_complete",
  hasAll(files.acceptance, [
    "rosta:readiness --json",
    "rosta:staging-acceptance --json",
    "containers_running",
    "ssr_home",
    "robots_noindex",
    "security_headers",
    "cors_credentials",
    "secure_csrf_cookie",
    "acceptance.json.sha256",
  ]),
  "Host acceptance must validate containers, Laravel, SSR, noindex, headers, CORS, cookies and signed evidence.",
);

gate(
  "rollback_and_restore_are_safe",
  hasAll(files.rollback, [
    "backup_database",
    "--no-build",
    "acceptance.sh",
    "Rollback candidate failed acceptance",
  ]) &&
    !files.rollback?.includes("migrate:rollback") &&
    hasAll(files.restore, [
      "ROSTA_CONFIRM_RESTORE",
      "sha256sum --check",
      "pre-destructive-restore",
      "acceptance.sh",
    ]),
  "Image rollback stays forward-only for schema; destructive restore requires checksum and explicit confirmation.",
);

gate(
  "ssr_uses_private_api_without_leaking_it",
  hasAll(files.siteConfig, [
    "ROSTA_INTERNAL_API_URL",
    "import.meta.env.SSR",
    "normalizeInternalApiUrl",
    "return siteConfig.apiUrl",
  ]),
  "Server-side loaders should use the private Docker API while browser requests retain the public HTTPS API.",
);

gate(
  "whole_bean_boundary_preserved",
  !/grind[_-]?(selector|state)|grind_option/i.test(Object.values(files).join("\n")),
  "Staging infrastructure must not introduce a grind selector, option or state.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generatedAt: new Date().toISOString(),
  marker: "phase22_staging=ready",
  passed: failed.length === 0,
  gates,
};
await writeFile("frontend-phase22-audit.json", `${JSON.stringify(report, null, 2)}\n`);

if (failed.length > 0) {
  console.error("Phase 22 staging audit failed:");
  failed.forEach((item) => console.error(`- ${item.name}: ${item.evidence}`));
  process.exit(1);
}

console.log(`Phase 22 staging audit passed (${gates.length} gates).`);
