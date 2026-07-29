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
  sitemapIndex: "src/routes/sitemap[.]xml.ts",
  sitemapStatic: "src/routes/sitemaps.static[.]xml.ts",
  sitemapProducts: "src/routes/sitemaps.products[.]xml.ts",
  sitemapRoasteries: "src/routes/sitemaps.roasteries[.]xml.ts",
  sitemapContent: "src/routes/sitemaps.content[.]xml.ts",
  sitemapLib: "src/lib/sitemap.ts",
  routeTree: "src/routeTree.gen.ts",
  server: "src/server.ts",
  redirectLib: "src/lib/seo-redirect.ts",
  staging: ".env.staging.example",
  acceptance: "deploy/staging/acceptance.sh",
  home: "src/routes/index.tsx",
  products: "src/routes/products.index.tsx",
  product: "src/routes/products.$slug.tsx",
  roasteries: "src/routes/roasteries.index.tsx",
  roastery: "src/routes/roasteries.$slug.tsx",
  blog: "src/routes/blog.index.tsx",
  article: "src/routes/blog.$slug.tsx",
  about: "src/routes/about.tsx",
  privacy: "src/routes/privacy.tsx",
  terms: "src/routes/terms.tsx",
  quiz: "src/routes/quiz.tsx",
  ogSvg: "public/og-home.svg",
  ogPng: "public/og-home.png",
};

const missing = [];
const files = {};
for (const [name, path] of Object.entries(paths)) {
  if (!(await exists(path))) {
    missing.push(path);
    continue;
  }
  if (name !== "ogPng") files[name] = await readFile(path, "utf8");
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

const rootHasRouteCanonical =
  files.root?.includes('rel: "canonical"') || files.root?.includes('property: "og:url"');

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
  ]) &&
    files.siteConfig?.includes('socialImagePath: "/og-home.png"') &&
    !files.siteConfig?.includes("در حال توسعه"),
  "Canonical URLs, indexing and the production social image must come from central configuration.",
);

gate(
  "root_does_not_duplicate_route_canonicals",
  !rootHasRouteCanonical,
  "The root layout must provide only universal metadata; public routes own canonical and og:url.",
);

gate(
  "private_routes_are_noindex",
  hasAll(files.root, [
    "routeShouldNoIndex",
    "!siteConfig.allowIndexing",
    "NOINDEX_PATHS",
    "NOINDEX_PREFIXES",
    'content: "noindex,follow"',
    '"/admin"',
    '"/auth"',
    '"/orders"',
    '"/panel"',
  ]),
  "Private customer, seller and administrator routes must stay out of search.",
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
  "runtime_permanent_redirects",
  hasAll(files.server, [
    "applyPublicSeoRedirect",
    "response.status !== 404",
    "resolveSeoRedirect(request)",
  ]) &&
    hasAll(files.redirectLib, [
      "/seo/redirects/resolve?path=",
      "PERMANENT_REDIRECT_STATUSES",
      "new Set([301, 308])",
      "destination.origin === requestUrl.origin",
    ]),
  "Laravel-managed permanent redirects must execute at the SSR 404 boundary and remain same-origin.",
);

gate(
  "sharded_live_authoritative_sitemap",
  hasAll(files.sitemapIndex, ['createFileRoute("/sitemap.xml")', "sitemapIndexResponse"]) &&
    hasAll(files.sitemapLib, [
      "SITEMAP_SHARDS",
      "listProducts",
      "listRoasteries",
      "listIndexableContent",
      'product.status === "published"',
      "roastery.isVerified",
      "absoluteUrl(entry.path)",
    ]) &&
    hasAll(files.sitemapProducts, ["productSitemapEntries", "sitemapShardResponse"]) &&
    hasAll(files.sitemapRoasteries, ["roasterySitemapEntries", "sitemapShardResponse"]) &&
    hasAll(files.sitemapContent, ["contentSitemapEntries", "sitemapShardResponse"]) &&
    hasAll(files.sitemapStatic, ["STATIC_SITEMAP_ENTRIES", "sitemapShardResponse"]),
  "The sitemap index must use bounded live catalog, roastery, content and static shards.",
);

gate(
  "sitemap_is_bounded_and_fail_visible",
  hasAll(files.sitemapLib, [
    "MAX_SITEMAP_URLS = 50_000",
    "status: 503",
    '"Retry-After": "300"',
    '"Cache-Control": "no-store"',
    "Sitemap shard exceeds",
  ]),
  "A failed or oversized upstream shard must never be published as a silent incomplete HTTP 200.",
);

gate(
  "robots_and_sitemaps_registered",
  [
    "/robots.txt",
    "/sitemap.xml",
    "/sitemaps/static.xml",
    "/sitemaps/products.xml",
    "/sitemaps/roasteries.xml",
    "/sitemaps/content.xml",
  ].every((route) => files.routeTree?.includes(route)),
  "The generated TanStack route tree must register every crawler endpoint.",
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
  "static_route_canonicals_are_absolute",
  [files.about, files.privacy, files.terms, files.quiz].every(
    (source) => source?.includes("absoluteUrl") && !source.includes('href: "/'),
  ),
  "About, privacy, terms and quiz canonical links must be absolute in initial HTML.",
);

gate(
  "pagination_and_query_indexability",
  hasAll(files.products, [
    "if (search.page > 1)",
    'params.set("page", String(search.page))',
    "shouldNoIndex",
    "search.q.trim()",
    "search.available",
    'search.sort !== "recommended"',
    'content: "noindex,follow"',
  ]) && hasAll(files.roasteries, ["`?page=${loaderData?.search.page}`", 'rel: "canonical"']),
  "Paginated catalogs must self-canonicalize while search, availability and alternate sorts noindex.",
);

gate(
  "social_preview_asset",
  (await exists(paths.ogPng)) &&
    hasAll(files.ogSvg, ['width="1200"', 'height="630"', "رستا"]) &&
    hasAll(files.root, [
      'property: "og:image:width"',
      'property: "og:image:height"',
      'property: "og:image:alt"',
    ]),
  "The default Open Graph card must be a real 1200x630 asset with explicit dimensions and alt.",
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
  "staging_indexing_and_seo_acceptance",
  files.staging?.includes("VITE_ALLOW_INDEXING=false") &&
    hasAll(files.acceptance, ["seo_runtime_ok", "sitemaps/products.xml", "og-home.png"]),
  "Staging must remain noindex and prove canonical, sitemap and social-image behavior at runtime.",
);

gate(
  "whole_bean_product_identity_is_consistent",
  files.product?.includes("هویت محصول و موجودی همیشه دانه کامل است") &&
    files.product?.includes("خدمت جداگانه") &&
    !files.product?.includes("هیچ انتخاب آسیابی وجود ندارد"),
  "Product schema and FAQ must keep whole-bean identity while describing optional grinding as a service.",
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
