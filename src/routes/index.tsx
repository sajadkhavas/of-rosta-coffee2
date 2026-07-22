import { createFileRoute, Link } from "@tanstack/react-router";
import { Footer } from "@/components/Footer";
import HeroBean from "@/components/HeroBean";
import { Navbar } from "@/components/Navbar";
import { ProductCard } from "@/components/ProductCard";
import { RoasteryCard } from "@/components/RoasteryCard";
import { absoluteUrl } from "@/config/site";
import { faqs, products, roasteries } from "@/data/seed";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "رستا | خرید قهوه تازه مستقیم از روستری" },
      {
        name: "description",
        content:
          "رستا مارکت‌پلیس قهوه ایران — دانه قهوه تازه‌رست از بهترین روستری‌های ایران را مقایسه و سفارش دهید. بدون واسطه، همیشه دانه کامل برای حفظ تازگی.",
      },
      {
        property: "og:title",
        content: "رستا | خرید قهوه تازه مستقیم از روستری",
      },
      {
        property: "og:description",
        content:
          "دانه قهوه تازه‌رست از بهترین روستری‌های ایران. بدون واسطه، دانه کامل برای حفظ تازگی.",
      },
      { property: "og:type", content: "website" },
      { property: "og:url", content: absoluteUrl("/") },
      { property: "og:image", content: absoluteUrl("/og-home.jpg") },
      { name: "twitter:image", content: absoluteUrl("/og-home.jpg") },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/") }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "Organization",
          name: "رستا",
          url: absoluteUrl("/"),
          logo: absoluteUrl("/favicon.ico"),
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
          url: absoluteUrl("/"),
          potentialAction: {
            "@type": "SearchAction",
            target: absoluteUrl("/products?q={search_term_string}"),
            "query-input": "required name=search_term_string",
          },
        }),
      },
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "FAQPage",
          mainEntity: faqs.map((faq) => ({
            "@type": "Question",
            name: faq.q,
            acceptedAnswer: { "@type": "Answer", text: faq.a },
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
        <section
          id="hero-section"
          className="relative min-h-screen overflow-hidden border-b border-[color:var(--mid)]"
        >
          <div
            aria-hidden
            className="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(200,150,90,0.18),transparent_60%)]"
          />
          <div className="relative mx-auto grid max-w-6xl gap-10 px-4 py-20 md:min-h-screen md:grid-cols-2 md:items-center md:py-28">
            <div className="text-center md:text-right">
              <span className="hero-tag" data-fade-up>
                <span className="h-1.5 w-1.5 rounded-full bg-[color:var(--roast)]" />
                تازه از مزرعه ☕
              </span>
              <h1
                data-split-text
                className="mt-6 font-display text-5xl font-black leading-[1.05] text-[color:var(--steam)] md:text-7xl"
              >
                قهوه‌ای که زنده است
              </h1>
              <p
                data-fade-up
                className="mx-auto mt-6 max-w-lg text-base leading-8 text-[color:var(--light)] md:mx-0 md:text-lg"
              >
                مستقیم از روستری به دست تو — دانه کامل، با تاریخ دقیق برشته‌کاری
                و ارسال سریع.
              </p>

              <div
                data-fade-up
                className="mx-auto mt-10 flex flex-wrap items-center justify-center gap-6 md:mx-0 md:justify-start"
              >
                <Link to="/roasteries" data-magnetic className="btn-primary">
                  کشف روستری‌ها
                  <span aria-hidden>←</span>
                </Link>
                <Link to="/products" className="btn-ghost">
                  قهوه‌ات رو پیدا کن
                  <span aria-hidden>←</span>
                </Link>
              </div>

              <dl className="mt-14 grid grid-cols-3 gap-4 border-t border-[color:var(--mid)] pt-8">
                {[
                  { n: roasteries.length, l: "روستری فعال", suffix: "+" },
                  { n: products.length, l: "محصول تازه", suffix: "+" },
                  { n: 24, l: "رست تا ارسال", suffix: "h" },
                ].map((stat) => (
                  <div key={stat.l} className="text-center md:text-right">
                    <dt
                      className="font-mono-num text-3xl font-bold text-[color:var(--roast)] md:text-5xl"
                      data-counter={stat.n}
                      data-suffix={stat.suffix}
                    >
                      {toFa(0)}
                    </dt>
                    <dd className="mt-2 text-[11px] tracking-[0.2em] text-[color:var(--muted-gold)]">
                      {stat.l}
                    </dd>
                  </div>
                ))}
              </dl>
            </div>

            <div className="relative flex min-h-[340px] items-center justify-center md:min-h-[520px]">
              <div
                aria-hidden
                className="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(200,150,90,0.25),transparent_60%)]"
              />
              <HeroBean />
            </div>
          </div>

          <div
            aria-hidden
            className="pointer-events-none absolute bottom-6 left-1/2 hidden -translate-x-1/2 flex-col items-center gap-3 md:flex"
          >
            <span className="text-[10px] tracking-[0.4em] text-[color:var(--muted-gold)]">
              اسکرول کن
            </span>
            <span className="scroll-line" />
          </div>
        </section>

        <section
          aria-label="مزایای رستا"
          className="border-b border-[color:var(--mid)] bg-[color:var(--dark)]"
        >
          <ul className="mx-auto grid max-w-6xl grid-cols-2 gap-3 px-4 py-6 text-sm md:grid-cols-4">
            {["تازه‌رست", "بدون واسطه", "دانه کامل", "ارسال سریع"].map(
              (item) => (
                <li
                  key={item}
                  className="flex items-center gap-3 text-[color:var(--light)]"
                >
                  <span className="grid h-7 w-7 place-items-center rounded-full border border-[color:var(--roast)] text-xs text-[color:var(--roast)]">
                    ✓
                  </span>
                  {item}
                </li>
              ),
            )}
          </ul>
        </section>

        <section className="mx-auto max-w-6xl px-4 py-20">
          <div className="mb-10 flex items-end justify-between gap-4">
            <div>
              <span className="eyebrow">انتخاب سردبیر</span>
              <h2 className="mt-3 font-display text-3xl font-bold leading-tight text-[color:var(--steam)] md:text-5xl">
                روستری‌های
                <br />
                <span className="text-[color:var(--roast)]">منتخب ایران</span>
              </h2>
            </div>
            <Link
              to="/roasteries"
              className="shrink-0 text-sm font-bold text-[color:var(--roast)] hover:underline"
            >
              مشاهده همه ←
            </Link>
          </div>
          <div className="flex snap-x snap-mandatory gap-5 overflow-x-auto pb-4 md:grid md:grid-cols-3 md:overflow-visible lg:grid-cols-3">
            {featuredRoasteries.slice(0, 3).map((roastery) => (
              <div
                key={roastery.slug}
                className="min-w-[280px] shrink-0 snap-start md:min-w-0"
              >
                <RoasteryCard roastery={roastery} />
              </div>
            ))}
          </div>
        </section>

        <section className="border-y border-[color:var(--mid)] bg-[color:var(--dark)]">
          <div className="mx-auto max-w-6xl px-4 py-20">
            <div className="text-center">
              <span className="eyebrow">فرایند</span>
              <h2 className="mt-3 font-display text-3xl font-bold leading-tight text-[color:var(--steam)] md:text-5xl">
                از دانه تا فنجان
                <br />
                <span className="text-[color:var(--roast)]">در سه گام</span>
              </h2>
            </div>
            <ol className="mt-14 grid gap-6 md:grid-cols-3">
              {[
                {
                  icon: "🏪",
                  title: "روستری را انتخاب کن",
                  desc: "از بین بهترین روستری‌های ایران روستری موردعلاقه‌ات را انتخاب کن.",
                },
                {
                  icon: "☕️",
                  title: "قهوه و وزن دلخواه‌ت را انتخاب کن",
                  desc: "همه محصولات به‌صورت دانه کامل ارسال می‌شوند تا بیشترین تازگی و عطر حفظ شود.",
                },
                {
                  icon: "🚚",
                  title: "قهوه تازه به دستت می‌رسد",
                  desc: "روستری پس از سفارش، قهوه را برشته و برایت ارسال می‌کند.",
                },
              ].map((step, index) => (
                <li
                  key={step.title}
                  className="card-dark card-dark-hover rounded-2xl p-8 text-center"
                >
                  <div className="font-mono-num text-xs tracking-[0.3em] text-[color:var(--roast)]">
                    ۰{toFa(index + 1)}
                  </div>
                  <div
                    aria-hidden
                    className="mx-auto mt-4 grid h-16 w-16 place-items-center rounded-full border border-[color:var(--roast)] bg-[color:var(--night)] text-3xl"
                  >
                    {step.icon}
                  </div>
                  <h3 className="mt-5 font-display text-xl font-bold text-[color:var(--steam)]">
                    {step.title}
                  </h3>
                  <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
                    {step.desc}
                  </p>
                </li>
              ))}
            </ol>
          </div>
        </section>

        <section className="mx-auto max-w-6xl px-4 py-20">
          <div className="mb-10 flex items-end justify-between gap-4">
            <div>
              <span className="eyebrow">تازه‌ترین‌ها</span>
              <h2 className="mt-3 font-display text-3xl font-bold leading-tight text-[color:var(--steam)] md:text-5xl">
                قهوه‌های
                <br />
                <span className="text-[color:var(--roast)]">منتخب هفته</span>
              </h2>
            </div>
            <Link
              to="/products"
              className="shrink-0 text-sm font-bold text-[color:var(--roast)] hover:underline"
            >
              همه محصولات ←
            </Link>
          </div>
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {featuredProducts.map((product) => (
              <ProductCard key={product.slug} product={product} />
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-6xl px-4 pb-20">
          <div className="relative overflow-hidden rounded-3xl border border-[color:var(--mid)] bg-gradient-to-br from-[#1a0a00] via-[#2a1405] to-[#0a0400] px-8 py-16 text-center">
            <div
              aria-hidden
              className="pointer-events-none absolute -right-6 -top-8 select-none text-[10rem] leading-none opacity-[0.06]"
            >
              ☕
            </div>
            <div
              aria-hidden
              className="pointer-events-none absolute -bottom-16 -left-8 select-none text-[14rem] leading-none opacity-[0.05]"
            >
              ☕
            </div>
            <span className="eyebrow relative">راهنمای انتخاب</span>
            <h2 className="relative mt-4 font-display text-3xl font-bold leading-tight text-[color:var(--steam)] md:text-5xl">
              نمی‌دانی کدام قهوه
              <br />
              <span className="text-[color:var(--roast)]">مناسب توست؟</span>
            </h2>
            <p className="relative mx-auto mt-5 max-w-xl text-sm leading-7 text-[color:var(--light)] md:text-base">
              با پاسخ به چند سؤال ساده، ذائقه‌ات را می‌شناسیم و بهترین قهوه را
              پیشنهاد می‌دهیم.
            </p>
            <Link
              to="/quiz"
              className="relative mt-8 inline-block rounded-lg bg-[color:var(--roast)] px-8 py-3 text-sm font-bold text-[color:var(--night)] transition hover:brightness-110"
            >
              شروع کوییز ذائقه
            </Link>
          </div>
        </section>

        <section className="border-t border-[color:var(--mid)] bg-[color:var(--dark)]">
          <div className="mx-auto max-w-3xl px-4 py-20">
            <div className="text-center">
              <span className="eyebrow">پرسش‌ها</span>
              <h2 className="mt-3 font-display text-3xl font-bold leading-tight text-[color:var(--steam)] md:text-5xl">
                پرسش‌های
                <br />
                <span className="text-[color:var(--roast)]">پرتکرار</span>
              </h2>
            </div>
            <div className="mt-10 space-y-3">
              {faqs.map((faq) => (
                <details
                  key={faq.q}
                  className="group rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-5 transition hover:border-[color:var(--roast)]"
                >
                  <summary className="cursor-pointer list-none text-base font-medium text-[color:var(--steam)]">
                    {faq.q}
                  </summary>
                  <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
                    {faq.a}
                  </p>
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
