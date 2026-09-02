import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link, Navigate } from "@tanstack/react-router";
import { useState } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton, TextareaField } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import {
  listAdminSettlementProfiles,
  reviewAdminSettlementProfile,
  type AdminSettlementProfile,
  type SettlementProfileStatus,
} from "@/lib/api/settlement-profiles";

export const Route = createFileRoute("/admin/settlement-profiles")({
  head: () => ({
    meta: [
      { title: "بررسی مقصدهای تسویه | ادمین رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminSettlementProfilesPage,
});

function AdminSettlementProfilesPage() {
  return (
    <>
      <Navbar />
      <main dir="rtl" className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "داشبورد ادمین", to: "/admin/workspace" },
            { label: "مقصدهای تسویه" },
          ]}
        />
        <AccountGuard>
          {(user) =>
            user.roles.includes("administrator") ? (
              <ReviewWorkspace />
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

function ReviewWorkspace() {
  const [status, setStatus] = useState<SettlementProfileStatus | "all">("pending_review");
  const query = useQuery({
    queryKey: ["admin", "settlement-profiles", status],
    queryFn: () => listAdminSettlementProfiles(status),
    staleTime: 10_000,
  });

  return (
    <section className="mt-6 space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold tracking-[0.18em] text-[color:var(--roast)]">
            SETTLEMENT VERIFICATION
          </p>
          <h1 className="mt-2 text-3xl font-bold">بررسی مقصدهای تسویه</h1>
          <p className="mt-3 max-w-3xl text-sm leading-7 text-[color:var(--light)]">
            تأیید این صفحه مستقیماً شرط ساخت Settlement Batch است. اطلاعات بانکی فقط در این سطح
            دسترسی ادمین نمایش داده می‌شود.
          </p>
        </div>
        <select
          value={status}
          onChange={(event) => setStatus(event.target.value as SettlementProfileStatus | "all")}
          className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm"
          aria-label="فیلتر وضعیت"
        >
          <option value="pending_review">در انتظار بررسی</option>
          <option value="verified">تأییدشده</option>
          <option value="rejected">ردشده</option>
          <option value="all">همه</option>
        </select>
      </header>

      {query.isLoading ? <Skeleton className="h-96" /> : null}
      {query.isError ? (
        <Alert variant="danger">
          {isApiError(query.error) ? query.error.message : "فهرست مقصدهای تسویه دریافت نشد."}
        </Alert>
      ) : null}
      {query.data?.length === 0 ? <EmptyState title="موردی در این وضعیت وجود ندارد" /> : null}
      {query.data?.length ? (
        <div className="grid gap-4">
          {query.data.map((profile) => (
            <ProfileReviewCard key={profile.id} profile={profile} />
          ))}
        </div>
      ) : null}

      <Link
        to="/admin/workspace"
        className="inline-flex text-sm font-bold text-[color:var(--roast)] underline"
      >
        بازگشت به داشبورد ادمین
      </Link>
    </section>
  );
}

function ProfileReviewCard({ profile }: { profile: AdminSettlementProfile }) {
  const client = useQueryClient();
  const [note, setNote] = useState(profile.review_note || "");
  const mutation = useMutation({
    mutationFn: ({ decision }: { decision: "verified" | "rejected" }) =>
      reviewAdminSettlementProfile(profile.id, decision, note),
    onSuccess: async () => client.invalidateQueries({ queryKey: ["admin", "settlement-profiles"] }),
  });

  return (
    <article className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold">{profile.roastery.name || profile.roastery.id}</h2>
          <p className="mt-1 text-xs text-[color:var(--light)]">
            {profile.entity_type === "company" ? "شخصیت حقوقی" : "شخصیت حقیقی"} · وضعیت:{" "}
            {profile.status}
          </p>
        </div>
        <span
          dir="ltr"
          className="rounded-lg border border-[color:var(--mid)] px-3 py-2 font-mono text-sm"
        >
          {profile.iban}
        </span>
      </div>

      <dl className="mt-5 grid gap-3 md:grid-cols-2">
        <div>
          <dt className="text-xs text-[color:var(--light)]">نام قانونی</dt>
          <dd className="mt-1 font-bold">{profile.legal_name}</dd>
        </div>
        <div>
          <dt className="text-xs text-[color:var(--light)]">صاحب حساب</dt>
          <dd className="mt-1 font-bold">{profile.account_holder_name}</dd>
        </div>
        <div>
          <dt className="text-xs text-[color:var(--light)]">شبای ماسک‌شده</dt>
          <dd dir="ltr" className="mt-1 font-mono">
            {profile.iban_masked}
          </dd>
        </div>
        <div>
          <dt className="text-xs text-[color:var(--light)]">زمان ارسال</dt>
          <dd className="mt-1">
            {profile.submitted_at ? new Date(profile.submitted_at).toLocaleString("fa-IR") : "—"}
          </dd>
        </div>
      </dl>

      <div className="mt-5">
        <TextareaField
          id={`settlement-review-${profile.id}`}
          label="یادداشت بررسی / دلیل رد"
          value={note}
          maxLength={2000}
          onChange={(event) => setNote(event.target.value)}
        />
      </div>
      <div className="mt-4 flex flex-wrap gap-3">
        <Button
          type="button"
          loading={mutation.isPending}
          onClick={() => mutation.mutate({ decision: "verified" })}
        >
          تأیید مقصد تسویه
        </Button>
        <Button
          type="button"
          variant="outline"
          loading={mutation.isPending}
          disabled={!note.trim()}
          onClick={() => mutation.mutate({ decision: "rejected" })}
        >
          رد با دلیل
        </Button>
      </div>
      {mutation.isError ? (
        <div className="mt-4">
          <Alert variant="danger">
            {isApiError(mutation.error) ? mutation.error.message : "ثبت تصمیم انجام نشد."}
          </Alert>
        </div>
      ) : null}
    </article>
  );
}
