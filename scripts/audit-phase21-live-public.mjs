import { access, readFile, readdir, writeFile } from "node:fs/promises";
import { join } from "node:path";

async function exists(path) {
  try {
    await access(path);
    return true;
  } catch {
    return false;
  }
}
async function walk(path) {
  const entries = await readdir(path, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const next = join(path, entry.name);
    if (entry.isDirectory()) files.push(...(await walk(next)));
    else if (/\.(ts|tsx)$/.test(entry.name)) files.push(next);
  }
  return files;
}

const paths = {
  package: "package.json",
  home: "src/routes/index.tsx",
  homeClient: "src/lib/api/homepage.ts",
  blogIndex: "src/routes/blog.index.tsx",
  blogEntry: "src/routes/blog.$slug.tsx",
  contentBlocks: "src/components/content/ContentBlocks.tsx",
  quiz: "src/routes/quiz.tsx",
  quizLogic: "src/lib/quiz-logic.ts",
  quizClient: "src/lib/api/quiz.ts",
  quizService: "backend/app/Services/Quiz/QuizService.php",
  products: "src/routes/products.index.tsx",
  roasteries: "src/routes/roasteries.index.tsx",
  product: "src/routes/products.$slug.tsx",
  order: "src/routes/orders.$id.tsx",
  reviews: "src/lib/api/reviews.ts",
  productionSafety: "backend/app/Providers/ProductionSafetyServiceProvider.php",
  providers: "backend/bootstrap/providers.php",
};
const files = Object.fromEntries(
  await Promise.all(
    Object.entries(paths).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);
const sourceFiles = await walk("src");
const source = (await Promise.all(sourceFiles.map((path) => readFile(path, "utf8")))).join("\n");
const packageJson = JSON.parse(files.package);
const gates = [];
const gate = (name, condition, evidence) =>
  gates.push({ name, passed: Boolean(condition), evidence });

gate(
  "permanent_phase21_gate",
  packageJson.scripts?.["audit:phase21"] === "node scripts/audit-phase21-live-public.mjs" &&
    packageJson.scripts?.check?.includes("audit:phase21"),
  "Phase 21 live-public audit must remain in bun run check.",
);
gate(
  "seed_catalog_removed",
  !(await exists("src/data/seed.ts")) &&
    !(await exists("src/data/blog-posts.ts")) &&
    !source.includes("@/data/seed") &&
    !source.includes("data/blog-posts"),
  "Production source must not contain or import the old static catalog or blog fixtures.",
);
gate(
  "homepage_is_live_ssr",
  files.home.includes("ensureQueryData(homepageQueryOptions())") &&
    files.home.includes("CatalogProductCard") &&
    files.home.includes("HomeRoasteryCard") &&
    files.homeClient.includes("listProducts") &&
    files.homeClient.includes("listRoasteries") &&
    !files.home.includes("data/seed"),
  "Homepage products, roasteries, counts and optional FAQ must be composed from live API/CMS data in the loader.",
);
gate(
  "editorial_is_safe_cms_ssr",
  files.blogIndex.includes("blogIndexQueryOptions") &&
    files.blogEntry.includes("blogEntryQueryOptions") &&
    files.blogEntry.includes("ContentBlocks") &&
    !files.blogEntry.includes("dangerouslySetInnerHTML") &&
    !files.contentBlocks.includes("dangerouslySetInnerHTML"),
  "Blog index and entries must use published CMS records and safe block rendering instead of static/raw HTML.",
);
gate(
  "quiz_uses_live_available_catalog",
  files.quiz.includes("getCurrentQuiz") &&
    files.quiz.includes("submitQuizAttempt") &&
    files.quizClient.includes('apiFetch<unknown>("/quiz/current")') &&
    files.quizClient.includes('apiFetch<unknown>("/quiz/attempts"') &&
    files.quizService.includes("Product::query()->published()") &&
    files.quizService.includes("whereHas('variants'") &&
    files.quizService.includes("whereColumn('stock_on_hand', '>', 'stock_reserved')") &&
    !files.quizLogic.includes("data/seed"),
  "Persisted Quiz ranking must execute server-side against published products with live available stock.",
);
gate(
  "public_catalog_lists_are_ssr",
  files.products.includes("loaderDeps") &&
    /ensureQueryData\s*\(\s*productsQueryOptions\s*\(/.test(files.products) &&
    files.roasteries.includes("loaderDeps") &&
    /ensureQueryData\s*\(\s*roasteriesQueryOptions\s*\(/.test(files.roasteries) &&
    !files.products.includes("dangerouslySetInnerHTML") &&
    !files.roasteries.includes("dangerouslySetInnerHTML"),
  "Product and roastery directory HTML and ItemList metadata must be loader-backed.",
);
gate(
  "verified_review_flow_is_live",
  files.product.includes("ProductReviews") &&
    files.order.includes("createVerifiedReview") &&
    files.order.includes("orderItemId: item.id") &&
    files.reviews.includes("is_verified_purchase: z.literal(true)") &&
    files.reviews.includes("order_item_id"),
  "Approved verified-purchase reviews must render publicly and submissions must originate from delivered order items.",
);
gate(
  "production_seeding_is_forbidden",
  files.productionSafety.includes("CommandStarting") &&
    files.productionSafety.includes("db:seed") &&
    files.productionSafety.includes("migrate:fresh") &&
    files.providers.includes("ProductionSafetyServiceProvider"),
  "Production must fail closed for database seed and migrate:fresh commands.",
);
gate(
  "whole_bean_boundary_preserved",
  !/grind[_-]?(selector|state)|grind_option/i.test(
    [files.home, files.quiz, files.product, files.order].join("\n"),
  ) &&
    files.product.includes("فقط دانه کامل") &&
    files.order.includes("دانه کامل"),
  "Live public and review surfaces must not introduce any grind state.",
);

const failed = gates.filter((item) => !item.passed);
await writeFile(
  "frontend-phase21-audit.json",
  `${JSON.stringify({ generatedAt: new Date().toISOString(), marker: "phase21_live_public=ready", passed: failed.length === 0, gates }, null, 2)}\n`,
);
if (failed.length) {
  console.error("Phase 21 live-public audit failed:");
  failed.forEach((item) => console.error(`- ${item.name}: ${item.evidence}`));
  process.exit(1);
}
console.log(`Phase 21 live-public audit passed (${gates.length} gates).`);
