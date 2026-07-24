from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:100]!r}")
    file.write_text(source.replace(old, new))


replace(
    "src/routes/blog.$slug.tsx",
    'import { blogEntryQueryOptions, blogIndexQueryOptions } from "@/lib/api/public-content";',
    'import { blogEntryQueryOptions, blogIndexQueryOptions, type PublicContentSummary } from "@/lib/api/public-content";\nimport type { ContentEntry } from "@/lib/api/content";',
)
replace(
    "src/routes/blog.$slug.tsx",
    "  const { entry, more } = Route.useLoaderData();",
    "  const { entry, more }: { entry: ContentEntry; more: PublicContentSummary[] } = Route.useLoaderData();",
)

replace(
    "src/routes/blog.index.tsx",
    'import { blogIndexQueryOptions } from "@/lib/api/public-content";',
    'import { blogIndexQueryOptions, type PublicContentSummary } from "@/lib/api/public-content";',
)
replace(
    "src/routes/blog.index.tsx",
    "  const entries = Route.useLoaderData();",
    "  const entries: PublicContentSummary[] = Route.useLoaderData();",
)

replace(
    "src/routes/index.tsx",
    'import { homepageQueryOptions } from "@/lib/api/homepage";',
    'import { homepageQueryOptions, type HomepageData, type HomeFaq } from "@/lib/api/homepage";',
)
replace(
    "src/routes/index.tsx",
    "    const faqs = loaderData?.faqs ?? [];",
    "    const faqs: HomeFaq[] = loaderData?.faqs ?? [];",
)
replace(
    "src/routes/index.tsx",
    "  const data = Route.useLoaderData();",
    "  const data: HomepageData = Route.useLoaderData();",
)

replace(
    "src/routes/products.$slug.tsx",
    "    const product = loaderData;",
    "    const product: ProductDetail | undefined = loaderData;",
)
replace(
    "src/routes/products.$slug.tsx",
    "  const product = Route.useLoaderData();",
    "  const product: ProductDetail = Route.useLoaderData();",
)

replace(
    "src/routes/products.index.tsx",
    '''  loader: async ({ context, deps }) => ({
    search: deps,
    catalog: await context.queryClient.ensureQueryData(
      productsQueryOptions(filtersFromSearch(deps)),
    ),
  }),
''',
    '''  loader: async ({ context, deps }) => ({
    search: deps,
    catalog: await context.queryClient.ensureQueryData(productsQueryOptions(filtersFromSearch(deps))),
  }),
''',
)
replace(
    "src/routes/products.index.tsx",
    "    const resolved = loaderData?.search ?? searchSchema.parse({});",
    "    const resolved: ProductsSearch = loaderData?.search ?? searchSchema.parse({});",
)
replace(
    "src/routes/products.index.tsx",
    "  const search = Route.useSearch();",
    "  const search: ProductsSearch = Route.useSearch();",
)
