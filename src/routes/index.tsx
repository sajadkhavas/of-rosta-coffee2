import { createFileRoute, Link } from "@tanstack/react-router";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { ProductCard } from "@/components/ProductCard";
import { RoasteryCard } from "@/components/RoasteryCard";
import { roasteries, products, faqs } from "@/data/seed";
import { toFa } from "@/lib/persian";

const BASE = "https://rosta.coffee";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "رستا | خرید قهوه تازه مستقیم از روستری" },
      {
        name: "description",
        content:
          "رستا مارکت‌پلیس قهوه ایران — قهوه تازه‌رست از بهترین روستری‌های ایران را مقایسه و سفارش دهید. بدون واسطه، با انتخاب آسیاب.",
      },
      { property: "og:title", content: "رستا | خرید قهوه تازه مستقیم از روستری" },
      {
        property: "og:description",
        content:
          "قهوه تازه‌رست از بهترین روستری‌های ایران. بدون واسطه، با انتخاب آسیاب.",
      },
      { property: "og:type", content: "website" },
      { property: "og:url", content: "/" },
      { property: "og:image", content: `${BASE}/og-home.jpg` },
      { name: "twitter:image", content: `${BASE}/og-home.jpg` },
    ],
    links: [{ rel: "canonical", href: "/" }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "Organization",
          name: "رستا",
          url: BASE,
          logo: `${BASE}/favicon.ico`,
          description: "مارکت‌پلیس قهوه ایران — خرید مستقیم از روستری‌ها",
          sameAs: [],
        }),
      },
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "WebSite",
          name: "رستا",
          url: BASE,
          potentialAction: {
            "@type": "SearchAction",
            target: `${BASE}/products?q={search_term_string}`,
            "query-input": "required name=search_term_string",
          },
        }),
      },
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "FAQPage",
          mainEntity: faqs.map((f) => ({
            "@type": "Question",
            name: f.q,
            acceptedAnswer: { "@type": "Answer", text: f.a },
          })),
        }),
      },
    ],
  }),
  component: HomePage,
});

const featuredRoasteries = roasteries;
const featuredProducts = products.slice(0, 8);

function HomePage() {
  return (
    <>
      <Navbar />
      <main>
        {/* HERO */}
        <section className="relative overflow-hidden">
          <div className="mx-auto max-w-6xl px-4 py-16 md:py-24 text-center">
            <span className="inline-block rounded-full bg-[color:var(--rosta-card)] px-3 py-1 text-xs font-medium text-[color:var(--rosta-accent)]">
              مارکت‌پلیس قهوه ایران
            </span>
            <h1 className="mt-4 text-3xl font-bold leading-tight text-[color:var(--rosta-primary)] md:text-5xl">
              قهوه تازه‌رست، مستقیم از روستری به دست تو
            </h1>
            <p className="mx-auto mt-4 max-w-2xl text-base text-[color:var(--rosta-secondary-text)] md:text-lg">
              مقایسه و خرید مستقیم قهوه تخصصی از بهترین روستری‌های ایران. با انتخاب وزن، نوع آسیاب و مشاهده تاریخ دقیق برشته‌کاری.
            </p>

            <div className="mx-auto mt-6 flex max-w-xl flex-col items-stretch gap-2 sm:flex-row">
              <label htmlFor="hero-search" className="sr-only">جستجوی قهوه</label>
              <input
                id="hero-search"
                type="search"
                placeholder="جستجوی قهوه، خاستگاه یا روستری…"
                className="flex-1 rounded-lg border border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] px-4 py-3 text-sm outline-none focus:border-[color:var(--rosta-accent)]"
              />
              <Link
                to="/roasteries"
                className="rounded-lg bg-[color:var(--rosta-primary)] px-6 py-3 text-sm font-medium text-[color:var(--rosta-bg)] transition hover:bg-[color:var(--rosta-accent)]"
              >
                کشف روستری‌ها
              </Link>
            </div>
          </div>
        </section>

        {/* TRUST BAR */}
        <section aria-label="مزایای رستا" className="border-y border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)]">
          <ul className="mx-auto grid max-w-6xl grid-cols-2 gap-3 px-4 py-6 text-sm md:grid-cols-4">
            {[
              "تازه‌رست",
              "بدون واسطه",
              "انتخاب آسیاب",
              "ارسال سریع",
            ].map((t) => (
              <li key={t} className="flex items-center gap-2 text-[color:var(--rosta-primary)]">
                <span className="grid h-6 w-6 place-items-center rounded-full bg-[color:var(--rosta-accent)] text-xs text-white">✓</span>
                {t}
              </li>
            ))}
          </ul>
        </section>

        {/* FEATURED ROASTERIES */}
        <section className="mx-auto max-w-6xl px-4 py-12">
          <div className="flex items-end justify-between">
            <h2 className="text-2xl font-bold">روستری‌های منتخب</h2>
            <Link to="/roasteries" className="text-sm text-[color:var(--rosta-accent)] hover:underline">
              مشاهده همه ←
            </Link>
          </div>
          <div className="mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4 md:grid md:grid-cols-3 md:overflow-visible lg:grid-cols-5">
            {featuredRoasteries.map((r) => (
              <div key={r.slug} className="min-w-[260px] shrink-0 snap-start md:min-w-0">
                <RoasteryCard roastery={r} />
              </div>
            ))}
          </div>
        </section>

        {/* HOW IT WORKS */}
        <section className="bg-[color:var(--rosta-card)]">
          <div className="mx-auto max-w-6xl px-4 py-12">
            <h2 className="text-center text-2xl font-bold">چطور کار می‌کند؟</h2>
            <ol className="mt-8 grid gap-6 md:grid-cols-3">
              {[
                { icon: "🏪", title: "روستری را انتخاب کن", desc: "از بین بهترین روستری‌های ایران روستری موردعلاقه‌ات را انتخاب کن." },
                { icon: "☕️", title: "قهوه، وزن و آسیاب را انتخاب کن", desc: "با توجه به دستگاه دم‌آوری، نوع آسیاب و وزن دلخواه را انتخاب کن." },
                { icon: "🚚", title: "قهوه تازه به دستت می‌رسد", desc: "روستری پس از سفارش، قهوه را برشته و برایت ارسال می‌کند." },
              ].map((s, i) => (
                <li key={i} className="rounded-xl border border-[color:var(--rosta-border)] bg-[color:var(--rosta-bg)] p-6 text-center">
                  <div aria-hidden className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[color:var(--rosta-primary)] text-2xl text-[color:var(--rosta-bg)]">
                    {s.icon}
                  </div>
                  <h3 className="mt-4 text-lg font-bold">{toFa(i + 1)}. {s.title}</h3>
                  <p className="mt-2 text-sm text-[color:var(--rosta-secondary-text)]">{s.desc}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>

        {/* FEATURED PRODUCTS */}
        <section className="mx-auto max-w-6xl px-4 py-12">
          <div className="flex items-end justify-between">
            <h2 className="text-2xl font-bold">قهوه‌های منتخب</h2>
            <Link to="/products" className="text-sm text-[color:var(--rosta-accent)] hover:underline">
              همه محصولات ←
            </Link>
          </div>
          <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {featuredProducts.map((p) => (
              <ProductCard key={p.slug} product={p} />
            ))}
          </div>
        </section>

        {/* FAQ */}
        <section className="bg-[color:var(--rosta-card)]">
          <div className="mx-auto max-w-3xl px-4 py-12">
            <h2 className="text-center text-2xl font-bold">پرسش‌های پرتکرار</h2>
            <div className="mt-6 space-y-3">
              {faqs.map((f, i) => (
                <details key={i} className="group rounded-xl border border-[color:var(--rosta-border)] bg-[color:var(--rosta-bg)] p-4">
                  <summary className="cursor-pointer list-none text-base font-medium text-[color:var(--rosta-primary)]">
                    {f.q}
                  </summary>
                  <p className="mt-3 text-sm leading-7 text-[color:var(--rosta-secondary-text)]">{f.a}</p>
                </details>
              ))}
            </div>
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
