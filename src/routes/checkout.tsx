import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { CircleCheck } from "lucide-react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { getProduct, getRoastery } from "@/data/seed";
import { productImage } from "@/lib/product-images";
import { formatToman, toFa } from "@/lib/persian";
import { useCart } from "@/lib/cart-context";

export const Route = createFileRoute("/checkout")({
  head: () => ({
    meta: [
      { title: "تسویه‌حساب | رستا" },
      { name: "description", content: "تکمیل سفارش در مارکت‌پلیس قهوه رستا." },
      { name: "robots", content: "noindex,follow" },
    ],
    links: [{ rel: "canonical", href: "/checkout" }],
  }),
  component: CheckoutPage,
});

const CITIES = ["تهران", "کرج", "اصفهان", "مشهد", "شیراز", "تبریز", "سایر"];

interface FormState {
  name: string;
  phone: string;
  city: string;
  address: string;
  postal: string;
  payment: "online" | "cod";
  notes: string;
}

function CheckoutPage() {
  const { items: raw, subtotal, clear } = useCart();
  const [form, setForm] = useState<FormState>({
    name: "",
    phone: "",
    city: "",
    address: "",
    postal: "",
    payment: "online",
    notes: "",
  });
  const [attempted, setAttempted] = useState(false);
  const [placed, setPlaced] = useState<null | { orderNumber: string }>(null);

  const items = useMemo(
    () =>
      raw
        .map((i) => {
          const product = getProduct(i.productSlug);
          return product ? { ...i, product } : null;
        })
        .filter((x): x is NonNullable<typeof x> & { product: NonNullable<ReturnType<typeof getProduct>> } => x !== null),
    [raw],
  );

  const grouped = useMemo(() => {
    const g = new Map<string, typeof items>();
    for (const it of items) {
      const k = it.product.roasterySlug;
      if (!g.has(k)) g.set(k, []);
      g.get(k)!.push(it);
    }
    return g;
  }, [items]);

  const phoneValid = /^09\d{9}$/.test(form.phone);
  const nameValid = form.name.trim().length > 0;
  const cityValid = form.city.length > 0;
  const addressValid = form.address.trim().length > 0;
  const canSubmit = nameValid && phoneValid && cityValid && addressValid;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setAttempted(true);
    if (!canSubmit) return;
    // TODO: replace with real order creation API call + redirect to /orders/:id when backend ready
    const orderNumber = String(Math.floor(10000 + Math.random() * 90000));
    setPlaced({ orderNumber });
    clear();
  };

  const setField = <K extends keyof FormState>(k: K, v: FormState[K]) =>
    setForm((f) => ({ ...f, [k]: v }));

  if (placed) {
    return (
      <>
        <Navbar />
        <main className="mx-auto max-w-xl px-4 py-20 text-center">
          <CircleCheck size={64} className="mx-auto text-[color:var(--roast)]" />
          <h1 className="mt-4 font-display text-3xl font-bold text-[color:var(--steam)]">
            سفارش شما ثبت شد ☕
          </h1>
          <p className="mt-2 text-sm text-[color:var(--light)]">
            به‌زودی با شما تماس گرفته می‌شود
          </p>
          <p className="mt-4 font-mono-num text-sm text-[color:var(--light)]">
            شماره سفارش: #{toFa(placed.orderNumber)}
          </p>
          <Link
            to="/"
            className="mt-8 inline-block rounded-lg bg-[color:var(--roast)] px-6 py-3 text-sm font-bold text-[color:var(--night)]"
          >
            بازگشت به صفحه اصلی
          </Link>
        </main>
        <Footer />
      </>
    );
  }

  if (raw.length === 0) {
    return (
      <>
        <Navbar />
        <main className="mx-auto max-w-3xl px-4 py-16 text-center">
          <h1 className="text-2xl font-bold text-[color:var(--steam)]">سبد خرید خالی است</h1>
          <p className="mt-2 text-sm text-[color:var(--light)]">
            برای تسویه‌حساب ابتدا محصول اضافه کنید.
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

  const inputCls =
    "w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 py-2.5 text-sm outline-none focus:border-[color:var(--roast)] text-[color:var(--steam)]";
  const labelCls = "block text-xs font-bold text-[color:var(--light)] mb-1.5";
  const cardCls =
    "rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5";
  const errCls = "mt-1 text-xs text-red-400";

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "سبد خرید", to: "/cart" },
            { label: "تسویه‌حساب" },
          ]}
        />
        <h1 className="text-2xl font-bold text-[color:var(--steam)]">تسویه‌حساب</h1>

        <form
          onSubmit={handleSubmit}
          className="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]"
        >
          <div className="space-y-5">
            {/* Recipient */}
            <section className={cardCls}>
              <h2 className="mb-4 text-sm font-bold text-[color:var(--steam)]">
                اطلاعات گیرنده
              </h2>
              <div className="grid gap-3 sm:grid-cols-2">
                <div>
                  <label className={labelCls} htmlFor="name">
                    نام و نام خانوادگی
                  </label>
                  <input
                    id="name"
                    type="text"
                    value={form.name}
                    onChange={(e) => setField("name", e.target.value)}
                    className={inputCls}
                  />
                  {attempted && !nameValid && (
                    <p className={errCls}>نام را وارد کنید</p>
                  )}
                </div>
                <div>
                  <label className={labelCls} htmlFor="phone">
                    شماره موبایل
                  </label>
                  <input
                    id="phone"
                    type="tel"
                    dir="ltr"
                    placeholder="۰۹xxxxxxxxx"
                    value={form.phone}
                    onChange={(e) => setField("phone", e.target.value)}
                    className={`${inputCls} text-right`}
                  />
                  {attempted && !phoneValid && (
                    <p className={errCls}>شماره موبایل معتبر (۱۱ رقم، شروع با ۰۹)</p>
                  )}
                </div>
              </div>
            </section>

            {/* Address */}
            <section className={cardCls}>
              <h2 className="mb-4 text-sm font-bold text-[color:var(--steam)]">
                آدرس تحویل
              </h2>
              <div className="space-y-3">
                <div>
                  <label className={labelCls} htmlFor="city">شهر</label>
                  <select
                    id="city"
                    value={form.city}
                    onChange={(e) => setField("city", e.target.value)}
                    className={inputCls}
                  >
                    <option value="">انتخاب کنید</option>
                    {CITIES.map((c) => (
                      <option key={c} value={c}>{c}</option>
                    ))}
                  </select>
                  {attempted && !cityValid && <p className={errCls}>شهر را انتخاب کنید</p>}
                </div>
                <div>
                  <label className={labelCls} htmlFor="address">آدرس کامل</label>
                  <textarea
                    id="address"
                    rows={3}
                    value={form.address}
                    onChange={(e) => setField("address", e.target.value)}
                    className={inputCls}
                  />
                  {attempted && !addressValid && (
                    <p className={errCls}>آدرس را وارد کنید</p>
                  )}
                </div>
                <div>
                  <label className={labelCls} htmlFor="postal">کد پستی (اختیاری)</label>
                  <input
                    id="postal"
                    type="text"
                    dir="ltr"
                    value={form.postal}
                    onChange={(e) => setField("postal", e.target.value)}
                    className={`${inputCls} text-right`}
                  />
                </div>
              </div>
            </section>

            {/* Payment */}
            <section className={cardCls}>
              <h2 className="mb-4 text-sm font-bold text-[color:var(--steam)]">
                روش پرداخت
              </h2>
              <div className="grid gap-3 sm:grid-cols-2">
                <button
                  type="button"
                  onClick={() => setField("payment", "online")}
                  className={`rounded-xl border p-4 text-right transition ${
                    form.payment === "online"
                      ? "border-[color:var(--roast)] bg-[color:var(--roast)]/10"
                      : "border-[color:var(--mid)] bg-[color:var(--night)]"
                  }`}
                >
                  <div className="text-sm font-bold text-[color:var(--steam)]">
                    پرداخت آنلاین (زرین‌پال)
                  </div>
                  <div className="mt-1 text-xs text-[color:var(--light)]">
                    امن و سریع
                  </div>
                </button>
                <div
                  aria-disabled
                  className="cursor-not-allowed rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 text-right opacity-50"
                >
                  <div className="text-sm font-bold text-[color:var(--steam)]">
                    پرداخت در محل
                  </div>
                  <div className="mt-1 text-xs text-[color:var(--light)]">به‌زودی</div>
                </div>
              </div>
            </section>

            {/* Notes */}
            <section className={cardCls}>
              <h2 className="mb-3 text-sm font-bold text-[color:var(--steam)]">
                یادداشت سفارش
              </h2>
              <textarea
                rows={3}
                value={form.notes}
                onChange={(e) => setField("notes", e.target.value)}
                placeholder="توضیحات اضافه برای روستری (اختیاری)"
                className={inputCls}
              />
            </section>
          </div>

          {/* Summary */}
          <aside className="h-fit rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 lg:sticky lg:top-20">
            <h2 className="text-sm font-bold text-[color:var(--steam)]">خلاصه سفارش</h2>
            <div className="mt-3 space-y-4">
              {Array.from(grouped.entries()).map(([slug, list]) => {
                const roastery = getRoastery(slug);
                return (
                  <div key={slug}>
                    <div className="text-[11px] font-bold text-[color:var(--light)]">
                      {roastery?.name}
                    </div>
                    <ul className="mt-2 space-y-2">
                      {list.map((it) => (
                        <li key={`${it.productSlug}-${it.weight}-${it.grind}`} className="flex gap-2">
                          <img
                            src={productImage(it.product.slug, 96)}
                            alt=""
                            width={48}
                            height={48}
                            loading="lazy"
                            className="h-12 w-12 shrink-0 rounded-md object-cover"
                          />
                          <div className="flex-1 text-xs">
                            <div className="font-bold text-[color:var(--steam)]">
                              {it.product.name}
                            </div>
                            <div className="text-[color:var(--light)]">
                              {toFa(it.weight)}گ · {it.grind} · ×{toFa(it.qty)}
                            </div>
                          </div>
                          <div className="font-mono-num text-xs font-bold text-[color:var(--roast)]">
                            {formatToman(it.product.prices[it.weight] * it.qty)}
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                );
              })}
            </div>
            <dl className="mt-4 space-y-2 border-t border-[color:var(--mid)] pt-4 text-sm">
              <div className="flex justify-between text-[color:var(--light)]">
                <dt>جمع اقلام</dt>
                <dd className="font-mono-num">{formatToman(subtotal)}</dd>
              </div>
              <div className="flex justify-between text-[color:var(--light)]">
                <dt>هزینه ارسال</dt>
                <dd className="text-xs">محاسبه پس از تایید آدرس</dd>
              </div>
              <div className="flex justify-between border-t border-[color:var(--mid)] pt-2 text-base font-bold text-[color:var(--steam)]">
                <dt>مجموع</dt>
                <dd className="font-mono-num text-[color:var(--roast)]">
                  {formatToman(subtotal)}
                </dd>
              </div>
            </dl>
            <button
              type="submit"
              disabled={!canSubmit}
              className="mt-4 w-full rounded-lg bg-[color:var(--roast)] py-3 text-sm font-bold text-[color:var(--night)] transition disabled:cursor-not-allowed disabled:opacity-40"
            >
              ثبت سفارش
            </button>
          </aside>
        </form>
      </main>
      <Footer />
    </>
  );
}
