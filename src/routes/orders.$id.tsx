import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Check, Copy } from "lucide-react";
import { useState } from "react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Alert, Button, Skeleton } from "@/components/system";
import { orderQueryOptions } from "@/lib/api/orders";
import { isApiError } from "@/lib/api/client";
import type { OrderStatus, SubOrderStatus } from "@/lib/api/contracts";
import {
  formatAccountDate,
  orderStatusLabels,
  statusBadgeClass,
  subOrderStatusLabels,
} from "@/lib/account-format";
import { formatIrr, formatWeight } from "@/lib/catalog-format";
import { absoluteUrl } from "@/config/site";

export const Route = createFileRoute("/orders/$id")({
  head: ({ params }) => ({
    meta: [
      { title: `سفارش #${params.id} | رستا` },
      { name: "robots", content: "noindex,nofollow" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl(`/orders/${params.id}`) }],
  }),
  component: OrderDetailPage,
});

const timelineSteps = [
  { key: "registered", label: "ثبت سفارش" },
  { key: "accepted", label: "تأیید روستری" },
  { key: "preparing", label: "در حال آماده‌سازی" },
  { key: "shipped", label: "ارسال" },
  { key: "delivered", label: "تحویل" },
] as const;

function orderStage(status: OrderStatus, subStatuses: SubOrderStatus[]): number {
  if (status === "delivered" || status === "partially_delivered") return 4;
  if (status === "shipped" || status === "partially_shipped") return 3;
  if (
    status === "processing" ||
    subStatuses.some((item) => ["preparing", "ready_to_ship"].includes(item))
  ) {
    return 2;
  }
  if (
    status === "paid" ||
    subStatuses.some((item) => ["accepted", "preparing", "ready_to_ship", "shipped", "delivered"].includes(item))
  ) {
    return 1;
  }
  return 0;
}

function StatusTimeline({ stage }: { stage: number }) {
  return (
    <ol className="grid gap-3 md:grid-cols-5" aria-label="مراحل سفارش">
      {timelineSteps.map((step, index) => {
        const done = index <= stage;
        const active = index === stage;
        return (
          <li key={step.key} className="relative flex items-center gap-3 md:flex-col md:text-center">
            <div
              className={`relative z-10 grid size-9 shrink-0 place-items-center rounded-full border-2 text-xs font-bold ${
                done
                  ? "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]"
                  : "border-[color:var(--mid)] bg-[color:var(--dark)] text-[color:var(--light)]"
              }`}
            >
              {done && !active ? <Check size={15} /> : (index + 1).toLocaleString("fa-IR")}
            </div>
            <span
              className={`text-xs ${
                active
                  ? "font-bold text-[color:var(--roast)]"
                  : done
                    ? "text-[color:var(--steam)]"
                    : "text-[color:var(--light)]"
              }`}
            >
              {step.label}
            </span>
            {index < timelineSteps.length - 1 ? (
              <span
                aria-hidden
                className={`absolute hidden h-0.5 w-[calc(100%-2.25rem)] translate-x-[calc(50%+1.125rem)] md:top-[1.05rem] md:block ${
                  index < stage ? "bg-[color:var(--roast)]" : "bg-[color:var(--mid)]"
                }`}
              />
            ) : null}
          </li>
        );
      })}
    </ol>
  );
}

function OrderDetailPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-5xl px-4 py-8">
        <AccountGuard>{() => <OrderContent />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function OrderContent() {
  const { id } = Route.useParams();
  const query = useQuery(orderQueryOptions(id));
  const [copiedCode, setCopiedCode] = useState<string>();

  const copyTracking = async (code: string) => {
    try {
      await navigator.clipboard.writeText(code);
      setCopiedCode(code);
      window.setTimeout(() => setCopiedCode(undefined), 1500);
    } catch {
      setCopiedCode(undefined);
    }
  };

  if (query.isPending) {
    return (
      <div className="grid gap-5">
        <Skeleton className="h-32" />
        <Skeleton className="h-44" />
        <Skeleton className="h-72" />
      </div>
    );
  }

  if (query.isError || !query.data) {
    const notFound = isApiError(query.error) && query.error.status === 404;
    return (
      <section className="mx-auto max-w-xl py-12 text-center">
        <h1 className="text-2xl font-bold">
          {notFound ? "سفارش پیدا نشد" : "جزئیات سفارش دریافت نشد"}
        </h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          {isApiError(query.error)
            ? query.error.message
            : "ارتباط با سرویس سفارش‌ها برقرار نشد."}
        </p>
        <div className="mt-6 flex justify-center gap-3">
          {!notFound ? <Button onClick={() => query.refetch()}>تلاش مجدد</Button> : null}
          <Link
            to="/orders"
            className="inline-flex min-h-11 items-center rounded-xl border border-[color:var(--mid)] px-5 text-sm font-bold"
          >
            بازگشت به سفارش‌ها
          </Link>
        </div>
      </section>
    );
  }

  const order = query.data;
  const stage = orderStage(
    order.status,
    order.subOrders.map((subOrder) => subOrder.status),
  );

  return (
    <>
      <Breadcrumb
        items={[
          { label: "خانه", to: "/" },
          { label: "سفارش‌های من", to: "/orders" },
          { label: `#${order.orderNumber}` },
        ]}
      />

      <header className="mt-5 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 sm:p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">ORDER</p>
            <h1 className="mt-2 text-2xl font-bold">سفارش #{order.orderNumber}</h1>
            <p className="mt-2 text-xs text-[color:var(--light)]">
              ثبت: {formatAccountDate(order.placedAt)}
            </p>
          </div>
          <span
            className={`rounded-full border px-3 py-1 text-xs font-bold ${statusBadgeClass(order.status)}`}
          >
            {orderStatusLabels[order.status]}
          </span>
        </div>
      </header>

      {[
        "cancelled",
        "partially_cancelled",
        "refunded",
      ].includes(order.status) ? (
        <div className="mt-5">
          <Alert
            variant={order.status === "refunded" ? "info" : "warning"}
            title={orderStatusLabels[order.status]}
          >
            این سفارش از مسیر عادی پردازش خارج شده است. جزئیات هر زیرسفارش را بررسی کنید.
          </Alert>
        </div>
      ) : (
        <section className="mt-5 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 sm:p-6">
          <h2 className="mb-6 font-bold">وضعیت کلی</h2>
          <StatusTimeline stage={stage} />
        </section>
      )}

      <div className="mt-5 grid gap-5 lg:grid-cols-[1fr_19rem]">
        <section className="grid gap-5">
          {order.subOrders.map((subOrder) => (
            <article
              key={subOrder.id}
              className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
            >
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <Link
                    to="/roasteries/$slug"
                    params={{ slug: subOrder.roastery.slug }}
                    className="font-bold hover:text-[color:var(--roast)]"
                  >
                    {subOrder.roastery.name}
                  </Link>
                  <p className="mt-1 text-xs text-[color:var(--light)]">
                    زیرسفارش #{subOrder.id}
                  </p>
                </div>
                <span
                  className={`rounded-full border px-3 py-1 text-[11px] font-bold ${statusBadgeClass(subOrder.status)}`}
                >
                  {subOrderStatusLabels[subOrder.status]}
                </span>
              </div>

              <ul className="mt-5 divide-y divide-[color:var(--mid)]">
                {subOrder.items.map((item) => (
                  <li key={item.id} className="flex gap-4 py-4 first:pt-0 last:pb-0">
                    <div className="size-16 shrink-0 overflow-hidden rounded-xl bg-[color:var(--night)]">
                      {item.product.imageUrl ? (
                        <img
                          src={item.product.imageUrl}
                          alt={item.product.name}
                          loading="lazy"
                          className="h-full w-full object-cover"
                        />
                      ) : null}
                    </div>
                    <div className="min-w-0 flex-1">
                      <Link
                        to="/products/$slug"
                        params={{ slug: item.product.slug }}
                        className="font-bold hover:text-[color:var(--roast)]"
                      >
                        {item.product.name}
                      </Link>
                      <p className="mt-1 text-xs text-[color:var(--light)]">
                        {formatWeight(item.variant.weightGrams)} · دانه کامل · {item.quantity.toLocaleString("fa-IR")} عدد
                      </p>
                      <p className="mt-2 font-mono text-sm font-bold text-[color:var(--roast)]">
                        {formatIrr(item.lineTotal)}
                      </p>
                    </div>
                  </li>
                ))}
              </ul>

              {subOrder.shipment ? (
                <div className="mt-5 rounded-xl border border-[color:var(--roast)]/40 bg-[color:var(--night)] p-4">
                  <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                      <p className="text-xs text-[color:var(--light)]">
                        ارسال {subOrder.shipment.carrier ? `با ${subOrder.shipment.carrier}` : "سفارش"}
                      </p>
                      {subOrder.shipment.trackingCode ? (
                        <p dir="ltr" className="mt-1 text-start font-mono text-sm font-bold text-[color:var(--roast)]">
                          {subOrder.shipment.trackingCode}
                        </p>
                      ) : (
                        <p className="mt-1 text-xs text-[color:var(--light)]">کد رهگیری هنوز ثبت نشده است.</p>
                      )}
                    </div>
                    {subOrder.shipment.trackingCode ? (
                      <button
                        type="button"
                        onClick={() => copyTracking(subOrder.shipment!.trackingCode!)}
                        className="inline-flex min-h-10 items-center gap-2 rounded-xl bg-[color:var(--roast)] px-4 text-xs font-bold text-[color:var(--night)]"
                      >
                        <Copy size={14} />
                        {copiedCode === subOrder.shipment.trackingCode ? "کپی شد" : "کپی کد"}
                      </button>
                    ) : null}
                  </div>
                </div>
              ) : null}

              <dl className="mt-5 space-y-2 border-t border-[color:var(--mid)] pt-4 text-sm">
                <div className="flex justify-between gap-4 text-[color:var(--light)]">
                  <dt>جمع اقلام روستری</dt>
                  <dd className="font-mono">{formatIrr(subOrder.subtotal)}</dd>
                </div>
                <div className="flex justify-between gap-4 text-[color:var(--light)]">
                  <dt>ارسال</dt>
                  <dd className="font-mono">{formatIrr(subOrder.shippingTotal)}</dd>
                </div>
              </dl>
            </article>
          ))}
        </section>

        <aside className="grid content-start gap-5">
          <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
            <h2 className="font-bold">خلاصه پرداخت</h2>
            <dl className="mt-4 space-y-3 text-sm">
              <div className="flex justify-between gap-3 text-[color:var(--light)]">
                <dt>جمع اقلام</dt>
                <dd className="font-mono">{formatIrr(order.subtotal)}</dd>
              </div>
              <div className="flex justify-between gap-3 text-[color:var(--light)]">
                <dt>ارسال</dt>
                <dd className="font-mono">{formatIrr(order.shippingTotal)}</dd>
              </div>
              <div className="flex justify-between gap-3 text-[color:var(--light)]">
                <dt>تخفیف</dt>
                <dd className="font-mono">− {formatIrr(order.discountTotal)}</dd>
              </div>
              <div className="flex justify-between gap-3 border-t border-[color:var(--mid)] pt-3 font-bold">
                <dt>مبلغ نهایی</dt>
                <dd className="font-mono text-[color:var(--roast)]">{formatIrr(order.grandTotal)}</dd>
              </div>
            </dl>
          </section>

          {order.address ? (
            <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
              <h2 className="font-bold">نشانی تحویل</h2>
              <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
                {order.address.province}، {order.address.city}، {order.address.addressLine}
              </p>
              <p className="mt-3 text-xs text-[color:var(--light)]">
                {order.address.recipientName} · {order.address.recipientMobile}
              </p>
              {order.address.postalCode ? (
                <p dir="ltr" className="mt-2 text-start font-mono text-xs text-[color:var(--light)]">
                  {order.address.postalCode}
                </p>
              ) : null}
            </section>
          ) : null}

          <Link
            to="/contact"
            className="inline-flex min-h-11 items-center justify-center rounded-xl border border-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--roast)]"
          >
            تماس با پشتیبانی
          </Link>
        </aside>
      </div>

      <div className="mt-8">
        <Link to="/orders" className="text-sm text-[color:var(--light)] hover:text-[color:var(--roast)]">
          بازگشت به سفارش‌ها
        </Link>
      </div>
    </>
  );
}
