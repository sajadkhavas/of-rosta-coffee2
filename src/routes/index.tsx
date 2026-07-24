import { createFileRoute, Link } from "@tanstack/react-router";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import { HomeRoasteryCard } from "@/components/catalog/HomeRoasteryCard";
import { EmptyState } from "@/components/system";
import { Footer } from "@/components/Footer";
import HeroBean from "@/components/HeroBean";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl } from "@/config/site";
import { homepageQueryOptions, type HomepageData, type HomeFaq } from "@/lib/api/homepage";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/")({
  loader: ({ context }) => context.queryClient.ensureQueryData(homepageQueryOptions()),
  head: ({ loaderData }) => {
    const faqs: HomeFaq[] = loaderData?.faqs ?? [];
    return {
      meta: [
        { title: "رستا | خرید قهوه تازه مستقیم از روستری" },
        {
          name: "description",
          content: "رستا مارکت‌پلیس دانه کامل قهوه تازه‌رست از روستری‌های تأییدشده ایران؛ با موجودی، قیمت و تاریخ رست زنده.",
        },
        { property: "og:title", content: "رستا | خرید قهوه تازه مستقیم از روستری" },
        { property: "og:description", content: "دانه کامل قهوه تازه‌رست با اطلاعات زنده کاتالوگ و روستری." },
        { property: "og:type", content: "website" },
        { property: "og:url", content: absoluteUrl("/") },
      ],
      links: [{ rel: "canonical", href: absoluteUrl("/") }],
      scripts: [
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
        ...(faqs.length
          ? [{
              type: "application/ld+json",
              children: JSON.stringify({
                "@context": "https://schema.org",
                "@type": "FAQPage",
                mainEntity: faqs.map((faq) => ({
                  "@type": "Question",
                  name: faq.question,
                  acceptedAnswer: { "@type": "Answer", text: faq.answer },
                })),
              }),
            }]
          : []),
      ],
    };
  },
  component: HomePage,
});

function HomePage() {
  const data: HomepageData = Route.useLoaderData();
  return (
    <>
      <Navbar />
      <main>
        <section id="hero-section" className="relative min-h-screen overflow-hidden border-b border-[color:var(--mid)]">
          <div aria-hidden className="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(200,150,90,0.18),transparent_60%)]" />
          <div className="relative mx-auto grid max-w-6xl gap-10 px-4 py-20 md:min-h-screen md:grid-cols-2 md:items-center md:py-28">
            <div className="text-center md:text-right">
              <span className="hero-tag" data-fade-up><span className="h-1.5 w-1.5 rounded-full bg-[color:var(--roast)]" />کاتالوگ زنده روستری‌ها ☕</span>
              <h1 data-split-text className="mt-6 font-display text-5xl font-black leading-[1.05] text-[color:var(--steam)] md:text-7xl">قهوه‌ای که زنده است</h1>
              <p data-fade-up className="mx-auto mt-6 max-w-lg text-base leading-8 text-[color:var(--light)] md:mx-0 md:text-lg">
                مستقیم از روستری به دست تو؛ فقط دانه کامل، با قیمت، موجودی و تاریخ رست واقعی.
              </p>
              <div data-fade-up className="mx-auto mt-10 flex flex-wrap items-center justify-center gap-6 md:mx-0 md:justify-start">
                <Link to="/products" className="btn-primary">مشاهده قهوه‌ها <span aria-hidden>←</span></Link>
                <Link to="/roasteries" className="btn-ghost">کشف روستری‌ها <span aria-hidden>←</span></Link>
              </div>
              <dl className="mt-14 grid grid-cols-3 gap-4 border-t border-[color:var(--mid)] pt-8">
                {[
                  { n: data.roasteryCount, label: "روستری تأییدشده" },
                  { n: data.productCount, label: "محصول زنده" },
                  { n: 5, label: "وزن استاندارد" },
                ].map((stat) => (
                  <div key={stat.label} className="text-center md:text-right">
                    <dt className="font-mono-num text-3xl font-bold text-[color:var(--roast)] md:text-5xl" data-counter={stat.n}>{toFa(stat.n)}</dt>
                    <dd className="mt-2 text-[11px] tracking-[0.1em] text-[color:var(--muted-gold)]">{stat.label}</dd>
                  </div>
                ))}
              </dl>
            </div>
            <div className="relative flex min-h-[340px] items-center justify-center md:min-h-[520px]">
              <div aria-hidden className="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(200,150,90,0.25),transparent_60%)]" />
              <HeroBean />
            </div>
          </div>
        </section>

        <section aria-label="مزایای رستا" className="border-b border-[color:var(--mid)] bg-[color:var(--dark)]">
          <ul className="mx-auto grid max-w-6xl grid-cols-2 gap-3 px-4 py-6 text-sm md:grid-cols-4">
            {["تازه‌رست", "اطلاعات زنده", "فقط دانه کامل", "خرید مستقیم"].map((item) => <li key={item} className="flex items-center gap-3 text-[color:var(--light)]"><span className="grid h-7 w-7 place-items-center rounded-full border border-[color:var(--roast)] text-xs text-[color:var(--roast)]">✓</span>{item}</li>)}
          </ul>
        </section>

        <section className="mx-auto max-w-6xl px-4 py-20">
          <Header eyebrow="روستری‌های زنده" title="روستری‌های تأییدشده" link="/roasteries" linkLabel="مشاهده همه" />
          {data.roasteries.length ? (
            <div className="grid gap-5 md:grid-cols-3">{data.roasteries.map((roastery) => <HomeRoasteryCard key={roastery.id} roastery={roastery} />)}</div>
          ) : (
            <EmptyState title="روستری منتشرشده‌ای وجود ندارد" description="پس از تأیید نخستین روستری، این بخش به‌صورت خودکار به‌روزرسانی می‌شود." />
          )}
        </section>

        <section className="border-y border-[color:var(--mid)] bg-[color:var(--dark)]">
          <div className="mx-auto max-w-6xl px-4 py-20">
            <div className="text-center"><span className="eyebrow">فرایند</span><h2 className="mt-3 font-display text-3xl font-bold text-[color:var(--steam)] md:text-5xl">از روستری تا فنجان</h2></div>
            <ol className="mt-12 grid gap-6 md:grid-cols-3">
              {[
                ["۰۱", "روستری و محصول را انتخاب کن", "فقط موارد تأییدشده و منتشرشده نمایش داده می‌شوند."],
                ["۰۲", "وزن دانه کامل را انتخاب کن", "وزن‌های ثابت ۵۰، ۱۰۰، ۲۵۰، ۵۰۰ و ۱۰۰۰ گرم."],
                ["۰۳", "وضعیت سفارش را زنده پیگیری کن", "پذیرش، آماده‌سازی، ارسال و تحویل از Backend معتبر."],
              ].map(([number, title, description]) => <li key={number} className="card-dark rounded-2xl p-7"><span className="font-mono-num text-[color:var(--roast)]">{number}</span><h3 className="mt-4 text-lg font-bold">{title}</h3><p className="mt-3 text-sm leading-7 text-[color:var(--light)]">{description}</p></li>)}
            </ol>
          </div>
        </section>

        <section className="mx-auto max-w-6xl px-4 py-20">
          <Header eyebrow="تازه‌ترین‌ها" title="محصولات منتشرشده" link="/products" linkLabel="همه محصولات" />
          {data.products.length ? (
            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{data.products.map((product) => <CatalogProductCard key={product.id} product={product} />)}</div>
          ) : (
            <EmptyState title="محصول منتشرشده‌ای وجود ندارد" description="رستا در Production هیچ محصول Seed یا ساختگی نمایش نمی‌دهد." />
          )}
        </section>

        <section className="mx-auto max-w-6xl px-4 pb-20">
          <div className="rounded-3xl border border-[color:var(--mid)] bg-gradient-to-br from-[#1a0a00] via-[#2a1405] to-[#0a0400] px-8 py-14 text-center">
            <span className="eyebrow">پیشنهاد زنده</span>
            <h2 className="mt-4 font-display text-3xl font-bold text-[color:var(--steam)] md:text-5xl">قهوه مناسب ذائقه‌ات را پیدا کن</h2>
            <p className="mx-auto mt-4 max-w-xl text-sm leading-7 text-[color:var(--light)]">کوییز فقط بین محصولات منتشرشده و موجود API جست‌وجو می‌کند.</p>
            <Link to="/quiz" className="mt-7 inline-flex rounded-xl bg-[color:var(--roast)] px-8 py-3 text-sm font-bold text-[color:var(--night)]">شروع کوییز</Link>
          </div>
        </section>

        {data.faqs.length ? (
          <section className="border-t border-[color:var(--mid)] bg-[color:var(--dark)]">
            <div className="mx-auto max-w-3xl px-4 py-20">
              <div className="text-center"><span className="eyebrow">پرسش‌ها</span><h2 className="mt-3 font-display text-3xl font-bold text-[color:var(--steam)]">پرسش‌های پرتکرار</h2></div>
              <div className="mt-10 space-y-3">{data.faqs.map((faq) => <details key={faq.question} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-5"><summary className="cursor-pointer font-medium">{faq.question}</summary><p className="mt-3 text-sm leading-7 text-[color:var(--light)]">{faq.answer}</p></details>)}</div>
            </div>
          </section>
        ) : null}
      </main>
      <Footer />
    </>
  );
}

function Header({ eyebrow, title, link, linkLabel }: { eyebrow: string; title: string; link: "/products" | "/roasteries"; linkLabel: string }) {
  return <div className="mb-10 flex items-end justify-between gap-4"><div><span className="eyebrow">{eyebrow}</span><h2 className="mt-3 font-display text-3xl font-bold text-[color:var(--steam)] md:text-5xl">{title}</h2></div><Link to={link} className="text-sm font-bold text-[color:var(--roast)]">{linkLabel} ←</Link></div>;
}
