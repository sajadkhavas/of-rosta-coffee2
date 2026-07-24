from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:80]!r}")
    file.write_text(source.replace(old, new))


replace(
    "src/components/RoastLevelBadge.tsx",
    'import type { RoastLevel } from "@/data/seed";\n\n',
    'type RoastLevel = "روشن" | "متوسط" | "تیره";\n\n',
)
replace(
    "src/components/WeightSelector.tsx",
    'import { WEIGHTS, type Weight } from "@/data/seed";\n',
    'const WEIGHTS = [50, 100, 250, 500, 1000] as const;\ntype Weight = (typeof WEIGHTS)[number];\n',
)

replace(
    "src/lib/api/content.ts",
    '''const comparisonTableBlock = z
  .object({
    type: z.literal("comparison_table"),
    columns: z.array(text(120)).min(1).max(8),
    rows: z.array(z.array(text(1000)).min(1).max(8)).min(1).max(50),
  })
  .strict()
  .superRefine((value, context) => {
    value.rows.forEach((row, index) => {
      if (row.length !== value.columns.length) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["rows", index],
          message: "تعداد سلول‌های جدول با ستون‌ها برابر نیست.",
        });
      }
    });
  });

export const contentBlockSchema = z.discriminatedUnion("type", [
  paragraphBlock,
  headingBlock,
  listBlock,
  quoteBlock,
  calloutBlock,
  faqBlock,
  productGridBlock,
  roasterySpotlightBlock,
  comparisonTableBlock,
]);
''',
    '''const comparisonTableBlock = z
  .object({
    type: z.literal("comparison_table"),
    columns: z.array(text(120)).min(1).max(8),
    rows: z.array(z.array(text(1000)).min(1).max(8)).min(1).max(50),
  })
  .strict();

export const contentBlockSchema = z
  .discriminatedUnion("type", [
    paragraphBlock,
    headingBlock,
    listBlock,
    quoteBlock,
    calloutBlock,
    faqBlock,
    productGridBlock,
    roasterySpotlightBlock,
    comparisonTableBlock,
  ])
  .superRefine((value, context) => {
    if (value.type !== "comparison_table") return;
    value.rows.forEach((row, index) => {
      if (row.length !== value.columns.length) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["rows", index],
          message: "تعداد سلول‌های جدول با ستون‌ها برابر نیست.",
        });
      }
    });
  });
''',
)

replace(
    "src/lib/api/inquiries.ts",
    'import { parseContract, resourceSchema } from "./contracts";',
    'import { parseContract, resourceSchema } from "./schemas";',
)

replace(
    "src/routes/sitemap[.]xml.ts",
    'import { blogPosts } from "@/data/blog-posts";\n',
    "",
)
replace(
    "src/routes/sitemap[.]xml.ts",
    '''          ...blogPosts.map((post) => ({
            path: `/blog/${post.slug}`,
            priority: "0.6",
            changefreq: "monthly" as const,
            lastmod: post.publishedAt,
          })),
''',
    "",
)

replace(
    "src/routes/__root.tsx",
    'import "@fontsource-variable/vazirmatn";',
    'import "@fontsource-variable/vazirmatn/index.css";',
)
replace(
    "src/routes/__root.tsx",
    'import "@fontsource-variable/playfair-display";',
    'import "@fontsource-variable/playfair-display/index.css";',
)
replace(
    "src/routes/__root.tsx",
    'head: ({ location }) => {\n    const noIndex = routeShouldNoIndex(location.pathname);',
    'head: ({ match }) => {\n    const pathname = match.pathname;\n    const noIndex = routeShouldNoIndex(pathname);',
)
replace(
    "src/routes/__root.tsx",
    "absoluteUrl(location.pathname)",
    "absoluteUrl(pathname)",
)

for name in (
    "brew.$slug.tsx",
    "collections.$slug.tsx",
    "compare.$slug.tsx",
    "guides.$slug.tsx",
    "origins.$slug.tsx",
    "tastes.$slug.tsx",
):
    replace(
        f"src/routes/{name}",
        "head: ({ loaderData }) => contentSeoHead(loaderData),",
        "head: ({ loaderData }) => (loaderData ? contentSeoHead(loaderData) : {}),",
    )

for name, variable in (
    ("products.$slug.tsx", "product"),
    ("roasteries.$slug.tsx", "roastery"),
):
    replace(
        f"src/routes/{name}",
        f"  head: ({{ loaderData }}) => {{\n    const {variable} = loaderData;",
        f"  head: ({{ loaderData }}) => {{\n    const {variable} = loaderData;\n    if (!{variable}) return {{}};",
    )

replace(
    "src/routes/products.index.tsx",
    '''  loaderDeps: ({ search }) => searchSchema.parse(search ?? {}),
  loader: ({ context, deps }) => context.queryClient.ensureQueryData(productsQueryOptions(filtersFromSearch(deps))),
  head: ({ search, loaderData }) => {
    const resolved = searchSchema.parse(search ?? {});
''',
    '''  loaderDeps: ({ search }) => searchSchema.parse(search ?? {}),
  loader: async ({ context, deps }) => ({
    search: deps,
    catalog: await context.queryClient.ensureQueryData(
      productsQueryOptions(filtersFromSearch(deps)),
    ),
  }),
  head: ({ loaderData }) => {
    const resolved = loaderData?.search ?? searchSchema.parse({});
''',
)
replace(
    "src/routes/products.index.tsx",
    "...(loaderData?.items.length ? [{",
    "...(loaderData?.catalog.items.length ? [{",
)
replace(
    "src/routes/products.index.tsx",
    "itemListElement: loaderData.items.map((product, index) => ({",
    "itemListElement: loaderData.catalog.items.map((product, index) => ({",
)

replace(
    "src/routes/auth.index.tsx",
    "  const content = modeContent[search.mode];",
    "  const mode: AuthMode = search.mode;\n  const content = modeContent[mode];",
)
replace(
    "src/routes/auth.index.tsx",
    "modeToPurpose(search.mode)",
    "modeToPurpose(mode)",
)
replace(
    "src/routes/auth.index.tsx",
    "mode: search.mode,",
    "mode,",
)

replace(
    "src/components/seller/SellerOperationsDashboard.tsx",
    '''  return {
    pending_acceptance: ["accepted", "rejected"],
    accepted: ["preparing"],
    preparing: ["ready_to_ship"],
    ready_to_ship: ["shipped"],
    shipped: ["delivered"],
  }[status ?? ""] ?? [];
''',
    '''  const actions: Partial<Record<string, FulfillmentInput["status"][]>> = {
    pending_acceptance: ["accepted", "rejected"],
    accepted: ["preparing"],
    preparing: ["ready_to_ship"],
    ready_to_ship: ["shipped"],
    shipped: ["delivered"],
  };
  return actions[status ?? ""] ?? [];
''',
)

replace(
    "src/routes/panel.manage.tsx",
    "compareAtPrice: number | null; isAvailable: boolean",
    "compareAtPrice?: number | null; isAvailable: boolean",
)
