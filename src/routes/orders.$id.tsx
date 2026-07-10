import { createFileRoute, notFound, Link } from "@tanstack/react-router";
import { useState } from "react";
import { Check, Copy } from "lucide-react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import {
  mockOrders,
  statusLabels,
  statusOrder,
  type OrderStatus,
} from "@/data/mock-orders";
import { formatToman, toFa } from "@/lib/persian";

import type { MockOrder, MockOrderItem } from "@/data/mock-orders";

export const Route = createFileRoute("/orders/$id")({
  loader: ({ params }): { order: MockOrder } => {
    const order = mockOrders.find((o) => o.id === params.id);
    if (!order) throw notFound();
    return { order };
  },

  head: ({ params, loaderData }) => ({
    meta: [
      {
        title: loaderData
          ? `سفارش #${toFa(params.id)} | رستا`
          : "سفارش پیدا نشد | رستا",
      },
      { name: "robots", content: "noindex,follow" },
    ],
    links: [{ rel: "canonical", href: `/orders/${params.id}` }],
  }),
  component: OrderDetailPage,
});

function StatusTimeline({ current }: { current: OrderStatus }) {
  const currentIdx = statusOrder.indexOf(current);
  return (
    <ol className="flex flex-col gap-3 md:flex-row md:items-center md:gap-0">
      {statusOrder.map((s, i) => {
        const done = i <= currentIdx;
        const active = i === currentIdx;
        return (
          <li key={s} className="flex items-center gap-2 md:flex-1 md:flex-col">
            <div
              className={`grid h-8 w-8 place-items-center rounded-full border-2 text-xs font-bold transition ${
                done
                  ? "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]"
                  : "border-[color:var(--mid)] text-[color:var(--light)]"
              }`}
            >
              {done && !active ? <Check size={14} /> : toFa(i + 1)}
            </div>
            <span
              className={`text-xs md:mt-2 ${
                active
                  ? "font-bold text-[color:var(--roast)]"
                  : done
                    ? "text-[color:var(--steam)]"
                    : "text-[color:var(--light)]"
              }`}
            >
              {statusLabels[s]}
            </span>
            {i < statusOrder.length - 1 && (
              <span
                aria-hidden
                className={`hidden h-0.5 flex-1 md:block ${
                  i < currentIdx ? "bg-[color:var(--roast)]" : "bg-[color:var(--mid)]"
                }`}
              />
            )}
          </li>
        );
      })}
    </ol>
  );
}

function OrderDetailPage() {
  const { order } = Route.useLoaderData();
  const [copied, setCopied] = useState(false);

  async function copyTracking() {
    if (!order.trackingCode) return;
    try {
      await navigator.clipboard.writeText(order.trackingCode);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {}
  }

  const subtotal = order.items.reduce((s, i) => s + i.price * i.qty, 0);

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "سفارش‌های من", to: "/orders" },
            { label: `#${toFa(order.id)}` },
          ]}
        />

        <header className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h1 className="text-xl font-bold text-[color:var(--steam)]">
              سفارش #{toFa(order.id)}
            </h1>
            <span className="text-xs text-[color:var(--light)]">
              ثبت: {order.createdAt} · تحویل تخمینی: {order.estimatedDelivery}
            </span>
          </div>
          <div className="mt-1 text-sm text-[color:var(--light)]">
            روستری: {order.roasteryName}
          </div>
        </header>

        <section className="mt-6 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
          <h2 className="mb-4 text-sm font-bold text-[color:var(--steam)]">وضعیت سفارش</h2>
          <StatusTimeline current={order.status} />
          {order.status === "shipped" && order.trackingCode && (
            <div className="mt-4 flex items-center justify-between rounded-lg border border-[color:var(--roast)] bg-[color:var(--night)] p-3">
              <div>
                <div className="text-xs text-[color:var(--light)]">کد رهگیری</div>
                <div className="font-mono-num text-sm font-bold text-[color:var(--roast)]">
                  {order.trackingCode}
                </div>
              </div>
              <button
                type="button"
                onClick={copyTracking}
                className="inline-flex items-center gap-1 rounded-lg bg-[color:var(--roast)] px-3 py-1.5 text-xs font-bold text-[color:var(--night)]"
              >
                <Copy size={12} />
                {copied ? "کپی شد" : "کپی"}
              </button>
            </div>
          )}
        </section>

        <section className="mt-6 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
          <h2 className="mb-3 text-sm font-bold text-[color:var(--steam)]">اقلام</h2>
          <ul className="divide-y divide-[color:var(--mid)]">
            {order.items.map((it, idx) => (
              <li key={idx} className="flex items-center justify-between py-3">
                <div>
                  <div className="text-sm font-bold text-[color:var(--steam)]">
                    {it.productName}
                  </div>
                  <div className="mt-0.5 text-xs text-[color:var(--light)]">
                    {toFa(it.weight)} گرم · {it.grind} · {toFa(it.qty)} عدد
                  </div>
                </div>
                <span className="font-mono-num text-sm text-[color:var(--roast)]">
                  {formatToman(it.price * it.qty)}
                </span>
              </li>
            ))}
          </ul>
          <dl className="mt-4 space-y-1 border-t border-[color:var(--mid)] pt-3 text-sm">
            <div className="flex justify-between text-[color:var(--light)]">
              <dt>جمع اقلام</dt>
              <dd className="font-mono-num">{formatToman(subtotal)}</dd>
            </div>
            <div className="flex justify-between text-[color:var(--light)]">
              <dt>هزینه ارسال</dt>
              <dd className="font-mono-num">
                {formatToman(Math.max(0, order.total - subtotal))}
              </dd>
            </div>
            <div className="flex justify-between pt-2 text-base font-bold text-[color:var(--steam)]">
              <dt>مجموع</dt>
              <dd className="font-mono-num text-[color:var(--roast)]">
                {formatToman(order.total)}
              </dd>
            </div>
          </dl>
        </section>

        <div className="mt-6 flex items-center justify-between">
          <Link
            to="/orders"
            className="text-sm text-[color:var(--light)] hover:text-[color:var(--roast)]"
          >
            ‹ بازگشت به سفارش‌ها
          </Link>
          <button
            type="button"
            className="rounded-lg border border-[color:var(--roast)] px-4 py-2 text-xs text-[color:var(--roast)]"
          >
            مشکلی دارید؟ تماس با پشتیبانی
          </button>
        </div>
      </main>
      <Footer />
    </>
  );
}
