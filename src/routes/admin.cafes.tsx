import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useState } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { listAdminCafes, setCafeStatus, type CafeStatus } from "@/lib/api/cafes";
export const Route = createFileRoute("/admin/cafes")({
  head: () => ({
    meta: [{ title: "مدیریت کافه‌ها | رستا" }, { name: "robots", content: "noindex,nofollow" }],
  }),
  component: Page,
});
const statuses: [CafeStatus, string][] = [
  ["pending", "در انتظار"],
  ["verified", "تأییدشده"],
  ["suspended", "تعلیق"],
  ["rejected", "ردشده"],
];
function Page() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-10" dir="rtl">
        <AccountGuard>
          {(u) =>
            u.roles.includes("administrator") ? <Admin /> : <Navigate to="/forbidden" replace />
          }
        </AccountGuard>
      </main>
      <Footer />
    </>
  );
}
function Admin() {
  const [status, setStatus] = useState<CafeStatus>("pending");
  const qc = useQueryClient();
  const q = useQuery({
    queryKey: ["admin", "cafes", status],
    queryFn: () => listAdminCafes(status),
  });
  const m = useMutation({
    mutationFn: ({ id, next }: { id: string; next: CafeStatus }) => setCafeStatus(id, next),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["admin", "cafes"] }),
  });
  return (
    <section>
      <h1 className="text-3xl font-bold">مدیریت کافه‌ها</h1>
      <p className="mt-2 text-sm text-[color:var(--light)]">
        تأیید کافه هم‌زمان دسترسی B2B را فعال می‌کند؛ تعلیق یا رد آن دسترسی را برمی‌دارد.
      </p>
      <div className="mt-6 flex flex-wrap gap-2">
        {statuses.map(([v, l]) => (
          <button
            key={v}
            onClick={() => setStatus(v)}
            className={`rounded-xl px-4 py-2 text-sm ${status === v ? "bg-[color:var(--roast)] text-[color:var(--night)]" : "border border-[color:var(--mid)]"}`}
          >
            {l}
          </button>
        ))}
      </div>
      <div className="mt-6 space-y-3">
        {q.data?.map((c) => (
          <article
            key={c.id}
            className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
          >
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <h2 className="font-bold">{c.name}</h2>
                <p className="mt-1 text-sm text-[color:var(--light)]">
                  {c.city} · {c.address}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {c.status !== "verified" ? (
                  <button
                    onClick={() => m.mutate({ id: c.id, next: "verified" })}
                    className="rounded-lg bg-emerald-700 px-3 py-2 text-sm"
                  >
                    تأیید
                  </button>
                ) : null}
                {c.status !== "suspended" ? (
                  <button
                    onClick={() => m.mutate({ id: c.id, next: "suspended" })}
                    className="rounded-lg border border-amber-600 px-3 py-2 text-sm"
                  >
                    تعلیق
                  </button>
                ) : null}
                {c.status !== "rejected" ? (
                  <button
                    onClick={() => m.mutate({ id: c.id, next: "rejected" })}
                    className="rounded-lg border border-red-700 px-3 py-2 text-sm"
                  >
                    رد
                  </button>
                ) : null}
              </div>
            </div>
          </article>
        ))}
        {!q.isLoading && q.data?.length === 0 ? <p>موردی در این وضعیت وجود ندارد.</p> : null}
      </div>
    </section>
  );
}
