import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useMemo, useState, type ReactNode } from "react";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import { EmptyState } from "@/components/system";
import { productsQueryOptions } from "@/lib/api/catalog";
import {
  EMPTY_PROFILE,
  matchProducts,
  saveProfile,
  type Adventure,
  type BrewMethod,
  type Experience,
  type RoastPref,
  type TasteProfile,
} from "@/lib/quiz-logic";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/quiz")({
  loader: ({ context }) => context.queryClient.ensureQueryData(productsQueryOptions({
    sort: "recommended",
    available: true,
    page: 1,
    perPage: 100,
  })),
  head: () => ({
    meta: [
      { title: "کوییز سلیقه قهوه با کاتالوگ زنده | رستا" },
      { name: "description", content: "پیشنهاد دانه کامل قهوه فقط از میان محصولات منتشرشده و موجود رستا." },
      { name: "robots", content: "noindex,follow" },
    ],
    links: [{ rel: "canonical", href: "/quiz" }],
  }),
  component: QuizPage,
});

const BREW_METHODS: BrewMethod[] = ["اسپرسوساز خانگی", "موکاپات", "فرنچ‌پرس", "V60 یا دریپ", "کلدبرو", "هنوز مشخص نیست"];
const FLAVORS = ["میوه‌ای و ترش", "شکلاتی و کارامل", "گلی و عطری", "آجیلی و خاکی", "مرکباتی", "شیرین و عسلی"];

function QuizPage() {
  const catalog = Route.useLoaderData().items;
  const [profile, setProfile] = useState<TasteProfile>(EMPTY_PROFILE);
  const [step, setStep] = useState(0);
  useEffect(() => saveProfile(profile), [profile]);
  const results = useMemo(() => matchProducts(profile, catalog, 8), [profile, catalog]);
  const steps = [
    <ChoiceStep key="brew" title="با چه دستگاهی قهوه درست می‌کنی؟" values={BREW_METHODS} value={profile.brewMethod} onChange={(brewMethod) => setProfile((current) => ({ ...current, brewMethod }))} />,
    <ChoiceStep<RoastPref> key="roast" title="چه سطح رستی را ترجیح می‌دهی؟" values={["روشن", "متوسط", "تیره", "پیشنهاد بده"]} value={profile.roast} onChange={(roast) => setProfile((current) => ({ ...current, roast }))} />,
    <ChoiceStep<Adventure> key="adventure" title="چقدر اهل طعم‌های متفاوت هستی؟" values={["safe", "balanced", "adventurous"]} labels={{ safe: "کلاسیک و قابل‌اعتماد", balanced: "متعادل", adventurous: "متفاوت و ماجراجویانه" }} value={profile.adventure} onChange={(adventure) => setProfile((current) => ({ ...current, adventure }))} />,
    <FlavorStep key="flavor" profile={profile} onChange={setProfile} />,
    <ChoiceStep<Experience> key="experience" title="تجربه‌ات با قهوه تخصصی چقدر است؟" values={["beginner", "some", "pro"]} labels={{ beginner: "تازه شروع کرده‌ام", some: "کمی تجربه دارم", pro: "حرفه‌ای هستم" }} value={profile.experience} onChange={(experience) => setProfile((current) => ({ ...current, experience }))} />,
  ];
  const complete = step >= steps.length;
  const answered = [profile.brewMethod, profile.roast, profile.adventure, profile.flavors.length > 0, profile.experience][step];

  return (
    <main className="min-h-screen bg-[color:var(--night)] px-4 py-10 text-[color:var(--steam)]">
      <div className="mx-auto max-w-5xl">
        <header className="flex items-center justify-between gap-4">
          <Link to="/" className="text-sm text-[color:var(--roast)]">خروج ←</Link>
          <p className="font-mono-num text-xs text-[color:var(--muted-gold)]">{complete ? "نتیجه" : `${toFa(step + 1)} / ${toFa(steps.length)}`}</p>
        </header>
        <div className="mt-6 h-1 overflow-hidden rounded-full bg-[color:var(--dark)]"><div className="h-full bg-[color:var(--roast)] transition-all" style={{ width: `${Math.min(100, ((step + 1) / (steps.length + 1)) * 100)}%` }} /></div>

        {!complete ? (
          <section className="mx-auto mt-14 max-w-2xl rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6 md:p-10">
            {steps[step]}
            <div className="mt-8 flex items-center justify-between gap-3">
              <button type="button" disabled={step === 0} onClick={() => setStep((value) => Math.max(0, value - 1))} className="rounded-xl border border-[color:var(--mid)] px-5 py-2 text-sm disabled:opacity-30">قبلی</button>
              <button type="button" disabled={!answered} onClick={() => setStep((value) => value + 1)} className="rounded-xl bg-[color:var(--roast)] px-6 py-2 text-sm font-bold text-[color:var(--night)] disabled:opacity-30">{step === steps.length - 1 ? "نمایش پیشنهادها" : "بعدی"}</button>
            </div>
          </section>
        ) : (
          <section className="mt-12">
            <div className="text-center"><span className="eyebrow">پیشنهاد زنده رستا</span><h1 className="mt-3 font-display text-4xl font-bold md:text-6xl">قهوه‌های مناسب ذائقه تو</h1><p className="mx-auto mt-4 max-w-2xl text-sm leading-7 text-[color:var(--light)]">این نتایج از کاتالوگ منتشرشده و موجود API محاسبه شده‌اند؛ محصول Seed یا ناموجود وارد پیشنهاد نمی‌شود.</p></div>
            {results.length ? <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{results.map((product) => <CatalogProductCard key={product.id} product={product} />)}</div> : <div className="mt-10"><EmptyState title="محصول موجودی مطابق پاسخ‌ها پیدا نشد" description="فیلتر محصولات را بررسی کنید یا بعداً دوباره کوییز را انجام دهید." /></div>}
            <div className="mt-10 flex flex-wrap justify-center gap-3"><button type="button" onClick={() => { setProfile(EMPTY_PROFILE); setStep(0); }} className="rounded-xl border border-[color:var(--roast)] px-5 py-2 text-sm font-bold text-[color:var(--roast)]">شروع دوباره</button><Link to="/products" className="rounded-xl bg-[color:var(--roast)] px-5 py-2 text-sm font-bold text-[color:var(--night)]">همه محصولات</Link></div>
          </section>
        )}
      </div>
    </main>
  );
}

function ChoiceStep<T extends string>({ title, values, labels, value, onChange }: { title: string; values: T[]; labels?: Partial<Record<T, string>>; value: T | null; onChange: (value: T) => void }) {
  return <div><h1 className="font-display text-3xl font-bold">{title}</h1><div className="mt-7 grid gap-3 sm:grid-cols-2">{values.map((item) => <Choice key={item} active={value === item} onClick={() => onChange(item)}>{labels?.[item] || item}</Choice>)}</div></div>;
}
function FlavorStep({ profile, onChange }: { profile: TasteProfile; onChange: React.Dispatch<React.SetStateAction<TasteProfile>> }) {
  return <div><h1 className="font-display text-3xl font-bold">کدام طعم‌ها بیشتر جذبت می‌کنند؟</h1><p className="mt-2 text-sm text-[color:var(--muted-gold)]">حداکثر سه مورد</p><div className="mt-7 grid gap-3 sm:grid-cols-2">{FLAVORS.map((flavor) => { const active = profile.flavors.includes(flavor); return <Choice key={flavor} active={active} onClick={() => onChange((current) => ({ ...current, flavors: active ? current.flavors.filter((item) => item !== flavor) : current.flavors.length < 3 ? [...current.flavors, flavor] : current.flavors }))}>{flavor}</Choice>; })}</div></div>;
}
function Choice({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
  return <button type="button" onClick={onClick} className={`rounded-xl border p-4 text-start text-sm transition ${active ? "border-[color:var(--roast)] bg-[color:var(--roast)]/10" : "border-[color:var(--mid)] bg-[color:var(--night)] hover:border-[color:var(--roast)]/60"}`}>{children}</button>;
}
