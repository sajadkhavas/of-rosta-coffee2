import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useState } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton } from "@/components/system";
import {
  adminAuditsQuery,
  adminFulfillmentIncidentsQuery,
  adminInquiriesQuery,
  adminNotificationsQuery,
  adminProductsQuery,
  adminReviewsQuery,
  adminRoasteriesQuery,
  moderateReview,
  resolveAdminFulfillmentIncident,
  setInquiryStatus,
  setProductStatus,
  setRoasteryStatus,
  type AdminFulfillmentIncidentStatus,
  type AdminInquiryStatus,
  type AdminNotificationStatus,
  type AdminProductStatus,
  type AdminReviewStatus,
  type AdminRoasteryStatus,
} from "@/lib/api/admin-operations";
import {
  adminSettlementBatchesQueryOptions,
  createAdminSettlementBatch,
  resolveAdminSettlementBatch,
  type AdminSettlementBatchStatus,
} from "@/lib/api/admin-finance";
import { isApiError } from "@/lib/api/client";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/admin/operations")({
  head: () => ({
    meta: [
      { title: "عملیات و نظارت | ادمین رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminOperationsPage,
});

type Tab =
  | "roasteries"
  | "products"
  | "incidents"
  | "settlements"
  | "reviews"
  | "inquiries"
  | "notifications"
  | "audits";
const tabs: Array<{ id: Tab; label: string }> = [
  { id: "roasteries", label: "روستری‌ها" },
  { id: "products", label: "محصولات" },
  { id: "incidents", label: "مشکلات ارسال" },
  { id: "settlements", label: "تسویه و پرداخت" },
  { id: "reviews", label: "نظرات" },
  { id: "inquiries", label: "پشتیبانی" },
  { id: "notifications", label: "اعلان‌ها" },
  { id: "audits", label: "ممیزی" },
];

const selectClass =
  "min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

function errorMessage(error: unknown): string {
  return isApiError(error)
    ? error.message
    : "دریافت یا ثبت اطلاعات انجام نشد. اتصال API و سطح دسترسی را بررسی کنید.";
}

function Panel({ children }: { children: React.ReactNode }) {
  return (
    <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
      {children}
    </section>
  );
}

function LoadingRows() {
  return (
    <div className="grid gap-3">
      {[1, 2, 3].map((item) => (
        <Skeleton key={item} className="h-28" />
      ))}
    </div>
  );
}

function AdminOperationsPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "عملیات ادمین" }]} />
        <AccountGuard>
          {(user) =>
            user.roles.includes("administrator") ? (
              <Dashboard />
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

function Dashboard() {
  const [tab, setTab] = useState<Tab>("roasteries");
  return (
    <section className="mt-8 space-y-6">
      <header>
        <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">
          OPERATIONS CONTROL
        </p>
        <h1 className="mt-2 text-3xl font-bold text-[color:var(--steam)]">
          نظارت کاتالوگ، تحویل، تسویه و سلامت عملیات
        </h1>
        <p className="mt-3 max-w-3xl text-sm leading-8 text-[color:var(--light)]">
          تمام تصمیم‌ها از API معتبر ثبت می‌شوند. Payload خصوصی اعلان‌ها نمایش داده نمی‌شود و
          تاریخچه ممیزی فقط خواندنی است.
        </p>
      </header>
      <nav className="flex gap-2 overflow-x-auto" aria-label="بخش‌های عملیات">
        {tabs.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setTab(item.id)}
            className={`whitespace-nowrap rounded-xl border px-4 py-2 text-sm font-bold ${tab === item.id ? "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]" : "border-[color:var(--mid)] text-[color:var(--light)]"}`}
          >
            {item.label}
          </button>
        ))}
      </nav>
      {tab === "roasteries" ? <RoasteriesQueue /> : null}
      {tab === "products" ? <ProductsQueue /> : null}
      {tab === "incidents" ? <FulfillmentIncidentsQueue /> : null}
      {tab === "settlements" ? <SettlementBatchesQueue /> : null}
      {tab === "reviews" ? <ReviewsQueue /> : null}
      {tab === "inquiries" ? <InquiriesQueue /> : null}
      {tab === "notifications" ? <NotificationsQueue /> : null}
      {tab === "audits" ? <AuditsQueue /> : null}
    </section>
  );
}

function RoasteriesQueue() {
  const client = useQueryClient();
  const [status, setStatus] = useState<AdminRoasteryStatus>("pending");
  const query = useQuery(adminRoasteriesQuery(status));
  const mutation = useMutation({
    mutationFn: ({ id, next }: { id: string; next: AdminRoasteryStatus }) =>
      setRoasteryStatus(id, next),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["admin", "operations", "roasteries"] }),
  });
  return (
    <Panel>
      <header className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">صف بررسی روستری‌ها</h2>
          <p className="mt-1 text-sm text-[color:var(--light)]">
            تأیید فقط پس از بررسی هویت، اطلاعات ارسال و رسانه‌ها.
          </p>
        </div>
        <select
          value={status}
          onChange={(event) => setStatus(event.target.value as AdminRoasteryStatus)}
          className={selectClass}
        >
          <option value="pending">در انتظار</option>
          <option value="verified">تأییدشده</option>
          <option value="suspended">تعلیق</option>
          <option value="rejected">ردشده</option>
        </select>
      </header>
      {query.isLoading ? (
        <LoadingRows />
      ) : query.isError ? (
        <Alert variant="danger">{errorMessage(query.error)}</Alert>
      ) : !query.data?.length ? (
        <EmptyState title="روستری‌ای در این صف نیست" />
      ) : (
        <div className="grid gap-4">
          {query.data.map((item) => (
            <article
              key={item.id}
              className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 className="font-bold">{item.name}</h3>
                  <p className="mt-1 text-xs text-[color:var(--light)]">
                    {item.city || "شهر ثبت نشده"} · {item.slug}
                  </p>
                  <p className="mt-3 max-w-3xl text-sm leading-7 text-[color:var(--light)]">
                    {item.description || "بدون توضیح"}
                  </p>
                </div>
                <span className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-xs">
                  {item.status}
                </span>
              </div>
              <div className="mt-4 flex flex-wrap gap-2">
                <Button
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "verified" })}
                >
                  تأیید
                </Button>
                <Button
                  variant="outline"
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "suspended" })}
                >
                  تعلیق
                </Button>
                <Button
                  variant="danger"
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "rejected" })}
                >
                  رد
                </Button>
              </div>
            </article>
          ))}
        </div>
      )}
      {mutation.isError ? (
        <p className="mt-4 text-sm text-red-300">{errorMessage(mutation.error)}</p>
      ) : null}
    </Panel>
  );
}

function ProductsQueue() {
  const client = useQueryClient();
  const [status, setStatus] = useState<AdminProductStatus>("review");
  const query = useQuery(adminProductsQuery(status));
  const mutation = useMutation({
    mutationFn: ({ id, next }: { id: string; next: AdminProductStatus }) =>
      setProductStatus(id, next),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["admin", "operations", "products"] }),
  });
  return (
    <Panel>
      <header className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">Moderation محصولات</h2>
          <p className="mt-1 text-sm text-[color:var(--light)]">
            وزن‌ها باید فقط دانه کامل و یکی از وزن‌های ثابت رستا باشند.
          </p>
        </div>
        <select
          value={status}
          onChange={(event) => setStatus(event.target.value as AdminProductStatus)}
          className={selectClass}
        >
          <option value="review">در انتظار بررسی</option>
          <option value="draft">پیش‌نویس</option>
          <option value="published">منتشرشده</option>
          <option value="archived">بایگانی</option>
        </select>
      </header>
      {query.isLoading ? (
        <LoadingRows />
      ) : query.isError ? (
        <Alert variant="danger">{errorMessage(query.error)}</Alert>
      ) : !query.data?.length ? (
        <EmptyState title="محصولی در این صف نیست" />
      ) : (
        <div className="grid gap-4">
          {query.data.map((item) => (
            <article
              key={item.id}
              className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
            >
              <div className="flex flex-wrap justify-between gap-3">
                <div>
                  <h3 className="font-bold">{item.name}</h3>
                  <p className="mt-1 text-xs text-[color:var(--light)]">
                    {item.roastery.name} · {item.origin.name}
                  </p>
                  <p className="mt-2 text-sm text-[color:var(--light)]">
                    رست {item.roast_level} · فرآوری {item.processing_method} · عربیکا{" "}
                    {toFa(item.arabica_percentage)}٪
                  </p>
                  <p className="mt-2 text-xs text-[color:var(--light)]">
                    وزن‌ها:{" "}
                    {item.variants
                      .map((variant) => `${toFa(variant.weight_grams)} گرم`)
                      .join("، ") || "بدون Variant"}
                  </p>
                </div>
                <span className="text-xs">{item.status}</span>
              </div>
              <div className="mt-4 flex flex-wrap gap-2">
                <Button
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "published" })}
                >
                  انتشار
                </Button>
                <Button
                  variant="outline"
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "review" })}
                >
                  بازگشت به بررسی
                </Button>
                <Button
                  variant="danger"
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "archived" })}
                >
                  بایگانی
                </Button>
              </div>
            </article>
          ))}
        </div>
      )}
      {mutation.isError ? (
        <p className="mt-4 text-sm text-red-300">{errorMessage(mutation.error)}</p>
      ) : null}
    </Panel>
  );
}

function FulfillmentIncidentsQueue() {
  const client = useQueryClient();
  const [status, setStatus] = useState<AdminFulfillmentIncidentStatus>("open");
  const [notes, setNotes] = useState<Record<string, string>>({});
  const [extensions, setExtensions] = useState<Record<string, number>>({});
  const query = useQuery(adminFulfillmentIncidentsQuery(status));
  const mutation = useMutation({
    mutationFn: ({
      id,
      resolution,
    }: {
      id: string;
      resolution: "resume_fulfillment" | "cancel_and_refund";
    }) =>
      resolveAdminFulfillmentIncident(id, {
        resolution,
        note: notes[id] || "تعیین تکلیف عملیاتی توسط پشتیبانی رستا",
        extendSlaHours: extensions[id] ?? 24,
      }),
    onSuccess: async () => {
      await client.invalidateQueries({
        queryKey: ["admin", "operations", "fulfillment-incidents"],
      });
    },
  });

  return (
    <Panel>
      <header className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">Incidentهای تعهد ارسال</h2>
          <p className="mt-1 max-w-3xl text-sm leading-7 text-[color:var(--light)]">
            روستری امکان رد مستقیم ندارد. ادامه آماده‌سازی یا لغو و بازپرداخت دقیق فقط توسط ادمین
            رستا ثبت می‌شود.
          </p>
        </div>
        <select
          value={status}
          onChange={(event) => setStatus(event.target.value as AdminFulfillmentIncidentStatus)}
          className={selectClass}
        >
          <option value="open">باز</option>
          <option value="resolved">بسته‌شده</option>
        </select>
      </header>
      {query.isLoading ? (
        <LoadingRows />
      ) : query.isError ? (
        <Alert variant="danger">{errorMessage(query.error)}</Alert>
      ) : !query.data?.length ? (
        <EmptyState title="Incidentی در این وضعیت نیست" />
      ) : (
        <div className="grid gap-4">
          {query.data.map((item) => (
            <article
              key={item.id}
              className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 className="font-bold">
                    {item.roastery.name || "روستری"} · سفارش {item.order_number || item.order_id}
                  </h3>
                  <p className="mt-1 text-xs text-[color:var(--light)]">
                    {item.code} · شدت {item.severity} · SLA {item.sla_status || "-"}
                  </p>
                </div>
                <span className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-xs">
                  {item.status}
                </span>
              </div>
              <p className="mt-3 whitespace-pre-wrap text-sm leading-7 text-[color:var(--light)]">
                {item.description}
              </p>
              <p className="mt-2 text-xs text-[color:var(--light)]">
                مهلت تحویل به حمل: {item.handoff_due_at || "ثبت نشده"}
              </p>
              {item.status === "open" ? (
                <div className="mt-4 grid gap-3 border-t border-[color:var(--mid)] pt-4">
                  <textarea
                    value={notes[item.id] || ""}
                    onChange={(event) =>
                      setNotes((current) => ({ ...current, [item.id]: event.target.value }))
                    }
                    placeholder="یادداشت تصمیم ادمین"
                    className="min-h-24 w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-3 text-sm"
                  />
                  <label className="grid gap-2 text-sm font-bold">
                    تمدید SLA برای ادامه آماده‌سازی
                    <input
                      type="number"
                      min={0}
                      max={168}
                      value={extensions[item.id] ?? 24}
                      onChange={(event) =>
                        setExtensions((current) => ({
                          ...current,
                          [item.id]: Number(event.target.value),
                        }))
                      }
                      className={selectClass}
                    />
                  </label>
                  <div className="flex flex-wrap gap-2">
                    <Button
                      loading={mutation.isPending}
                      onClick={() =>
                        mutation.mutate({ id: item.id, resolution: "resume_fulfillment" })
                      }
                    >
                      رفع مشکل و ادامه ارسال
                    </Button>
                    <Button
                      variant="danger"
                      loading={mutation.isPending}
                      onClick={() =>
                        mutation.mutate({ id: item.id, resolution: "cancel_and_refund" })
                      }
                    >
                      لغو همین زیرسفارش و ثبت بازپرداخت
                    </Button>
                  </div>
                  {mutation.isError ? (
                    <Alert variant="danger">{errorMessage(mutation.error)}</Alert>
                  ) : null}
                </div>
              ) : (
                <p className="mt-3 text-sm text-[color:var(--light)]">
                  نتیجه: {item.resolution || "ثبت نشده"} · {item.resolution_note || "بدون یادداشت"}
                </p>
              )}
            </article>
          ))}
        </div>
      )}
    </Panel>
  );
}

function SettlementBatchesQueue() {
  const client = useQueryClient();
  const [status, setStatus] = useState<AdminSettlementBatchStatus | "all">("all");
  const [roasteryId, setRoasteryId] = useState("");
  const [payoutReferences, setPayoutReferences] = useState<Record<string, string>>({});
  const [failureMessages, setFailureMessages] = useState<Record<string, string>>({});
  const query = useQuery(adminSettlementBatchesQueryOptions(status));

  const createMutation = useMutation({
    mutationFn: () =>
      createAdminSettlementBatch({
        roasteryId,
        idempotencyKey: crypto.randomUUID(),
      }),
    onSuccess: async () => {
      setRoasteryId("");
      await client.invalidateQueries({ queryKey: ["admin", "finance", "settlement-batches"] });
    },
  });

  const resolveMutation = useMutation({
    mutationFn: ({ batchId, action }: { batchId: string; action: "process" | "paid" | "failed" }) =>
      resolveAdminSettlementBatch({
        batchId,
        action,
        payoutReference: payoutReferences[batchId],
        failureCode: action === "failed" ? "bank_transfer_failed" : undefined,
        failureMessage: failureMessages[batchId],
      }),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["admin", "finance", "settlement-batches"] }),
  });

  return (
    <Panel>
      <header className="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold">Batchهای تسویه روستری</h2>
          <p className="mt-1 max-w-3xl text-sm leading-7 text-[color:var(--light)]">
            فقط Allocationهای متعلق به روستری که تحویل قطعی شده‌اند، مهلت اعتراضشان پایان یافته و
            اختلاف بازی ندارند وارد Batch می‌شوند. هزینه‌های هاب و خدمات متعلق به رستا جدا می‌مانند.
          </p>
        </div>
        <select
          value={status}
          onChange={(event) => setStatus(event.target.value as AdminSettlementBatchStatus | "all")}
          className={selectClass}
        >
          <option value="all">همه وضعیت‌ها</option>
          <option value="pending">در انتظار</option>
          <option value="processing">در حال پردازش</option>
          <option value="paid">پرداخت‌شده</option>
          <option value="failed">ناموفق</option>
        </select>
      </header>

      <form
        className="mb-6 grid gap-3 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 md:grid-cols-[1fr_auto]"
        onSubmit={(event) => {
          event.preventDefault();
          if (roasteryId.trim()) createMutation.mutate();
        }}
      >
        <label className="grid gap-2 text-sm font-bold">
          شناسه روستری برای ساخت Batch
          <input
            dir="ltr"
            value={roasteryId}
            onChange={(event) => setRoasteryId(event.target.value)}
            className={selectClass}
            placeholder="01H..."
            required
          />
        </label>
        <Button type="submit" loading={createMutation.isPending}>
          ساخت Batch از مبالغ واجد شرایط
        </Button>
        {createMutation.isError ? (
          <div className="md:col-span-2">
            <Alert variant="danger">{errorMessage(createMutation.error)}</Alert>
          </div>
        ) : null}
      </form>

      {query.isLoading ? (
        <LoadingRows />
      ) : query.isError ? (
        <Alert variant="danger">{errorMessage(query.error)}</Alert>
      ) : !query.data?.items.length ? (
        <EmptyState title="Batch تسویه‌ای در این وضعیت نیست" />
      ) : (
        <div className="grid gap-4">
          {query.data.items.map((batch) => (
            <article
              key={batch.id}
              className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 className="font-mono-num text-sm font-bold">Batch {batch.id}</h3>
                  <p className="mt-2 text-xs text-[color:var(--light)]">
                    {batch.roastery.name || batch.roastery.id} · {toFa(batch.allocation_count)} ردیف
                  </p>
                </div>
                <span className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-xs">
                  {settlementStatusLabel(batch.status)}
                </span>
              </div>

              <div className="mt-4 grid gap-3 text-sm md:grid-cols-4">
                <MoneyValue label="ناخالص" value={batch.gross_total} />
                <MoneyValue label="تخفیف" value={batch.discount_total} />
                <MoneyValue label="مالیات" value={batch.tax_total} />
                <MoneyValue label="خالص پرداخت" value={batch.net_total} strong />
              </div>

              {batch.status === "pending" || batch.status === "failed" ? (
                <div className="mt-4">
                  <Button
                    loading={resolveMutation.isPending}
                    onClick={() => resolveMutation.mutate({ batchId: batch.id, action: "process" })}
                  >
                    شروع پردازش پرداخت
                  </Button>
                </div>
              ) : null}

              {batch.status === "processing" ? (
                <div className="mt-4 grid gap-3 border-t border-[color:var(--mid)] pt-4 lg:grid-cols-2">
                  <label className="grid gap-2 text-sm font-bold">
                    شماره پیگیری بانکی
                    <input
                      dir="ltr"
                      value={payoutReferences[batch.id] ?? ""}
                      onChange={(event) =>
                        setPayoutReferences((current) => ({
                          ...current,
                          [batch.id]: event.target.value,
                        }))
                      }
                      className={selectClass}
                    />
                  </label>
                  <label className="grid gap-2 text-sm font-bold">
                    توضیح خطا برای ثبت ناموفق
                    <input
                      value={failureMessages[batch.id] ?? ""}
                      onChange={(event) =>
                        setFailureMessages((current) => ({
                          ...current,
                          [batch.id]: event.target.value,
                        }))
                      }
                      className={selectClass}
                    />
                  </label>
                  <div className="flex flex-wrap gap-2 lg:col-span-2">
                    <Button
                      loading={resolveMutation.isPending}
                      disabled={!payoutReferences[batch.id]?.trim()}
                      onClick={() => resolveMutation.mutate({ batchId: batch.id, action: "paid" })}
                    >
                      ثبت پرداخت موفق
                    </Button>
                    <Button
                      variant="danger"
                      loading={resolveMutation.isPending}
                      disabled={!failureMessages[batch.id]?.trim()}
                      onClick={() =>
                        resolveMutation.mutate({ batchId: batch.id, action: "failed" })
                      }
                    >
                      ثبت پرداخت ناموفق
                    </Button>
                  </div>
                </div>
              ) : null}

              {batch.payout_reference ? (
                <p dir="ltr" className="mt-4 text-left text-xs text-emerald-300">
                  Bank reference: {batch.payout_reference}
                </p>
              ) : null}
              {batch.failure_message ? (
                <p className="mt-4 text-xs text-red-300">{batch.failure_message}</p>
              ) : null}
            </article>
          ))}
        </div>
      )}
      {resolveMutation.isError ? (
        <div className="mt-4">
          <Alert variant="danger">{errorMessage(resolveMutation.error)}</Alert>
        </div>
      ) : null}
    </Panel>
  );
}

function MoneyValue({
  label,
  value,
  strong = false,
}: {
  label: string;
  value: number;
  strong?: boolean;
}) {
  return (
    <div className="rounded-lg border border-[color:var(--mid)] p-3">
      <p className="text-xs text-[color:var(--light)]">{label}</p>
      <p className={`mt-1 font-mono-num ${strong ? "font-bold text-[color:var(--roast)]" : ""}`}>
        {value.toLocaleString("fa-IR")} ریال
      </p>
    </div>
  );
}

function settlementStatusLabel(status: AdminSettlementBatchStatus) {
  return {
    pending: "در انتظار پردازش",
    processing: "در حال پرداخت",
    paid: "پرداخت‌شده",
    failed: "ناموفق؛ قابل تلاش مجدد",
  }[status];
}

function ReviewsQueue() {
  const client = useQueryClient();
  const [status, setStatus] = useState<AdminReviewStatus>("pending");
  const [reasons, setReasons] = useState<Record<string, string>>({});
  const query = useQuery(adminReviewsQuery(status));
  const mutation = useMutation({
    mutationFn: ({ id, next }: { id: string; next: "approved" | "rejected" }) =>
      moderateReview(id, next, reasons[id]),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["admin", "operations", "reviews"] }),
  });
  return (
    <Panel>
      <header className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">مدیریت نظر خریداران</h2>
          <p className="mt-1 text-sm text-[color:var(--light)]">
            فقط خرید تأییدشده وارد این صف می‌شود.
          </p>
        </div>
        <select
          value={status}
          onChange={(event) => setStatus(event.target.value as AdminReviewStatus)}
          className={selectClass}
        >
          <option value="pending">در انتظار</option>
          <option value="approved">تأییدشده</option>
          <option value="rejected">ردشده</option>
        </select>
      </header>
      {query.isLoading ? (
        <LoadingRows />
      ) : query.isError ? (
        <Alert variant="danger">{errorMessage(query.error)}</Alert>
      ) : !query.data?.length ? (
        <EmptyState title="نظری در این صف نیست" />
      ) : (
        <div className="grid gap-4">
          {query.data.map((item) => (
            <article
              key={item.id}
              className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
            >
              <p className="text-sm font-bold">
                امتیاز {toFa(item.rating)} از ۵ {item.title ? `· ${item.title}` : ""}
              </p>
              <p className="mt-3 whitespace-pre-wrap text-sm leading-7 text-[color:var(--light)]">
                {item.body}
              </p>
              <textarea
                value={reasons[item.id] || ""}
                onChange={(event) =>
                  setReasons((current) => ({ ...current, [item.id]: event.target.value }))
                }
                placeholder="دلیل تصمیم، مخصوصاً برای رد"
                className="mt-4 min-h-20 w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-3 text-sm"
              />
              <div className="mt-3 flex gap-2">
                <Button
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "approved" })}
                >
                  تأیید
                </Button>
                <Button
                  variant="danger"
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "rejected" })}
                >
                  رد
                </Button>
              </div>
            </article>
          ))}
        </div>
      )}
    </Panel>
  );
}

function InquiriesQueue() {
  const client = useQueryClient();
  const [status, setStatus] = useState<AdminInquiryStatus>("new");
  const query = useQuery(adminInquiriesQuery(status));
  const mutation = useMutation({
    mutationFn: ({ id, next }: { id: string; next: AdminInquiryStatus }) =>
      setInquiryStatus(id, next),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["admin", "operations", "inquiries"] }),
  });
  return (
    <Panel>
      <header className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">صف پشتیبانی و درخواست‌ها</h2>
          <p className="mt-1 text-sm text-[color:var(--light)]">
            اطلاعات تماس فقط برای رسیدگی عملیاتی نمایش داده می‌شود.
          </p>
        </div>
        <select
          value={status}
          onChange={(event) => setStatus(event.target.value as AdminInquiryStatus)}
          className={selectClass}
        >
          <option value="new">جدید</option>
          <option value="in_progress">در حال پیگیری</option>
          <option value="resolved">حل‌شده</option>
          <option value="closed">بسته</option>
          <option value="spam">اسپم</option>
        </select>
      </header>
      {query.isLoading ? (
        <LoadingRows />
      ) : query.isError ? (
        <Alert variant="danger">{errorMessage(query.error)}</Alert>
      ) : !query.data?.length ? (
        <EmptyState title="درخواستی در این صف نیست" />
      ) : (
        <div className="grid gap-4">
          {query.data.map((item) => (
            <article
              key={item.id}
              className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
            >
              <div className="flex flex-wrap justify-between gap-3">
                <div>
                  <h3 className="font-bold">
                    {item.name} · {item.type}
                  </h3>
                  <p dir="ltr" className="mt-1 text-left text-xs text-[color:var(--light)]">
                    {item.mobile || item.email || "بدون تماس"}
                  </p>
                  {item.order_number ? (
                    <p className="mt-1 text-xs">سفارش: {item.order_number}</p>
                  ) : null}
                </div>
                <span className="text-xs">{item.status}</span>
              </div>
              <p className="mt-3 whitespace-pre-wrap text-sm leading-7 text-[color:var(--light)]">
                {item.message}
              </p>
              <div className="mt-4 flex flex-wrap gap-2">
                <Button
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "in_progress" })}
                >
                  در حال پیگیری
                </Button>
                <Button
                  variant="outline"
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "resolved" })}
                >
                  حل شد
                </Button>
                <Button
                  variant="ghost"
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "closed" })}
                >
                  بستن
                </Button>
                <Button
                  variant="danger"
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate({ id: item.id, next: "spam" })}
                >
                  اسپم
                </Button>
              </div>
            </article>
          ))}
        </div>
      )}
    </Panel>
  );
}

function NotificationsQueue() {
  const [status, setStatus] = useState<AdminNotificationStatus>("failed");
  const query = useQuery(adminNotificationsQuery(status));
  return (
    <Panel>
      <header className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">سلامت Notification Outbox</h2>
          <p className="mt-1 text-sm text-[color:var(--light)]">
            مقصد Mask شده و Payload پیام هرگز به مرورگر ارسال نمی‌شود.
          </p>
        </div>
        <select
          value={status}
          onChange={(event) => setStatus(event.target.value as AdminNotificationStatus)}
          className={selectClass}
        >
          <option value="failed">ناموفق</option>
          <option value="pending">در انتظار</option>
          <option value="processing">در حال پردازش</option>
          <option value="sent">ارسال‌شده</option>
        </select>
      </header>
      {query.isLoading ? (
        <LoadingRows />
      ) : query.isError ? (
        <Alert variant="danger">{errorMessage(query.error)}</Alert>
      ) : !query.data?.length ? (
        <EmptyState title="اعلانی در این وضعیت نیست" />
      ) : (
        <div className="grid gap-3">
          {query.data.map((item) => (
            <article
              key={item.id}
              className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
            >
              <div className="flex flex-wrap justify-between gap-3">
                <p className="font-bold">{item.template_key}</p>
                <span className="text-xs">{item.status}</span>
              </div>
              <p className="mt-2 text-sm text-[color:var(--light)]">
                {item.channel} · {item.destination_hint} · تلاش {toFa(item.attempts)}
              </p>
              {item.last_error ? (
                <p className="mt-2 text-sm text-red-300">{item.last_error}</p>
              ) : null}
            </article>
          ))}
        </div>
      )}
    </Panel>
  );
}

function AuditsQueue() {
  const [action, setAction] = useState("");
  const query = useQuery(adminAuditsQuery(action));
  return (
    <Panel>
      <header className="mb-5">
        <h2 className="text-xl font-bold">Audit Log فقط‌خواندنی</h2>
        <input
          value={action}
          onChange={(event) => setAction(event.target.value)}
          placeholder="فیلتر پیشوند Action، مثل review."
          className={`mt-4 w-full ${selectClass}`}
        />
      </header>
      {query.isLoading ? (
        <LoadingRows />
      ) : query.isError ? (
        <Alert variant="danger">{errorMessage(query.error)}</Alert>
      ) : !query.data?.length ? (
        <EmptyState title="رکورد ممیزی پیدا نشد" />
      ) : (
        <div className="grid gap-3">
          {query.data.map((item) => (
            <article
              key={item.id}
              className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
            >
              <div className="flex flex-wrap justify-between gap-3">
                <p className="font-mono-num text-sm font-bold">{item.action}</p>
                <span className="text-xs text-[color:var(--light)]">{item.created_at || ""}</span>
              </div>
              <p className="mt-2 text-xs text-[color:var(--light)]">
                {item.actor?.name || "سیستم"} · {item.auditable_type} · {item.auditable_id || "-"}
              </p>
              <pre
                className="mt-3 overflow-x-auto rounded-lg bg-black/20 p-3 text-left text-xs"
                dir="ltr"
              >
                {JSON.stringify(item.metadata, null, 2)}
              </pre>
            </article>
          ))}
        </div>
      )}
    </Panel>
  );
}
