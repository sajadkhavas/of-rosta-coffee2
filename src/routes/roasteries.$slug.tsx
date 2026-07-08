import { createFileRoute, notFound } from "@tanstack/react-router";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { ProductCard } from "@/components/ProductCard";
import { getRoastery, productsByRoastery } from "@/data/seed";
import { toFa } from "@/lib/persian";

const BASE = "https://rosta.coffee";

export const Route = createFileRoute("/roasteries/$slug")({
  loader: ({ params }) => {
    const roastery = getRoastery(params.slug);
    if (!roastery) throw notFound();
    return { roastery, products: productsByRoastery(params.slug) };
  },
  head: ({ params, loaderData }) => {
    if (!loaderData) {
      return {
        meta: [
          { title: "روستری پیدا نشد | رستا" },
          { name: "robots", content: "noindex" },
        ],
      };
    }
    const { roastery, products } = loaderData;
    const title = `روستری ${roastery.name} | خرید قهوه تازه | رستا`;
    const description = `خرید مستقیم قهوه از ${roastery.name} در ${roastery.city}. ${products.length} محصول اسپشیالیتی با رست تازه.`;
    const url = `/roasteries/${params.slug}`;
    return {
      meta: [
        { title },
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:url", content: url },
        { property: "og:type", content: "website" },
      ],
      links: [{ rel: "canonical", href: url }],
      scripts: [
        {
          type: "application/ld+json",
          children: JSON.stringify({
            "@context": "https://schema.org",
            "@type": "LocalBusiness",
            name: roastery.name,
            description: roastery.description,
            address: {
              "@type": "PostalAddress",
              addressLocality: roastery.city,
              addressCountry: "IR",
            },
            aggregateRating: {
              "@type": "AggregateRating",
              ratingValue: roastery.rating,
              reviewCount: Math.max(10, products.length * 8),
            },
            url: `${BASE}${url}`,
          }),
        },
        {
          type: "application/ld+json",
          children: JSON.stringify(
            breadcrumbJsonLd([
              { label: "خانه", to: "/" },
              { label: "روستری‌ها", to: "/roasteries" },
              { label: roastery.name, to: url },
            ]),
          ),
        },
      ],
    };
  },
  component: RoasteryPage,
});

function RoasteryPage() {
  const { roastery, products } = Route.useLoaderData();
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

        {/* Cover */}
        <section
          aria-label="معرفی روستری"
          className="relative overflow-hidden rounded-xl border border-[color:var(--rosta-border)]"
          style={{
            background: `linear-gradient(135deg, ${roastery.color}, var(--rosta-primary))`,
          }}
        >
          <div className="flex flex-col items-start gap-6 p-8 text-[color:var(--rosta-bg)] md:flex-row md:items-center">
            <div
              aria-hidden
              className="grid h-24 w-24 place-items-center rounded-full bg-white/10 text-3xl font-bold ring-4 ring-white/20"
            >
              {roastery.initials}
            </div>
            <div className="flex-1">
              <h1 className="text-3xl font-bold">{roastery.name}</h1>
              <div className="mt-2 flex flex-wrap items-center gap-2 text-sm opacity-90">
                <span>📍 {roastery.city}</span>
                <span>★ {toFa(roastery.rating.toFixed(1))}</span>
                <span>{toFa(products.length)} محصول</span>
              </div>
              <ul className="mt-3 flex flex-wrap gap-1.5">
                {roastery.specialty.map((s: string) => (
                  <li key={s} className="rounded-full bg-white/15 px-2.5 py-0.5 text-xs">
                    {s}
                  </li>
                ))}
              </ul>
              <p className="mt-4 max-w-2xl text-sm leading-7 opacity-95">
                {roastery.description}
              </p>
            </div>
          </div>
        </section>

        {/* Products */}
        <section className="mt-10">
          <h2 className="text-2xl font-bold">محصولات {roastery.name}</h2>
          <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {products.map((p: typeof products[number]) => (
              <ProductCard key={p.slug} product={p} />
            ))}
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
