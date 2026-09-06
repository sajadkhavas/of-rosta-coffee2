import { useMutation, useQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { listSellerProducts, listSellerRoasteries } from "@/lib/api/seller-operations";
import {
  getWholesaleTiers,
  replaceWholesaleTiers,
  type WholesaleThreshold,
  type WholesaleTier,
} from "@/lib/api/wholesale";
import { formatIrr } from "@/lib/persian";
export const Route = createFileRoute("/panel/wholesale")({
  head: () => ({
    meta: [{ title: "قیمت عمده | پنل روستری" }, { name: "robots", content: "noindex,nofollow" }],
  }),
  component: Page,
});
const thresholds: WholesaleThreshold[] = [5000, 10000, 20000, 50000];
function Page() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-10" dir="rtl">
        <AccountGuard>{() => <Workspace />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}
function Workspace() {
  const roasteries = useQuery({
    queryKey: ["seller", "roasteries"],
    queryFn: listSellerRoasteries,
  });
  const [rid, setRid] = useState("");
  useEffect(() => {
    if (!rid && roasteries.data?.[0]) setRid(roasteries.data[0].id);
  }, [rid, roasteries.data]);
  const products = useQuery({
    queryKey: ["seller", "products", rid],
    queryFn: () => listSellerProducts(rid),
    enabled: Boolean(rid),
  });
  return (
    <section>
      <h1 className="text-3xl font-bold">قیمت‌گذاری عمده کافه‌ها</h1>
      <p className="mt-3 max-w-3xl text-sm leading-7 text-[color:var(--light)]">
        برای هر وزن محصول، قیمت واحد در مرزهای ۵، ۱۰، ۲۰ و ۵۰ کیلو را تعیین کنید. فقط کافه تأییدشده
        این قیمت را در Checkout دریافت می‌کند.
      </p>
      <select
        value={rid}
        onChange={(e) => setRid(e.target.value)}
        className="mt-6 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"
      >
        {roasteries.data?.map((r) => (
          <option key={r.id} value={r.id}>
            {r.name}
          </option>
        ))}
      </select>
      <div className="mt-8 space-y-5">
        {products.data?.items.map((p) => (
          <article
            key={p.id}
            className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
          >
            <h2 className="text-xl font-bold">{p.name}</h2>
            <div className="mt-4 grid gap-4 xl:grid-cols-2">
              {p.variants.map((v) => (
                <VariantForm key={v.id} roasteryId={rid} productId={p.id} variant={v} />
              ))}
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
function VariantForm({
  roasteryId,
  productId,
  variant,
}: {
  roasteryId: string;
  productId: string;
  variant: { id: string; weightGrams: number; price: number };
}) {
  const q = useQuery({
    queryKey: ["wholesale", variant.id],
    queryFn: () => getWholesaleTiers(roasteryId, productId, variant.id),
  });
  const [values, setValues] = useState<Record<number, string>>({});
  useEffect(() => {
    if (q.data)
      setValues(Object.fromEntries(q.data.map((t) => [t.minWeightGrams, String(t.unitPrice)])));
  }, [q.data]);
  const m = useMutation({
    mutationFn: (tiers: WholesaleTier[]) =>
      replaceWholesaleTiers(roasteryId, productId, variant.id, tiers),
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    const tiers = thresholds
      .filter((t) => values[t]?.trim())
      .map((t) => ({ minWeightGrams: t, unitPrice: Number(values[t]), isActive: true }));
    m.mutate(tiers);
  };
  return (
    <form
      onSubmit={submit}
      className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
    >
      <div className="flex justify-between gap-3">
        <strong>{variant.weightGrams.toLocaleString("fa-IR")} گرم</strong>
        <span className="text-xs text-[color:var(--light)]">تک: {formatIrr(variant.price)}</span>
      </div>
      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        {thresholds.map((t) => (
          <label key={t} className="text-xs">
            از {(t / 1000).toLocaleString("fa-IR")} کیلو
            <input
              inputMode="numeric"
              value={values[t] ?? ""}
              onChange={(e) => setValues({ ...values, [t]: e.target.value.replace(/\D/g, "") })}
              placeholder="قیمت واحد IRR"
              className="mt-1 min-h-10 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--dark)] px-2"
            />
          </label>
        ))}
      </div>
      {m.isError ? (
        <p className="mt-3 text-xs text-red-300">
          قیمت‌ها باید از قیمت تک بیشتر نباشند و با افزایش وزن ثابت یا کمتر شوند.
        </p>
      ) : null}
      {m.isSuccess ? <p className="mt-3 text-xs text-emerald-300">ذخیره شد.</p> : null}
      <button className="mt-4 rounded-lg bg-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--night)]">
        ذخیره پله‌ها
      </button>
    </form>
  );
}
