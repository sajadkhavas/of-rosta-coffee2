import { useQuery } from "@tanstack/react-query";
import { createFileRoute, Link, Navigate } from "@tanstack/react-router";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Skeleton } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import { adminWorkspaceQueryOptions } from "@/lib/api/workspaces";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/admin/workspace")({
  head: () => ({
    meta: [
      { title: "داشبورد مدیریتی | ادمین رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminWorkspacePage,
});

const workspaceLinks = [
  { to: "/admin/operations", label: "عملیات و نظارت" },
  { to: "/admin/finance", label: "مالی و تطبیق" },
  { to: "/admin/settlement-profiles", label: "بررسی مقصدهای تسویه" },
  { to: "/admin/content", label: "محتوا" },
  { to: "/admin/quiz-reviews", label: "Quiz و نظرات" },
] as const;

function AdminWorkspacePage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "داشبورد ادمین" }]} />
        <AccountGuard>
          {(user) =>
            user.roles.includes("administrator") ? (
              <AdminWorkspace />
            ) : (
              <Navigate to="/forbidden" replace />
            )
          }
        </AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function AdminWorkspace() {
  const query = useQuery(adminWorkspaceQueryOptions());

  return (
    <section className="mt-8 space-y-6" aria-labelledby="admin-workspace-title">
      <header>
        <p className="text-xs font-bold tracking-[0.18em] text-[color:var(--roast)]">
          ADMIN WORKSPACE
        </p>
        <h1
          id="admin-workspace-title"
          className="mt-2 text-3xl font-bold text-[color:var(--steam)]"
        >
          مرکز وضعیت رستا
        </h1>
        <p className="mt-3 max-w-3xl text-sm leading-7 text-[color:var(--light)]">
          KPIهای این صفحه در Backend از داده‌های اصلی محاسبه می‌شوند. این Workspace فقط آن‌ها را
          نمایش می‌دهد و هیچ مبلغ، نرخ یا حقیقت مالی را در مرورگر بازسازی نمی‌کند.
        </p>
      </header>

      {query.isLoading ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="در حال دریافت KPIها">
          {[1, 2, 3, 4, 5].map((item) => (
            <Skeleton key={item} className="h-28" />
          ))}
        </div>
      ) : null}

      {query.isError ? (
        <Alert variant="danger" title="شاخص‌های مدیریتی دریافت نشد">
          {isApiError(query.error)
            ? query.error.message
            : "اتصال API یا سطح دسترسی ادمین را بررسی کنید."}
        </Alert>
      ) : null}

      {query.data ? (
        <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-live="polite">
          <KpiCard label="روستری منتظر بررسی" value={query.data.kpis.pending_roasteries} />
          <KpiCard label="محصول در بررسی" value={query.data.kpis.products_in_review} />
          <KpiCard label="Incident باز" value={query.data.kpis.open_fulfillment_incidents} />
          <KpiCard label="اعلان ناموفق" value={query.data.kpis.failed_notifications} />
          <KpiCard label="تطبیق مالی باز" value={query.data.kpis.open_financial_reconciliation} />
        </dl>
      ) : null}

      <nav aria-label="بخش‌های مدیریت" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        {workspaceLinks.map((item) => (
          <Link
            key={item.to}
            to={item.to}
            className="inline-flex min-h-12 items-center justify-center rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] px-4 text-sm font-bold text-[color:var(--steam)] transition hover:border-[color:var(--roast)] hover:text-[color:var(--roast)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--roast)]"
          >
            {item.label}
          </Link>
        ))}
      </nav>
    </section>
  );
}

function KpiCard({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4">
      <dt className="text-xs leading-5 text-[color:var(--light)]">{label}</dt>
      <dd className="mt-3 text-3xl font-bold tabular-nums text-[color:var(--steam)]">
        {toFa(value)}
      </dd>
    </div>
  );
}
