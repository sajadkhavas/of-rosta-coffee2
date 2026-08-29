import { useQuery } from "@tanstack/react-query";
import { Alert, EmptyState, Skeleton } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import { sellerWorkspaceQueryOptions } from "@/lib/api/workspaces";
import { toFa } from "@/lib/persian";

const kpiLabels = [
  ["pending_acceptance", "در انتظار پذیرش"],
  ["active_fulfillment", "آماده‌سازی فعال"],
  ["active_shipping", "ارسال فعال"],
  ["open_incidents", "Incident باز"],
] as const;

export function SellerWorkspaceOverview() {
  const query = useQuery(sellerWorkspaceQueryOptions());

  if (query.isLoading) {
    return (
      <section className="mt-8 grid gap-4" aria-label="در حال دریافت شاخص‌های عملیاتی">
        <Skeleton className="h-24" />
        <Skeleton className="h-32" />
      </section>
    );
  }

  if (query.isError) {
    return (
      <section className="mt-8">
        <Alert variant="danger" title="شاخص‌های عملیاتی دریافت نشد">
          {isApiError(query.error)
            ? query.error.message
            : "اتصال API یا سطح دسترسی پنل روستری را بررسی کنید."}
        </Alert>
      </section>
    );
  }

  if (!query.data?.items.length) {
    return null;
  }

  return (
    <section className="mt-8 space-y-4" aria-labelledby="seller-workspace-kpis">
      <header>
        <p className="text-xs font-bold tracking-[0.18em] text-[color:var(--roast)]">
          SERVER KPI
        </p>
        <h2 id="seller-workspace-kpis" className="mt-2 text-2xl font-bold text-[color:var(--steam)]">
          نمای لحظه‌ای عملیات روستری
        </h2>
        <p className="mt-2 max-w-3xl text-sm leading-7 text-[color:var(--light)]">
          این اعداد مستقیماً در Backend محاسبه می‌شوند؛ مرورگر فقط نتیجه authoritative را نمایش
          می‌دهد و هیچ مبلغ یا KPI مالی را دوباره محاسبه نمی‌کند.
        </p>
      </header>

      <div className="grid gap-4 xl:grid-cols-2" aria-live="polite">
        {query.data.items.map((item) => (
          <article
            key={item.roastery.id}
            className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
          >
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 className="font-bold text-[color:var(--steam)]">{item.roastery.name}</h3>
                <p className="mt-1 text-xs text-[color:var(--light)]">{item.roastery.status}</p>
              </div>
              <span className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-xs text-[color:var(--light)]">
                {item.access_roles.join(" · ")}
              </span>
            </div>
            <dl className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
              {kpiLabels.map(([key, label]) => (
                <div key={key} className="rounded-xl bg-[color:var(--night)] p-3">
                  <dt className="text-xs leading-5 text-[color:var(--light)]">{label}</dt>
                  <dd className="mt-2 text-2xl font-bold tabular-nums text-[color:var(--steam)]">
                    {toFa(item.kpis[key])}
                  </dd>
                </div>
              ))}
            </dl>
          </article>
        ))}
      </div>
    </section>
  );
}
