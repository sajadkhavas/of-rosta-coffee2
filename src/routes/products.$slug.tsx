import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { Alert } from "@/components/system";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import {
  productQueryOptions,
  relatedProductsQueryOptions,
} from "@/lib/api/catalog";
import { isApiError } from "@/lib/api/client";
import type { MediaAsset, ProductDetail, ProductVariant } from "@/lib/api/contracts";
import {
  bestMediaUrl,
  formatIrr,
  formatRoastDate,
  formatWeight,
  processingLabel,
  roastLevelLabel,
} from "@/lib/catalog-format";
import { absoluteUrl } from "@/config/site";
import { useCart } from "@/lib/cart-context";

export const Route = createFileRoute("/products/$slug")({
  head: ({ params }) => ({
    meta: [
      { title: `خرید دانه قهوه ${params.slug} | رستا` },
      {
        name: "description",
        content: "مشخصات، وزن‌ها، موجودی و تاریخ رست دانه کامل قهوه از روستری‌های رستا.",
      },
      { property: "og:type", content: "product" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl(`/products/${params.slug}`) }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify(
          breadcrumbJsonLd([
            { label: "خانه", to: "/" },
            { label: "محصولات", to: "/products" },
            { label: params.slug, to: `/products/${params.slug}` },
          ]),
        ),
      },
    ],
  }),
  component: ProductPage,
});

function productJsonLd(product: ProductDetail) {
  const image = product.gallery.map(bestMediaUrl).filter(Boolean);
  const available = product.variants.filter((variant) => variant.isAvailable);
  const prices = available.map((variant) => variant.price);
  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.name,
    description: product.seo.description || product.shortDescription || product.description,
    image,
    brand: { "@type": "Brand", name: product.roastery.name },
    category: "دانه کامل قهوه",
    additionalProperty: [
      { "@type": "PropertyValue", name: "خاستگاه", value: product.origin.name },
      { "@type": "PropertyValue", name: "سطح رست", value: roastLevelLabel(product.roastLevel) },
      { "@type": "PropertyValue", name: "فرآوری", value: processingLabel(product.processingMethod) },
      { "@type": "PropertyValue", name: "شکل محصول", value: "دانه کامل" },
    ],
    offers: prices.length
      ? {
          "@type": "AggregateOffer",
          priceCurrency: "IRR",
          lowPrice: Math.min(...prices),
          highPrice: Math.max(...prices),
          offerCount: prices.length,
          availability: "https://schema.org/InStock",
          url: absoluteUrl(`/products/${product.slug}`),
        }
      : {
          "@type": "Offer",
          priceCurrency: "IRR",
          availability: "https://schema.org/OutOfStock",
          url: absoluteUrl(`/products/${product.slug}`),
        },
  };
}

function ProductPage() {
  const { slug } = Route.useParams();
  const productQuery = useQuery(productQueryOptions(slug));
  const relatedQuery = useQuery(relatedProductsQueryOptions(slug));
  const product = productQuery.data;
  const [selectedVariantId, setSelectedVariantId] = useState("");
  const [selectedImage, setSelectedImage] = useState("");
  const [notice, setNotice] = useState("");
  const [added, setAdded] = useState(false);
  const { addItem, replaceWithItem } = useCart();

  useEffect(() => {
    if (!product) return;
    const firstAvailable = product.variants.find((variant) => variant.isAvailable);
    setSelectedVariantId(firstAvailable?.id ?? product.variants[0]?.id ?? "");
    setSelectedImage(bestMediaUrl(product.gallery[0] ?? product.primaryImage) ?? "");
    document.title = product.seo.title || `${product.name} از ${product.roastery.name} | رستا`;
  }, [product]);

  const selectedVariant = useMemo<ProductVariant | undefined>(
    () => product?.variants.find((variant) => variant.id === selectedVariantId),
    [product, selectedVariantId],
  );

  const addSelectedVariant = () => {
    if (!product || !selectedVariant?.isAvailable) return;
    const input = { product, variant: selectedVariant };
    const result = addItem(input);
    if (result.status === "requires_reset") {
      const confirmed = window.confirm(
        `سبد شما شامل محصولات ${result.currentRoasteryName} است. برای افزودن محصول این روستری، سبد قبلی پاک شود؟`,
      );
      if (!confirmed) return;
      replaceWithItem(input);
      setNotice("سبد قبلی پاک شد و محصول این روستری جایگزین شد.");
    } else {
      setNotice("محصول اضافه شد؛ قیمت و موجودی در صفحه سبد توسط سرور تأیید می‌شود.");
    }
    setAdded(true);
    window.setTimeout(() => setAdded(false), 1800);
  };

  if (productQuery.isPending) {
    return (
      <>
        <Navbar />
        <main className="mx-auto grid min-h-[60vh] max-w-6xl place-items-center px-4 py-12">
          <div className="text-center" role="status">
            <div className="mx-auto size-9 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
            <p className="mt-4 text-sm text-[color:var(--light)]">در حال دریافت محصول…</p>
          </div>
        </main>
        <Footer />
      </>
    );
  }

  if (productQuery.isError || !product) {
    const notFound = isApiError(productQuery.error) && productQuery.error.status === 404;
    return (
      <>
        <Navbar />
        <main className="mx-auto grid min-h-[60vh] max-w-xl place-items-center px-4 py-12 text-center">
          <section>
            <h1 className="text-2xl font-bold">{notFound ? "محصول پیدا نشد" : "محصول بارگذاری نشد"}</h1>
            <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
              {isApiError(productQuery.error)
                ? productQuery.error.message
                : "ارتباط با سرویس کاتالوگ برقرار نشد."}
            </p>
            <div className="mt-6 flex justify-center gap-3">
              {!notFound ? (
                <button type="button" onClick={() => productQuery.refetch()} className="rounded-xl bg-[color:var(--roast)] px-5 py-2.5 text-sm font-bold text-[color:var(--night)]">
                  تلاش مجدد
                </button>
              ) : null}
              <Link to="/products" className="rounded-xl border border-[color:var(--mid)] px-5 py-2.5 text-sm">بازگشت به محصولات</Link>
            </div>
          </section>
        </main>
        <Footer />
      </>
    );
  }

  const gallery: MediaAsset[] = product.gallery.length
    ? product.gallery
    : product.primaryImage
      ? [product.primaryImage]
      : [];
  const roastDate = formatRoastDate(product.latestRoastBatch?.roastedAt);
  const faq = [
    {
      question: "این قهوه به چه شکل ارسال می‌شود؟",
      answer: "تمام محصولات رستا فقط به‌صورت دانه کامل ارسال می‌شوند و هیچ انتخاب آسیابی وجود ندارد.",
    },
    {
      question: "قیمت و موجودی چه زمانی قطعی می‌شود؟",
      answer: "پس از افزودن Variant به سبد، سرور رستا قیمت و موجودی را اعتبارسنجی می‌کند و در Checkout دوباره Quote می‌سازد.",
    },
  ];

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(productJsonLd(product)) }} />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{
            __html: JSON.stringify({
              "@context": "https://schema.org",
              "@type": "FAQPage",
              mainEntity: faq.map((item) => ({
                "@type": "Question",
                name: item.question,
                acceptedAnswer: { "@type": "Answer", text: item.answer },
              })),
            }),
          }}
        />
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "محصولات", to: "/products" }, { label: product.name }]} />

        <article className="mt-6 grid gap-8 md:grid-cols-2">
          <section aria-label="گالری محصول">
            <div className="aspect-square overflow-hidden rounded-2xl border border-[color:var(--mid)] bg-[color:var(--steam)]">
              {selectedImage ? (
                <img src={selectedImage} alt={product.name} className="h-full w-full object-cover" fetchPriority="high" />
              ) : (
                <div className="grid h-full place-items-center text-[color:var(--mid)]">تصویر محصول</div>
              )}
            </div>
            {gallery.length > 1 ? (
              <div className="mt-3 grid grid-cols-4 gap-3">
                {gallery.map((asset) => {
                  const url = bestMediaUrl(asset);
                  if (!url) return null;
                  return (
                    <button key={asset.id} type="button" onClick={() => setSelectedImage(url)} className={`aspect-square overflow-hidden rounded-xl border ${selectedImage === url ? "border-[color:var(--roast)]" : "border-[color:var(--mid)]"}`}>
                      <img src={url} alt={asset.alt || product.name} loading="lazy" className="h-full w-full object-cover" />
                    </button>
                  );
                })}
              </div>
            ) : null}
          </section>

          <section>
            <Link to="/roasteries/$slug" params={{ slug: product.roastery.slug }} className="text-sm font-bold text-[color:var(--roast)] hover:underline">
              {product.roastery.name}
            </Link>
            <h1 className="mt-2 text-3xl font-bold text-[color:var(--steam)] sm:text-4xl">{product.name}</h1>
            <p className="mt-4 text-sm leading-8 text-[color:var(--light)]">{product.shortDescription || product.description}</p>

            <div className="mt-5 flex flex-wrap gap-2 text-xs">
              <span className="rounded-full border border-[color:var(--mid)] px-3 py-1">{product.origin.name}</span>
              <span className="rounded-full border border-[color:var(--mid)] px-3 py-1">رست {roastLevelLabel(product.roastLevel)}</span>
              <span className="rounded-full border border-[color:var(--mid)] px-3 py-1">فرآوری {processingLabel(product.processingMethod)}</span>
              <span className="rounded-full border border-[color:var(--mid)] px-3 py-1">{product.arabicaPercentage.toLocaleString("fa-IR")}٪ عربیکا</span>
              {roastDate ? <span className="rounded-full bg-[color:var(--roast)] px-3 py-1 font-bold text-[color:var(--night)]">رست {roastDate}</span> : null}
            </div>

            <section className="mt-7">
              <h2 className="text-sm font-bold">انتخاب وزن</h2>
              <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                {product.variants.map((variant) => (
                  <button
                    key={variant.id}
                    type="button"
                    disabled={!variant.isAvailable}
                    onClick={() => setSelectedVariantId(variant.id)}
                    className={`rounded-xl border p-3 text-start transition disabled:cursor-not-allowed disabled:opacity-40 ${selectedVariantId === variant.id ? "border-[color:var(--roast)] bg-[color:var(--roast)]/10" : "border-[color:var(--mid)] bg-[color:var(--dark)]"}`}
                  >
                    <span className="block text-sm font-bold">{formatWeight(variant.weightGrams)}</span>
                    <span className="mt-1 block text-xs text-[color:var(--light)]">{formatIrr(variant.price)}</span>
                    {typeof variant.availableQuantity === "number" ? <span className="mt-1 block text-[10px] text-[color:var(--roast)]">{variant.availableQuantity.toLocaleString("fa-IR")} عدد موجود</span> : null}
                  </button>
                ))}
              </div>
            </section>

            <div className="mt-6 rounded-xl border border-[color:var(--roast)]/40 bg-[color:var(--night)] p-4 text-xs leading-7 text-[color:var(--light)]">
              رستا فقط دانه کامل می‌فروشد. قیمت نمایش‌داده‌شده Snapshot کاتالوگ است و مبلغ نهایی در سبد توسط سرور تأیید می‌شود.
            </div>

            {notice ? <div className="mt-5"><Alert variant="success" title="سبد خرید">{notice}</Alert></div> : null}

            <div className="mt-6 flex items-center justify-between gap-4 border-t border-[color:var(--mid)] pt-5">
              <div>
                <p className="text-xs text-[color:var(--light)]">قیمت انتخاب‌شده</p>
                <p className="mt-1 text-xl font-bold text-[color:var(--roast)]">{selectedVariant ? formatIrr(selectedVariant.price) : "انتخاب وزن"}</p>
              </div>
              <button
                type="button"
                disabled={!selectedVariant?.isAvailable}
                onClick={addSelectedVariant}
                className="min-h-12 rounded-xl bg-[color:var(--roast)] px-6 py-3 text-sm font-bold text-[color:var(--night)] disabled:cursor-not-allowed disabled:opacity-50"
              >
                {added ? "افزوده شد ✓" : "افزودن به سبد"}
              </button>
            </div>
          </section>
        </article>

        <section className="mt-12 grid gap-4 md:grid-cols-2">
          <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
            <h2 className="font-bold">جزئیات دانه</h2>
            <dl className="mt-4 grid gap-3 text-sm text-[color:var(--light)]">
              <div className="flex justify-between gap-4"><dt>خاستگاه</dt><dd>{product.origin.name}</dd></div>
              <div className="flex justify-between gap-4"><dt>فرآوری</dt><dd>{processingLabel(product.processingMethod)}</dd></div>
              <div className="flex justify-between gap-4"><dt>رست</dt><dd>{roastLevelLabel(product.roastLevel)}</dd></div>
              <div className="flex justify-between gap-4"><dt>نت‌های چشایی</dt><dd>{product.tastingNotes.join("، ")}</dd></div>
            </dl>
          </div>
          <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
            <h2 className="font-bold">سوالات این محصول</h2>
            <div className="mt-4 space-y-4">
              {faq.map((item) => (
                <details key={item.question} className="rounded-xl border border-[color:var(--mid)] p-4 first:open">
                  <summary className="cursor-pointer text-sm font-bold">{item.question}</summary>
                  <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">{item.answer}</p>
                </details>
              ))}
            </div>
          </div>
        </section>

        {relatedQuery.data?.length ? (
          <section className="mt-14">
            <h2 className="text-2xl font-bold">محصولات مشابه</h2>
            <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {relatedQuery.data.slice(0, 4).map((item) => <CatalogProductCard key={item.id} product={item} />)}
            </div>
          </section>
        ) : null}
      </main>
      <Footer />
    </>
  );
}
