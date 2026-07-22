import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const root = process.cwd();
const failures = [];

function read(relativePath) {
  try {
    return readFileSync(resolve(root, relativePath), "utf8");
  } catch {
    failures.push(`Missing or unreadable file: ${relativePath}`);
    return "";
  }
}

function requireContains(relativePath, needle, message) {
  if (!read(relativePath).includes(needle)) failures.push(message);
}

const rootRoute = read("src/routes/__root.tsx");
for (const route of [
  '"/cart"',
  '"/checkout"',
  '"/profile"',
  '"/quiz"',
  '"/search"',
  '"/auth"',
  '"/orders"',
]) {
  if (!rootRoute.includes(route)) {
    failures.push(`Noindex policy is missing route ${route}.`);
  }
}
if (rootRoute.includes('rel: "canonical", href: absoluteUrl("/")')) {
  failures.push("Root route must not canonicalize every page to the homepage.");
}
requireContains(
  "src/routes/__root.tsx",
  "absoluteUrl(location.pathname)",
  "Root canonical and Open Graph URL must follow the current pathname.",
);

const sitemap = read("src/routes/sitemap[.]xml.ts");
if (sitemap.includes('path: "/quiz"')) {
  failures.push("Quiz must not be included in the sitemap.");
}
for (const needle of [
  "listIndexableContent",
  "MAX_CATALOG_PAGES",
  "structuredContentEntries",
]) {
  if (!sitemap.includes(needle)) {
    failures.push(`Sitemap is missing required boundary: ${needle}`);
  }
}

requireContains(
  "src/routes/robots[.]txt.ts",
  'Disallow: /checkout',
  "robots.txt must protect transactional routes.",
);
requireContains(
  "src/routes/robots[.]txt.ts",
  'absoluteUrl("/sitemap.xml")',
  "robots.txt must advertise the canonical sitemap.",
);

for (const route of [
  "guides.$slug.tsx",
  "origins.$slug.tsx",
  "brew.$slug.tsx",
  "tastes.$slug.tsx",
  "collections.$slug.tsx",
  "compare.$slug.tsx",
]) {
  const path = `src/routes/${route}`;
  requireContains(
    path,
    "ensureQueryData",
    `${route} must preload critical content before rendering.`,
  );
  requireContains(
    path,
    "contentSeoHead(loaderData)",
    `${route} must render metadata from authoritative loader data.`,
  );
}

requireContains(
  "src/routes/products.$slug.tsx",
  "productQueryOptions(params.slug)",
  "Product pages must load authoritative data before rendering metadata.",
);
requireContains(
  "src/routes/products.$slug.tsx",
  '"@type": "ProductGroup"',
  "Whole-bean weight variants must use ProductGroup structured data.",
);
requireContains(
  "src/routes/roasteries.$slug.tsx",
  "roasteryQueryOptions(params.slug)",
  "Roastery pages must load authoritative data before rendering metadata.",
);

const structuredRenderer = read(
  "src/components/content/StructuredContentPage.tsx",
);
if (structuredRenderer.includes("dangerouslySetInnerHTML")) {
  failures.push("Structured editorial content must not render raw HTML.");
}
requireContains(
  "src/lib/api/content.ts",
  'z.discriminatedUnion("type"',
  "Content API responses must be runtime-validated by block type.",
);
requireContains(
  "src/lib/seo.ts",
  'replace(/</g, "\\\\u003c")',
  "JSON-LD serialization must escape opening angle brackets.",
);

if (failures.length) {
  console.error("SEO foundation audit failed:");
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log("SEO foundation audit passed.");
