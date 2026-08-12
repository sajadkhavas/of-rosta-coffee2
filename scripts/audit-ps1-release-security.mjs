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
  dependencyPolicy: "security/dependency-audit-exceptions.json",
  dependencyAudit: "scripts/audit-dependencies.mjs",
  ci: ".github/workflows/ci.yml",
  server: "src/server.ts",
  sw: "public/sw.js",
  robots: "src/routes/robots[.]txt.ts",
  hub: "src/routes/hub.operations.tsx",
  browserCache: "tests/browser/ps1-private-cache.spec.ts",
  sellerBootstrap: "backend/routes/seller-bootstrap.php",
  appProvider: "backend/app/Providers/AppServiceProvider.php",
  apiRoutes: "backend/routes/api.php",
  routeTest: "backend/tests/Feature/RouteUniquenessContractTest.php",
  frontendStaging: ".env.staging.example",
  backendStaging: "backend/.env.staging.example",
  stagingLib: "deploy/staging/lib.sh",
  stagingPreflight: "deploy/staging/preflight.sh",
  stagingDeploy: "deploy/staging/deploy.sh",
  stagingContract: "deploy/staging/contract-test.sh",
  caddy: "deploy/staging/Caddyfile",
};

const files = {};
for (const [key, path] of Object.entries(paths)) {
  if (await exists(path)) files[key] = await readFile(path, "utf8");
}

const packageJson = JSON.parse(files.package);
const policy = JSON.parse(files.dependencyPolicy);
const gates = [];
const gate = (name, passed, evidence) => gates.push({ name, passed: Boolean(passed), evidence });
const hasAll = (source, tokens) => typeof source === "string" && tokens.every((token) => source.includes(token));

gate(
  "dependency_audit_is_permanent",
  packageJson.scripts?.["audit:dependencies"] === "node scripts/audit-dependencies.mjs" &&
    packageJson.scripts?.check?.includes("audit:dependencies") &&
    files.ci?.includes("Audit High/Critical dependencies") &&
    files.dependencyAudit?.includes('"audit", "--audit-level=high"'),
  "High/Critical dependency audit must stay in package check and CI.",
);

gate(
  "dependency_exceptions_are_explicit",
  policy.schemaVersion === 1 && Array.isArray(policy.exceptions) &&
    files.dependencyAudit?.includes("advisoryId") &&
    files.dependencyAudit?.includes("expiresAt") &&
    files.dependencyAudit?.includes("MAX_EXCEPTION_DAYS = 30"),
  "Exceptions require advisory ID, owner/reason and bounded expiry.",
);

gate(
  "unused_react_router_dom_removed",
  !Object.hasOwn(packageJson.dependencies ?? {}, "react-router-dom") &&
    !Object.hasOwn(packageJson.devDependencies ?? {}, "react-router-dom"),
  "react-router-dom must remain absent unless a real import is introduced.",
);

gate(
  "hub_is_private_everywhere",
  hasAll(files.server, ['"/hub"', '"private, no-store, max-age=0"']) &&
    hasAll(files.sw, ['"/hub"', "isPrivateRequest(url)"]) &&
    files.robots?.includes('"Disallow: /hub/"') &&
    hasAll(files.hub, ['name: "robots"', "noindex,nofollow"]) &&
    hasAll(files.browserCache, ["/hub/operations", "leakedHubEntries", "toEqual([])"]) &&
    hasAll(files.caddy, ["@private_hub", 'Cache-Control "private, no-store, max-age=0"']),
  "Hub HTML must be private/no-store, noindex, SW-bypassed and browser-audited.",
);

gate(
  "seller_route_is_single_source",
  hasAll(files.sellerBootstrap, [
    "Route::get('/seller/roasteries'",
    "api.v1.seller.roasteries.bootstrap",
    "'auth:sanctum', 'rosta.session'",
  ]) &&
    files.appProvider?.includes("seller-bootstrap.php") &&
    !files.apiRoutes?.includes("Route::get('/seller/roasteries'") &&
    hasAll(files.routeTest, ["Duplicate route method/URI", "api/v1/seller/roasteries", "api.v1.seller.roasteries.bootstrap"]),
  "The onboarding bootstrap route must be the single GET /seller/roasteries contract with its existing auth/session behavior.",
);

gate(
  "staging_cookie_namespace_isolated",
  hasAll(files.frontendStaging, [
    "STAGING_SITE_DOMAIN=staging.rosta.shop",
    "STAGING_API_DOMAIN=api.staging.rosta.shop",
    "STAGING_MEDIA_DOMAIN=media.staging.rosta.shop",
  ]) &&
    hasAll(files.backendStaging, [
      "SESSION_COOKIE=rosta_staging_session",
      "SESSION_DOMAIN=.staging.rosta.shop",
      "ROSTA_CONTRACT_VERSION=2026-07-26-r5c",
    ]) &&
    !files.backendStaging.includes("SESSION_DOMAIN=.rosta.shop\n") &&
    hasAll(files.stagingLib, ["rosta_staging_session", "SESSION_DOMAIN must be scoped to the staging-only site domain"]),
  "Staging cookies must be scoped under .staging.rosta.shop and use a distinct session name.",
);

gate(
  "staging_lock_contract_is_fail_closed",
  hasAll(files.stagingPreflight, ["backend/composer.lock is required", "deploy.sh never generates dependencies"]) &&
    hasAll(files.stagingDeploy, ["backend/composer.lock is required", "deployment never generates dependencies"]) &&
    !/deploy\.sh will generate|composer update|ensure_composer_lock/.test(`${files.stagingPreflight}\n${files.stagingDeploy}`) &&
    hasAll(files.stagingContract, ["bash -n", "PS1 staging shell contract passed"]),
  "Preflight and deploy must agree that the committed Composer lock is mandatory.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generatedAt: new Date().toISOString(),
  passed: failed.length === 0,
  gates,
  marker: failed.length === 0 ? "ROSTA_PS1_RELEASE_SECURITY_CONTRACT_AUDITED" : null,
};
await writeFile("ps1-release-security-audit.json", `${JSON.stringify(report, null, 2)}\n`);

if (failed.length > 0) {
  console.error("PS1 release/security audit failed:");
  for (const item of failed) console.error(`- ${item.name}: ${item.evidence}`);
  process.exit(1);
}

console.log(`PS1 release/security audit passed (${gates.length} gates).`);
