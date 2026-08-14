import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useState } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton } from "@/components/system";
import { adminQuizVersions, adminReplies, adminReports, archiveQuizVersion, cloneQuizDraft, decideReply, decideReport, publishQuizVersion, type AdminReply, type AdminReport } from "@/lib/api/admin-quiz-review-safety";
import { isApiError } from "@/lib/api/client";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/admin/quiz-reviews")({
  head: () => ({ meta: [{ title: "کوییز و سلامت نظرها | ادمین رستا" }, { name: "robots", content: "noindex,nofollow" }] }),
  component: Page,
});
type Tab = "reports" | "replies" | "quiz";
function Page() {
  return <><Navbar /><main dir="rtl" className="mx-auto max-w-6xl px-4 py-8"><Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "ادمین", to: "/admin/operations" }, { label: "کوییز و نظرها" }]} /><AccountGuard>{(user) => user.roles.includes("administrator") ? <Dashboard /> : <Navigate to="/forbidden" replace />}</AccountGuard></main><Footer /></>;
}
function Dashboard() {
  const [tab, setTab] = useState<Tab>("reports");
  return <section className="mt-8"><header><p className="eyebrow">QUIZ / REVIEW SAFETY</p><h1 className="mt-2 text-3xl font-bold">مدیریت نسخه کوییز و Abuse Review</h1><p className="mt-2 text-sm leading-7 text-[color:var(--light)]">نسخه published قابل ویرایش نیست؛ برای تغییر، draft جدید بساز و سپس publish کن. تصمیم‌های Review در Audit ثبت می‌شوند.</p></header><nav className="mt-6 flex gap-2 overflow-x-auto" aria-label="بخش‌های مدیریت">{([['reports','گزارش‌ها'],['replies','پاسخ فروشنده'],['quiz','نسخه‌های کوییز']] as const).map(([id,label]) => <button key={id} type="button" onClick={() => setTab(id)} className={`min-h-11 whitespace-nowrap rounded-xl border px-4 text-sm font-bold ${tab === id ? "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]" : "border-[color:var(--mid)]"}`}>{label}</button>)}</nav>{tab === "reports" ? <Reports /> : tab === "replies" ? <Replies /> : <QuizVersions />}</section>;
}
function Reports() {
  const client = useQueryClient(); const [status, setStatus] = useState<AdminReport["status"]>("open");
  const query = useQuery({ queryKey: ["admin","review-reports",status], queryFn: () => adminReports(status), staleTime: 10_000 });
  const mutation = useMutation({ mutationFn: ({ id, next }: { id: string; next: AdminReport["status"] }) => decideReport(id, next), onSuccess: async () => client.invalidateQueries({ queryKey: ["admin","review-reports"] }) });
  return <Panel title="صف گزارش مشتری"><StatusSelect value={status} options={["open","reviewing","resolved","dismissed"]} onChange={(value) => setStatus(value as AdminReport["status"])} />{renderState(query, (items) => <div className="grid gap-3">{items.map((item) => <article key={item.id} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"><strong>{item.reason}</strong><p className="mt-2 text-sm text-[color:var(--light)]">Review: {item.review_id}</p>{item.evidence ? <p className="mt-2 text-sm leading-7">{item.evidence}</p> : null}<div className="mt-3 flex flex-wrap gap-2"><Button loading={mutation.isPending} onClick={() => mutation.mutate({ id: item.id, next: "reviewing" })}>در حال بررسی</Button><Button variant="outline" loading={mutation.isPending} onClick={() => mutation.mutate({ id: item.id, next: "resolved" })}>حل شد</Button><Button variant="danger" loading={mutation.isPending} onClick={() => mutation.mutate({ id: item.id, next: "dismissed" })}>رد گزارش</Button></div></article>)}</div>)}</Panel>;
}
function Replies() {
  const client = useQueryClient(); const [status, setStatus] = useState<AdminReply["status"]>("visible");
  const query = useQuery({ queryKey: ["admin","review-replies",status], queryFn: () => adminReplies(status), staleTime: 10_000 });
  const mutation = useMutation({ mutationFn: ({ id, next }: { id: string; next: AdminReply["status"] }) => decideReply(id, next), onSuccess: async () => client.invalidateQueries({ queryKey: ["admin","review-replies"] }) });
  return <Panel title="Moderation پاسخ فروشنده"><StatusSelect value={status} options={["visible","hidden","rejected"]} onChange={(value) => setStatus(value as AdminReply["status"])} />{renderState(query, (items) => <div className="grid gap-3">{items.map((item) => <article key={item.id} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"><p className="whitespace-pre-wrap text-sm leading-7">{item.body}</p><p className="mt-2 text-xs text-[color:var(--light)]">Review: {item.review_id}</p><div className="mt-3 flex flex-wrap gap-2"><Button loading={mutation.isPending} onClick={() => mutation.mutate({ id: item.id, next: "visible" })}>نمایش</Button><Button variant="outline" loading={mutation.isPending} onClick={() => mutation.mutate({ id: item.id, next: "hidden" })}>مخفی</Button><Button variant="danger" loading={mutation.isPending} onClick={() => mutation.mutate({ id: item.id, next: "rejected" })}>رد</Button></div></article>)}</div>)}</Panel>;
}
function QuizVersions() {
  const client = useQueryClient(); const query = useQuery({ queryKey: ["admin","quiz-versions"], queryFn: adminQuizVersions, staleTime: 10_000 });
  const refresh = async () => client.invalidateQueries({ queryKey: ["admin","quiz-versions"] });
  const clone = useMutation({ mutationFn: cloneQuizDraft, onSuccess: refresh }); const publish = useMutation({ mutationFn: publishQuizVersion, onSuccess: refresh }); const archive = useMutation({ mutationFn: archiveQuizVersion, onSuccess: refresh });
  const current = query.data?.find((item) => item.status === "published");
  return <Panel title="نسخه‌های کوییز"><div className="mb-4"><Button disabled={!current} loading={clone.isPending} onClick={() => current && clone.mutate(current)}>ساخت draft از نسخه فعال</Button></div>{renderState(query, (items) => <div className="grid gap-3">{items.map((item) => <article key={item.id} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"><div className="flex items-center justify-between gap-3"><h3 className="font-bold">نسخه {toFa(item.version)} · {item.title}</h3><span className="text-xs text-[color:var(--muted-gold)]">{item.status}</span></div><p className="mt-2 font-mono text-[10px] text-[color:var(--light)]">{item.checksum}</p><div className="mt-3 flex gap-2">{item.status === "draft" ? <Button loading={publish.isPending} onClick={() => publish.mutate(item.id)}>انتشار immutable</Button> : null}{item.status !== "archived" ? <Button variant="outline" loading={archive.isPending} onClick={() => archive.mutate(item.id)}>بایگانی</Button> : null}</div></article>)}</div>)}</Panel>;
}
function Panel({ title, children }: { title: string; children: React.ReactNode }) { return <section className="mt-6 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"><h2 className="mb-4 text-xl font-bold">{title}</h2>{children}</section>; }
function StatusSelect({ value, options, onChange }: { value: string; options: string[]; onChange: (value: string) => void }) { return <label className="mb-4 block text-sm">وضعیت<select value={value} onChange={(event) => onChange(event.target.value)} className="mt-2 min-h-11 w-full max-w-xs rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3">{options.map((item) => <option key={item} value={item}>{item}</option>)}</select></label>; }
function renderState<T>(query: { isLoading: boolean; isError: boolean; error: unknown; data?: T[] }, render: (items: T[]) => React.ReactNode) { if (query.isLoading) return <Skeleton className="h-40" />; if (query.isError) return <Alert variant="danger">{isApiError(query.error) ? query.error.message : "دریافت داده انجام نشد."}</Alert>; if (!query.data?.length) return <EmptyState title="این صف خالی است" />; return render(query.data); }
