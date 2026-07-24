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
  siteConfig: "src/config/site.ts",
  root: "src/routes/__root.tsx",
  robots: "src/routes/robots[.]txt.ts",
  sitemap: "src/routes/sitemap[.]xml.ts",
  routeTree: "src/routeTree.gen.ts",
  server: "src/server.ts",
  staging: ".env.staging.example",
  home: "src/routes/index.tsx",
  products: "src/routes/products.index.tsx",
  product: "src/routes/products.$slug.tsx",
  roasteries: "src/routes/roasteries.index.tsx",
  roastery: "src/routes/roasteries.$slug.tsx",
  blog: "src/routes/blog.index.tsx",
  article: "src/routes/blog.$slug.tsx",
};

const missing = [];
const files = {};
for (const [name, path] of Object.entries(paths)) {
  if (!(await exists(path))) {
    missing.push(path);
    continue;
  }
  files[name] = await readFile(path, "utf8");
}

const packageJson = files.package ? JSON.parse(files.package) : {};
const gates = [];
const gate = (name, passed, evidence) => gates.push({ name, passed: Boolean(passed), evidence });
const hasAll = (source, tokens) =>
  typeof source === "string" && tokens.every((token) => source.includes(token));

const publicMetadata = [
  files.home,
  files.products,
  files.product,
  files.roasteries,
  files.roastery,
  files.blog,
  files.article,
].filter(Boolean);

const seoSurface = Object.values(files).join("\n");

gate(
  "seo_audit_files_present",
  missing.length === 0,
  missing.length ? `Missing SEO inputs: ${missing.join(", ")}` : "All SEO audit inputs exist.",
);

gate(
  "permanent_seo_gate",
  packageJson.scripts?.["audit:seo"] === "node scripts/audit-seo-content.mjs" &&
    packageJson.scripts?.check?.includes("audit:seo"),
  "The SEO audit must remain in the default frontend quality chain.",
);

gate(
  "central_url_and_indexing_contract",
  hasAll(files.siteConfig, [
    "VITE_SITE_URL",
    "VITE_ALLOW_INDEXING",
    "allowIndexing",
    "absoluteUrl",
    'direction: "rtl"',
  ]) && !files.siteConfig?.includes("rosta.coffee"),
  "Canonical URLs and indexing state must come from the central RTL site configuration.",
);

gate(
  "private_routes_are_noindex",
  hasAll(files.root, [
    "routeShouldNoIndex",
    "!siteConfig.allowIndexing",
    "NOINDEX_PATHS",
    "NOINDEX_PREFIXES",
    'name: "robots"',
    'content: "noindex,follow"',
    '"/admin"',
    '"/auth"',
    '"/orders"',
    '"/panel"',
  ]),
  "Global indexing policy must keep private customer, seller and administrator routes out of search.",
);

gate(
  "environment_aware_robots",
  hasAll(files.robots, [
    'createFileRoute("/robots.txt")',
    "siteConfig.allowIndexing",
    '"Disallow: /admin/"',
    '"Disallow: /panel/"',
    '"Disallow: /auth/"',
    '"Disallow: /cart"',
    '"Disallow: /checkout"',
    '"Disallow: /orders"',
    'absoluteUrl("/sitemap.xml")',
    'Content-Type": "text/plain; charset=utf-8"',
  ]),
  "robots.txt must be dynamic, environment-aware and disallow every private surface.",
);

gate(
  "live_authoritative_sitemap",
  hasAll(files.sitemap, [
    'createFileRoute("/sitemap.xml")',
    "listProducts",
    "listRoasteries",
    "listIndexableContent",
    'product.status === "published"',
    "absoluteUrl(entry.path)",
    'Content-Type": "application/xml; charset=utf-8"',
  ]) &&
    !files.sitemap?.includes("@/data/seed") &&
    !files.sitemap?.includes("@/data/blog-posts"),
  "The sitemap must use live published Laravel catalog/CMS data and never static production fixtures.",
);

gate(
  "robots_and_sitemap_registered",
  hasAll(files.routeTree, [
    "RobotsDottxtRouteImport",
    "SitemapDotxmlRouteImport",
    "/robots.txt",
    "/sitemap.xml",
  ]),
  "The generated TanStack route tree must register both crawler endpoints.",
);

gate(
  "public_metadata_is_canonical_and_structured",
  publicMetadata.every((source) => source.includes("absoluteUrl")) &&
    [files.home, files.products, files.product, files.roastery, files.article]
      .filter(Boolean)
      .every((source) => source.includes("application/ld+json") || source.includes("seoHead")) &&
    publicMetadata.every((source) => !source.includes("rosta.coffee")),
  "Major public SSR routes must derive canonical URLs centrally and retain structured metadata.",
);

gate(
  "private_responses_are_not_cacheable",
  hasAll(files.server, [
    "PRIVATE_PREFIXES",
    '"/admin"',
    '"/cart"',
    '"/checkout"',
    '"/orders"',
    '"/panel"',
    '"/profile"',
    'Cache-Control", "private, no-store, max-age=0"',
  ]),
  "SSR responses for private routes must remain non-cacheable.",
);

gate(
  "staging_indexing_disabled",
  files.staging?.includes("VITE_ALLOW_INDEXING=false"),
  "Staging must stay non-indexable until release acceptance.",
);

gate(
  "whole_bean_search_surface",
  seoSurface.includes("دانه کامل") &&
    !/grind[_-]?(selector|state)|grind_option|grind_preference/i.test(seoSurface),
  "SEO surfaces must describe whole beans without introducing grind selection or preference state.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generatedAt: new Date().toISOString(),
  marker: "seo_content_contract=ready",
  passed: failed.length === 0,
  gates,
};
await writeFile("frontend-seo-audit.json", `${JSON.stringify(report, null, 2)}\n`);

if (failed.length) {
  console.error("SEO/content audit failed:");
  failed.forEach((item) => console.error(`- ${item.name}: ${item.evidence}`));
  process.exit(1);
}

console.log(`SEO/content audit passed (${gates.length} gates).`);
