import { createFileRoute, notFound, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { ChevronDown } from "lucide-react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { ProductCard } from "@/components/ProductCard";
import { RoastDateBadge } from "@/components/RoastDateBadge";
import { RoastLevelBadge } from "@/components/RoastLevelBadge";
import { WeightSelector } from "@/components/WeightSelector";
import {
  getProduct,
  getRoastery,
  products,
  productsByRoastery,
  type Weight,
} from "@/data/seed";
import { formatToman, toFa } from "@/lib/persian";
import { productImage, productThumbnails } from "@/lib/product-images";
import { useCart } from "@/lib/cart-context";

const BASE = "https://rosta.coffee";

export const Route = createFileRoute("/products/$slug")({
  loader: ({ params }) => {
    const product = getProduct(params.slug);
    if (!product) throw notFound();
    const roastery = getRoastery(product.roasterySlug)!;
    const related = productsByRoastery(product.roasterySlug).filter(
      (p) => p.slug !== product.slug,
    );
    const similarByOrigin = products
      .filter(
        (p) => p.origin === product.origin && p.roasterySlug !== product.roasterySlug,
      )
      .slice(0, 4);
    return { product, roastery, related, similarByOrigin };
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
    const title = `${product.name} از ${roastery.name} — خرید آنلاین | رستا`;
    const description = `خرید ${product.name}، تک‌خاستگاه ${product.origin}، فرآوری ${product.processing}. رست ${toFa(product.roastDaysAgo)} روز پیش توسط ${roastery.name}. ارسال سریع سراسر ایران.`;
    const url = `/products/${params.slug}`;
    const image = productImage(product.slug, 1200);
    return {
      meta: [
        { title },
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:url", content: url },
        { property: "og:type", content: "product" },
        { property: "og:image", content: image },
        { name: "twitter:image", content: image },
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
            image: [image],
            brand: { "@type": "Brand", name: roastery.name },
            additionalProperty: [
              { "@type": "PropertyValue", name: "خاستگاه", value: product.origin },
              { "@type": "PropertyValue", name: "سطح رست", value: product.roastLevel },
              { "@type": "PropertyValue", name: "درصد عربیکا", value: `${product.arabicaPct}%` },
              { "@type": "PropertyValue", name: "فرآوری", value: product.processing },
            ],
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
            review: [],
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
        {
          type: "application/ld+json",
          children: JSON.stringify({
            "@context": "https://schema.org",
            "@type": "FAQPage",
            mainEntity: buildFaq(product).map((f) => ({
              "@type": "Question",
              name: f.q,
              acceptedAnswer: { "@type": "Answer", text: f.a },
            })),
          }),
        },
      ],
    };
  },
  component: ProductPage,
});

function buildFaq(product: {
  origin: string;
  processing: string;
  arabicaPct: number;
}) {
  return [
    {
      q: "این قهوه به چه شکل ارسال می‌شود؟",
      a: `این قهوه به‌صورت دانه کامل ارسال می‌شود. برای بهترین طعم، توصیه می‌کنیم قهوه را نزدیک به زمان مصرف آسیاب کنید تا رایحه و طعم آن حفظ شود.`,
    },
    {
      q: "چرا قیمت این قهوه با محصولات دیگر فرق دارد؟",
      a: `این قهوه تک‌خاستگاه از ${product.origin} با فرآوری ${product.processing} و ${toFa(product.arabicaPct)}٪ عربیکاست که هزینه تولید بالاتری نسبت به قهوه‌های ترکیبی دارد.`,
    },
  ];
}

function Accordion({
  title,
  children,
  defaultOpen = false,
}: {
  title: string;
  children: React.ReactNode;
  defaultOpen?: boolean;
}) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)]">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex w-full items-center justify-between px-4 py-3 text-sm font-bold text-[color:var(--steam)]"
      >
        {title}
        <ChevronDown
          size={16}
          className={`transition-transform ${open ? "rotate-180" : ""}`}
        />
      </button>
      {open && (
        <div className="border-t border-[color:var(--mid)] px-4 py-3 text-sm leading-7 text-[color:var(--light)]">
          {children}
        </div>
      )}
    </div>
  );
}

function ProductPage() {
  const { product, roastery, related, similarByOrigin } = Route.useLoaderData();
  const [weight, setWeight] = useState<Weight>(250);
  const [grind, setGrind] = useState<Grind>("دانه");
  const [mainImg, setMainImg] = useState(productImage(product.slug, 1200));
  const thumbs = productThumbnails(product.slug, 200);
  const price = useMemo(() => product.prices[weight], [product, weight]);
  const faq = buildFaq(product);
  const inStock = true;
  const { addItem } = useCart();
  const [added, setAdded] = useState(false);
  const handleAdd = () => {
    addItem(product.slug, weight, grind, 1);
    setAdded(true);
    window.setTimeout(() => setAdded(false), 1500);
  };

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
          {/* Gallery */}
          <div>
            <div className="overflow-hidden rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)]">
              <img
                src={mainImg}
                alt={`${product.name} — قهوه ${product.origin} از ${roastery.name}`}
                width={800}
                height={800}
                loading="eager"
                fetchPriority="high"
                className="h-full w-full object-cover"
              />
            </div>
            <div className="mt-3 grid grid-cols-3 gap-3">
              {thumbs.map((src, i) => (
                <button
                  key={i}
                  type="button"
                  onClick={() => setMainImg(src.replace("w=200", "w=1200"))}
                  className="overflow-hidden rounded-lg border border-[color:var(--mid)] transition hover:border-[color:var(--roast)]"
                >
                  <img
                    src={src}
                    alt={`${product.name} — نمای ${toFa(i + 1)}`}
                    width={200}
                    height={200}
                    loading="lazy"
                    className="aspect-square h-full w-full object-cover"
                  />
                </button>
              ))}
            </div>
          </div>

          {/* Info */}
          <div>
            <div className="flex items-center justify-between text-sm">
              <Link
                to="/roasteries/$slug"
                params={{ slug: roastery.slug }}
                className="text-[color:var(--roast)] hover:underline"
              >
                {roastery.name}
              </Link>
              <span className="font-mono-num text-[color:var(--roast)]">
                ★ {toFa(roastery.rating.toFixed(1))}
              </span>
            </div>
            <h1 className="mt-2 font-display text-3xl font-bold text-[color:var(--steam)]">
              {product.name}
            </h1>

            <div className="mt-3 flex flex-wrap items-center gap-2">
              <span className="inline-flex items-center gap-1 rounded-full border border-[color:var(--mid)] bg-[color:var(--dark)] px-2.5 py-0.5 text-xs text-[color:var(--light)]">
                <span aria-hidden>{product.originFlag}</span> {product.origin}
              </span>
              <RoastLevelBadge level={product.roastLevel} />
              <RoastDateBadge daysAgo={product.roastDaysAgo} />
              <span className="rounded-full border border-[color:var(--mid)] bg-[color:var(--dark)] px-2.5 py-0.5 text-xs text-[color:var(--light)]">
                {toFa(product.arabicaPct)}٪ عربیکا
              </span>
            </div>

            <div className="mt-4 flex items-baseline justify-between">
              <div className="font-mono-num text-3xl font-bold text-[color:var(--roast)]">
                {formatToman(price)}
              </div>
              <span
                className={`rounded-full px-3 py-1 text-xs font-bold ${
                  inStock
                    ? "bg-emerald-900/30 text-emerald-400"
                    : "bg-amber-900/30 text-amber-400"
                }`}
              >
                {inStock ? "موجود" : "به‌زودی"}
              </span>
            </div>

            <section className="mt-5">
              <h2 className="mb-2 text-xs font-bold text-[color:var(--light)]">وزن</h2>
              <WeightSelector value={weight} onChange={setWeight} />
            </section>

            <section className="mt-4">
              <h2 className="mb-2 text-xs font-bold text-[color:var(--light)]">آسیاب</h2>
              <GrindSelector value={grind} onChange={setGrind} />
            </section>

            <section className="mt-5">
              <h2 className="mb-2 text-xs font-bold text-[color:var(--light)]">نت‌های چشایی</h2>
              <ul className="flex flex-wrap gap-1.5">
                {product.tastingNotes.map((n: string) => (
                  <li
                    key={n}
                    className="rounded-full border border-[color:var(--roast)] bg-[color:var(--night)] px-3 py-1 text-xs text-[color:var(--roast)]"
                  >
                    {n}
                  </li>
                ))}
              </ul>
            </section>

            <button
              type="button"
              onClick={handleAdd}
              className="mt-6 w-full rounded-lg bg-[color:var(--roast)] py-3 text-sm font-bold text-[color:var(--night)] transition hover:opacity-90"
            >
              {added ? "افزوده شد ✓" : "افزودن به سبد خرید"}
            </button>

            <div className="mt-6 space-y-2">
              <Accordion title="توضیحات محصول" defaultOpen>
                {product.description}
              </Accordion>
              <Accordion title="روش فرآوری">
                فرآوری {product.processing}: از روش‌های سنتی تولید قهوه است که مستقیماً بر
                طعم و بدنه فنجان اثر می‌گذارد.
              </Accordion>
              <Accordion title="درباره روستری">
                <div className="flex items-start gap-3">
                  <div
                    aria-hidden
                    className="grid h-10 w-10 shrink-0 place-items-center rounded-full font-bold text-[color:var(--night)]"
                    style={{ backgroundColor: roastery.color }}
                  >
                    {roastery.initials}
                  </div>
                  <div>
                    <div className="font-bold text-[color:var(--steam)]">{roastery.name}</div>
                    <div className="text-xs">📍 {roastery.city}</div>
                    <p className="mt-1">{roastery.description}</p>
                    <Link
                      to="/roasteries/$slug"
                      params={{ slug: roastery.slug }}
                      className="mt-2 inline-block text-xs text-[color:var(--roast)] underline"
                    >
                      مشاهده صفحه روستری
                    </Link>
                  </div>
                </div>
              </Accordion>
            </div>
          </div>
        </article>

        {/* FAQ */}
        <section className="mt-12">
          <h2 className="mb-4 text-xl font-bold text-[color:var(--steam)]">سوالات متداول</h2>
          <div className="space-y-2">
            {faq.map((f) => (
              <Accordion key={f.q} title={f.q}>
                {f.a}
              </Accordion>
            ))}
          </div>
        </section>

        {/* Related from same roastery */}
        {related.length > 0 && (
          <section className="mt-12">
            <h2 className="mb-4 text-xl font-bold text-[color:var(--steam)]">
              قهوه‌های دیگر از {roastery.name}
            </h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {related.map((p: typeof related[number]) => (
                <ProductCard key={p.slug} product={p} />
              ))}
            </div>
          </section>
        )}

        {/* Similar by origin from other roasteries */}
        {similarByOrigin.length > 0 && (
          <section className="mt-12">
            <h2 className="mb-4 text-xl font-bold text-[color:var(--steam)]">
              محصولات مشابه از سایر روستری‌ها
            </h2>
            <div className="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 lg:grid-cols-4">
              {similarByOrigin.map((p: typeof similarByOrigin[number]) => (
                <div key={p.slug} className="w-72 shrink-0 snap-start md:w-auto">
                  <ProductCard product={p} />
                </div>
              ))}
            </div>
          </section>
        )}

        {/* Reviews placeholder */}
        <section className="mt-12 rounded-2xl border border-dashed border-[color:var(--mid)] bg-[color:var(--dark)] p-8 text-center">
          <h2 className="text-xl font-bold text-[color:var(--steam)]">نظرات مشتریان</h2>
          <p className="mt-2 text-sm text-[color:var(--light)]">
            هنوز نظری ثبت نشده — اولین نفر باش
          </p>
          <button
            type="button"
            disabled
            className="mt-4 cursor-not-allowed rounded-lg border border-[color:var(--mid)] px-4 py-2 text-xs text-[color:var(--light)] opacity-60"
          >
            ثبت نظر (به‌زودی)
          </button>
        </section>
      </main>
      <Footer />
    </>
  );
}
