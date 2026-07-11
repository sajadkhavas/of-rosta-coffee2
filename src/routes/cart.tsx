import { createFileRoute, Link } from "@tanstack/react-router";
import { X } from "lucide-react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { getProduct, getRoastery } from "@/data/seed";
import { productImage } from "@/lib/product-images";
import { formatToman, toFa } from "@/lib/persian";
import { useCart, type CartItem } from "@/lib/cart-context";

export const Route = createFileRoute("/cart")({
  head: () => ({
    meta: [
      { title: "سبد خرید | رستا" },
      { name: "description", content: "سبد خرید شما در مارکت‌پلیس قهوه رستا." },
      { name: "robots", content: "noindex,follow" },
    ],
    links: [{ rel: "canonical", href: "/cart" }],
  }),
  component: CartPage,
});

function CartPage() {
  const { items: raw, updateQty, removeItem, subtotal } = useCart();

  const items = raw
    .map((i) => {
      const product = getProduct(i.productSlug);
      return product ? { ...i, product } : null;
    })
    .filter((x): x is NonNullable<typeof x> & { product: NonNullable<ReturnType<typeof getProduct>> } => x !== null);

  const grouped = new Map<string, typeof items>();
  for (const it of items) {
    const key = it.product.roasterySlug;
    if (!grouped.has(key)) grouped.set(key, []);
    grouped.get(key)!.push(it);
  }

  if (raw.length === 0) {
    return (
      <>
        <Navbar />
        <main className="mx-auto max-w-3xl px-4 py-16 text-center">
          <svg width="80" height="80" viewBox="0 0 24 24" fill="none" className="mx-auto text-[color:var(--roast)]">
            <path d="M6 8h12l-1 12H7L6 8zM9 8V6a3 3 0 016 0v2" stroke="currentColor" strokeWidth="1.5" />
          </svg>
          <h1 className="mt-4 text-2xl font-bold text-[color:var(--steam)]">سبد خرید خالی است</h1>
          <p className="mt-2 text-sm text-[color:var(--light)]">
            هنوز چیزی اضافه نکرده‌اید. مجموعه قهوه‌های تازه‌رست را ببینید.
          </p>
          <Link
            to="/products"
            className="mt-6 inline-block rounded-lg bg-[color:var(--roast)] px-6 py-3 text-sm font-bold text-[color:var(--night)]"
          >
            مشاهده محصولات
          </Link>
        </main>
        <Footer />
      </>
    );
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "سبد خرید" }]} />
        <h1 className="text-2xl font-bold text-[color:var(--steam)]">سبد خرید</h1>

        <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
          <div className="space-y-6">
            {Array.from(grouped.entries()).map(([roasterySlug, list]) => {
              const roastery = getRoastery(roasterySlug);
              return (
                <section
                  key={roasterySlug}
                  className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4"
                >
                  <header className="mb-3 flex items-center justify-between border-b border-[color:var(--mid)] pb-3">
                    <h2 className="text-sm font-bold text-[color:var(--steam)]">
                      سفارش از {roastery?.name}
                    </h2>
                    {grouped.size > 1 && (
                      <span className="text-[11px] text-[color:var(--light)]">
                        هر سفارش جداگانه پردازش می‌شود
                      </span>
                    )}
                  </header>
                  <ul className="space-y-3">
                    {list.map((it: CartItem & { product: NonNullable<ReturnType<typeof getProduct>> }) => (
                      <li key={`${it.productSlug}-${it.weight}`} className="flex gap-3">
                        <img
                          src={productImage(it.product.slug, 200)}
                          alt={it.product.name}
                          width={80}
                          height={80}
                          loading="lazy"
                          className="h-20 w-20 rounded-lg object-cover"
                        />
                        <div className="flex-1">
                          <div className="flex items-start justify-between gap-2">
                            <Link
                              to="/products/$slug"
                              params={{ slug: it.product.slug }}
                              className="text-sm font-bold text-[color:var(--steam)] hover:text-[color:var(--roast)]"
                            >
                              {it.product.name}
                            </Link>
                            <button
                              type="button"
                              aria-label="حذف از سبد"
                              onClick={() => removeItem(it.productSlug, it.weight)}
                              className="text-[color:var(--light)] transition hover:text-red-400"
                            >
                              <X size={16} />
                            </button>
                          </div>
                          <div className="mt-1 text-xs text-[color:var(--light)]">
                            {toFa(it.weight)} گرم · دانه کامل
                          </div>
                          <div className="mt-2 flex items-center justify-between">
                            <div className="inline-flex items-center gap-2 rounded-lg border border-[color:var(--mid)]">
                              <button
                                type="button"
                                aria-label="کاهش تعداد"
                                onClick={() => updateQty(it.productSlug, it.weight, it.qty - 1)}
                                className="px-2 py-1 text-[color:var(--roast)]"
                              >
                                −
                              </button>
                              <span className="font-mono-num text-sm">{toFa(it.qty)}</span>
                              <button
                                type="button"
                                aria-label="افزایش تعداد"
                                onClick={() => updateQty(it.productSlug, it.weight, it.qty + 1)}
                                className="px-2 py-1 text-[color:var(--roast)]"
                              >
                                +
                              </button>
                            </div>
                            <span className="font-mono-num text-sm font-bold text-[color:var(--roast)]">
                              {formatToman(it.product.prices[it.weight] * it.qty)}
                            </span>
                          </div>
                        </div>
                      </li>
                    ))}
                  </ul>
                </section>
              );
            })}
          </div>

          <aside className="h-fit rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 lg:sticky lg:top-20">
            <h2 className="text-sm font-bold text-[color:var(--steam)]">خلاصه سفارش</h2>
            <dl className="mt-3 space-y-2 text-sm">
              <div className="flex justify-between text-[color:var(--light)]">
                <dt>جمع اقلام</dt>
                <dd className="font-mono-num">{formatToman(subtotal)}</dd>
              </div>
              <div className="flex justify-between text-[color:var(--light)]">
                <dt>هزینه ارسال</dt>
                <dd className="text-xs">محاسبه در مرحله بعد</dd>
              </div>
              <div className="flex justify-between border-t border-[color:var(--mid)] pt-2 text-base font-bold text-[color:var(--steam)]">
                <dt>مجموع</dt>
                <dd className="font-mono-num text-[color:var(--roast)]">
                  {formatToman(subtotal)}
                </dd>
              </div>
            </dl>
            <Link
              to="/checkout"
              className="mt-4 block w-full rounded-lg bg-[color:var(--roast)] py-3 text-center text-sm font-bold text-[color:var(--night)]"
            >
              تسویه‌حساب
            </Link>
            <Link
              to="/products"
              className="mt-2 block w-full rounded-lg border border-[color:var(--mid)] py-3 text-center text-sm text-[color:var(--light)]"
            >
              ادامه خرید
            </Link>
          </aside>
        </div>
      </main>
      <Footer />
    </>
  );
}
