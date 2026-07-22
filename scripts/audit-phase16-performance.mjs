import { readFile, writeFile } from "node:fs/promises";

const files = {
  package: await readFile("package.json", "utf8"),
  vite: await readFile("vite.config.ts", "utf8"),
  root: await readFile("src/routes/__root.tsx", "utf8"),
  animation: await readFile("src/lib/animations.ts", "utf8"),
  performance: await readFile("src/lib/performance.ts", "utf8"),
  serviceWorker: await readFile("public/sw.js", "utf8"),
  serviceWorkerRegistration: await readFile(
    "src/components/ServiceWorkerRegistration.tsx",
    "utf8",
  ),
  manifest: await readFile("public/manifest.json", "utf8"),
  server: await readFile("src/server.ts", "utf8"),
  router: await readFile("src/router.tsx", "utf8"),
  networkStatus: await readFile("src/components/NetworkStatus.tsx", "utf8"),
  bundle: await readFile("scripts/check-bundle-size.mjs", "utf8"),
};

const gates = [];
function gate(name, condition, evidence) {
  gates.push({ name, passed: Boolean(condition), evidence });
}

const manifest = JSON.parse(files.manifest);
const packageJson = JSON.parse(files.package);

gate(
  "permanent_phase16_gate",
  packageJson.scripts?.["audit:phase16"] ===
    "node scripts/audit-phase16-performance.mjs" &&
    packageJson.scripts?.check?.includes("audit:phase16"),
  "audit:phase16 is part of the permanent frontend check chain",
);
gate(
  "adaptive_motion_loading",
  files.root.includes('import("../lib/animations")') &&
    files.root.includes("getBrowserPerformanceTier") &&
    files.root.includes("scheduleIdleTask") &&
    files.root.includes("cleanupAnimations?.()"),
  "motion is idle-loaded, capability-aware and cleaned on route changes",
);
gate(
  "safe_animation_dom",
  !files.animation.includes("innerHTML") &&
    files.animation.includes("textContent = word") &&
    files.animation.includes("lenis.destroy()") &&
    files.animation.includes("observer.disconnect()"),
  "split text avoids HTML injection and long-lived motion resources have cleanup",
);
gate(
  "web_vitals_without_runtime_dependency",
  files.performance.includes('"CLS" | "FCP" | "INP" | "LCP" | "TTFB"') &&
    files.performance.includes("PerformanceObserver") &&
    !files.package.includes('"web-vitals"'),
  "Core Web Vitals are observed without adding a customer bundle dependency",
);
gate(
  "private_cache_boundary",
  ["/api", "/admin", "/auth", "/cart", "/checkout", "/orders", "/panel", "/profile"].every(
    (path) => files.serviceWorker.includes(`"${path}"`) && files.server.includes(`"${path}"`),
  ) &&
    files.serviceWorker.includes('fetch(request, { cache: "no-store" })'),
  "private and transactional paths bypass service-worker storage and HTML uses network truth",
);
gate(
  "bounded_pwa_caches",
  files.serviceWorker.includes("MEDIA_LIMIT") &&
    files.serviceWorker.includes("trimCache") &&
    files.serviceWorker.includes("ROSTA_SKIP_WAITING") &&
    files.serviceWorkerRegistration.includes("UPDATE_INTERVAL_MS") &&
    files.serviceWorkerRegistration.includes("controllerchange"),
  "runtime media storage is bounded and updates are explicit",
);
gate(
  "installable_manifest",
  manifest.id === "/" &&
    manifest.scope === "/" &&
    manifest.display === "standalone" &&
    manifest.icons?.some((icon) => icon.purpose === "maskable") &&
    Array.isArray(manifest.shortcuts) &&
    manifest.shortcuts.length >= 2,
  "manifest has stable identity, maskable icon and useful shortcuts",
);
gate(
  "reconnect_resilience",
  files.router.includes("refetchOnReconnect: true") &&
    files.router.includes("retry: shouldRetryQuery") &&
    files.networkStatus.includes('window.addEventListener("offline"') &&
    files.networkStatus.includes('window.addEventListener("online"'),
  "queries recover on reconnect and the customer receives honest network state",
);
gate(
  "heavy_vendor_splitting",
  ["motion", "three", "charts", "tanstack", "react"].every((chunk) =>
    files.vite.includes(`"${chunk}"`),
  ),
  "known heavy libraries have explicit production chunks",
);
gate(
  "dual_bundle_budget",
  files.bundle.includes("MAX_CLIENT_CHUNK_BYTES") &&
    files.bundle.includes("MAX_CLIENT_CHUNK_GZIP_BYTES") &&
    files.bundle.includes("categoryTotals"),
  "bundle gate checks raw and gzip chunk sizes and reports feature categories",
);
gate(
  "whole_bean_boundary",
  ![files.performance, files.animation, files.networkStatus, files.serviceWorker].some(
    (content) => /grind[_-]?selector|grind[_-]?state/i.test(content),
  ),
  "performance work introduces no grind selector or grind state",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generatedAt: new Date().toISOString(),
  marker: "phase16_pwa_performance=ready",
  passed: failed.length === 0,
  gates,
};
await writeFile(
  "frontend-phase16-audit.json",
  `${JSON.stringify(report, null, 2)}\n`,
);

if (failed.length > 0) {
  console.error("Phase 16 performance audit failed:");
  failed.forEach((item) => console.error(`- ${item.name}: ${item.evidence}`));
  process.exit(1);
}

console.log(`Phase 16 performance audit passed (${gates.length} gates).`);
