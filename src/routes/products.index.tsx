import { useQuery } from "@tanstack/react-query";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { fallback, zodValidator } from "@tanstack/zod-adapter";
import { z } from "zod";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl } from "@/config/site";
import { isApiError } from "@/lib/api/client";
import { productsQueryOptions } from "@/lib/api/catalog";
import type { ProcessingMethod, ProductFilters, RoastLevel } from "@/lib/api/contracts";

const searchSchema = z.object({
  q: fallback(z.string(), "").default(""),
  origin: fallback(z.string(), "").default(""),
  roast: fallback(z.enum(["", "light", "medium", "dark"]), "").default(""),
  processing: fallback(z.enum(["", "washed", "natural", "honey", "other"]), "").default(""),
  roastery: fallback(z.string(), "").default(""),
  available: fallback(z.boolean(), false).default(false),
  sort: fallback(
    z.enum(["recommended", "newest", "price_asc", "price_desc"]),
    "recommended",
  ).default("recommended"),
  page: fallback(z.coerce.number().int().min(1), 1).default(1),
});
type ProductsSearch = z.infer<typeof searchSchema>;
const roastLabels = { light: "روشن", medium: "متوسط", dark: "تیره" } as const;
const processingLabels = {
  washed: "شسته",
  natural: "طبیعی",
  honey: "هانی",
  other: "سایر",
} as const;

function filtersFromSearch(search: ProductsSearch): ProductFilters {
  return {
    query: search.q.trim() || undefined,
    origin: search.origin ? [search.origin] : undefined,
    roastLevel: search.roast ? [search.roast as RoastLevel] : undefined,
    processingMethod: search.processing ? [search.processing as ProcessingMethod] : undefined,
    roastery: search.roastery ? [search.roastery] : undefined,
    available: search.available || undefined,
    sort: search.sort,
    page: search.page,
    perPage: 24,
  };
}
function canonicalFor(search: ProductsSearch) {
  const params = new URLSearchParams();
  if (search.origin) params.set("origin", search.origin);
  if (search.roast) params.set("roast", search.roast);
  if (search.processing) params.set("processing", search.processing);
  if (search.roastery) params.set("roastery", search.roastery);
  const query = params.toString();
  return absoluteUrl(`/products${query ? `?${query}` : ""}`);
}

export const Route = createFileRoute("/products/")({
  validateSearch: zodValidator(searchSchema),
  loaderDeps: ({ search }) => searchSchema.parse(search ?? {}),
  loader: async ({ context, deps }) => ({
    search: deps,
    catalog: await context.queryClient.ensureQueryData(
      productsQueryOptions(filtersFromSearch(deps)),
    ),
  }),
  head: ({ loaderData }) => {
    const resolved: ProductsSearch = loaderData?.search ?? searchSchema.parse({});
    const title = resolved.origin
      ? `خرید قهوه ${resolved.origin} | رستا`
      : resolved.roast
        ? `خرید قهوه رست ${roastLabels[resolved.roast]} | رستا`
        : "خرید دانه قهوه تازه و اسپشیالیتی | رستا";
    const description =
      "مقایسه و خرید دانه کامل قهوه تازه‌رست از روستری‌های منتخب ایران؛ با فیلتر خاستگاه، سطح رست، فرآوری، وزن و موجودی.";
    return {
      meta: [
        { title },
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
      ],
      links: [{ rel: "canonical", href: canonicalFor(resolved) }],
      scripts: [
        {
          type: "application/ld+json",
          children: JSON.stringify(
            breadcrumbJsonLd([
              { label: "خانه", to: "/" },
              { label: "محصولات", to: "/products" },
            ]),
          ),
        },
        ...(loaderData?.catalog.items.length
          ? [
              {
                type: "application/ld+json",
                children: JSON.stringify({
                  "@context": "https://schema.org",
                  "@type": "ItemList",
                  itemListElement: loaderData.catalog.items.map((product, index) => ({
                    "@type": "ListItem",
                    position: index + 1,
                    url: absoluteUrl(`/products/${product.slug}`),
                    name: product.name,
                  })),
                }),
              },
            ]
          : []),
      ],
    };
  },
  component: ProductsPage,
});

function ProductsPage() {
  const search: ProductsSearch = Route.useSearch();
  const navigate = useNavigate({ from: "/products/" });
  const query = useQuery(productsQueryOptions(filtersFromSearch(search)));
  const products = query.data?.items ?? [];
  const currentPage = query.data?.meta?.current_page ?? search.page;
  const lastPage = query.data?.meta?.last_page ?? currentPage;
  const total = query.data?.meta?.total;
  const updateSearch = (patch: Partial<ProductsSearch>, resetPage = true) =>
    navigate({
      search: (previous: ProductsSearch) => ({
        ...previous,
        ...patch,
        page: resetPage ? 1 : (patch.page ?? previous.page),
      }),
      replace: true,
    });
  const clearFilters = () => navigate({ search: searchSchema.parse({}), replace: true });

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "محصولات" }]} />
        <header className="mt-4 grid gap-5 lg:grid-cols-[1fr_auto] lg:items-end">
          <div>
            <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">CATALOG</p>
            <h1 className="mt-2 text-3xl font-bold sm:text-4xl">
              {search.origin
                ? `دانه قهوه ${search.origin}`
                : search.roast
                  ? `قهوه با رست ${roastLabels[search.roast]}`
                  : "همه دانه‌های قهوه"}
            </h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-[color:var(--light)]">
              فقط دانه کامل؛ قیمت، وزن، موجودی و تاریخ رست مستقیماً از API.
            </p>
          </div>
          {typeof total === "number" ? (
            <p className="text-sm text-[color:var(--light)]">
              {total.toLocaleString("fa-IR")} محصول
            </p>
          ) : null}
        </header>

        <section
          aria-label="فیلتر کاتالوگ"
          className="mt-8 grid gap-4 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4 md:grid-cols-2 lg:grid-cols-4"
        >
          <label className="grid gap-2 text-xs font-bold text-[color:var(--light)] md:col-span-2">
            جستجو
            <input
              value={search.q}
              onChange={(event) => updateSearch({ q: event.target.value })}
              placeholder="نام محصول، خاستگاه یا روستری"
              className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
            />
          </label>
          <FilterSelect
            label="سطح رست"
            value={search.roast}
            onChange={(value) => updateSearch({ roast: value as ProductsSearch["roast"] })}
            options={Object.entries(roastLabels)}
          />
          <FilterSelect
            label="فرآوری"
            value={search.processing}
            onChange={(value) =>
              updateSearch({ processing: value as ProductsSearch["processing"] })
            }
            options={Object.entries(processingLabels)}
          />
          <label className="grid gap-2 text-xs font-bold text-[color:var(--light)]">
            خاستگاه
            <input
              value={search.origin}
              onChange={(event) => updateSearch({ origin: event.target.value })}
              placeholder="مثلاً اتیوپی"
              className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
            />
          </label>
          <label className="grid gap-2 text-xs font-bold text-[color:var(--light)]">
            روستری
            <input
              value={search.roastery}
              onChange={(event) => updateSearch({ roastery: event.target.value })}
              placeholder="Slug روستری"
              className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
            />
          </label>
          <FilterSelect
            label="مرتب‌سازی"
            value={search.sort}
            includeAll={false}
            onChange={(value) => updateSearch({ sort: value as ProductsSearch["sort"] })}
            options={[
              ["recommended", "پیشنهادی"],
              ["newest", "تازه‌ترین رست"],
              ["price_asc", "کمترین قیمت"],
              ["price_desc", "بیشترین قیمت"],
            ]}
          />
          <label className="flex min-h-11 items-center gap-3 self-end rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm">
            <input
              type="checkbox"
              checked={search.available}
              onChange={(event) => updateSearch({ available: event.target.checked })}
              className="size-4 accent-[color:var(--roast)]"
            />
            فقط موجودها
          </label>
          <button
            type="button"
            onClick={clearFilters}
            className="min-h-11 self-end rounded-xl border border-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--roast)]"
          >
            پاک‌کردن فیلترها
          </button>
        </section>

        {query.isPending ? (
          <GridSkeleton />
        ) : query.isError ? (
          <ErrorState error={query.error} retry={() => query.refetch()} />
        ) : !products.length ? (
          <section className="mt-10 rounded-2xl border border-dashed border-[color:var(--mid)] p-10 text-center">
            <h2 className="font-bold">محصولی با این فیلتر پیدا نشد</h2>
            <button
              type="button"
              onClick={clearFilters}
              className="mt-4 text-sm text-[color:var(--roast)]"
            >
              نمایش همه محصولات
            </button>
          </section>
        ) : (
          <>
            <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {products.map((product) => (
                <CatalogProductCard key={product.id} product={product} />
              ))}
            </div>
            {lastPage > 1 ? (
              <nav
                aria-label="صفحه‌بندی محصولات"
                className="mt-10 flex items-center justify-center gap-3"
              >
                <PageButton
                  disabled={currentPage <= 1}
                  onClick={() => updateSearch({ page: currentPage - 1 }, false)}
                >
                  قبلی
                </PageButton>
                <span className="text-sm text-[color:var(--light)]">
                  صفحه {currentPage.toLocaleString("fa-IR")} از {lastPage.toLocaleString("fa-IR")}
                </span>
                <PageButton
                  disabled={currentPage >= lastPage}
                  onClick={() => updateSearch({ page: currentPage + 1 }, false)}
                >
                  بعدی
                </PageButton>
              </nav>
            ) : null}
          </>
        )}
      </main>
      <Footer />
    </>
  );
}

function FilterSelect({
  label,
  value,
  options,
  onChange,
  includeAll = true,
}: {
  label: string;
  value: string;
  options: Array<[string, string]>;
  onChange: (value: string) => void;
  includeAll?: boolean;
}) {
  return (
    <label className="grid gap-2 text-xs font-bold text-[color:var(--light)]">
      {label}
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
      >
        {includeAll ? <option value="">همه</option> : null}
        {options.map(([optionValue, optionLabel]) => (
          <option key={optionValue} value={optionValue}>
            {optionLabel}
          </option>
        ))}
      </select>
    </label>
  );
}
function PageButton({
  children,
  disabled,
  onClick,
}: {
  children: React.ReactNode;
  disabled: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className="min-h-11 rounded-xl border border-[color:var(--mid)] px-4 text-sm disabled:opacity-40"
    >
      {children}
    </button>
  );
}
function GridSkeleton() {
  return (
    <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" role="status">
      {Array.from({ length: 8 }, (_, index) => (
        <div key={index} className="h-[25rem] animate-pulse rounded-2xl bg-[color:var(--dark)]" />
      ))}
    </div>
  );
}
function ErrorState({ error, retry }: { error: unknown; retry: () => unknown }) {
  return (
    <section className="mt-10 rounded-2xl border border-red-400/40 bg-red-950/20 p-6 text-center">
      <h2 className="font-bold">کاتالوگ بارگذاری نشد</h2>
      <p className="mt-2 text-sm text-[color:var(--light)]">
        {isApiError(error) ? error.message : "ارتباط با API برقرار نشد."}
      </p>
      <button
        type="button"
        onClick={retry}
        className="mt-5 min-h-11 rounded-xl bg-[color:var(--roast)] px-5 text-sm font-bold text-[color:var(--night)]"
      >
        تلاش مجدد
      </button>
    </section>
  );
}
