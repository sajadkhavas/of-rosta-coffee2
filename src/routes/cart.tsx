import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Minus, Plus, RefreshCw, X } from "lucide-react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Alert, Button } from "@/components/system";
import { absoluteUrl } from "@/config/site";
import { validateCart } from "@/lib/api/checkout";
import { isApiError } from "@/lib/api/client";
import { formatIrr, formatWeight } from "@/lib/catalog-format";
import { useCart } from "@/lib/cart-context";

export const Route = createFileRoute("/cart")({
  head: () => ({
    meta: [
      { title: "سبد خرید | رستا" },
      {
        name: "description",
        content: "سبد خرید دانه کامل قهوه با اعتبارسنجی قیمت و موجودی سمت سرور.",
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
      className="inline-grid size-10 place-items-center rounded-lg text-[color:var(--light)] transition hover:bg-white/5 hover:text-[color:var(--roast)] disabled:opacity-40"
    >
      {children}
    </button>
  );
}

function CartPage() {
  const { items, hydrated, apiItems, localSubtotal, updateQuantity, removeItem } = useCart();

  const quoteQuery = useQuery({
    queryKey: ["cart", "validate", apiItems],
    queryFn: () => validateCart(apiItems),
    enabled: hydrated && apiItems.length > 0,
    staleTime: 5_000,
    retry: false,
  });

  if (!hydrated) {
    return (
      <>
        <Navbar />
        <main className="grid min-h-[60vh] place-items-center" role="status">
          <div className="text-center">
            <div className="mx-auto size-9 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
            <p className="mt-4 text-sm text-[color:var(--light)]">در حال خواندن سبد…</p>
          </div>
        </main>
        <Footer />
      </>
    );
  }

  if (items.length === 0) {
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
              یک وزن موجود از دانه قهوه را انتخاب و به سبد اضافه کنید.
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

  const quote = quoteQuery.data;
  const canCheckout = quoteQuery.isSuccess && Boolean(quote);
  const expiresAt = quote?.expiresAt
    ? new Date(quote.expiresAt).toLocaleTimeString("fa-IR", { hour: "2-digit", minute: "2-digit" })
    : null;

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "سبد خرید" }]} />
        <header className="mt-4">
          <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">
            SERVER VALIDATED CART
          </p>
          <h1 className="mt-2 text-3xl font-bold">سبد خرید</h1>
          <p className="mt-2 text-sm leading-7 text-[color:var(--light)]">
            قیمت، موجودی و قانون تک‌روستری بودن سبد توسط سرور رستا بررسی می‌شود.
          </p>
        </header>

        {quoteQuery.isError ? (
          <div className="mt-5">
            <Alert variant="danger" title="سبد توسط سرور تأیید نشد">
              {isApiError(quoteQuery.error)
                ? quoteQuery.error.message
                : "اعتبارسنجی قیمت و موجودی انجام نشد."}
            </Alert>
          </div>
        ) : null}

        {quote?.warnings.length ? (
          <div className="mt-5 space-y-3">
            {quote.warnings.map((warning, index) => (
              <Alert key={`${warning.code}-${index}`} variant="warning" title="نیاز به بررسی سبد">
                {warning.message}
              </Alert>
            ))}
          </div>
        ) : null}

        <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
          <section
            className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4"
            aria-label="اقلام سبد"
          >
            <header className="mb-3 flex items-center justify-between border-b border-[color:var(--mid)] pb-3">
              <div>
                <h2 className="text-sm font-bold">{items[0].roasteryName}</h2>
                <p className="mt-1 text-[11px] text-[color:var(--light)]">
                  تمام اقلام فقط دانه کامل هستند.
                </p>
              </div>
              <span className="rounded-full border border-[color:var(--roast)] px-3 py-1 text-[11px] text-[color:var(--roast)]">
                تک‌روستری
              </span>
            </header>
            <ul className="divide-y divide-[color:var(--mid)]">
              {items.map((item) => (
                <li key={item.variantId} className="flex gap-3 py-4 first:pt-1 last:pb-1">
                  {item.productImageUrl ? (
                    <img
                      src={item.productImageUrl}
                      alt={item.productName}
                      width={88}
                      height={88}
                      loading="lazy"
                      className="size-22 rounded-xl object-cover"
                    />
                  ) : (
                    <div className="grid size-22 shrink-0 place-items-center rounded-xl bg-[color:var(--night)] text-xs text-[color:var(--light)]">
                      تصویر
                    </div>
                  )}
                  <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <Link
                          to="/products/$slug"
                          params={{ slug: item.productSlug }}
                          className="text-sm font-bold hover:text-[color:var(--roast)]"
                        >
                          {item.productName}
                        </Link>
                        <p className="mt-1 text-xs text-[color:var(--light)]">
                          {formatWeight(item.weightGrams)} · دانه کامل
                        </p>
                      </div>
                      <IconAction label="حذف از سبد" onClick={() => removeItem(item.variantId)}>
                        <X size={17} />
                      </IconAction>
                    </div>
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                      <div className="inline-flex items-center rounded-xl border border-[color:var(--mid)]">
                        <IconAction
                          label="کاهش تعداد"
                          onClick={() => updateQuantity(item.variantId, item.quantity - 1)}
                        >
                          <Minus size={16} />
                        </IconAction>
                        <span className="min-w-8 text-center font-mono text-sm">
                          {item.quantity.toLocaleString("fa-IR")}
                        </span>
                        <IconAction
                          label="افزایش تعداد"
                          disabled={item.quantity >= 20}
                          onClick={() => updateQuantity(item.variantId, item.quantity + 1)}
                        >
                          <Plus size={16} />
                        </IconAction>
                      </div>
                      <div className="text-end">
                        <p className="text-[10px] text-[color:var(--light)]">قیمت آخرین مشاهده</p>
                        <p className="font-mono text-sm font-bold text-[color:var(--roast)]">
                          {formatIrr(item.unitPriceSnapshot * item.quantity)}
                        </p>
                      </div>
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          </section>

          <aside className="h-fit rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 lg:sticky lg:top-20">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-bold">خلاصه معتبر سرور</h2>
              <button
                type="button"
                onClick={() => quoteQuery.refetch()}
                disabled={quoteQuery.isFetching}
                aria-label="اعتبارسنجی دوباره"
                className="grid size-10 place-items-center rounded-lg border border-[color:var(--mid)] text-[color:var(--light)] disabled:opacity-50"
              >
                <RefreshCw size={16} className={quoteQuery.isFetching ? "animate-spin" : ""} />
              </button>
            </div>

            {quoteQuery.isPending ? (
              <div className="mt-6 text-center" role="status">
                <div className="mx-auto size-7 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
                <p className="mt-3 text-xs text-[color:var(--light)]">بررسی قیمت و موجودی…</p>
              </div>
            ) : quote ? (
              <dl className="mt-5 space-y-3 text-sm">
                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>جمع اقلام</dt>
                  <dd className="font-mono">{formatIrr(quote.subtotal)}</dd>
                </div>
                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>ارسال</dt>
                  <dd className="font-mono">{formatIrr(quote.shippingTotal)}</dd>
                </div>
                {quote.discountTotal > 0 ? (
                  <div className="flex justify-between text-emerald-300">
                    <dt>تخفیف</dt>
                    <dd className="font-mono">− {formatIrr(quote.discountTotal)}</dd>
                  </div>
                ) : null}
                <div className="flex justify-between border-t border-[color:var(--mid)] pt-3 font-bold">
                  <dt>مبلغ قابل پرداخت</dt>
                  <dd className="font-mono text-[color:var(--roast)]">
                    {formatIrr(quote.grandTotal)}
                  </dd>
                </div>
              </dl>
            ) : (
              <dl className="mt-5 space-y-3 text-sm text-[color:var(--light)]">
                <div className="flex justify-between">
                  <dt>جمع آخرین مشاهده</dt>
                  <dd className="font-mono">{formatIrr(localSubtotal)}</dd>
                </div>
              </dl>
            )}

            {expiresAt ? (
              <p className="mt-3 text-[11px] text-[color:var(--light)]">
                این اعتبارسنجی تا ساعت {expiresAt} معتبر است؛ Checkout قیمت را دوباره محاسبه می‌کند.
              </p>
            ) : null}

            {canCheckout ? (
              <Link
                to="/checkout"
                className="mt-5 flex min-h-12 w-full items-center justify-center rounded-xl bg-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--night)]"
              >
                ادامه و انتخاب آدرس
              </Link>
            ) : (
              <Button type="button" disabled className="mt-5 w-full">
                ابتدا تأیید سرور لازم است
              </Button>
            )}
            <Link
              to="/products"
              className="mt-3 block w-full rounded-xl border border-[color:var(--mid)] py-3 text-center text-sm text-[color:var(--light)]"
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
