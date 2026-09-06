import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { myCafesQueryOptions, updateCafe, type Cafe } from "@/lib/api/cafes";
import { useState, type FormEvent } from "react";
export const Route = createFileRoute("/cafes/portal")({
  head: () => ({
    meta: [{ title: "پنل کافه | رستا" }, { name: "robots", content: "noindex,nofollow" }],
  }),
  component: Page,
});
const labels = {
  pending: "در انتظار بررسی",
  verified: "تأییدشده",
  suspended: "تعلیق",
  rejected: "ردشده",
} as const;
function Page() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-5xl px-4 py-10" dir="rtl">
        <AccountGuard>{() => <Portal />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}
function Portal() {
  const q = useQuery(myCafesQueryOptions());
  return (
    <section>
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold">پنل کافه</h1>
          <p className="mt-2 text-sm text-[color:var(--light)]">
            پروفایل، وضعیت تأیید و دسترسی خرید عمده کافه را اینجا مدیریت کنید.
          </p>
        </div>
        <Link
          to="/cafes/apply"
          className="rounded-xl border border-[color:var(--mid)] px-4 py-3 text-sm"
        >
          ثبت کافه جدید
        </Link>
      </div>
      <div className="mt-8 space-y-4">
        {q.data?.map((c) => (
          <CafeCard key={c.id} cafe={c} />
        ))}
        {!q.isLoading && q.data?.length === 0 ? <p>هنوز کافه‌ای ثبت نکرده‌اید.</p> : null}
      </div>
    </section>
  );
}
function CafeCard({ cafe }: { cafe: Cafe }) {
  const qc = useQueryClient();
  const [edit, setEdit] = useState(false);
  const [form, setForm] = useState({
    name: cafe.name,
    city: cafe.city,
    address: cafe.address,
    phone: cafe.phone ?? "",
    description: cafe.description ?? "",
  });
  const m = useMutation({
    mutationFn: () => updateCafe(cafe.id, form),
    onSuccess: () => {
      setEdit(false);
      void qc.invalidateQueries({ queryKey: ["cafes", "mine"] });
    },
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    m.mutate();
  };
  return (
    <article className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
      <div className="flex flex-wrap justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">{cafe.name}</h2>
          <p className="mt-1 text-sm text-[color:var(--light)]">
            {labels[cafe.status]} · نقش شما: {cafe.membership_role === "manager" ? "مدیر" : "مالک"}
          </p>
        </div>
        <button onClick={() => setEdit(!edit)} className="text-sm text-[color:var(--roast)]">
          {edit ? "بستن" : "ویرایش"}
        </button>
      </div>
      {cafe.status === "verified" ? (
        <p className="mt-4 rounded-xl bg-emerald-950/30 p-3 text-sm text-emerald-200">
          قیمت‌های عمده ۵/۱۰/۲۰/۵۰ کیلو برای این حساب فعال است.
        </p>
      ) : (
        <p className="mt-4 rounded-xl bg-amber-950/30 p-3 text-sm text-amber-200">
          تا زمان تأیید، خرید با قیمت تک‌فروشی انجام می‌شود.
        </p>
      )}
      {edit ? (
        <form onSubmit={submit} className="mt-5 grid gap-3">
          <input
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"
          />
          <input
            value={form.city}
            onChange={(e) => setForm({ ...form, city: e.target.value })}
            className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"
          />
          <textarea
            value={form.address}
            onChange={(e) => setForm({ ...form, address: e.target.value })}
            className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"
          />
          <input
            value={form.phone}
            onChange={(e) => setForm({ ...form, phone: e.target.value })}
            className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"
          />
          <textarea
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"
          />
          <button
            disabled={m.isPending}
            className="justify-self-start rounded-xl bg-[color:var(--roast)] px-4 py-2 font-bold text-[color:var(--night)]"
          >
            ذخیره
          </button>
        </form>
      ) : null}
    </article>
  );
}
