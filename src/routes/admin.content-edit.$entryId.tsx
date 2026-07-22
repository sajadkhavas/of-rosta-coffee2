import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  createFileRoute,
  Navigate,
  useNavigate,
} from "@tanstack/react-router";
import { AccountGuard } from "@/components/account/AccountGuard";
import { EditorialContentDialog } from "@/components/admin/EditorialContentDialog";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import {
  contentAuthorsQueryOptions,
  type AdminContentDetail,
} from "@/lib/api/admin-content";

export const Route = createFileRoute("/admin/content-edit/$entryId")({
  head: () => ({
    meta: [
      { title: "ویرایش محتوای تحریریه | ادمین رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminContentEditPage,
});

function AdminContentEditPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto min-h-[70vh] max-w-7xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "مدیریت محتوا", to: "/admin/content" },
            { label: "ویرایش محتوا" },
          ]}
        />
        <AccountGuard>
          {(user) =>
            user.roles.includes("administrator") ? (
              <EditorialEditWorkspace />
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

function EditorialEditWorkspace() {
  const { entryId } = Route.useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const authorsQuery = useQuery(contentAuthorsQueryOptions());

  const closeEditor = () => {
    void navigate({ to: "/admin/content" });
  };

  const handleSaved = (entry: AdminContentDetail) => {
    queryClient.setQueryData(["admin", "content", entry.id], entry);
    void queryClient.invalidateQueries({ queryKey: ["admin", "content"] });
    void queryClient.invalidateQueries({
      queryKey: ["admin", "content-link-report"],
    });
  };

  return (
    <section className="mt-8 rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6 text-center">
      <h1 className="text-2xl font-bold text-[color:var(--steam)]">
        محیط ویرایش تحریریه
      </h1>
      <p className="mt-3 text-sm leading-8 text-[color:var(--light)]">
        ویرایشگر در پنجره امن باز می‌شود. بستن پنجره شما را به فهرست محتوا برمی‌گرداند.
      </p>

      <EditorialContentDialog
        entryId={entryId}
        authors={authorsQuery.data ?? []}
        onClose={closeEditor}
        onSaved={handleSaved}
      />
    </section>
  );
}
