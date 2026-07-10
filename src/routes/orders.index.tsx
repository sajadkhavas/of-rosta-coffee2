import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { mockOrders, statusLabels, statusColor } from "@/data/mock-orders";
import { formatToman, toFa } from "@/lib/persian";

export const Route = createFileRoute("/orders/")({
  head: () => ({
    meta: [
      { title: "سفارش‌های من | رستا" },
      { name: "description", content: "پیگیری سفارش‌های قهوه شما در رستا." },
      { name: "robots", content: "noindex,follow" },
    ],
    links: [{ rel: "canonical", href: "/orders" }],
  }),
  component: OrdersPage,
});

type Tab = "all" | "active" | "delivered";

function OrdersPage() {
  const [tab, setTab] = useState<Tab>("all");
  const filtered = mockOrders.filter((o) => {
    if (tab === "all") return true;
    if (tab === "delivered") return o.status === "delivered";
    return o.status !== "delivered";
  });

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "سفارش‌های من" }]} />
        <h1 className="text-2xl font-bold text-[color:var(--steam)]">سفارش‌های من</h1>

        <div className="mt-4 flex gap-2 border-b border-[color:var(--mid)]">
          {(
            [
              { k: "all", l: "همه" },
              { k: "active", l: "در حال پردازش" },
              { k: "delivered", l: "تحویل شده" },
            ] as { k: Tab; l: string }[]
          ).map((t) => (
            <button
              key={t.k}
              onClick={() => setTab(t.k)}
              className={`border-b-2 px-4 py-2 text-sm transition ${
                tab === t.k
                  ? "border-[color:var(--roast)] text-[color:var(--roast)]"
                  : "border-transparent text-[color:var(--light)]"
              }`}
            >
              {t.l}
            </button>
          ))}
        </div>

        <ul className="mt-4 space-y-3">
          {filtered.map((o) => (
            <li key={o.id}>
              <Link
                to="/orders/$id"
                params={{ id: o.id }}
                className="block rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4 transition hover:border-[color:var(--roast)]"
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <div className="text-sm font-bold text-[color:var(--steam)]">
                      {o.roasteryName}
                    </div>
                    <div className="mt-0.5 text-xs text-[color:var(--light)]">
                      سفارش #{toFa(o.id)} · {o.createdAt}
                    </div>
                  </div>
                  <span
                    className="rounded-full px-3 py-1 text-[11px] font-bold text-[color:var(--night)]"
                    style={{ backgroundColor: statusColor[o.status] }}
                  >
                    {statusLabels[o.status]}
                  </span>
                </div>
                <div className="mt-3 flex items-center justify-between text-xs">
                  <span className="text-[color:var(--light)]">
                    {toFa(o.items.length)} قلم
                  </span>
                  <span className="font-mono-num font-bold text-[color:var(--roast)]">
                    {formatToman(o.total)}
                  </span>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      </main>
      <Footer />
    </>
  );
}
