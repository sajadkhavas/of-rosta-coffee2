import { createFileRoute, notFound, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { ProductCard } from "@/components/ProductCard";
import { RoastDateBadge } from "@/components/RoastDateBadge";
import { RoastLevelBadge } from "@/components/RoastLevelBadge";
import { WeightSelector } from "@/components/WeightSelector";
import { GrindSelector } from "@/components/GrindSelector";
import {
  getProduct,
  getRoastery,
  productsByRoastery,
  type Grind,
  type Weight,
} from "@/data/seed";
import { formatToman, toFa } from "@/lib/persian";

const BASE = "https://rosta.coffee";

export const Route = createFileRoute("/products/$slug")({
  loader: ({ params }) => {
    const product = getProduct(params.slug);
    if (!product) throw notFound();
    const roastery = getRoastery(product.roasterySlug)!;
    const related = productsByRoastery(product.roasterySlug).filter((p) => p.slug !== product.slug);
    return { product, roastery, related };
  },
  head: ({ params, loaderData }) => {
    if (!loaderData) {
      return {
        meta: [
          { title: "محصول پیدا نشد | رستا" },
          { name: "robots", content: "noindex" },
        ],
      };
    }
    const { product, roastery } = loaderData;
    const title = `${product.name} از ${roastery.name} | رستا`;
    const description = `خرید ${product.name} رست ${toFa(product.roastDaysAgo)} روز پیش از روستری ${roastery.name}. خاستگاه: ${product.origin}. ${toFa(product.arabicaPct)}٪ عربیکا. ارسال سریع.`;
    const url = `/products/${params.slug}`;
    return {
      meta: [
        { title },
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:url", content: url },
        { property: "og:type", content: "product" },
      ],
      links: [{ rel: "canonical", href: url }],
      scripts: [
        {
          type: "application/ld+json",
          children: JSON.stringify({
            "@context": "https://schema.org",
            "@type": "Product",
            name: product.name,
            description: product.description,
            brand: { "@type": "Brand", name: roastery.name },
            offers: {
              "@type": "Offer",
              price: product.prices[250],
              priceCurrency: "IRR",
              availability: "https://schema.org/InStock",
              url: `${BASE}${url}`,
            },
            aggregateRating: {
              "@type": "AggregateRating",
              ratingValue: roastery.rating,
              reviewCount: 24,
            },
          }),
        },
        {
          type: "application/ld+json",
          children: JSON.stringify(
            breadcrumbJsonLd([
              { label: "خانه", to: "/" },
              { label: "محصولات", to: "/products" },
              { label: product.name, to: url },
            ]),
          ),
        },
      ],
    };
  },
  component: ProductPage,
});

function ProductPage() {
  const { product, roastery, related } = Route.useLoaderData();
  const [weight, setWeight] = useState<Weight>(250);
  const [grind, setGrind] = useState<Grind>("دانه");

  const price = useMemo(() => product.prices[weight], [product, weight]);

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "محصولات", to: "/products" },
            { label: product.name },
          ]}
        />

        <article className="grid gap-8 md:grid-cols-2">
          {/* Visual */}
          <div
            className="flex aspect-square items-center justify-center rounded-xl border border-[color:var(--rosta-border)] p-8 text-8xl"
            style={{
              background: `linear-gradient(135deg, ${roastery.color}22, var(--rosta-card))`,
            }}
            aria-label={`تصویر ${product.name}`}
          >
            <span aria-hidden>☕</span>
          </div>

          {/* Info */}
          <div>
            <div className="flex items-center justify-between text-sm">
              <Link
                to="/roasteries/$slug"
                params={{ slug: roastery.slug }}
                className="text-[color:var(--rosta-accent)] hover:underline"
              >
                {roastery.name}
              </Link>
              <RoastDateBadge daysAgo={product.roastDaysAgo} />
            </div>
            <h1 className="mt-2 text-3xl font-bold">{product.name}</h1>

            <ul className="mt-3 flex flex-wrap gap-1.5">
              <li className="inline-flex items-center gap-1 rounded-full bg-[color:var(--rosta-card)] px-2.5 py-0.5 text-xs">
                <span aria-hidden>{product.originFlag}</span> {product.origin}
              </li>
              <li><RoastLevelBadge level={product.roastLevel} /></li>
              <li className="rounded-full bg-[color:var(--rosta-card)] px-2.5 py-0.5 text-xs">
                {toFa(product.arabicaPct)}٪ عربیکا
              </li>
              <li className="rounded-full bg-[color:var(--rosta-card)] px-2.5 py-0.5 text-xs">
                فرآوری: {product.processing}
              </li>
            </ul>

            <p className="mt-4 text-sm leading-7 text-[color:var(--rosta-secondary-text)]">
              {product.description}
            </p>

            <section className="mt-6">
              <h2 className="text-xs font-bold text-[color:var(--rosta-secondary-text)]">نت‌های چشایی</h2>
              <ul className="mt-2 flex flex-wrap gap-1.5">
                {product.tastingNotes.map((n) => (
                  <li key={n} className="rounded-full border border-[color:var(--rosta-accent)] bg-[color:var(--rosta-bg)] px-3 py-1 text-xs text-[color:var(--rosta-accent)]">
                    {n}
                  </li>
                ))}
              </ul>
            </section>

            <section className="mt-6">
              <h2 className="mb-2 text-xs font-bold text-[color:var(--rosta-secondary-text)]">وزن</h2>
              <WeightSelector value={weight} onChange={setWeight} />
            </section>

            <section className="mt-4">
              <h2 className="mb-2 text-xs font-bold text-[color:var(--rosta-secondary-text)]">آسیاب</h2>
              <GrindSelector value={grind} onChange={setGrind} />
            </section>

            <div className="mt-6 rounded-xl border border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] p-4">
              <div className="flex items-center justify-between">
                <span className="text-sm text-[color:var(--rosta-secondary-text)]">قیمت</span>
                <span className="text-2xl font-bold text-[color:var(--rosta-primary)]">
                  {formatToman(price)}
                </span>
              </div>
              <button
                type="button"
                className="mt-3 w-full rounded-lg bg-[color:var(--rosta-primary)] py-3 text-sm font-medium text-[color:var(--rosta-bg)] transition hover:bg-[color:var(--rosta-accent)]"
              >
                افزودن به سبد
              </button>
            </div>
          </div>
        </article>

        {/* Roastery card */}
        <section className="mt-12">
          <h2 className="text-xl font-bold">درباره روستری</h2>
          <Link
            to="/roasteries/$slug"
            params={{ slug: roastery.slug }}
            className="mt-4 flex items-start gap-4 rounded-xl border border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] p-5 transition hover:border-[color:var(--rosta-accent)]"
          >
            <div
              aria-hidden
              className="grid h-14 w-14 shrink-0 place-items-center rounded-full text-lg font-bold text-white"
              style={{ backgroundColor: roastery.color }}
            >
              {roastery.initials}
            </div>
            <div className="flex-1">
              <div className="text-base font-bold">{roastery.name}</div>
              <div className="mt-1 text-xs text-[color:var(--rosta-secondary-text)]">
                📍 {roastery.city} · ★ {toFa(roastery.rating.toFixed(1))}
              </div>
              <p className="mt-2 text-sm text-[color:var(--rosta-secondary-text)]">{roastery.description}</p>
            </div>
          </Link>
        </section>

        {/* Related products */}
        {related.length > 0 && (
          <section className="mt-12">
            <h2 className="text-xl font-bold">قهوه‌های دیگر از {roastery.name}</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {related.map((p) => (
                <ProductCard key={p.slug} product={p} />
              ))}
            </div>
          </section>
        )}

        {/* Reviews placeholder */}
        <section className="mt-12 rounded-xl border border-dashed border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] p-8 text-center">
          <h2 className="text-xl font-bold">نظرات مشتریان</h2>
          <p className="mt-2 text-sm text-[color:var(--rosta-secondary-text)]">
            بخش نظرات به‌زودی فعال می‌شود.
          </p>
        </section>
      </main>
      <Footer />
    </>
  );
}
