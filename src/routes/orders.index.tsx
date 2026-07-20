import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { fallback, zodValidator } from "@tanstack/zod-adapter";
import { z } from "zod";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Alert, Button, EmptyState, Skeleton } from "@/components/system";
import { ordersQueryOptions } from "@/lib/api/orders";
import { isApiError } from "@/lib/api/client";
import type { OrderStatus } from "@/lib/api/contracts";
import {
  formatAccountDate,
  orderStatusLabels,
  statusBadgeClass,
} from "@/lib/account-format";
import { formatIrr } from "@/lib/catalog-format";
import { absoluteUrl } from "@/config/site";

const searchSchema = z.object({
  status: fallback(z.enum(["all", "processing", "delivered"]), "all").default("all"),
  page: fallback(z.coerce.number().int().min(1), 1).default(1),
});

type OrdersSearch = z.infer<typeof searchSchema>;

export const Route = createFileRoute("/orders/")({
  validateSearch: zodValidator(searchSchema),
  head: () => ({
    meta: [
      { title: "سفارش‌های من | رستا" },
      { name: "description", content: "مشاهده و پیگیری سفارش‌های حساب رستا." },
      { name: "robots", content: "noindex,nofollow" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/orders") }],
  }),
  component: OrdersPage,
});

const tabs: Array<{ value: OrdersSearch["status"]; label: string }> = [
  { value: "all", label: "همه" },
  { value: "processing", label: "در حال پردازش" },
  { value: "delivered", label: "تحویل‌شده" },
];

function OrdersPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "سفارش‌های من" }]} />
        <AccountGuard>{() => <OrdersContent />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function OrdersContent() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/orders/" });
  const apiStatus: OrderStatus | "all" = search.status;
  const query = useQuery(
    ordersQueryOptions({
      status: apiStatus,
      page: search.page,
      perPage: 12,
    }),
  );
  const orders = query.data?.items ?? [];
  const currentPage = query.data?.meta?.current_page ?? search.page;
  const lastPage = query.data?.meta?.last_page ?? currentPage;

  const updateSearch = (patch: Partial<OrdersSearch>, resetPage = true) =>
    navigate({
      search: (previous: OrdersSearch) => ({
        ...previous,
        ...patch,
        page: resetPage ? 1 : (patch.page ?? previous.page),
      }),
      replace: true,
    });

  return (
    <>
      <header className="mt-4">
        <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">ORDERS</p>
        <h1 className="mt-2 text-3xl font-bold">سفارش‌های من</h1>
        <p className="mt-2 text-sm text-[color:var(--light)]">
          وضعیت سفارش، زیرسفارش‌های روستری و اطلاعات ارسال مستقیماً از API دریافت می‌شود.
        </p>
      </header>

      <div className="mt-6 flex gap-2 overflow-x-auto border-b border-[color:var(--mid)]" role="tablist" aria-label="فیلتر وضعیت سفارش">
        {tabs.map((tab) => (
          <button
            key={tab.value}
            type="button"
            role="tab"
            aria-selected={search.status === tab.value}
            onClick={() => updateSearch({ status: tab.value })}
            className={`min-h-11 whitespace-nowrap border-b-2 px-4 text-sm font-bold transition ${
              search.status === tab.value
                ? "border-[color:var(--roast)] text-[color:var(--roast)]"
                : "border-transparent text-[color:var(--light)]"
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {query.isPending ? (
        <div className="mt-5 grid gap-4" aria-label="در حال بارگذاری سفارش‌ها">
          {Array.from({ length: 4 }, (_, index) => (
            <Skeleton key={index} className="h-40" />
          ))}
        </div>
      ) : query.isError ? (
        <section className="mt-7">
          <Alert variant="danger" title="سفارش‌ها دریافت نشدند">
            {isApiError(query.error)
              ? query.error.message
              : "ارتباط با سرویس سفارش‌ها برقرار نشد."}
          </Alert>
          <Button className="mt-4" variant="outline" onClick={() => query.refetch()}>
            تلاش مجدد
          </Button>
        </section>
      ) : orders.length === 0 ? (
        <div className="mt-7">
          <EmptyState
            title="سفارشی در این وضعیت ندارید"
            description="پس از ثبت سفارش، وضعیت پردازش و ارسال از همین صفحه قابل پیگیری است."
            action={
              <Link
                to="/products"
                className="inline-flex min-h-11 items-center rounded-xl bg-[color:var(--roast)] px-5 text-sm font-bold text-[color:var(--night)]"
              >
                مشاهده محصولات
              </Link>
            }
          />
        </div>
      ) : (
        <ul className="mt-5 space-y-4">
          {orders.map((order) => {
            const itemCount = order.subOrders.reduce(
              (total, subOrder) =>
                total + subOrder.items.reduce((count, item) => count + item.quantity, 0),
              0,
            );
            const roasteryNames = order.subOrders
              .map((subOrder) => subOrder.roastery.name)
              .join("، ");
            return (
              <li key={order.id}>
                <Link
                  to="/orders/$id"
                  params={{ id: order.id }}
                  className="block rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 transition hover:-translate-y-0.5 hover:border-[color:var(--roast)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--roast)]"
                >
                  <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                      <h2 className="font-bold">سفارش #{order.orderNumber}</h2>
                      <p className="mt-1 text-xs text-[color:var(--light)]">
                        {formatAccountDate(order.placedAt)}
                      </p>
                    </div>
                    <span
                      className={`rounded-full border px-3 py-1 text-[11px] font-bold ${statusBadgeClass(order.status)}`}
                    >
                      {orderStatusLabels[order.status]}
                    </span>
                  </div>
                  <p className="mt-4 text-sm text-[color:var(--light)]">
                    {roasteryNames || "روستری ثبت نشده"}
                  </p>
                  <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[color:var(--mid)] pt-4 text-xs">
                    <span className="text-[color:var(--light)]">
                      {itemCount.toLocaleString("fa-IR")} قلم · {order.subOrders.length.toLocaleString("fa-IR")} زیرسفارش
                    </span>
                    <span className="font-mono font-bold text-[color:var(--roast)]">
                      {formatIrr(order.grandTotal)}
                    </span>
                  </div>
                </Link>
              </li>
            );
          })}
        </ul>
      )}

      {lastPage > 1 ? (
        <nav aria-label="صفحه‌بندی سفارش‌ها" className="mt-8 flex items-center justify-center gap-3">
          <button
            type="button"
            disabled={currentPage <= 1}
            onClick={() => updateSearch({ page: currentPage - 1 }, false)}
            className="rounded-xl border border-[color:var(--mid)] px-4 py-2 text-sm disabled:opacity-40"
          >
            قبلی
          </button>
          <span className="text-sm text-[color:var(--light)]">
            صفحه {currentPage.toLocaleString("fa-IR")} از {lastPage.toLocaleString("fa-IR")}
          </span>
          <button
            type="button"
            disabled={currentPage >= lastPage}
            onClick={() => updateSearch({ page: currentPage + 1 }, false)}
            className="rounded-xl border border-[color:var(--mid)] px-4 py-2 text-sm disabled:opacity-40"
          >
            بعدی
          </button>
        </nav>
      ) : null}
    </>
  );
}
