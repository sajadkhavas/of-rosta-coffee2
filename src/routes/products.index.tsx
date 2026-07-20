import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { fallback, zodValidator } from "@tanstack/zod-adapter";
import { z } from "zod";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import { productsQueryOptions } from "@/lib/api/catalog";
import type {
  ProcessingMethod,
  ProductFilters,
  RoastLevel,
} from "@/lib/api/contracts";
import { isApiError } from "@/lib/api/client";
import { absoluteUrl } from "@/config/site";

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

function filtersFromSearch(search: ProductsSearch): ProductFilters {
  return {
    query: search.q.trim() || undefined,
    origin: search.origin ? [search.origin] : undefined,
    roastLevel: search.roast ? [search.roast as RoastLevel] : undefined,
    processingMethod: search.processing
      ? [search.processing as ProcessingMethod]
      : undefined,
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
  head: ({ search }) => {
    const title = search.origin
      ? `خرید قهوه ${search.origin} | رستا`
      : search.roast
        ? `خرید قهوه رست ${search.roast} | رستا`
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
      links: [{ rel: "canonical", href: canonicalFor(search) }],
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
      ],
    };
  },
  component: ProductsPage,
});

const roastLabels: Record<Exclude<ProductsSearch["roast"], "">, string> = {
  light: "روشن",
  medium: "متوسط",
  dark: "تیره",
};

const processingLabels: Record<Exclude<ProductsSearch["processing"], "">, string> = {
  washed: "شسته",
  natural: "طبیعی",
  honey: "هانی",
  other: "سایر",
};

function ProductsPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/products/" });
  const filters = filtersFromSearch(search);
  const query = useQuery(productsQueryOptions(filters));

  const setSearch = (patch: Partial<ProductsSearch>, resetPage = true) => {
    navigate({
      search: (previous: ProductsSearch) => ({
        ...previous,
        ...patch,
        page: resetPage ? 1 : (patch.page ?? previous.page),
      }),
      replace: true,
    });
  };

  const products = query.data?.items ?? [];
  const currentPage = query.data?.meta?.current_page ?? search.page;
  const lastPage = query.data?.meta?.last_page ?? currentPage;
  const total = query.data?.meta?.total;
  const originOptions = Array.from(
    new Map(products.map((product) => [product.origin.id, product.origin])).values(),
  );
  const roasteryOptions = Array.from(
    new Map(products.map((product) => [product.roastery.slug, product.roastery])).values(),
  );

  const clearFilters = () =>
    navigate({
      search: {
        q: "",
        origin: "",
        roast: "",
        processing: "",
        roastery: "",
        available: false,
        sort: "recommended",
        page: 1,
      },
      replace: true,
    });

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "محصولات" }]} />
        <header className="mt-4 grid gap-5 lg:grid-cols-[1fr_auto] lg:items-end">
          <div>
            <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">
              CATALOG
            </p>
            <h1 className="mt-2 text-3xl font-bold text-[color:var(--steam)] sm:text-4xl">
              {search.origin
                ? `دانه قهوه ${search.origin}`
                : search.roast
                  ? `قهوه با رست ${roastLabels[search.roast]}`
                  : "همه دانه‌های قهوه"}
            </h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-[color:var(--light)]">
              فقط دانه کامل؛ قیمت، وزن، موجودی و تاریخ رست مستقیماً از API روستری‌ها.
            </p>
          </div>
          {typeof total === "number" ? (
            <p className="text-sm text-[color:var(--light)]">{total.toLocaleString("fa-IR")} محصول</p>
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
              onChange={(event) => setSearch({ q: event.target.value })}
              placeholder="نام محصول، خاستگاه یا روستری"
              className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]"
            />
          </label>
          <label className="grid gap-2 text-xs font-bold text-[color:var(--light)]">
            سطح رست
            <select
              value={search.roast}
              onChange={(event) => setSearch({ roast: event.target.value as ProductsSearch["roast"] })}
              className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
            >
              <option value="">همه</option>
              {Object.entries(roastLabels).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>
          <label className="grid gap-2 text-xs font-bold text-[color:var(--light)]">
            فرآوری
            <select
              value={search.processing}
              onChange={(event) =>
                setSearch({ processing: event.target.value as ProductsSearch["processing"] })
              }
              className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
            >
              <option value="">همه</option>
              {Object.entries(processingLabels).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>
          <label className="grid gap-2 text-xs font-bold text-[color:var(--light)]">
            خاستگاه
            <select
              value={search.origin}
              onChange={(event) => setSearch({ origin: event.target.value })}
              className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
            >
              <option value="">همه</option>
              {search.origin && !originOptions.some((origin) => origin.id === search.origin) ? (
                <option value={search.origin}>{search.origin}</option>
              ) : null}
              {originOptions.map((origin) => (
                <option key={origin.id} value={origin.id}>{origin.name}</option>
              ))}
            </select>
          </label>
          <label className="grid gap-2 text-xs font-bold text-[color:var(--light)]">
            روستری
            <select
              value={search.roastery}
              onChange={(event) => setSearch({ roastery: event.target.value })}
              className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
            >
              <option value="">همه</option>
              {search.roastery && !roasteryOptions.some((item) => item.slug === search.roastery) ? (
                <option value={search.roastery}>{search.roastery}</option>
              ) : null}
              {roasteryOptions.map((item) => (
                <option key={item.slug} value={item.slug}>{item.name}</option>
              ))}
            </select>
          </label>
          <label className="grid gap-2 text-xs font-bold text-[color:var(--light)]">
            مرتب‌سازی
            <select
              value={search.sort}
              onChange={(event) => setSearch({ sort: event.target.value as ProductsSearch["sort"] })}
              className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
            >
              <option value="recommended">پیشنهادی</option>
              <option value="newest">تازه‌ترین رست</option>
              <option value="price_asc">کمترین قیمت</option>
              <option value="price_desc">بیشترین قیمت</option>
            </select>
          </label>
          <label className="flex min-h-11 items-center gap-3 self-end rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm text-[color:var(--light)]">
            <input
              type="checkbox"
              checked={search.available}
              onChange={(event) => setSearch({ available: event.target.checked })}
              className="size-4 accent-[color:var(--roast)]"
            />
            فقط موجودها
          </label>
          <button
            type="button"
            onClick={clearFilters}
            className="min-h-11 self-end rounded-xl border border-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--roast)] hover:bg-[color:var(--roast)] hover:text-[color:var(--night)]"
          >
            پاک‌کردن فیلترها
          </button>
        </section>

        {query.isPending ? (
          <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="در حال بارگذاری">
            {Array.from({ length: 8 }, (_, index) => (
              <div key={index} className="h-[25rem] animate-pulse rounded-2xl bg-[color:var(--dark)]" />
            ))}
          </div>
        ) : query.isError ? (
          <section className="mt-10 rounded-2xl border border-red-400/40 bg-red-950/20 p-6 text-center">
            <h2 className="font-bold text-[color:var(--steam)]">کاتالوگ بارگذاری نشد</h2>
            <p className="mt-2 text-sm text-[color:var(--light)]">
              {isApiError(query.error) ? query.error.message : "ارتباط با API برقرار نشد."}
            </p>
            <button
              type="button"
              onClick={() => query.refetch()}
              className="mt-5 rounded-xl bg-[color:var(--roast)] px-5 py-2.5 text-sm font-bold text-[color:var(--night)]"
            >
              تلاش مجدد
            </button>
          </section>
        ) : products.length === 0 ? (
          <section className="mt-10 rounded-2xl border border-dashed border-[color:var(--mid)] p-10 text-center">
            <h2 className="font-bold">محصولی با این فیلتر پیدا نشد</h2>
            <button type="button" onClick={clearFilters} className="mt-4 text-sm text-[color:var(--roast)]">
              نمایش همه محصولات
            </button>
          </section>
        ) : (
          <>
            <script
              type="application/ld+json"
              dangerouslySetInnerHTML={{
                __html: JSON.stringify({
                  "@context": "https://schema.org",
                  "@type": "ItemList",
                  itemListElement: products.map((product, index) => ({
                    "@type": "ListItem",
                    position: index + 1,
                    url: absoluteUrl(`/products/${product.slug}`),
                    name: product.name,
                  })),
                }),
              }}
            />
            <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {products.map((product) => (
                <CatalogProductCard key={product.id} product={product} />
              ))}
            </div>
            {lastPage > 1 ? (
              <nav aria-label="صفحه‌بندی محصولات" className="mt-10 flex items-center justify-center gap-3">
                <button
                  type="button"
                  disabled={currentPage <= 1}
                  onClick={() => setSearch({ page: currentPage - 1 }, false)}
                  className="rounded-xl border border-[color:var(--mid)] px-4 py-2 text-sm disabled:opacity-40"
                >
                  قبلی
                </button>
                <span className="text-sm text-[color:var(--light)]">
                  صفحه {currentPage.toLocaleString("fa-IR")} از {lastPage.toLocaleString("fa-IR")}
                </span>
                <button
                  type="button"
                  disabled={currentPage >= lastPage}
                  onClick={() => setSearch({ page: currentPage + 1 }, false)}
                  className="rounded-xl border border-[color:var(--mid)] px-4 py-2 text-sm disabled:opacity-40"
                >
                  بعدی
                </button>
              </nav>
            ) : null}
          </>
        )}
      </main>
      <Footer />
    </>
  );
}
