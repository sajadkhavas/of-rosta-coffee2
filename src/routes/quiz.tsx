import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useRef, useState } from "react";
import { X } from "lucide-react";
import { ProductCard } from "@/components/ProductCard";
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
  head: () => ({
    meta: [
      { title: "کدوم قهوه به سلیقه من می‌خوره؟ | تست سلیقه قهوه رستا" },
      {
        name: "description",
        content:
          "با کوییز ۶۰ ثانیه‌ای رستا، قهوه‌ای که دقیقاً به سلیقه‌ات می‌خوره رو پیدا کن. بر اساس دستگاه دم‌آوری، تلخی و طعم مورد علاقه‌ات.",
      },
      { property: "og:title", content: "کوییز سلیقه قهوه رستا" },
      {
        property: "og:description",
        content: "در ۶۰ ثانیه قهوه‌ای که به سلیقه‌ات می‌خوره رو پیدا کن.",
      },
      { property: "og:url", content: "/quiz" },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: "/quiz" }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "FAQPage",
          mainEntity: [
            {
              "@type": "Question",
              name: "کوییز سلیقه چطور کار می‌کند؟",
              acceptedAnswer: {
                "@type": "Answer",
                text:
                  "با پاسخ به هفت سؤال کوتاه درباره روش دم‌آوری، سطح رست، طعم‌های مورد علاقه و میزان ماجراجویی، سیستم رستا محصولاتی را که بیشترین انطباق با ذائقه شما دارند پیشنهاد می‌دهد.",
              },
            },
            {
              "@type": "Question",
              name: "آیا می‌توانم دوباره کوییز را انجام دهم؟",
              acceptedAnswer: {
                "@type": "Answer",
                text:
                  "بله. در صفحه نتایج روی «دوباره کوییز را انجام بده» بزنید تا از سؤال اول شروع کنید. پاسخ قبلی شما حفظ نمی‌شود.",
              },
            },
          ],
        }),
      },
    ],
  }),
  component: QuizPage,
});

const BREW_METHODS: BrewMethod[] = [
  "اسپرسوساز خانگی",
  "موکاپات",
  "فرنچ‌پرس",
  "V60 یا دریپ",
  "کلدبرو",
  "هنوز مشخص نیست",
];

const FLAVORS = [
  "میوه‌ای و ترش",
  "شکلاتی و کارامل",
  "گلی و عطری",
  "آجیلی و خاکی",
  "مرکباتی",
  "شیرین و عسلی",
];

interface StepBase {
  title: string;
  isAnswered: (p: TasteProfile) => boolean;
}

function QuizPage() {
  const [profile, setProfile] = useState<TasteProfile>(EMPTY_PROFILE);
  const [currentStep, setCurrentStep] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);

  const steps: StepBase[] = [
    { title: "روش دم‌آوری", isAnswered: (p) => !!p.brewMethod },
    { title: "سطح رست", isAnswered: (p) => !!p.roast },
    { title: "ماجراجویی", isAnswered: (p) => !!p.adventure },
    { title: "طعم‌ها", isAnswered: (p) => p.flavors.length > 0 },
    { title: "کافئین", isAnswered: (p) => p.decaf !== null },
    { title: "تجربه", isAnswered: (p) => !!p.experience },
  ];
  const totalQuestions = steps.length;
  const totalSections = totalQuestions + 1; // +1 for results

  const scrollTo = (index: number) => {
    const container = containerRef.current;
    if (!container) return;
    const target = container.querySelectorAll<HTMLElement>("[data-section]")[index];
    if (target) target.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  const goNext = () => {
    const next = Math.min(currentStep + 1, totalSections - 1);
    scrollTo(next);
  };
  const goPrev = () => {
    const prev = Math.max(currentStep - 1, 0);
    scrollTo(prev);
  };

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "ArrowDown" || e.key === "PageDown") {
        e.preventDefault();
        goNext();
      } else if (e.key === "ArrowUp" || e.key === "PageUp") {
        e.preventDefault();
        goPrev();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentStep, totalSections]);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;
    const sections = container.querySelectorAll<HTMLElement>("[data-section]");
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && entry.intersectionRatio > 0.5) {
            const idx = Number(entry.target.getAttribute("data-index") || 0);
            setCurrentStep(idx);
          }
        });
      },
      { threshold: [0.5], root: container },
    );
    sections.forEach((s) => observer.observe(s));
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    saveProfile(profile);
  }, [profile]);

  const reset = () => {
    setProfile(EMPTY_PROFILE);
    scrollTo(0);
  };

  const canProceed = currentStep < totalQuestions ? steps[currentStep].isAnswered(profile) : true;
  const isLastQuestion = currentStep === totalQuestions - 1;

  return (
    <div className="fixed inset-0 z-[60] bg-[color:var(--night)] text-[color:var(--steam)]">
      {/* Exit */}
      <Link
        to="/"
        aria-label="خروج از کوییز"
        className="fixed left-4 top-4 z-[70] grid h-10 w-10 place-items-center rounded-full border border-[color:var(--mid)] bg-[color:var(--dark)]/70 text-[color:var(--light)] backdrop-blur hover:border-[color:var(--roast)] hover:text-[color:var(--roast)]"
      >
        <X size={18} />
      </Link>

      {/* Progress dots */}
      <div className="fixed left-1/2 top-4 z-[70] -translate-x-1/2">
        <div className="flex items-center gap-1.5 rounded-full border border-[color:var(--mid)] bg-[color:var(--dark)]/70 px-3 py-2 backdrop-blur">
          {Array.from({ length: totalQuestions }).map((_, i) => (
            <button
              key={i}
              type="button"
              onClick={() => scrollTo(i)}
              aria-label={`سؤال ${toFa(i + 1)}`}
              className="h-1.5 rounded-full transition-all"
              style={{
                width: i === currentStep ? 24 : 8,
                background: i <= currentStep ? "var(--roast)" : "rgba(232,213,183,0.25)",
              }}
            />
          ))}
        </div>
      </div>

      <div
        ref={containerRef}
        className="h-screen overflow-y-scroll"
        style={{ scrollSnapType: "y mandatory" }}
      >
        {/* Q1 — Brew method */}
        <Section index={0} title="با چه دستگاهی قهوه درست می‌کنی؟" step={1} total={totalQuestions}>
          <div className="grid grid-cols-2 gap-3">
            {BREW_METHODS.map((m) => (
              <ChoiceCard
                key={m}
                active={profile.brewMethod === m}
                onClick={() => setProfile((p) => ({ ...p, brewMethod: m }))}
              >
                {m}
              </ChoiceCard>
            ))}
          </div>
        </Section>

        {/* Q2 — Bean form */}
        <Section index={1} title="دانه می‌خری یا آسیاب‌شده؟" step={2} total={totalQuestions}>
          <div className="grid gap-3">
            {(
              [
                { v: "whole", label: "دانه کامل — خودم آسیاب می‌کنم" },
                { v: "ground", label: "آسیاب‌شده — متناسب با دستگاهم" },
                { v: "any", label: "فرقی نداره" },
              ] as { v: BeanForm; label: string }[]
            ).map((o) => (
              <ChoiceCard
                key={o.v}
                active={profile.beanForm === o.v}
                onClick={() => setProfile((p) => ({ ...p, beanForm: o.v }))}
              >
                {o.label}
              </ChoiceCard>
            ))}
          </div>
        </Section>

        {/* Q3 — Roast */}
        <Section index={2} title="چه سطح رستی رو ترجیح می‌دی؟" step={3} total={totalQuestions}>
          <div className="grid gap-3">
            {(
              [
                { v: "روشن", label: "روشن — ترش و میوه‌ای", color: "#C8965A" },
                { v: "متوسط", label: "متوسط — متعادل", color: "#8B5A2B" },
                { v: "تیره", label: "تیره — تلخ و غلیظ", color: "#3D1A00" },
                { v: "پیشنهاد بده", label: "مطمئن نیستم، پیشنهاد بده", color: "#4A2C0A" },
              ] as { v: RoastPref; label: string; color: string }[]
            ).map((o) => (
              <button
                key={o.v}
                type="button"
                onClick={() => setProfile((p) => ({ ...p, roast: o.v }))}
                className={`flex items-center gap-4 rounded-xl border p-4 text-right transition ${
                  profile.roast === o.v
                    ? "border-[color:var(--roast)] bg-[color:var(--roast)]/10"
                    : "border-[color:var(--mid)] hover:border-[color:var(--roast)]/50"
                }`}
              >
                <span
                  aria-hidden
                  className="h-8 w-8 shrink-0 rounded-full"
                  style={{ background: o.color, border: "2px solid rgba(232,213,183,0.2)" }}
                />
                <span className="text-sm font-medium">{o.label}</span>
              </button>
            ))}
          </div>
        </Section>

        {/* Q4 — Adventure */}
        <Section index={3} title="چقدر اهل امتحان طعم‌های جدید هستی؟" step={4} total={totalQuestions}>
          <div className="grid gap-3">
            {(
              [
                { v: "safe", label: "طعمی که مطمئنم دوستش دارم", desc: "کلاسیک و ایمن" },
                { v: "balanced", label: "ترکیبی از آشنا و جدید", desc: "متعادل" },
                { v: "adventurous", label: "غافلگیرم کن، دنبال چیز متفاوتم", desc: "ماجراجو" },
              ] as { v: Adventure; label: string; desc: string }[]
            ).map((o) => (
              <ChoiceCard
                key={o.v}
                active={profile.adventure === o.v}
                onClick={() => setProfile((p) => ({ ...p, adventure: o.v }))}
              >
                <div className="text-sm font-bold">{o.label}</div>
                <div className="mt-1 text-xs text-[color:var(--muted-gold)]">{o.desc}</div>
              </ChoiceCard>
            ))}
          </div>
        </Section>

        {/* Q5 — Flavors */}
        <Section
          index={4}
          title="کدوم طعم‌ها بیشتر جذبت می‌کنن؟"
          step={5}
          total={totalQuestions}
          hint="حداکثر ۳ مورد"
        >
          <div className="grid grid-cols-2 gap-3">
            {FLAVORS.map((f) => {
              const selected = profile.flavors.includes(f);
              return (
                <ChoiceCard
                  key={f}
                  active={selected}
                  onClick={() => {
                    setProfile((p) => {
                      if (selected) return { ...p, flavors: p.flavors.filter((x) => x !== f) };
                      if (p.flavors.length >= 3) return p;
                      return { ...p, flavors: [...p.flavors, f] };
                    });
                  }}
                >
                  {f}
                </ChoiceCard>
              );
            })}
          </div>
        </Section>

        {/* Q6 — Caffeine */}
        <Section index={5} title="کافئین معمولی می‌خوای یا کم‌کافئین؟" step={6} total={totalQuestions}>
          <div className="grid gap-3">
            {(
              [
                { v: false, label: "کافئین کامل" },
                { v: true, label: "ترجیح می‌دم کم‌کافئین باشه" },
              ] as { v: boolean; label: string }[]
            ).map((o) => (
              <ChoiceCard
                key={String(o.v)}
                active={profile.decaf === o.v}
                onClick={() => setProfile((p) => ({ ...p, decaf: o.v }))}
              >
                {o.label}
              </ChoiceCard>
            ))}
          </div>
        </Section>

        {/* Q7 — Experience */}
        <Section index={6} title="با قهوه اسپشیالیتی چقدر آشنایی؟" step={7} total={totalQuestions}>
          <div className="grid gap-3">
            {(
              [
                {
                  v: "beginner",
                  label: "تازه شروع کردم",
                  desc: "می‌خوام قهوه‌های ساده و قابل‌اعتماد رو امتحان کنم",
                },
                {
                  v: "some",
                  label: "یه کم می‌دونم",
                  desc: "با چند خاستگاه و روش دم‌آوری آشنام",
                },
                {
                  v: "pro",
                  label: "قهوه‌باز حرفه‌ایم",
                  desc: "دنبال قهوه‌های نادر و خاصم",
                },
              ] as { v: Experience; label: string; desc: string }[]
            ).map((o) => (
              <ChoiceCard
                key={o.v}
                active={profile.experience === o.v}
                onClick={() => setProfile((p) => ({ ...p, experience: o.v }))}
              >
                <div className="text-sm font-bold">{o.label}</div>
                <div className="mt-1 text-xs text-[color:var(--muted-gold)]">{o.desc}</div>
              </ChoiceCard>
            ))}
          </div>
        </Section>

        {/* Results */}
        <ResultsSection index={totalQuestions} profile={profile} onReset={reset} />
      </div>

      {/* Bottom nav bar — hidden on results */}
      {currentStep < totalQuestions && (
        <div className="fixed bottom-0 left-0 right-0 z-[70] border-t border-[color:var(--mid)] bg-[color:var(--night)]/95 backdrop-blur">
          <div className="mx-auto flex max-w-lg items-center justify-between gap-3 px-4 py-4">
            <button
              type="button"
              onClick={goPrev}
              disabled={currentStep === 0}
              className="text-sm text-[color:var(--light)] disabled:opacity-30"
            >
              قبلی
            </button>
            <div className="text-xs text-[color:var(--muted-gold)]">
              {toFa(currentStep + 1)} / {toFa(totalQuestions)}
            </div>
            <button
              type="button"
              onClick={goNext}
              disabled={!canProceed}
              className="rounded-lg bg-[color:var(--roast)] px-6 py-2 text-sm font-bold text-[color:var(--night)] transition hover:brightness-110 disabled:opacity-30"
            >
              {isLastQuestion ? "نشونم بده ←" : "بعدی"}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

function Section({
  index,
  title,
  step,
  total,
  hint,
  children,
}: {
  index: number;
  title: string;
  step: number;
  total: number;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <section
      data-section
      data-index={index}
      className="flex h-screen items-center justify-center px-4"
      style={{ scrollSnapAlign: "start" }}
    >
      <div className="mx-auto w-full max-w-lg pb-24 pt-20">
        <div className="mb-6 text-xs tracking-[0.3em] text-[color:var(--muted-gold)]">
          {toFa(step)} / {toFa(total)}
        </div>
        <h2 className="mb-2 font-display text-3xl font-bold leading-tight text-[color:var(--steam)] md:text-4xl">
          {title}
        </h2>
        {hint && <div className="mb-6 text-xs text-[color:var(--muted-gold)]">{hint}</div>}
        <div className="mt-6">{children}</div>
      </div>
    </section>
  );
}

function ChoiceCard({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-xl border p-4 text-right transition ${
        active
          ? "border-[color:var(--roast)] bg-[color:var(--roast)]/10 text-[color:var(--steam)]"
          : "border-[color:var(--mid)] bg-[color:var(--dark)] text-[color:var(--light)] hover:border-[color:var(--roast)]/50"
      }`}
    >
      {children}
    </button>
  );
}

function ResultsSection({
  index,
  profile,
  onReset,
}: {
  index: number;
  profile: TasteProfile;
  onReset: () => void;
}) {
  const matched = matchProducts(profile, 4);
  return (
    <section
      data-section
      data-index={index}
      className="min-h-screen px-4 pt-20 pb-24"
      style={{ scrollSnapAlign: "start" }}
    >
      <div className="mx-auto max-w-4xl">
        <div className="text-center">
          <span className="eyebrow">نتیجه کوییز</span>
          <h2 className="mt-3 font-display text-4xl font-bold leading-tight text-[color:var(--steam)] md:text-5xl">
            قهوه‌ات پیدا شد ☕
          </h2>
          <p className="mx-auto mt-4 max-w-lg text-sm leading-7 text-[color:var(--light)]">
            بر اساس پاسخ‌های تو، این‌ها بهترین انتخاب‌های رستا هستند.
          </p>
        </div>

        {/* Summary chips */}
        <div className="mx-auto mt-8 flex max-w-xl flex-wrap justify-center gap-2 text-xs">
          {profile.brewMethod && <Chip>{profile.brewMethod}</Chip>}
          {profile.roast && profile.roast !== "پیشنهاد بده" && <Chip>رست {profile.roast}</Chip>}
          {profile.flavors.map((f) => (
            <Chip key={f}>{f}</Chip>
          ))}
          {profile.decaf === true && <Chip>کم‌کافئین</Chip>}
        </div>

        <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {matched.map((p) => (
            <ProductCard key={p.slug} product={p} />
          ))}
        </div>

        <div className="mt-12 flex flex-col items-center gap-4">
          <Link
            to="/products"
            className="rounded-lg bg-[color:var(--roast)] px-8 py-3 text-sm font-bold text-[color:var(--night)] transition hover:brightness-110"
          >
            مشاهده همه پیشنهادها ←
          </Link>
          <button
            type="button"
            onClick={onReset}
            className="text-sm text-[color:var(--light)] underline hover:text-[color:var(--roast)]"
          >
            دوباره کوییز رو انجام بده
          </button>
        </div>
      </div>
    </section>
  );
}

function Chip({ children }: { children: React.ReactNode }) {
  return (
    <span className="rounded-full border border-[color:var(--roast)]/40 bg-[color:var(--dark)] px-3 py-1 text-[color:var(--light)]">
      {children}
    </span>
  );
}
