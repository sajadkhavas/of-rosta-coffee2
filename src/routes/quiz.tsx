import { useMutation } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import { Alert, EmptyState, Skeleton } from "@/components/system";
import { absoluteUrl } from "@/config/site";
import { isApiError } from "@/lib/api/client";
import {
  deleteGuestQuizAttempt,
  getCurrentQuiz,
  submitQuizAttempt,
  syncQuizAttempt,
  type QuizAnswers,
  type QuizQuestion,
  type QuizSubmission,
} from "@/lib/api/quiz";
import {
  clearQuizSession,
  createIdempotencyKey,
  createOpaqueGuestToken,
  saveQuizSession,
} from "@/lib/quiz-logic";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/quiz")({
  loader: () => getCurrentQuiz(),
  head: () => ({
    meta: [
      { title: "کوییز سلیقه قهوه | رستا" },
      { name: "description", content: "پیشنهاد نسخه‌بندی‌شده قهوه از کاتالوگ زنده و موجود رستا." },
      { name: "robots", content: "noindex,follow" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/quiz") }],
  }),
  pendingComponent: () => (
    <main className="mx-auto max-w-3xl px-4 py-16">
      <Skeleton className="h-80" />
    </main>
  ),
  component: QuizPage,
});

function QuizPage() {
  const version = Route.useLoaderData();
  const [answers, setAnswers] = useState<QuizAnswers>({});
  const [step, setStep] = useState(0);
  const [result, setResult] = useState<QuizSubmission | null>(null);
  const [guestToken, setGuestToken] = useState<string | null>(null);
  const [synced, setSynced] = useState(false);
  const [deleted, setDeleted] = useState(false);

  const submitMutation = useMutation({
    mutationFn: async () => {
      const token = guestToken ?? createOpaqueGuestToken();
      const data = await submitQuizAttempt({
        answers,
        guestToken: token,
        idempotencyKey: createIdempotencyKey(),
      });
      return { data, token };
    },
    onSuccess: ({ data, token }) => {
      setGuestToken(token);
      setResult(data);
      setDeleted(false);
      saveQuizSession({
        attemptId: data.attempt.id,
        guestToken: token,
        version: data.attempt.version,
      });
    },
  });

  const syncMutation = useMutation({
    mutationFn: async () => {
      if (!result || !guestToken) throw new Error("نتیجه مهمان در دسترس نیست.");
      return syncQuizAttempt({
        attemptId: result.attempt.id,
        guestToken,
        idempotencyKey: createIdempotencyKey(),
      });
    },
    onSuccess: () => {
      setSynced(true);
      clearQuizSession();
    },
  });
  const deleteMutation = useMutation({
    mutationFn: async () => {
      if (result && guestToken) await deleteGuestQuizAttempt(result.attempt.id, guestToken);
    },
    onSuccess: () => {
      clearQuizSession();
      setResult(null);
      setDeleted(true);
    },
  });

  const question = version.questions[step];
  const answered = question ? answerIsComplete(question, answers[question.key]) : false;
  const progress = useMemo(
    () => Math.round(((step + 1) / version.questions.length) * 100),
    [step, version.questions.length],
  );
  const next = () => {
    if (!answered) return;
    if (step === version.questions.length - 1) submitMutation.mutate();
    else setStep((value) => value + 1);
  };
  const reset = () => {
    setAnswers({});
    setResult(null);
    setGuestToken(null);
    setSynced(false);
    setDeleted(false);
    setStep(0);
    clearQuizSession();
  };

  return (
    <main
      dir="rtl"
      className="min-h-screen bg-[color:var(--night)] px-4 py-10 text-[color:var(--steam)]"
    >
      <div className="mx-auto max-w-5xl">
        <header className="flex items-center justify-between gap-4">
          <Link
            to="/"
            className="rounded-lg px-2 py-1 text-sm text-[color:var(--roast)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--roast)]"
          >
            خروج ←
          </Link>
          <p className="font-mono text-xs text-[color:var(--muted-gold)]">
            نسخه {toFa(version.version)} ·{" "}
            {result ? "نتیجه" : `${toFa(step + 1)} / ${toFa(version.questions.length)}`}
          </p>
        </header>
        <div
          className="mt-6 h-1 overflow-hidden rounded-full bg-[color:var(--dark)]"
          role="progressbar"
          aria-label="پیشرفت کوییز"
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={result ? 100 : progress}
        >
          <div
            className="h-full bg-[color:var(--roast)] transition-all"
            style={{ width: `${result ? 100 : progress}%` }}
          />
        </div>
        {!result ? (
          <section
            className="mx-auto mt-14 max-w-2xl rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6 md:p-10"
            aria-live="polite"
          >
            <h1 className="font-display text-3xl font-bold">{question?.title}</h1>
            {question?.type === "multi" ? (
              <p className="mt-2 text-xs text-[color:var(--muted-gold)]">
                حداکثر {toFa(question.max_selections ?? 3)} انتخاب
              </p>
            ) : null}
            <QuestionChoices
              question={question}
              value={question ? answers[question.key] : undefined}
              onChange={(value) =>
                question && setAnswers((current) => ({ ...current, [question.key]: value }))
              }
            />
            {submitMutation.isError ? (
              <div className="mt-5">
                <Alert variant="danger" title="ثبت نتیجه انجام نشد">
                  {message(submitMutation.error)}
                </Alert>
              </div>
            ) : null}
            <div className="mt-8 flex items-center justify-between gap-3">
              <button
                type="button"
                disabled={step === 0 || submitMutation.isPending}
                onClick={() => setStep((value) => Math.max(0, value - 1))}
                className="min-h-11 rounded-xl border border-[color:var(--mid)] px-5 py-2 text-sm disabled:opacity-30"
              >
                قبلی
              </button>
              <button
                type="button"
                disabled={!answered || submitMutation.isPending}
                onClick={next}
                className="min-h-11 rounded-xl bg-[color:var(--roast)] px-6 py-2 text-sm font-bold text-[color:var(--night)] disabled:opacity-30"
              >
                {submitMutation.isPending
                  ? "در حال بررسی کاتالوگ…"
                  : step === version.questions.length - 1
                    ? "نمایش پیشنهادهای زنده"
                    : "بعدی"}
              </button>
            </div>
          </section>
        ) : (
          <section className="mt-12" aria-live="polite">
            <div className="text-center">
              <span className="eyebrow">پیشنهاد زنده رستا</span>
              <h1 className="mt-3 font-display text-4xl font-bold md:text-6xl">
                قهوه‌های هماهنگ با پاسخ‌های تو
              </h1>
              <p className="mx-auto mt-4 max-w-2xl text-sm leading-7 text-[color:var(--light)]">
                امتیازها با نسخه {toFa(result.attempt.version)} و موجودی زنده Backend محاسبه
                شده‌اند. هویت همه محصولات پیشنهادی فقط دانه کامل است و این پیشنهاد ادعای پزشکی یا
                سلامتی نیست.
              </p>
            </div>
            {result.recommendations.length ? (
              <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                {result.recommendations.map((item) => (
                  <div key={item.product.id} className="space-y-2">
                    <CatalogProductCard product={item.product} />
                    <div className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-3 text-xs leading-6 text-[color:var(--light)]">
                      <strong className="text-[color:var(--roast)]">چرا این پیشنهاد؟</strong>
                      {item.reasons.length ? (
                        <ul className="mt-1 list-inside list-disc">
                          {item.reasons.map((reason) => (
                            <li key={reason}>{reason}</li>
                          ))}
                        </ul>
                      ) : (
                        <p className="mt-1">در میان گزینه‌های موجود امتیاز نسبی بالاتری دارد.</p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="mt-10">
                <EmptyState
                  title="محصول موجود مطابق پاسخ‌ها پیدا نشد"
                  description="کاتالوگ ممکن است تغییر کرده باشد؛ بعداً دوباره بررسی کنید."
                />
              </div>
            )}
            <div className="mx-auto mt-10 max-w-2xl rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
              <h2 className="font-bold">حریم خصوصی و حساب</h2>
              <p className="mt-2 text-sm leading-7 text-[color:var(--light)]">
                مهمان بدون ورود کار می‌کند. فقط با انتخاب «ذخیره در حساب من» این attempt به حساب
                فعلی منتقل می‌شود.
              </p>
              {syncMutation.isError ? (
                <div className="mt-3">
                  <Alert variant="warning">
                    {isApiError(syncMutation.error) && syncMutation.error.status === 401
                      ? "برای ذخیره در حساب وارد شو؛ نتیجه مهمان حذف نشده است."
                      : message(syncMutation.error)}
                  </Alert>
                </div>
              ) : null}
              {synced ? (
                <div className="mt-3">
                  <Alert variant="success" title="در حساب ذخیره شد">
                    اکنون می‌توانی آن را در تاریخچه ببینی یا حذف کنی.
                  </Alert>
                </div>
              ) : null}
              <div className="mt-4 flex flex-wrap gap-3">
                {!synced ? (
                  <button
                    type="button"
                    disabled={syncMutation.isPending}
                    onClick={() => syncMutation.mutate()}
                    className="min-h-11 rounded-xl bg-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--night)] disabled:opacity-50"
                  >
                    ذخیره در حساب من
                  </button>
                ) : (
                  <Link
                    to="/profile/quiz"
                    className="min-h-11 rounded-xl bg-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--night)]"
                  >
                    مشاهده تاریخچه
                  </Link>
                )}
                {!synced ? (
                  <button
                    type="button"
                    disabled={deleteMutation.isPending}
                    onClick={() => deleteMutation.mutate()}
                    className="min-h-11 rounded-xl border border-red-400/60 px-4 py-2 text-sm text-red-200"
                  >
                    حذف نتیجه مهمان
                  </button>
                ) : null}
                <button
                  type="button"
                  onClick={reset}
                  className="min-h-11 rounded-xl border border-[color:var(--mid)] px-4 py-2 text-sm"
                >
                  شروع دوباره
                </button>
              </div>
            </div>
          </section>
        )}
        {deleted ? (
          <div className="mx-auto mt-5 max-w-2xl">
            <Alert variant="success">نتیجه مهمان حذف شد.</Alert>
          </div>
        ) : null}
      </div>
    </main>
  );
}

function QuestionChoices({
  question,
  value,
  onChange,
}: {
  question?: QuizQuestion;
  value?: string | string[];
  onChange: (value: string | string[]) => void;
}) {
  if (!question) return null;
  const selected = Array.isArray(value) ? value : value ? [value] : [];
  const max = question.max_selections ?? 3;
  return (
    <fieldset className="mt-7 grid gap-3 sm:grid-cols-2">
      <legend className="sr-only">{question.title}</legend>
      {question.options.map((option) => {
        const active = selected.includes(option.value);
        return (
          <button
            key={option.value}
            type="button"
            aria-pressed={active}
            onClick={() => {
              if (question.type === "single") onChange(option.value);
              else if (active) onChange(selected.filter((item) => item !== option.value));
              else if (selected.length < max) onChange([...selected, option.value]);
            }}
            className={`min-h-12 rounded-xl border p-4 text-start text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--roast)] ${active ? "border-[color:var(--roast)] bg-[color:var(--roast)]/10" : "border-[color:var(--mid)] bg-[color:var(--night)]"}`}
          >
            {option.label}
          </button>
        );
      })}
    </fieldset>
  );
}
function answerIsComplete(question: QuizQuestion, value: string | string[] | undefined) {
  return question.type === "multi"
    ? Array.isArray(value) && value.length > 0
    : typeof value === "string" && value.length > 0;
}
function message(error: unknown) {
  return isApiError(error)
    ? error.message
    : error instanceof Error
      ? error.message
      : "عملیات انجام نشد.";
}
