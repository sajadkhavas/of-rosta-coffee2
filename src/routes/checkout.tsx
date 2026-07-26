import { createFileRoute, Link } from "@tanstack/react-router";
import { useMutation, useQuery } from "@tanstack/react-query";
import { fallback, zodValidator } from "@tanstack/zod-adapter";
import { useEffect, useState } from "react";
import { CheckCircle2, Clock3, CreditCard, RefreshCw, ShieldCheck } from "lucide-react";
import { z } from "zod";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Alert, Button, TextareaField, TextField } from "@/components/system";
import { absoluteUrl } from "@/config/site";
import {
  createCheckoutQuote,
  createOrder,
  requestPayment,
  verifyPayment,
} from "@/lib/api/checkout";
import { isApiError } from "@/lib/api/client";
import { addressesQueryOptions } from "@/lib/api/identity";
import { formatIrr, formatWeight } from "@/lib/catalog-format";
import { useCart } from "@/lib/cart-context";
import type { VerifiedPaymentResult } from "@/lib/payment-security";
import {
  buildOrderFingerprint,
  buildPaymentFingerprint,
  clearPaymentExpectation,
  clearTransactionIntent,
  getOrCreateTransactionIntent,
  readPaymentExpectation,
  savePaymentExpectation,
} from "@/lib/transaction-intent";

const callbackIdSchema = z
  .string()
  .trim()
  .max(200)
  .regex(/^[A-Za-z0-9._:-]+$/);
const searchSchema = z.object({
  payment_id: fallback(callbackIdSchema, "").default(""),
  order_id: fallback(callbackIdSchema, "").default(""),
  status: fallback(z.string().trim().max(40), "").default(""),
});

interface PaymentVerificationResult extends VerifiedPaymentResult {
  expectationFound: boolean;
  consistent: boolean;
}

interface PaymentVerificationQuery {
  data?: PaymentVerificationResult;
  isPending: boolean;
  isError: boolean;
  error: unknown;
  refetch: () => unknown;
}

export const Route = createFileRoute("/checkout")({
  validateSearch: zodValidator(searchSchema),
  head: () => ({
    meta: [
      { title: "تسویه‌حساب امن | رستا" },
      {
        name: "description",
        content: "انتخاب آدرس، Quote معتبر، رزرو موجودی و پرداخت آنلاین سفارش رستا.",
      },
      { name: "robots", content: "noindex,nofollow" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/checkout") }],
  }),
  component: CheckoutPage,
});

function CheckoutPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <AccountGuard>{() => <CheckoutContent />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function CheckoutContent() {
  const search = Route.useSearch();
  const { items, hydrated, apiItems, clear } = useCart();
  const addressesQuery = useQuery(addressesQueryOptions());
  const [selectedAddressId, setSelectedAddressId] = useState("");
  const [couponInput, setCouponInput] = useState("");
  const [couponCode, setCouponCode] = useState("");
  const [notes, setNotes] = useState("");

  useEffect(() => {
    if (selectedAddressId || !addressesQuery.data?.length) return;
    const preferred =
      addressesQuery.data.find((address) => address.isDefault) ?? addressesQuery.data[0];
    setSelectedAddressId(preferred.id);
  }, [addressesQuery.data, selectedAddressId]);

  const quoteQuery = useQuery({
    queryKey: ["checkout", "quote", apiItems, selectedAddressId, couponCode],
    queryFn: () =>
      createCheckoutQuote({
        items: apiItems,
        addressId: selectedAddressId,
        couponCode: couponCode || null,
      }),
    enabled: hydrated && apiItems.length > 0 && Boolean(selectedAddressId) && !search.payment_id,
    staleTime: 0,
    retry: false,
  });

  const paymentExpectation = search.payment_id ? readPaymentExpectation(search.payment_id) : null;

  const verifyQuery = useQuery<PaymentVerificationResult>({
    queryKey: [
      "payments",
      "verify",
      search.payment_id,
      paymentExpectation?.orderId ?? "unbound",
      paymentExpectation?.amount ?? 0,
    ],
    queryFn: async () => {
      const verified = await verifyPayment(search.payment_id);
      return {
        ...verified,
        expectationFound: Boolean(paymentExpectation),
        consistent: verified.status === "paid" && Boolean(paymentExpectation),
      };
    },
    enabled: Boolean(search.payment_id),
    staleTime: 0,
    retry: false,
  });

  useEffect(() => {
    if (verifyQuery.data?.status !== "paid" || !verifyQuery.data.consistent) return;
    clear();
    clearTransactionIntent("order");
    clearTransactionIntent("payment");
    clearPaymentExpectation();
  }, [clear, verifyQuery.data?.consistent, verifyQuery.data?.status]);

  const checkoutMutation = useMutation({
    mutationFn: async () => {
      const quote = quoteQuery.data;
      if (!quote) throw new Error("Quote معتبر در دسترس نیست.");

      const orderFingerprint = buildOrderFingerprint({
        quoteId: quote.id,
        addressId: selectedAddressId,
        couponCode,
        notes,
        items: apiItems,
      });
      const order = await createOrder({
        quoteId: quote.id,
        idempotencyKey: getOrCreateTransactionIntent("order", orderFingerprint),
        notes,
      });
      if (order.grandTotal !== quote.grandTotal || order.currency !== quote.currency) {
        throw new Error("مبلغ سفارش ایجادشده با Quote معتبر این Checkout سازگار نیست.");
      }

      const payment = await requestPayment({
        orderId: order.id,
        idempotencyKey: getOrCreateTransactionIntent(
          "payment",
          buildPaymentFingerprint({
            orderId: order.id,
            amount: order.grandTotal,
            currency: order.currency,
          }),
        ),
      });
      savePaymentExpectation(payment.paymentId, order.id, order.grandTotal, order.currency);
      window.location.assign(payment.redirectUrl);
      return { order, payment };
    },
    retry: false,
  });

  if (search.payment_id) {
    return <PaymentVerification paymentId={search.payment_id} query={verifyQuery} />;
  }

  if (!hydrated) {
    return (
      <div className="grid min-h-[45vh] place-items-center" role="status">
        <div className="text-center">
          <div className="mx-auto size-9 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
          <p className="mt-4 text-sm text-[color:var(--light)]">در حال آماده‌سازی Checkout…</p>
        </div>
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <section className="mx-auto grid min-h-[55vh] max-w-xl place-items-center text-center">
        <div>
          <h1 className="text-2xl font-bold">سبد خرید خالی است</h1>
          <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
            برای ایجاد Quote و سفارش، ابتدا یک Variant موجود را به سبد اضافه کنید.
          </p>
          <Link
            to="/products"
            className="mt-6 inline-flex min-h-11 items-center rounded-xl bg-[color:var(--roast)] px-6 text-sm font-bold text-[color:var(--night)]"
          >
            مشاهده محصولات
          </Link>
        </div>
      </section>
    );
  }

  const quote = quoteQuery.data;
  const addresses = addressesQuery.data ?? [];
  const expiresAt = quote?.expiresAt
    ? new Date(quote.expiresAt).toLocaleTimeString("fa-IR", { hour: "2-digit", minute: "2-digit" })
    : null;

  return (
    <>
      <Breadcrumb
        items={[
          { label: "خانه", to: "/" },
          { label: "سبد خرید", to: "/cart" },
          { label: "تسویه‌حساب" },
        ]}
      />
      <header className="mt-4">
        <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">
          ATOMIC CHECKOUT
        </p>
        <h1 className="mt-2 text-3xl font-bold">تسویه‌حساب</h1>
        <p className="mt-3 max-w-3xl text-sm leading-7 text-[color:var(--light)]">
          Quote نهایی با آدرس انتخابی محاسبه می‌شود؛ سپس سفارش با کلید Idempotency متصل به محتوای
          سفارش ایجاد و موجودی به‌صورت اتمیک رزرو می‌شود.
        </p>
      </header>

      <div className="mt-5 grid gap-3 sm:grid-cols-3">
        {[
          [ShieldCheck, "قیمت سمت سرور"],
          [Clock3, "رزرو اتمیک موجودی"],
          [CreditCard, "Verify پرداخت"],
        ].map(([Icon, label]) => {
          const StepIcon = Icon as typeof ShieldCheck;
          return (
            <div
              key={label as string}
              className="flex items-center gap-3 rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-3 text-xs text-[color:var(--light)]"
            >
              <StepIcon size={18} className="text-[color:var(--roast)]" />
              {label as string}
            </div>
          );
        })}
      </div>

      {checkoutMutation.isError ? (
        <div className="mt-5">
          <Alert variant="danger" title="ثبت سفارش یا اتصال به درگاه انجام نشد">
            {isApiError(checkoutMutation.error)
              ? checkoutMutation.error.message
              : checkoutMutation.error instanceof Error
                ? checkoutMutation.error.message
                : "عملیات Checkout کامل نشد."}
          </Alert>
        </div>
      ) : null}

      <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_370px]">
        <div className="space-y-5">
          <section
            className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
            aria-labelledby="address-heading"
          >
            <div className="flex items-center justify-between gap-4">
              <h2 id="address-heading" className="font-bold">
                آدرس تحویل
              </h2>
              <Link
                to="/profile"
                className="text-xs text-[color:var(--roast)] underline underline-offset-4"
              >
                مدیریت آدرس‌ها
              </Link>
            </div>

            {addressesQuery.isPending ? (
              <p className="mt-4 text-sm text-[color:var(--light)]">در حال دریافت آدرس‌ها…</p>
            ) : addressesQuery.isError ? (
              <div className="mt-4">
                <Alert variant="danger" title="آدرس‌ها بارگذاری نشدند">
                  {isApiError(addressesQuery.error)
                    ? addressesQuery.error.message
                    : "ارتباط با سرویس آدرس برقرار نشد."}
                </Alert>
                <Button type="button" className="mt-4" onClick={() => addressesQuery.refetch()}>
                  تلاش مجدد
                </Button>
              </div>
            ) : addresses.length === 0 ? (
              <div className="mt-4">
                <Alert variant="warning" title="ابتدا یک آدرس ثبت کنید">
                  Checkout فقط با `address_id` معتبر سمت سرور ادامه پیدا می‌کند.
                </Alert>
                <Link
                  to="/profile"
                  className="mt-4 inline-flex min-h-11 items-center rounded-xl bg-[color:var(--roast)] px-5 text-sm font-bold text-[color:var(--night)]"
                >
                  ثبت آدرس در پروفایل
                </Link>
              </div>
            ) : (
              <div className="mt-4 grid gap-3">
                {addresses.map((address) => (
                  <label
                    key={address.id}
                    className={`flex cursor-pointer gap-3 rounded-xl border p-4 transition ${selectedAddressId === address.id ? "border-[color:var(--roast)] bg-[color:var(--roast)]/10" : "border-[color:var(--mid)] bg-[color:var(--night)]"}`}
                  >
                    <input
                      type="radio"
                      name="address"
                      value={address.id}
                      checked={selectedAddressId === address.id}
                      onChange={() => setSelectedAddressId(address.id)}
                      className="mt-1 size-4 accent-[color:var(--roast)]"
                    />
                    <span className="min-w-0 text-sm">
                      <span className="flex flex-wrap items-center gap-2 font-bold">
                        {address.title || "آدرس تحویل"}
                        {address.isDefault ? (
                          <span className="rounded-full bg-[color:var(--roast)] px-2 py-0.5 text-[10px] text-[color:var(--night)]">
                            پیش‌فرض
                          </span>
                        ) : null}
                      </span>
                      <span className="mt-2 block leading-7 text-[color:var(--light)]">
                        {address.province}، {address.city}، {address.addressLine}
                      </span>
                      <span dir="ltr" className="mt-1 block text-xs text-[color:var(--light)]">
                        {address.recipientMobile}
                      </span>
                    </span>
                  </label>
                ))}
              </div>
            )}
          </section>

          <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
            <h2 className="font-bold">کد تخفیف</h2>
            <div className="mt-4 flex gap-3">
              <TextField
                label="کد تخفیف"
                value={couponInput}
                onChange={(event) => setCouponInput(event.target.value)}
                className="flex-1"
              />
              <Button
                type="button"
                variant="secondary"
                className="self-end"
                onClick={() => setCouponCode(couponInput.trim().slice(0, 100))}
                disabled={!selectedAddressId || couponInput.trim() === couponCode}
              >
                اعمال
              </Button>
            </div>
            {couponCode ? (
              <button
                type="button"
                onClick={() => {
                  setCouponCode("");
                  setCouponInput("");
                }}
                className="mt-3 text-xs text-[color:var(--roast)] underline"
              >
                حذف کد «{couponCode}»
              </button>
            ) : null}
          </section>

          <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
            <TextareaField
              label="یادداشت سفارش"
              rows={4}
              maxLength={1000}
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
            />
            <p className="mt-2 text-end text-[11px] text-[color:var(--light)]">
              {notes.length.toLocaleString("fa-IR")} / ۱۰۰۰
            </p>
          </section>
        </div>

        <aside className="h-fit rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 lg:sticky lg:top-20">
          <div className="flex items-center justify-between gap-3">
            <h2 className="font-bold">
              خلاصه سفارش · {quote?.groups.length.toLocaleString("fa-IR") ?? "—"} روستری
            </h2>
            <button
              type="button"
              onClick={() => quoteQuery.refetch()}
              disabled={!selectedAddressId || quoteQuery.isFetching}
              aria-label="محاسبه دوباره Quote"
              className="grid size-10 place-items-center rounded-lg border border-[color:var(--mid)] disabled:opacity-40"
            >
              <RefreshCw size={16} className={quoteQuery.isFetching ? "animate-spin" : ""} />
            </button>
          </div>

          <ul className="mt-4 space-y-3">
            {items.map((item) => (
              <li key={item.variantId} className="flex items-start justify-between gap-3 text-xs">
                <div className="min-w-0">
                  <p className="truncate font-bold">{item.productName}</p>
                  <p className="mt-1 text-[color:var(--light)]">
                    {item.roasteryName} · {formatWeight(item.weightGrams)} · ×
                    {item.quantity.toLocaleString("fa-IR")}
                  </p>
                  <p className="mt-1 text-[10px] text-[color:var(--light)]">
                    {item.packagingFeeAmount === 0
                      ? "بسته‌بندی رایگان"
                      : `بسته‌بندی ${formatIrr(item.packagingFeeAmount * item.quantity)}`}
                  </p>
                </div>
                <span className="shrink-0 font-mono text-[color:var(--light)]">
                  {formatIrr(item.unitPriceSnapshot * item.quantity)}
                </span>
              </li>
            ))}
          </ul>

          {quoteQuery.isPending && selectedAddressId ? (
            <div className="mt-5 border-t border-[color:var(--mid)] pt-5 text-center" role="status">
              <div className="mx-auto size-7 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
              <p className="mt-3 text-xs text-[color:var(--light)]">محاسبه ارسال و مبلغ نهایی…</p>
            </div>
          ) : quoteQuery.isError ? (
            <div className="mt-5 border-t border-[color:var(--mid)] pt-5">
              <Alert variant="danger" title="Quote ایجاد نشد">
                {isApiError(quoteQuery.error)
                  ? quoteQuery.error.message
                  : "قیمت یا موجودی قابل تأیید نیست."}
              </Alert>
            </div>
          ) : quote ? (
            <>
              {quote.warnings.length ? (
                <div className="mt-5 space-y-2">
                  {quote.warnings.map((warning, index) => (
                    <Alert key={`${warning.code}-${index}`} variant="warning" title="پیام Checkout">
                      {warning.message}
                    </Alert>
                  ))}
                </div>
              ) : null}
              <dl className="mt-5 space-y-3 border-t border-[color:var(--mid)] pt-4 text-sm">
                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>جمع اقلام</dt>
                  <dd className="font-mono">{formatIrr(quote.subtotal)}</dd>
                </div>
                <div className="flex justify-between text-[color:var(--light)]">
                  <dt>بسته‌بندی روستری</dt>
                  <dd className="font-mono">
                    {quote.packagingTotal === 0 ? "رایگان" : formatIrr(quote.packagingTotal)}
                  </dd>
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
                  <dt>قابل پرداخت</dt>
                  <dd className="font-mono text-[color:var(--roast)]">
                    {formatIrr(quote.grandTotal)}
                  </dd>
                </div>
              </dl>
              {expiresAt ? (
                <p className="mt-3 text-[11px] text-[color:var(--light)]">
                  Quote تا ساعت {expiresAt} معتبر است.
                </p>
              ) : null}
            </>
          ) : null}

          <Button
            type="button"
            className="mt-5 w-full"
            disabled={!quote || checkoutMutation.isPending || addresses.length === 0}
            onClick={() => checkoutMutation.mutate()}
          >
            {checkoutMutation.isPending ? "ایجاد سفارش و اتصال…" : "ثبت سفارش و پرداخت آنلاین"}
          </Button>
          <p className="mt-3 text-center text-[11px] leading-6 text-[color:var(--light)]">
            با زدن این دکمه، سرور Quote را دوباره کنترل و موجودی را رزرو می‌کند.
          </p>
          <Link
            to="/cart"
            className="mt-3 block text-center text-xs text-[color:var(--roast)] underline underline-offset-4"
          >
            بازگشت به سبد
          </Link>
        </aside>
      </div>
    </>
  );
}

function PaymentVerification({
  paymentId,
  query,
}: {
  paymentId: string;
  query: PaymentVerificationQuery;
}) {
  const result = query.data;

  return (
    <section className="mx-auto grid min-h-[55vh] max-w-xl place-items-center text-center">
      <div className="w-full rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-7">
        {query.isPending ? (
          <div role="status">
            <div className="mx-auto size-10 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
            <h1 className="mt-5 text-2xl font-bold">در حال تأیید پرداخت</h1>
            <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
              وضعیت تراکنش {paymentId} مستقیماً از سرور پرداخت بررسی می‌شود.
            </p>
          </div>
        ) : query.isError ? (
          <>
            <h1 className="text-2xl font-bold">تأیید پرداخت انجام نشد</h1>
            <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
              {isApiError(query.error)
                ? query.error.message
                : query.error instanceof Error
                  ? query.error.message
                  : "ارتباط با سرویس Verify برقرار نشد."}
            </p>
            <Button type="button" className="mt-6" onClick={() => query.refetch()}>
              بررسی دوباره
            </Button>
          </>
        ) : result?.status === "paid" && result.consistent ? (
          <>
            <CheckCircle2 size={56} className="mx-auto text-emerald-300" />
            <h1 className="mt-5 text-2xl font-bold">پرداخت با موفقیت تأیید شد</h1>
            <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
              پرداخت، مبلغ و وضعیت سفارش با Intent همین مرورگر تطبیق داشت؛ سبد پاک شد.
            </p>
            <Link
              to="/orders/$id"
              params={{ id: result.orderId }}
              className="mt-6 inline-flex min-h-11 items-center rounded-xl bg-[color:var(--roast)] px-6 text-sm font-bold text-[color:var(--night)]"
            >
              مشاهده سفارش
            </Link>
          </>
        ) : (
          <>
            <h1 className="text-2xl font-bold">پرداخت تأیید نشد</h1>
            <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
              وضعیت ثبت‌شده: {result?.status ?? "نامشخص"}. تا زمان دریافت پاسخ `paid` کاملاً منطبق،
              سبد پاک نمی‌شود.
            </p>
            <div className="mt-6 flex flex-wrap justify-center gap-3">
              <Button type="button" onClick={() => query.refetch()}>
                بررسی دوباره
              </Button>
              <Link
                to="/orders"
                className="inline-flex min-h-11 items-center rounded-xl border border-[color:var(--mid)] px-5 text-sm"
              >
                سفارش‌های من
              </Link>
            </div>
          </>
        )}
      </div>
    </section>
  );
}
