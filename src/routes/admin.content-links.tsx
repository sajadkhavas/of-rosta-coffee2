import { useQuery } from "@tanstack/react-query";
import {
  createFileRoute,
  Link,
  Navigate,
  useNavigate,
} from "@tanstack/react-router";
import { AccountGuard } from "@/components/account/AccountGuard";
import { ContentLinkReportPanel } from "@/components/admin/ContentLinkReportPanel";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { contentLinkReportQueryOptions } from "@/lib/api/admin-content";

export const Route = createFileRoute("/admin/content-links")({
  head: () => ({
    meta: [
      { title: "سلامت لینک‌سازی داخلی | ادمین رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminContentLinksPage,
});

function AdminContentLinksPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "مدیریت محتوا", to: "/admin/content" },
            { label: "سلامت لینک‌سازی" },
          ]}
        />
        <AccountGuard>
          {(user) =>
            user.roles.includes("administrator") ? (
              <ContentLinksWorkspace />
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

function ContentLinksWorkspace() {
  const reportQuery = useQuery(contentLinkReportQueryOptions());
  const navigate = useNavigate();

  return (
    <div className="mt-8 space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="max-w-3xl text-sm leading-8 text-[color:var(--light)]">
          این گزارش فقط داده‌های ذخیره‌شده در Laravel را بررسی می‌کند. برای اصلاح هر مورد، محتوا را در پنل تحریریه باز کنید و روابط آن را به‌روزرسانی کنید.
        </p>
        <Link
          to="/admin/content"
          className="rounded-xl border border-[color:var(--mid)] px-4 py-2 text-sm font-bold text-[color:var(--steam)] transition hover:border-[color:var(--roast)]"
        >
          بازگشت به تحریریه
        </Link>
      </div>

      <ContentLinkReportPanel
        report={reportQuery.data}
        isPending={reportQuery.isPending}
        isFetching={reportQuery.isFetching}
        error={reportQuery.error}
        onRefresh={() => {
          void reportQuery.refetch();
        }}
        onEditEntry={(entryId) => {
          void navigate({
            to: "/admin/content-edit/$entryId",
            params: { entryId },
          });
        }}
      />
    </div>
  );
}
