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
  ci: ".github/workflows/ci.yml",
  manifest: "public/manifest.json",
  staticRobots: "public/robots.txt",
  routeRobots: "src/routes/robots[.]txt.ts",
  performance: "src/lib/performance.ts",
  home: "src/routes/index.tsx",
  blog: "src/routes/blog.$slug.tsx",
  contact: "src/routes/contact.tsx",
  inquiries: "src/lib/api/inquiries.ts",
  frontendStaging: ".env.staging.example",
  backendStaging: "backend/.env.staging.example",
  composerLock: "backend/composer.lock",
  generatedRouteTree: "src/routeTree.gen.ts",
  phase17RouteTree: "src/routeTree.phase17.ts",
  router: "src/router.tsx",
  icon: "public/icon.svg",
  maskableIcon: "public/icon-maskable.svg",
  productionDockerfile: "backend/Dockerfile.production",
  stagingCompose: "backend/docker-compose.staging.yml",
  backendDeploy: "backend/scripts/deploy-staging.sh",
  frontendDeploy: "scripts/deploy-staging-frontend.sh",
};

const files = {};
for (const [name, path] of Object.entries(paths)) {
  if (await exists(path)) files[name] = await readFile(path, "utf8");
}

const packageJson = JSON.parse(files.package);
const manifest = JSON.parse(files.manifest);
const gates = [];

function gate(name, condition, evidence) {
  gates.push({ name, passed: Boolean(condition), evidence });
}

gate(
  "permanent_phase17_gate",
  packageJson.scripts?.["audit:phase17"] ===
    "node scripts/audit-phase17-release-baseline.mjs" &&
    packageJson.scripts?.check?.includes("audit:phase17"),
  "audit:phase17 must remain in the permanent frontend check chain",
);

gate(
  "complete_frontend_ci",
  files.ci?.includes("bun run check") &&
    !files.ci?.includes("bun run audit:phase6 2>&1"),
  "GitHub CI must execute the complete frontend quality gate instead of a partial phase audit",
);

gate(
  "single_robots_source",
  !(await exists(paths.staticRobots)) &&
    files.routeRobots?.includes("siteConfig.allowIndexing"),
  "only the environment-aware /robots.txt route may control crawler access",
);

gate(
  "valid_pwa_icon_contract",
  manifest.icons?.some(
    (icon) =>
      icon.src === "/icon.svg" &&
      icon.type === "image/svg+xml" &&
      icon.sizes === "any",
  ) &&
    manifest.icons?.some(
      (icon) =>
        icon.src === "/icon-maskable.svg" &&
        icon.type === "image/svg+xml" &&
        icon.purpose === "maskable",
    ) &&
    files.icon?.startsWith("<svg") &&
    files.maskableIcon?.startsWith("<svg"),
  "manifest icon MIME types, file contents and maskable purpose must agree",
);

gate(
  "native_cursor_fallback",
  files.performance?.includes("rosta-native-cursor-fallback") &&
    files.performance?.includes("html:not(.cursor-enhanced)"),
  "fine-pointer users must retain a native cursor until the enhanced cursor is mounted",
);

const publicMetadata = [files.home, files.blog, files.contact].filter(Boolean);
gate(
  "canonical_domain_single_source",
  publicMetadata.every((content) => !content.includes("rosta.coffee")) &&
    publicMetadata.every((content) => content.includes("absoluteUrl")),
  "public canonical, Open Graph and structured-data URLs must come from the central site config",
);

gate(
  "honest_contact_state",
  !files.contact?.includes("setSent(true)") &&
    files.contact?.includes("createInquiry") &&
    files.contact?.includes("setReceipt(result)") &&
    files.contact?.includes("شناسه پیگیری") &&
    files.inquiries?.includes('apiFetch("/inquiries"') &&
    files.inquiries?.includes("reference_id"),
  "the contact page may claim success only after the inquiry API returns a persisted reference ID",
);

gate(
  "staging_is_non_indexable_and_secure",
  files.frontendStaging?.includes("VITE_ALLOW_INDEXING=false") &&
    files.backendStaging?.includes("APP_DEBUG=false") &&
    files.backendStaging?.includes("SESSION_SECURE_COOKIE=true") &&
    files.backendStaging?.includes("SESSION_DOMAIN=.rosta.shop"),
  "staging must stay non-indexable and use secure cross-subdomain session settings",
);

gate(
  "deterministic_backend_dependencies",
  await exists(paths.composerLock),
  "backend/composer.lock must be generated and committed before staging deployment",
);

const requiredRoutes = [
  "/admin/content",
  "/admin/content-links",
  "/admin/content-edit/$entryId",
  "/admin/finance",
  "/guides/$slug",
  "/origins/$slug",
  "/brew/$slug",
  "/tastes/$slug",
  "/collections/$slug",
  "/compare/$slug",
  "/robots.txt",
];
const activeRouteTree = files.phase17RouteTree ?? files.generatedRouteTree ?? "";
gate(
  "active_route_tree_current",
  requiredRoutes.every((route) => activeRouteTree.includes(route)) &&
    files.router?.includes('from "./routeTree.phase17"'),
  "the active TanStack route tree must register every current public and administrator route",
);

gate(
  "staging_deployment_is_guarded",
  files.productionDockerfile?.includes("FROM php:8.3-fpm") &&
    files.productionDockerfile?.includes("COPY composer.json composer.lock") &&
    files.stagingCompose?.includes("worker:") &&
    files.stagingCompose?.includes("scheduler:") &&
    files.stagingCompose?.includes("internal: true") &&
    files.backendDeploy?.includes("composer.lock is required") &&
    files.backendDeploy?.includes("php artisan migrate --force") &&
    files.frontendDeploy?.includes("bun run check") &&
    files.frontendDeploy?.includes("VITE_ALLOW_INDEXING=false"),
  "staging deployment must be deterministic, non-indexable and include API, queue and scheduler gates",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generatedAt: new Date().toISOString(),
  marker: "phase17_release_baseline=ready",
  passed: failed.length === 0,
  gates,
};

await writeFile(
  "frontend-phase17-audit.json",
  `${JSON.stringify(report, null, 2)}\n`,
);

if (failed.length > 0) {
  console.error("Phase 17 release baseline audit failed:");
  failed.forEach((item) => console.error(`- ${item.name}: ${item.evidence}`));
  process.exit(1);
}

console.log(`Phase 17 release baseline audit passed (${gates.length} gates).`);
