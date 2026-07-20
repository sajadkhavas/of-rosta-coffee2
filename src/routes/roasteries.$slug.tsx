import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import { productsQueryOptions, roasteryQueryOptions } from "@/lib/api/catalog";
import { isApiError } from "@/lib/api/client";
import { bestMediaUrl } from "@/lib/catalog-format";
import { absoluteUrl } from "@/config/site";

export const Route = createFileRoute("/roasteries/$slug")({
  head: ({ params }) => ({
    meta: [
      { title: `روستری ${params.slug} | خرید آنلاین قهوه | رستا` },
      {
        name: "description",
        content: "معرفی روستری، شهر، زمان آماده‌سازی، سیاست ارسال و دانه‌های موجود برای خرید مستقیم در رستا.",
      },
      { property: "og:type", content: "profile" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl(`/roasteries/${params.slug}`) }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify(
          breadcrumbJsonLd([
            { label: "خانه", to: "/" },
            { label: "روستری‌ها", to: "/roasteries" },
            { label: params.slug, to: `/roasteries/${params.slug}` },
          ]),
        ),
      },
    ],
  }),
  component: RoasteryPage,
});

function RoasteryPage() {
  const { slug } = Route.useParams();
  const roasteryQuery = useQuery(roasteryQueryOptions(slug));
  const productsQuery = useQuery(
    productsQueryOptions({ roastery: [slug], available: true, page: 1, perPage: 24 }),
  );
  const roastery = roasteryQuery.data;

  if (roasteryQuery.isPending) {
    return (
      <>
        <Navbar />
        <main className="mx-auto grid min-h-[60vh] max-w-6xl place-items-center px-4 py-12">
          <div role="status" className="text-center">
            <div className="mx-auto size-9 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
            <p className="mt-4 text-sm text-[color:var(--light)]">در حال دریافت روستری…</p>
          </div>
        </main>
        <Footer />
      </>
    );
  }

  if (roasteryQuery.isError || !roastery) {
    const notFound = isApiError(roasteryQuery.error) && roasteryQuery.error.status === 404;
    return (
      <>
        <Navbar />
        <main className="mx-auto grid min-h-[60vh] max-w-xl place-items-center px-4 py-12 text-center">
          <section>
            <h1 className="text-2xl font-bold">{notFound ? "روستری پیدا نشد" : "پروفایل روستری بارگذاری نشد"}</h1>
            <p className="mt-3 text-sm text-[color:var(--light)]">
              {isApiError(roasteryQuery.error) ? roasteryQuery.error.message : "ارتباط با API برقرار نشد."}
            </p>
            {!notFound ? (
              <button type="button" onClick={() => roasteryQuery.refetch()} className="mt-5 rounded-xl bg-[color:var(--roast)] px-5 py-2.5 text-sm font-bold text-[color:var(--night)]">
                تلاش مجدد
              </button>
            ) : null}
          </section>
        </main>
        <Footer />
      </>
    );
  }

  const cover = bestMediaUrl(roastery.cover);
  const logo = bestMediaUrl(roastery.logo);
  const products = productsQuery.data?.items ?? [];
  const organizationJsonLd = {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: roastery.name,
    description: roastery.description,
    url: absoluteUrl(`/roasteries/${roastery.slug}`),
    logo: logo || undefined,
    image: cover || undefined,
    address: roastery.city
      ? { "@type": "PostalAddress", addressLocality: roastery.city, addressCountry: "IR" }
      : undefined,
    aggregateRating: roastery.rating
      ? {
          "@type": "AggregateRating",
          ratingValue: roastery.rating.value,
          ratingCount: roastery.rating.count,
        }
      : undefined,
  };

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(organizationJsonLd) }} />
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "روستری‌ها", to: "/roasteries" }, { label: roastery.name }]} />

        <section className="relative mt-5 overflow-hidden rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)]">
          <div className="relative min-h-56">
            {cover ? (
              <img src={cover} alt={`کاور ${roastery.name}`} className="absolute inset-0 h-full w-full object-cover opacity-55" fetchPriority="high" />
            ) : null}
            <div className="absolute inset-0 bg-gradient-to-t from-[color:var(--night)] via-[color:var(--night)]/65 to-transparent" />
            <div className="relative flex min-h-56 flex-col justify-end p-6 sm:p-8">
              <div className="flex flex-col gap-5 sm:flex-row sm:items-end">
                {logo ? (
                  <img src={logo} alt={`لوگوی ${roastery.name}`} className="size-24 rounded-2xl border-4 border-[color:var(--dark)] bg-white object-cover" />
                ) : (
                  <div className="grid size-24 place-items-center rounded-2xl border border-[color:var(--roast)] bg-[color:var(--dark)] text-2xl font-bold text-[color:var(--roast)]">
                    {roastery.name.slice(0, 2)}
                  </div>
                )}
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-3xl font-bold sm:text-4xl">{roastery.name}</h1>
                    {roastery.isVerified ? <span className="rounded-full bg-[color:var(--roast)] px-3 py-1 text-xs font-bold text-[color:var(--night)]">تأییدشده</span> : null}
                  </div>
                  <div className="mt-3 flex flex-wrap gap-3 text-sm text-[color:var(--light)]">
                    {roastery.city ? <span>📍 {roastery.city}</span> : null}
                    {roastery.rating ? <span>★ {roastery.rating.value.toLocaleString("fa-IR")} از {roastery.rating.count.toLocaleString("fa-IR")} نظر</span> : null}
                    {roastery.preparationTime ? <span>آماده‌سازی {roastery.preparationTime.minHours.toLocaleString("fa-IR")} تا {roastery.preparationTime.maxHours.toLocaleString("fa-IR")} ساعت</span> : null}
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div className="grid gap-5 border-t border-[color:var(--mid)] p-6 sm:p-8 lg:grid-cols-[1fr_18rem]">
            <div>
              <h2 className="font-bold">درباره روستری</h2>
              <p className="mt-3 whitespace-pre-line text-sm leading-8 text-[color:var(--light)]">{roastery.description}</p>
            </div>
            {roastery.shippingPolicy ? (
              <aside className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
                <h2 className="text-sm font-bold">سیاست ارسال</h2>
                <p className="mt-2 text-xs leading-7 text-[color:var(--light)]">{roastery.shippingPolicy}</p>
              </aside>
            ) : null}
          </div>
        </section>

        <section className="mt-12">
          <div className="flex items-end justify-between gap-4">
            <div>
              <p className="text-xs font-bold text-[color:var(--roast)]">دانه‌های موجود</p>
              <h2 className="mt-1 text-2xl font-bold">محصولات {roastery.name}</h2>
            </div>
            {productsQuery.data?.meta?.total !== undefined ? <span className="text-sm text-[color:var(--light)]">{productsQuery.data.meta.total?.toLocaleString("fa-IR")} محصول</span> : null}
          </div>

          {productsQuery.isPending ? (
            <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {Array.from({ length: 3 }, (_, index) => <div key={index} className="h-[25rem] animate-pulse rounded-2xl bg-[color:var(--dark)]" />)}
            </div>
          ) : productsQuery.isError ? (
            <div className="mt-6 rounded-2xl border border-red-400/40 p-6 text-center">
              <p className="text-sm text-[color:var(--light)]">محصولات این روستری دریافت نشد.</p>
              <button type="button" onClick={() => productsQuery.refetch()} className="mt-4 text-sm text-[color:var(--roast)]">تلاش مجدد</button>
            </div>
          ) : products.length === 0 ? (
            <div className="mt-6 rounded-2xl border border-dashed border-[color:var(--mid)] p-8 text-center text-sm text-[color:var(--light)]">
              در حال حاضر محصول موجودی از این روستری منتشر نشده است.
            </div>
          ) : (
            <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {products.map((product) => <CatalogProductCard key={product.id} product={product} />)}
            </div>
          )}
        </section>
      </main>
      <Footer />
    </>
  );
}
