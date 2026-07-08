import { createFileRoute } from "@tanstack/react-router";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/about")({
  head: () => ({
    meta: [
      { title: "درباره رستا | مارکت‌پلیس قهوه ایران" },
      {
        name: "description",
        content:
          "رستا پلتفرم خرید مستقیم قهوه تازه‌رست از روستری‌های ایران است. ماموریت ما، قهوه‌ای بهتر بدون واسطه برای همه.",
      },
      { property: "og:title", content: "درباره رستا" },
      {
        property: "og:description",
        content: "پلتفرم خرید مستقیم قهوه تازه‌رست از روستری‌های ایران.",
      },
      { property: "og:url", content: "/about" },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: "/about" }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify(
          breadcrumbJsonLd([
            { label: "خانه", to: "/" },
            { label: "درباره ما", to: "/about" },
          ]),
        ),
      },
    ],
  }),
  component: AboutPage,
});

function AboutPage() {
  const steps = [
    { t: "روستری را انتخاب کن", d: "از بین بهترین روستری‌های اسپشیالیتی ایران انتخاب کن." },
    { t: "قهوه، وزن و آسیاب را انتخاب کن", d: "دقیقا آنچه با روش دم‌آوری تو سازگار است." },
    { t: "قهوه تازه به دستت می‌رسد", d: "روستری پس از سفارش، قهوه را برشته و ارسال می‌کند." },
  ];

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-3xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "درباره ما" }]} />

        <article>
          <h1 className="text-4xl font-bold">درباره رستا</h1>
          <p className="mt-4 text-base leading-8 text-[color:var(--rosta-secondary-text)]">
            رستا یک مارکت‌پلیس ایرانی برای خرید مستقیم قهوه تازه‌رست از روستری‌های
            اسپشیالیتی است. ماموریت ما این است که فاصله بین روستری و مصرف‌کننده را
            کوتاه کنیم تا هر فنجان قهوه‌ای که می‌نوشید، طعمی متفاوت داشته باشد.
          </p>

          <section className="mt-10">
            <h2 className="text-2xl font-bold">چطور کار می‌کند؟</h2>
            <ol className="mt-4 space-y-3">
              {steps.map((s, i) => (
                <li key={i} className="rounded-xl border border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] p-4">
                  <div className="flex items-center gap-3">
                    <span className="grid h-8 w-8 place-items-center rounded-full bg-[color:var(--rosta-primary)] text-sm font-bold text-[color:var(--rosta-bg)]">
                      {toFa(i + 1)}
                    </span>
                    <h3 className="text-base font-bold">{s.t}</h3>
                  </div>
                  <p className="mt-2 pr-11 text-sm text-[color:var(--rosta-secondary-text)]">{s.d}</p>
                </li>
              ))}
            </ol>
          </section>

          <section className="mt-10">
            <h2 className="text-2xl font-bold">چرا قهوه تازه؟</h2>
            <p className="mt-3 text-base leading-8 text-[color:var(--rosta-secondary-text)]">
              قهوه پس از برشته‌کاری به سرعت تازگی خود را از دست می‌دهد. بهترین طعم قهوه
              معمولاً بین ۳ تا ۱۴ روز پس از رست است. در رستا تاریخ دقیق رست هر محصول
              نمایش داده می‌شود تا مطمئن باشید همیشه قهوه‌ای تازه می‌نوشید.
            </p>
          </section>
        </article>
      </main>
      <Footer />
    </>
  );
}
