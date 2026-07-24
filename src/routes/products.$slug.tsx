import { useQuery } from "@tanstack/react-query";
import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { Alert } from "@/components/system";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { ProductReviews } from "@/components/reviews/ProductReviews";
import { siteConfig, absoluteUrl } from "@/config/site";
import { bestMediaUrl, formatIrr, formatRoastDate, formatWeight, processingLabel, roastLevelLabel } from "@/lib/catalog-format";
import { productQueryOptions, relatedProductsQueryOptions } from "@/lib/api/catalog";
import { productReviewsQueryOptions } from "@/lib/api/reviews";
import { isApiError } from "@/lib/api/client";
import type { MediaAsset, ProductDetail, ProductVariant } from "@/lib/api/contracts";
import { useCart } from "@/lib/cart-context";
import { seoHead } from "@/lib/seo";

const PRODUCT_FAQ = [
  { question: "این قهوه به چه شکل ارسال می‌شود؟", answer: "تمام محصولات رستا فقط به‌صورت دانه کامل ارسال می‌شوند و هیچ انتخاب آسیابی وجود ندارد." },
  { question: "قیمت و موجودی چه زمانی قطعی می‌شود؟", answer: "پس از افزودن Variant به سبد، سرور رستا قیمت و موجودی را اعتبارسنجی می‌کند و در Checkout دوباره Quote می‌سازد." },
];

function productJsonLd(product: ProductDetail, reviews?: { summary: { count: number; average: number | null } }) {
  const canonical = absoluteUrl(`/products/${product.slug}`);
  const images = [product.primaryImage, ...product.gallery].map(bestMediaUrl).filter((value): value is string => Boolean(value));
  return {
    "@context": "https://schema.org",
    "@type": "ProductGroup",
    "@id": `${canonical}#group`,
    name: product.name,
    description: product.seo.description || product.shortDescription || product.description,
    url: canonical,
    image: images,
    productGroupID: product.id,
    variesBy: "https://schema.org/size",
    brand: { "@type": "Brand", name: product.roastery.name },
    category: "دانه کامل قهوه",
    ...(reviews?.summary.average && reviews.summary.count > 0 ? {
      aggregateRating: {
        "@type": "AggregateRating",
        ratingValue: reviews.summary.average,
        reviewCount: reviews.summary.count,
        bestRating: 5,
        worstRating: 1,
      },
    } : {}),
    additionalProperty: [
      { "@type": "PropertyValue", name: "خاستگاه", value: product.origin.name },
      { "@type": "PropertyValue", name: "سطح رست", value: roastLevelLabel(product.roastLevel) },
      { "@type": "PropertyValue", name: "فرآوری", value: processingLabel(product.processingMethod) },
      { "@type": "PropertyValue", name: "شکل محصول", value: "دانه کامل" },
    ],
    hasVariant: product.variants.map((variant) => ({
      "@type": "Product",
      name: `${product.name} ${formatWeight(variant.weightGrams)}`,
      sku: variant.sku,
      size: formatWeight(variant.weightGrams),
      isVariantOf: { "@id": `${canonical}#group` },
      offers: {
        "@type": "Offer",
        url: canonical,
        priceCurrency: variant.currency,
        price: variant.price,
        availability: variant.isAvailable ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
        itemCondition: "https://schema.org/NewCondition",
      },
    })),
  };
}

export const Route = createFileRoute("/products/$slug")({
  loader: async ({ params, context }) => {
    try {
      const product = await context.queryClient.ensureQueryData(productQueryOptions(params.slug));
      await context.queryClient.ensureQueryData(productReviewsQueryOptions(params.slug)).catch(() => undefined);
      return product;
    } catch (error) {
      if (isApiError(error) && error.status === 404) throw notFound();
      throw error;
    }
  },
  head: ({ loaderData }) => {
    const product: ProductDetail | undefined = loaderData;
    if (!product) return {};
    const image = bestMediaUrl(product.primaryImage ?? product.gallery[0]);
    return seoHead({
      title: product.seo.title || `${product.name} از ${product.roastery.name} | رستا`,
      description: product.seo.description || product.shortDescription || product.description,
      path: `/products/${product.slug}`,
      index: siteConfig.allowIndexing && product.status === "published",
      type: "product",
      image,
      modifiedAt: product.latestRoastBatch?.roastedAt,
      jsonLd: [
        productJsonLd(product),
        { "@context": "https://schema.org", "@type": "FAQPage", mainEntity: PRODUCT_FAQ.map((item) => ({ "@type": "Question", name: item.question, acceptedAnswer: { "@type": "Answer", text: item.answer } })) },
        breadcrumbJsonLd([{ label: "خانه", to: "/" }, { label: "محصولات", to: "/products" }, { label: product.name, to: `/products/${product.slug}` }]),
      ],
    });
  },
  component: ProductPage,
});

function ProductPage() {
  const { slug } = Route.useParams();
  const product: ProductDetail = Route.useLoaderData();
  const relatedQuery = useQuery(relatedProductsQueryOptions(slug));
  const [selectedVariantId, setSelectedVariantId] = useState("");
  const [selectedImage, setSelectedImage] = useState("");
  const [notice, setNotice] = useState("");
  const [noticeVariant, setNoticeVariant] = useState<"success" | "warning">("success");
  const [added, setAdded] = useState(false);
  const { addItem, replaceWithItem } = useCart();

  useEffect(() => {
    const firstAvailable = product.variants.find((variant) => variant.isAvailable);
    setSelectedVariantId(firstAvailable?.id ?? product.variants[0]?.id ?? "");
    setSelectedImage(bestMediaUrl(product.gallery[0] ?? product.primaryImage) ?? "");
  }, [product]);
  const selectedVariant = useMemo<ProductVariant | undefined>(() => product.variants.find((variant) => variant.id === selectedVariantId), [product.variants, selectedVariantId]);
  const gallery: MediaAsset[] = product.gallery.length ? product.gallery : product.primaryImage ? [product.primaryImage] : [];

  const addSelectedVariant = () => {
    if (!selectedVariant?.isAvailable) return;
    const input = { product, variant: selectedVariant };
    const result = addItem(input);
    if (result.status === "requires_reset") {
      if (!window.confirm(`سبد شما شامل محصولات ${result.currentRoasteryName} است. سبد قبلی پاک شود؟`)) return;
      replaceWithItem(input);
      setNotice("سبد قبلی پاک شد و محصول این روستری جایگزین شد.");
    } else if (result.status === "limit_reached") {
      setNoticeVariant("warning");
      setNotice("تعداد Variantهای سبد به سقف امن رسیده است.");
      return;
    } else {
      setNoticeVariant("success");
      setNotice("محصول اضافه شد؛ قیمت و موجودی در سبد دوباره توسط سرور تأیید می‌شود.");
    }
    setAdded(true);
    window.setTimeout(() => setAdded(false), 1800);
  };

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "محصولات", to: "/products" }, { label: product.name }]} />
        <article className="mt-6 grid gap-8 md:grid-cols-2">
          <section aria-label="گالری محصول">
            <div className="aspect-square overflow-hidden rounded-2xl border border-[color:var(--mid)] bg-[color:var(--steam)]">
              {selectedImage ? <img src={selectedImage} alt={product.name} className="h-full w-full object-cover" fetchPriority="high" /> : <div className="grid h-full place-items-center text-[color:var(--mid)]">تصویر محصول</div>}
            </div>
            {gallery.length > 1 ? <div className="mt-3 grid grid-cols-4 gap-3">{gallery.map((asset) => { const url = bestMediaUrl(asset); return url ? <button key={asset.id} type="button" onClick={() => setSelectedImage(url)} className={`aspect-square overflow-hidden rounded-xl border ${selectedImage === url ? "border-[color:var(--roast)]" : "border-[color:var(--mid)]"}`}><img src={url} alt={asset.alt || product.name} loading="lazy" className="h-full w-full object-cover" /></button> : null; })}</div> : null}
          </section>

          <section>
            <Link to="/roasteries/$slug" params={{ slug: product.roastery.slug }} className="text-sm font-bold text-[color:var(--roast)]">{product.roastery.name}</Link>
            <h1 className="mt-2 text-3xl font-bold md:text-4xl">{product.name}</h1>
            <p className="mt-4 text-sm leading-8 text-[color:var(--light)]">{product.shortDescription || product.description}</p>
            <div className="mt-5 flex flex-wrap gap-2 text-xs">
              <span className="rounded-full border border-[color:var(--mid)] px-3 py-1">{product.origin.name}</span>
              <span className="rounded-full border border-[color:var(--mid)] px-3 py-1">رست {roastLevelLabel(product.roastLevel)}</span>
              <span className="rounded-full border border-[color:var(--mid)] px-3 py-1">فرآوری {processingLabel(product.processingMethod)}</span>
              <span className="rounded-full border border-[color:var(--mid)] px-3 py-1">{product.arabicaPercentage.toLocaleString("fa-IR")}٪ عربیکا</span>
              {formatRoastDate(product.latestRoastBatch?.roastedAt) ? <span className="rounded-full bg-[color:var(--roast)] px-3 py-1 font-bold text-[color:var(--night)]">رست {formatRoastDate(product.latestRoastBatch?.roastedAt)}</span> : null}
            </div>
            <section className="mt-7"><h2 className="text-sm font-bold">انتخاب وزن دانه کامل</h2><div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">{product.variants.map((variant) => <button key={variant.id} type="button" disabled={!variant.isAvailable} onClick={() => setSelectedVariantId(variant.id)} className={`rounded-xl border p-3 text-start disabled:opacity-40 ${selectedVariantId === variant.id ? "border-[color:var(--roast)] bg-[color:var(--roast)]/10" : "border-[color:var(--mid)] bg-[color:var(--dark)]"}`}><span className="block text-sm font-bold">{formatWeight(variant.weightGrams)}</span><span className="mt-1 block text-xs text-[color:var(--light)]">{formatIrr(variant.price)}</span>{typeof variant.availableQuantity === "number" ? <span className="mt-1 block text-[10px] text-[color:var(--roast)]">{variant.availableQuantity.toLocaleString("fa-IR")} عدد موجود</span> : null}</button>)}</div></section>
            <div className="mt-6 rounded-xl border border-[color:var(--roast)]/40 bg-[color:var(--night)] p-4 text-xs leading-7 text-[color:var(--light)]">رستا فقط دانه کامل می‌فروشد. قیمت نهایی در سبد توسط سرور تأیید می‌شود.</div>
            {notice ? <div className="mt-5"><Alert variant={noticeVariant} title="سبد خرید">{notice}</Alert></div> : null}
            <div className="mt-6 flex items-center justify-between gap-4 border-t border-[color:var(--mid)] pt-5"><div><p className="text-xs text-[color:var(--light)]">قیمت انتخاب‌شده</p><p className="mt-1 text-xl font-bold text-[color:var(--roast)]">{selectedVariant ? formatIrr(selectedVariant.price) : "انتخاب وزن"}</p></div><button type="button" disabled={!selectedVariant?.isAvailable} onClick={addSelectedVariant} className="min-h-12 rounded-xl bg-[color:var(--roast)] px-6 text-sm font-bold text-[color:var(--night)] disabled:opacity-50">{added ? "افزوده شد ✓" : "افزودن به سبد"}</button></div>
          </section>
        </article>

        <section className="mt-12 grid gap-4 md:grid-cols-2">
          <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"><h2 className="font-bold">جزئیات دانه</h2><dl className="mt-4 grid gap-3 text-sm text-[color:var(--light)]"><div className="flex justify-between"><dt>خاستگاه</dt><dd>{product.origin.name}</dd></div><div className="flex justify-between"><dt>فرآوری</dt><dd>{processingLabel(product.processingMethod)}</dd></div><div className="flex justify-between"><dt>رست</dt><dd>{roastLevelLabel(product.roastLevel)}</dd></div><div className="flex justify-between"><dt>نت‌های چشایی</dt><dd>{product.tastingNotes.join("، ")}</dd></div></dl></div>
          <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"><h2 className="font-bold">سوالات این محصول</h2><div className="mt-4 space-y-4">{PRODUCT_FAQ.map((item) => <details key={item.question} className="rounded-xl border border-[color:var(--mid)] p-4"><summary className="cursor-pointer text-sm font-bold">{item.question}</summary><p className="mt-3 text-sm leading-7 text-[color:var(--light)]">{item.answer}</p></details>)}</div></div>
        </section>

        <ProductReviews productSlug={slug} />
        {relatedQuery.data?.length ? <section className="mt-14"><h2 className="text-2xl font-bold">محصولات مشابه</h2><div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{relatedQuery.data.slice(0, 4).map((item) => <CatalogProductCard key={item.id} product={item} />)}</div></section> : null}
      </main>
      <Footer />
    </>
  );
}
