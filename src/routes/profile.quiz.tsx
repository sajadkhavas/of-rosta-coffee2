import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import { deleteMyQuizAttempt, listMyQuizAttempts } from "@/lib/api/quiz";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/profile/quiz")({
  head: () => ({ meta: [{ title: "تاریخچه کوییز | حساب رستا" }, { name: "robots", content: "noindex,nofollow" }] }),
  component: Page,
});

function Page() {
  return <><Navbar /><main dir="rtl" className="mx-auto max-w-4xl px-4 py-8"><Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "حساب من", to: "/profile" }, { label: "کوییز" }]} /><AccountGuard>{() => <History />}</AccountGuard></main><Footer /></>;
}

function History() {
  const client = useQueryClient();
  const query = useQuery({ queryKey: ["profile", "quiz-attempts"], queryFn: listMyQuizAttempts, staleTime: 15_000 });
  const mutation = useMutation({ mutationFn: deleteMyQuizAttempt, onSuccess: async () => client.invalidateQueries({ queryKey: ["profile", "quiz-attempts"] }) });
  return <section className="mt-8">
    <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="eyebrow">PRIVATE PROFILE</p><h1 className="mt-2 text-3xl font-bold">نتایج کوییز من</h1><p className="mt-2 text-sm text-[color:var(--light)]">این داده خصوصی است و هر نتیجه را می‌توانی حذف کنی.</p></div><Link to="/quiz" className="rounded-xl bg-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--night)]">کوییز جدید</Link></div>
    {query.isLoading ? <div className="mt-6 grid gap-3"><Skeleton className="h-32" /><Skeleton className="h-32" /></div> : query.isError ? <div className="mt-6"><Alert variant="danger">{isApiError(query.error) ? query.error.message : "تاریخچه دریافت نشد."}</Alert><Button variant="outline" className="mt-3" onClick={() => query.refetch()}>تلاش مجدد</Button></div> : !query.data?.length ? <div className="mt-6"><EmptyState title="نتیجه‌ای در حساب ذخیره نشده" description="کوییز به‌صورت مهمان هم کار می‌کند؛ ذخیره فقط با اقدام خودت انجام می‌شود." /></div> : <div className="mt-6 grid gap-4">{query.data.map((attempt) => <article key={attempt.id} className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"><div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-bold">نسخه {toFa(attempt.version)}</h2>{attempt.completed_at ? <time className="text-xs text-[color:var(--light)]" dateTime={attempt.completed_at}>{new Date(attempt.completed_at).toLocaleString("fa-IR")}</time> : null}</div><dl className="mt-4 grid gap-2 text-sm text-[color:var(--light)] sm:grid-cols-2">{Object.entries(attempt.answers).map(([key, value]) => <div key={key}><dt className="text-xs text-[color:var(--muted-gold)]">{key}</dt><dd>{Array.isArray(value) ? value.join("، ") : value}</dd></div>)}</dl><Button variant="danger" className="mt-4" loading={mutation.isPending} onClick={() => mutation.mutate(attempt.id)}>حذف این داده</Button></article>)}</div>}
    {mutation.isError ? <div className="mt-4"><Alert variant="danger">{isApiError(mutation.error) ? mutation.error.message : "حذف انجام نشد."}</Alert></div> : null}
  </section>;
}
