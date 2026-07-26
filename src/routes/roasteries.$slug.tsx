import { useQuery } from "@tanstack/react-query";
import { createFileRoute, notFound } from "@tanstack/react-router";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import { RoasteryGrindingCapability } from "@/components/catalog/RoasteryGrindingCapability";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl, siteConfig } from "@/config/site";
import { productsQueryOptions, roasteryQueryOptions } from "@/lib/api/catalog";
import { isApiError } from "@/lib/api/client";
import { bestMediaUrl } from "@/lib/catalog-format";
import { seoHead } from "@/lib/seo";

export const Route = createFileRoute("/roasteries/$slug")({
  loader: async ({ params, context }) => {
    try {
      const roastery = await context.queryClient.ensureQueryData(roasteryQueryOptions(params.slug));
      await context.queryClient.ensureQueryData(
        productsQueryOptions({
          roastery: [params.slug],
          available: true,
          page: 1,
          perPage: 24,
        }),
      );
      return roastery;
    } catch (error) {
      if (isApiError(error) && error.status === 404) throw notFound();
      throw error;
    }
  },
  head: ({ loaderData }) => {
    const roastery = loaderData;
    if (!roastery) return {};
    const cover = bestMediaUrl(roastery.cover);
    const logo = bestMediaUrl(roastery.logo);
    const path = `/roasteries/${roastery.slug}`;

    return seoHead({
      title: `${roastery.name} | محصولات و معرفی روستری در رستا`,
      description:
        roastery.description ||
        `مشاهده محصولات، زمان آماده‌سازی و سیاست ارسال ${roastery.name} در رستا.`,
      path,
      index: siteConfig.allowIndexing && roastery.isVerified,
      image: cover || logo,
      jsonLd: [
        {
          "@context": "https://schema.org",
          "@type": "Organization",
          name: roastery.name,
          description: roastery.description,
          url: absoluteUrl(path),
          logo: logo || undefined,
          image: cover || undefined,
          address: roastery.city
            ? {
                "@type": "PostalAddress",
                addressLocality: roastery.city,
                addressCountry: "IR",
              }
            : undefined,
          aggregateRating: roastery.rating?.count
            ? {
                "@type": "AggregateRating",
                ratingValue: roastery.rating.value,
                ratingCount: roastery.rating.count,
              }
            : undefined,
        },
        breadcrumbJsonLd([
          { label: "خانه", to: "/" },
          { label: "روستری‌ها", to: "/roasteries" },
          { label: roastery.name, to: path },
        ]),
      ],
    });
  },
  component: RoasteryPage,
});

function RoasteryPage() {
  const { slug } = Route.useParams();
  const roastery = Route.useLoaderData();
  const productsQuery = useQuery(
    productsQueryOptions({
      roastery: [slug],
      available: true,
      page: 1,
      perPage: 24,
    }),
  );

  const cover = bestMediaUrl(roastery.cover);
  const logo = bestMediaUrl(roastery.logo);
  const products = productsQuery.data?.items ?? [];

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "روستری‌ها", to: "/roasteries" },
            { label: roastery.name },
          ]}
        />

        <section className="relative mt-5 overflow-hidden rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)]">
          <div className="relative min-h-56">
            {cover ? (
              <img
                src={cover}
                alt={`کاور ${roastery.name}`}
                className="absolute inset-0 h-full w-full object-cover opacity-55"
                fetchPriority="high"
              />
            ) : null}
            <div className="absolute inset-0 bg-gradient-to-t from-[color:var(--night)] via-[color:var(--night)]/65 to-transparent" />
            <div className="relative flex min-h-56 flex-col justify-end p-6 sm:p-8">
              <div className="flex flex-col gap-5 sm:flex-row sm:items-end">
                {logo ? (
                  <img
                    src={logo}
                    alt={`لوگوی ${roastery.name}`}
                    className="size-24 rounded-2xl border-4 border-[color:var(--dark)] bg-white object-cover"
                  />
                ) : (
                  <div className="grid size-24 place-items-center rounded-2xl border border-[color:var(--roast)] bg-[color:var(--dark)] text-2xl font-bold text-[color:var(--roast)]">
                    {roastery.name.slice(0, 2)}
                  </div>
                )}
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-3xl font-bold sm:text-4xl">{roastery.name}</h1>
                    {roastery.isVerified ? (
                      <span className="rounded-full bg-[color:var(--roast)] px-3 py-1 text-xs font-bold text-[color:var(--night)]">
                        تأییدشده
                      </span>
                    ) : null}
                  </div>
                  <div className="mt-3 flex flex-wrap gap-3 text-sm text-[color:var(--light)]">
                    {roastery.city ? <span>📍 {roastery.city}</span> : null}
                    {roastery.rating ? (
                      <span>
                        ★ {roastery.rating.value.toLocaleString("fa-IR")} از{" "}
                        {roastery.rating.count.toLocaleString("fa-IR")} نظر
                      </span>
                    ) : null}
                    {roastery.preparationTime ? (
                      <span>
                        آماده‌سازی {roastery.preparationTime.minHours.toLocaleString("fa-IR")} تا{" "}
                        {roastery.preparationTime.maxHours.toLocaleString("fa-IR")} ساعت
                      </span>
                    ) : null}
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div className="grid gap-5 border-t border-[color:var(--mid)] p-6 sm:p-8 lg:grid-cols-[1fr_18rem]">
            <div>
              <h2 className="font-bold">درباره روستری</h2>
              <p className="mt-3 whitespace-pre-line text-sm leading-8 text-[color:var(--light)]">
                {roastery.description}
              </p>
            </div>
            <div className="grid content-start gap-4">
              <RoasteryGrindingCapability roasterySlug={roastery.slug} />
              {roastery.shippingPolicy ? (
                <aside className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
                  <h2 className="text-sm font-bold">سیاست ارسال</h2>
                  <p className="mt-2 text-xs leading-7 text-[color:var(--light)]">
                    {roastery.shippingPolicy}
                  </p>
                </aside>
              ) : null}
            </div>
          </div>
        </section>

        <section className="mt-12">
          <div className="flex items-end justify-between gap-4">
            <div>
              <p className="text-xs font-bold text-[color:var(--roast)]">دانه‌های موجود</p>
              <h2 className="mt-1 text-2xl font-bold">محصولات {roastery.name}</h2>
            </div>
            {productsQuery.data?.meta?.total !== undefined ? (
              <span className="text-sm text-[color:var(--light)]">
                {productsQuery.data.meta.total.toLocaleString("fa-IR")} محصول
              </span>
            ) : null}
          </div>

          {productsQuery.isPending ? (
            <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {Array.from({ length: 3 }, (_, index) => (
                <div
                  key={index}
                  className="h-[25rem] animate-pulse rounded-2xl bg-[color:var(--dark)]"
                />
              ))}
            </div>
          ) : productsQuery.isError ? (
            <div className="mt-6 rounded-2xl border border-red-400/40 p-6 text-center">
              <p className="text-sm text-[color:var(--light)]">محصولات این روستری دریافت نشد.</p>
              <button
                type="button"
                onClick={() => productsQuery.refetch()}
                className="mt-4 text-sm text-[color:var(--roast)]"
              >
                تلاش مجدد
              </button>
            </div>
          ) : products.length === 0 ? (
            <div className="mt-6 rounded-2xl border border-dashed border-[color:var(--mid)] p-8 text-center text-sm text-[color:var(--light)]">
              در حال حاضر محصول موجودی از این روستری منتشر نشده است.
            </div>
          ) : (
            <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {products.map((product) => (
                <CatalogProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
        </section>
      </main>
      <Footer />
    </>
  );
}
