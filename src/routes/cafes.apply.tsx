import { useMutation } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { applyCafe } from "@/lib/api/cafes";
import { isApiError } from "@/lib/api/client";
export const Route = createFileRoute("/cafes/apply")({
  head: () => ({
    meta: [{ title: "ثبت کافه | رستا" }, { name: "robots", content: "noindex,nofollow" }],
  }),
  component: ApplyPage,
});
const field =
  "min-h-11 w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3";
function ApplyPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-3xl px-4 py-10" dir="rtl">
        <AccountGuard>{() => <ApplyForm />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}
function ApplyForm() {
  const [form, setForm] = useState({
    name: "",
    slug: "",
    city: "",
    address: "",
    phone: "",
    description: "",
    latitude: null as number | null,
    longitude: null as number | null,
  });
  const mutation = useMutation({ mutationFn: () => applyCafe(form) });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    mutation.mutate();
  };
  const locate = () =>
    navigator.geolocation?.getCurrentPosition(({ coords }) =>
      setForm((v) => ({ ...v, latitude: coords.latitude, longitude: coords.longitude })),
    );
  if (mutation.data)
    return (
      <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6">
        <h1 className="text-2xl font-bold">درخواست ثبت شد</h1>
        <p className="mt-3 leading-7">
          {mutation.data.name} با وضعیت «در انتظار بررسی» ثبت شد. قیمت عمده پس از تأیید کافه فعال
          می‌شود.
        </p>
        <Link to="/cafes/portal" className="mt-5 inline-block text-[color:var(--roast)]">
          رفتن به پنل کافه
        </Link>
      </div>
    );
  return (
    <form
      onSubmit={submit}
      className="space-y-5 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6"
    >
      <h1 className="text-3xl font-bold">ثبت کافه در رستا</h1>
      <p className="text-sm leading-7 text-[color:var(--light)]">
        بعد از ثبت، کافه Pending می‌شود و فقط پس از تأیید ادمین در فهرست عمومی و قیمت همکاری فعال
        خواهد شد.
      </p>
      <label className="block text-sm">
        نام کافه
        <input
          required
          value={form.name}
          onChange={(e) => setForm({ ...form, name: e.target.value })}
          className={`${field} mt-2`}
        />
      </label>
      <label className="block text-sm">
        شناسه انگلیسی اختیاری
        <input
          value={form.slug}
          onChange={(e) => setForm({ ...form, slug: e.target.value })}
          className={`${field} mt-2`}
          placeholder="my-cafe"
        />
      </label>
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="text-sm">
          شهر
          <input
            required
            value={form.city}
            onChange={(e) => setForm({ ...form, city: e.target.value })}
            className={`${field} mt-2`}
          />
        </label>
        <label className="text-sm">
          شماره تماس
          <input
            value={form.phone}
            onChange={(e) => setForm({ ...form, phone: e.target.value })}
            className={`${field} mt-2`}
          />
        </label>
      </div>
      <label className="block text-sm">
        آدرس
        <textarea
          required
          value={form.address}
          onChange={(e) => setForm({ ...form, address: e.target.value })}
          className={`${field} mt-2 min-h-24 py-3`}
        />
      </label>
      <label className="block text-sm">
        توضیحات
        <textarea
          value={form.description}
          onChange={(e) => setForm({ ...form, description: e.target.value })}
          className={`${field} mt-2 min-h-24 py-3`}
        />
      </label>
      <div className="flex flex-wrap items-center gap-3">
        <button
          type="button"
          onClick={locate}
          className="rounded-xl border border-[color:var(--mid)] px-4 py-3 text-sm"
        >
          ثبت موقعیت فعلی
        </button>
        {form.latitude != null ? (
          <span className="text-xs text-[color:var(--light)]">مختصات ثبت شد</span>
        ) : null}
      </div>
      {mutation.isError ? (
        <p className="text-sm text-red-300">
          {isApiError(mutation.error) ? mutation.error.message : "ثبت درخواست انجام نشد."}
        </p>
      ) : null}
      <button
        disabled={mutation.isPending}
        className="rounded-xl bg-[color:var(--roast)] px-5 py-3 font-bold text-[color:var(--night)]"
      >
        {mutation.isPending ? "در حال ثبت…" : "ارسال برای بررسی"}
      </button>
    </form>
  );
}
