import { createFileRoute, Link } from "@tanstack/react-router";
import { Minus, Plus, X } from "lucide-react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Alert } from "@/components/system";
import { getProduct, getRoastery } from "@/data/seed";
import { absoluteUrl } from "@/config/site";
import { productImage } from "@/lib/product-images";
import { formatToman, toFa } from "@/lib/persian";
import { useCart, type CartItem } from "@/lib/cart-context";

export const Route = createFileRoute("/cart")({
  head: () => ({
    meta: [
      { title: "سبد خرید آزمایشی | رستا" },
      {
        name: "description",
        content: "وضعیت محلی سبد رستا تا پیش از اتصال Variant، قیمت و موجودی سمت سرور.",
      },
      { name: "robots", content: "noindex,nofollow" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/cart") }],
  }),
  component: CartPage,
});

function IconAction({
  label,
  children,
  onClick,
  disabled,
}: {
  label: string;
  children: React.ReactNode;
  onClick: () => void;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      disabled={disabled}
      onClick={onClick}
      className="inline-grid size-9 place-items-center rounded-lg text-[color:var(--light)] transition hover:bg-white/5 hover:text-[color:var(--roast)] disabled:opacity-40"
    >
      {children}
    </button>
  );
}

function CartPage() {
  const { items: raw, updateQty, removeItem, subtotal } = useCart();

  const items = raw
    .map((item) => {
      const product = getProduct(item.productSlug);
      return product ? { ...item, product } : null;
    })
    .filter(
      (
        item,
      ): item is NonNullable<typeof item> & {
        product: NonNullable<ReturnType<typeof getProduct>>;
      } => item !== null,
    );

  const grouped = new Map<string, typeof items>();
  for (const item of items) {
    const key = item.product.roasterySlug;
    if (!grouped.has(key)) grouped.set(key, []);
    grouped.get(key)!.push(item);
  }

  if (raw.length === 0) {
    return (
      <>
        <Navbar />
        <main className="mx-auto grid min-h-[60vh] max-w-xl place-items-center px-4 py-16 text-center">
          <section>
            <svg
              width="80"
              height="80"
              viewBox="0 0 24 24"
              fill="none"
              className="mx-auto text-[color:var(--roast)]"
            >
              <path
                d="M6 8h12l-1 12H7L6 8zM9 8V6a3 3 0 016 0v2"
                stroke="currentColor"
                strokeWidth="1.5"
              />
            </svg>
            <h1 className="mt-4 text-2xl font-bold">سبد خرید خالی است</h1>
            <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
              افزودن محصول تا اتصال Variant واقعی، Quote قیمت، موجودی و رزرو سمت سرور در فاز ۵ غیرفعال است.
            </p>
            <Link
              to="/products"
              className="mt-6 inline-flex min-h-11 items-center rounded-xl bg-[color:var(--roast)] px-6 text-sm font-bold text-[color:var(--night)]"
            >
              مشاهده کاتالوگ
            </Link>
          </section>
        </main>
        <Footer />
      </>
    );
  }

  const multipleRoasteries = grouped.size > 1;

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "سبد خرید" }]} />
        <header className="mt-4">
          <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">
            LOCAL CART PREVIEW
          </p>
          <h1 className="mt-2 text-3xl font-bold">سبد خرید</h1>
          <p className="mt-2 text-sm text-[color:var(--light)]">
            این سبد فعلاً فقط State محلی توسعه است و قیمت یا موجودی عملیاتی محسوب نمی‌شود.
          </p>
        </header>

        {multipleRoasteries ? (
          <div className="mt-5">
            <Alert variant="danger" title="محصولات چند روستری در یک سفارش مجاز نیستند">
              معماری نهایی رستا هر سفارش را به یک روستری محدود می‌کند. برای ادامه باید فقط محصولات یک روستری در سبد باقی بمانند.
            </Alert>
          </div>
        ) : null}

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
                    <h2 className="text-sm font-bold">
                      سبد محلی {roastery?.name || roasterySlug}
                    </h2>
                    <span className="text-[11px] text-[color:var(--light)]">دانه کامل</span>
                  </header>
                  <ul className="space-y-3">
                    {list.map(
                      (
                        item: CartItem & {
                          product: NonNullable<ReturnType<typeof getProduct>>;
                        },
                      ) => (
                        <li key={`${item.productSlug}-${item.weight}`} className="flex gap-3">
                          <img
                            src={productImage(item.product.slug, 200)}
                            alt={item.product.name}
                            width={80}
                            height={80}
                            loading="lazy"
                            className="size-20 rounded-xl object-cover"
                          />
                          <div className="min-w-0 flex-1">
                            <div className="flex items-start justify-between gap-2">
                              <Link
                                to="/products/$slug"
                                params={{ slug: item.product.slug }}
                                className="text-sm font-bold hover:text-[color:var(--roast)]"
                              >
                                {item.product.name}
                              </Link>
                              <IconAction
                                label="حذف از سبد"
                                onClick={() => removeItem(item.productSlug, item.weight)}
                              >
                                <X size={16} />
                              </IconAction>
                            </div>
                            <p className="mt-1 text-xs text-[color:var(--light)]">
                              {toFa(item.weight)} گرم · دانه کامل
                            </p>
                            <div className="mt-2 flex items-center justify-between gap-3">
                              <div className="inline-flex items-center rounded-xl border border-[color:var(--mid)]">
                                <IconAction
                                  label="کاهش تعداد"
                                  onClick={() => updateQty(item.productSlug, item.weight, item.qty - 1)}
                                >
                                  <Minus size={15} />
                                </IconAction>
                                <span className="min-w-7 text-center font-mono text-sm">
                                  {toFa(item.qty)}
                                </span>
                                <IconAction
                                  label="افزایش تعداد"
                                  onClick={() => updateQty(item.productSlug, item.weight, item.qty + 1)}
                                >
                                  <Plus size={15} />
                                </IconAction>
                              </div>
                              <span className="font-mono text-sm font-bold text-[color:var(--roast)]">
                                {formatToman(item.product.prices[item.weight] * item.qty)}
                              </span>
                            </div>
                          </div>
                        </li>
                      ),
                    )}
                  </ul>
                </section>
              );
            })}
          </div>

          <aside className="h-fit rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 lg:sticky lg:top-20">
            <h2 className="font-bold">خلاصه محلی</h2>
            <dl className="mt-4 space-y-3 text-sm">
              <div className="flex justify-between text-[color:var(--light)]">
                <dt>جمع نمایشی اقلام</dt>
                <dd className="font-mono">{formatToman(subtotal)}</dd>
              </div>
              <div className="flex justify-between text-[color:var(--light)]">
                <dt>هزینه ارسال</dt>
                <dd className="text-xs">در Quote واقعی محاسبه می‌شود</dd>
              </div>
              <div className="flex justify-between border-t border-[color:var(--mid)] pt-3 font-bold">
                <dt>مجموع نمایشی</dt>
                <dd className="font-mono text-[color:var(--roast)]">{formatToman(subtotal)}</dd>
              </div>
            </dl>
            {multipleRoasteries ? (
              <button
                type="button"
                disabled
                className="mt-5 min-h-11 w-full rounded-xl bg-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--night)] opacity-50"
              >
                ابتدا سبد را تک‌روستری کنید
              </button>
            ) : (
              <Link
                to="/checkout"
                className="mt-5 flex min-h-11 w-full items-center justify-center rounded-xl bg-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--night)]"
              >
                مشاهده پیش‌نمایش تسویه
              </Link>
            )}
            <Link
              to="/products"
              className="mt-3 block w-full rounded-xl border border-[color:var(--mid)] py-3 text-center text-sm text-[color:var(--light)]"
            >
              ادامه مشاهده کاتالوگ
            </Link>
          </aside>
        </div>
      </main>
      <Footer />
    </>
  );
}
