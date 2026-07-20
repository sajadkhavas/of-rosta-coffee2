import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState, type FormEvent } from "react";
import { Info, LockKeyhole } from "lucide-react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import {
  Alert,
  Button,
  FormSummary,
  TextareaField,
  TextField,
} from "@/components/system";
import { getProduct, getRoastery } from "@/data/seed";
import { productImage } from "@/lib/product-images";
import { formatToman, toFa } from "@/lib/persian";
import { useCart } from "@/lib/cart-context";
import { absoluteUrl } from "@/config/site";

export const Route = createFileRoute("/checkout")({
  head: () => ({
    meta: [
      { title: "تسویه‌حساب آزمایشی | رستا" },
      {
        name: "description",
        content: "پیش‌نمایش غیرعملیاتی تسویه‌حساب رستا تا اتصال سفارش، موجودی و درگاه پرداخت.",
      },
      { name: "robots", content: "noindex,nofollow" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/checkout") }],
  }),
  component: CheckoutPage,
});

const CHECKOUT_LIVE = false;
const CITIES = ["تهران", "کرج", "اصفهان", "مشهد", "شیراز", "تبریز", "سایر"];

interface FormState {
  name: string;
  phone: string;
  city: string;
  address: string;
  postal: string;
  notes: string;
}

function CheckoutPage() {
  const { items: raw, subtotal } = useCart();
  const [submitted, setSubmitted] = useState(false);
  const [form, setForm] = useState<FormState>({
    name: "",
    phone: "",
    city: "",
    address: "",
    postal: "",
    notes: "",
  });

  const items = useMemo(
    () =>
      raw
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
        ),
    [raw],
  );

  const grouped = useMemo(() => {
    const groups = new Map<string, typeof items>();
    for (const item of items) {
      const slug = item.product.roasterySlug;
      if (!groups.has(slug)) groups.set(slug, []);
      groups.get(slug)!.push(item);
    }
    return groups;
  }, [items]);

  const errors = [
    !form.name.trim()
      ? { fieldId: "checkout-name", message: "نام و نام خانوادگی الزامی است." }
      : null,
    !/^09\d{9}$/.test(form.phone)
      ? { fieldId: "checkout-phone", message: "شماره موبایل معتبر وارد کنید." }
      : null,
    !form.city ? { fieldId: "checkout-city", message: "شهر را انتخاب کنید." } : null,
    form.address.trim().length < 10
      ? { fieldId: "checkout-address", message: "آدرس کامل را وارد کنید." }
      : null,
  ].filter((error): error is NonNullable<typeof error> => error !== null);

  const setField = <Key extends keyof FormState>(key: Key, value: FormState[Key]) =>
    setForm((current) => ({ ...current, [key]: value }));

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitted(true);
    if (!CHECKOUT_LIVE || errors.length > 0) return;
  };

  if (raw.length === 0) {
    return (
      <>
        <Navbar />
        <main className="mx-auto grid min-h-[60vh] max-w-xl place-items-center px-4 py-16 text-center">
          <section>
            <h1 className="text-2xl font-bold">سبد خرید خالی است</h1>
            <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
              افزودن محصول تا اتصال Variant، Quote سمت سرور، رزرو موجودی و پرداخت در فاز ۵ غیرفعال است.
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
  const cardClassName =
    "rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5";

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
        <header className="mt-4">
          <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">
            DEVELOPMENT PREVIEW
          </p>
          <h1 className="mt-2 text-3xl font-bold">تسویه‌حساب</h1>
          <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
            اطلاعات این صفحه فقط برای بررسی تجربه کاربری است و هیچ سفارش یا پرداختی ایجاد نمی‌کند.
          </p>
        </header>

        <div className="mt-6 grid gap-3">
          <Alert variant="warning" title="پرداخت و ثبت سفارش غیرفعال است">
            <span className="inline-flex items-start gap-2">
              <Info size={18} className="mt-1 shrink-0" />
              فعال‌سازی فقط پس از اتصال Quote سمت سرور، بررسی موجودی، ایجاد Order واقعی، Split Payment و Payment Verify انجام می‌شود.
            </span>
          </Alert>
          {multipleRoasteries ? (
            <Alert variant="danger" title="سبد چندروستری قابل ثبت نیست">
              طبق معماری رستا هر سفارش فقط می‌تواند متعلق به یک روستری باشد. در فاز ۵، افزودن محصول روستری دوم با تأیید پاک‌کردن سبد کنترل خواهد شد.
            </Alert>
          ) : null}
        </div>

        <form
          onSubmit={handleSubmit}
          className="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]"
          noValidate
        >
          <div className="space-y-5">
            {submitted ? <FormSummary errors={errors} /> : null}

            <section className={cardClassName} aria-labelledby="recipient-title">
              <h2 id="recipient-title" className="mb-4 font-bold">
                اطلاعات گیرنده
              </h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  id="checkout-name"
                  label="نام و نام خانوادگی"
                  autoComplete="name"
                  value={form.name}
                  onChange={(event) => setField("name", event.target.value)}
                  error={
                    submitted && !form.name.trim()
                      ? "نام و نام خانوادگی الزامی است."
                      : undefined
                  }
                />
                <TextField
                  id="checkout-phone"
                  label="شماره موبایل"
                  type="tel"
                  dir="ltr"
                  inputMode="numeric"
                  autoComplete="tel"
                  value={form.phone}
                  onChange={(event) => setField("phone", event.target.value)}
                  error={
                    submitted && !/^09\d{9}$/.test(form.phone)
                      ? "شماره موبایل معتبر نیست."
                      : undefined
                  }
                />
              </div>
            </section>

            <section className={cardClassName} aria-labelledby="address-title">
              <h2 id="address-title" className="mb-4 font-bold">
                آدرس تحویل
              </h2>
              <div className="grid gap-4">
                <label
                  htmlFor="checkout-city"
                  className="grid gap-2 text-sm font-bold text-[color:var(--steam)]"
                >
                  شهر
                  <select
                    id="checkout-city"
                    value={form.city}
                    onChange={(event) => setField("city", event.target.value)}
                    aria-invalid={submitted && !form.city}
                    className="min-h-12 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-4 text-sm font-normal outline-none focus:border-[color:var(--roast)]"
                  >
                    <option value="">انتخاب کنید</option>
                    {CITIES.map((city) => (
                      <option key={city} value={city}>
                        {city}
                      </option>
                    ))}
                  </select>
                  {submitted && !form.city ? (
                    <span role="alert" className="text-xs font-normal text-red-300">
                      شهر را انتخاب کنید.
                    </span>
                  ) : null}
                </label>
                <TextareaField
                  id="checkout-address"
                  label="آدرس کامل"
                  rows={3}
                  value={form.address}
                  onChange={(event) => setField("address", event.target.value)}
                  error={
                    submitted && form.address.trim().length < 10
                      ? "آدرس کامل‌تر وارد کنید."
                      : undefined
                  }
                />
                <TextField
                  id="checkout-postal"
                  label="کد پستی"
                  dir="ltr"
                  inputMode="numeric"
                  value={form.postal}
                  onChange={(event) => setField("postal", event.target.value)}
                />
              </div>
            </section>

            <section className={cardClassName} aria-labelledby="payment-title">
              <h2 id="payment-title" className="mb-4 font-bold">
                روش پرداخت
              </h2>
              <div
                aria-disabled="true"
                className="rounded-xl border border-[color:var(--roast)]/45 bg-[color:var(--roast)]/10 p-4 opacity-80"
              >
                <div className="flex items-center gap-2 text-sm font-bold">
                  <LockKeyhole size={17} className="text-[color:var(--roast)]" />
                  پرداخت آنلاین تسهیمی
                </div>
                <p className="mt-2 text-xs leading-6 text-[color:var(--light)]">
                  پس از اتصال درگاه دارای Split Payment و تأیید Callback فعال می‌شود.
                </p>
              </div>
            </section>

            <section className={cardClassName}>
              <TextareaField
                label="یادداشت سفارش"
                rows={3}
                value={form.notes}
                onChange={(event) => setField("notes", event.target.value)}
              />
            </section>
          </div>

          <aside className="h-fit rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 lg:sticky lg:top-20">
            <h2 className="font-bold">خلاصه آزمایشی</h2>
            <div className="mt-4 space-y-5">
              {Array.from(grouped.entries()).map(([slug, list]) => (
                <div key={slug}>
                  <p className="text-xs font-bold text-[color:var(--light)]">
                    {getRoastery(slug)?.name || slug}
                  </p>
                  <ul className="mt-2 space-y-3">
                    {list.map((item) => (
                      <li key={`${item.productSlug}-${item.weight}`} className="flex gap-3">
                        <img
                          src={productImage(item.product.slug, 96)}
                          alt=""
                          width={48}
                          height={48}
                          loading="lazy"
                          className="size-12 shrink-0 rounded-xl object-cover"
                        />
                        <div className="min-w-0 flex-1 text-xs">
                          <p className="font-bold">{item.product.name}</p>
                          <p className="mt-1 text-[color:var(--light)]">
                            {toFa(item.weight)} گرم · دانه کامل · ×{toFa(item.qty)}
                          </p>
                        </div>
                        <span className="font-mono text-xs font-bold text-[color:var(--roast)]">
                          {formatToman(item.product.prices[item.weight] * item.qty)}
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
            <dl className="mt-5 space-y-2 border-t border-[color:var(--mid)] pt-4 text-sm">
              <div className="flex justify-between text-[color:var(--light)]">
                <dt>جمع نمایشی</dt>
                <dd className="font-mono">{formatToman(subtotal)}</dd>
              </div>
              <div className="flex justify-between text-[color:var(--light)]">
                <dt>ارسال</dt>
                <dd className="text-xs">در Quote واقعی محاسبه می‌شود</dd>
              </div>
            </dl>
            <Button
              type="submit"
              disabled={!CHECKOUT_LIVE || multipleRoasteries}
              className="mt-5 w-full"
            >
              ثبت سفارش هنوز فعال نیست
            </Button>
            <Link
              to="/cart"
              className="mt-3 block text-center text-xs text-[color:var(--roast)] underline"
            >
              بازگشت به سبد
            </Link>
          </aside>
        </form>
      </main>
      <Footer />
    </>
  );
}
