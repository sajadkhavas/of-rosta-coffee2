import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import {
  AlertTriangle,
  CheckCircle2,
  RefreshCw,
  ShieldCheck,
  WalletCards,
} from "lucide-react";
import { useRef, useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import {
  Alert,
  Button,
  Dialog,
  EmptyState,
  PageHeader,
  Skeleton,
  TextareaField,
  TextField,
  useToast,
} from "@/components/system";
import {
  adminReconciliationQueryOptions,
  adminRefundsQueryOptions,
  approveAdminRefund,
  createAdminRefund,
  dispatchAdminRefund,
  resolveAdminReconciliationCase,
  resolveAdminRefund,
  type AdminReconciliationCase,
  type AdminReconciliationStatus,
  type AdminRefund,
  type AdminRefundStatus,
} from "@/lib/api/admin-finance";
import { isApiError } from "@/lib/api/client";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/admin/finance")({
  head: () => ({
    meta: [
      { title: "بازپرداخت و تطبیق مالی | ادمین رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminFinancePage,
});

const refundLabels: Record<AdminRefundStatus, string> = {
  requested: "درخواست‌شده",
  approved: "تأییدشده",
  processing: "در حال پردازش",
  succeeded: "موفق",
  failed: "ناموفق",
  cancelled: "لغوشده",
  requires_review: "نیازمند تطبیق",
};

const caseLabels: Record<AdminReconciliationStatus, string> = {
  open: "باز",
  investigating: "در حال بررسی",
  resolved: "حل‌شده",
  dismissed: "مختومه بدون اقدام",
};

const statusClasses: Record<AdminRefundStatus, string> = {
  requested: "border-blue-400/40 bg-blue-950/20 text-blue-200",
  approved: "border-violet-400/40 bg-violet-950/20 text-violet-200",
  processing: "border-amber-400/40 bg-amber-950/20 text-amber-200",
  succeeded: "border-emerald-400/40 bg-emerald-950/20 text-emerald-200",
  failed: "border-red-400/40 bg-red-950/20 text-red-200",
  cancelled: "border-slate-400/40 bg-slate-950/20 text-slate-200",
  requires_review: "border-orange-400/40 bg-orange-950/20 text-orange-200",
};

const fieldClass =
  "min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

function AdminFinancePage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "عملیات مالی" },
          ]}
        />
        <AccountGuard>
          {(user) =>
            user.roles.includes("administrator") ? (
              <AdminFinanceDashboard currentUserId={user.id} />
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

function AdminFinanceDashboard({ currentUserId }: { currentUserId: string }) {
  const queryClient = useQueryClient();
  const { pushToast } = useToast();
  const idempotencyKey = useRef("");
  const [refundStatus, setRefundStatus] = useState<AdminRefundStatus | "all">(
    "all",
  );
  const [caseStatus, setCaseStatus] = useState<AdminReconciliationStatus>(
    "open",
  );
  const [selectedRefund, setSelectedRefund] = useState<AdminRefund | null>(null);
  const [selectedCase, setSelectedCase] =
    useState<AdminReconciliationCase | null>(null);
  const [resolveRefundOpen, setResolveRefundOpen] = useState(false);
  const [resolveCaseOpen, setResolveCaseOpen] = useState(false);
  const [refundForm, setRefundForm] = useState({
    orderId: "",
    amount: "",
    reason: "",
  });
  const [refundResolution, setRefundResolution] = useState({
    outcome: "succeeded" as "succeeded" | "failed" | "cancelled",
    providerReference: "",
    failureCode: "",
    message: "",
  });
  const [caseResolution, setCaseResolution] = useState({
    status: "resolved" as "resolved" | "dismissed",
    resolution: "",
  });

  const refundsQuery = useQuery(adminRefundsQueryOptions(refundStatus));
  const casesQuery = useQuery(adminReconciliationQueryOptions(caseStatus));

  const invalidateFinance = async () => {
    await queryClient.invalidateQueries({ queryKey: ["admin", "finance"] });
  };

  const createMutation = useMutation({
    mutationFn: createAdminRefund,
    onSuccess: async (refund) => {
      setSelectedRefund(refund);
      setRefundForm({ orderId: "", amount: "", reason: "" });
      idempotencyKey.current = "";
      await invalidateFinance();
      pushToast({
        title: "درخواست بازپرداخت ثبت شد",
        description: "برای اجرای مالی، تأیید ادمین دوم الزامی است.",
        variant: "success",
      });
    },
  });

  const approveMutation = useMutation({
    mutationFn: approveAdminRefund,
    onSuccess: async (refund) => {
      setSelectedRefund(refund);
      await invalidateFinance();
      pushToast({ title: "بازپرداخت تأیید شد", variant: "success" });
    },
  });

  const dispatchMutation = useMutation({
    mutationFn: dispatchAdminRefund,
    onSuccess: async (refund) => {
      setSelectedRefund(refund);
      await invalidateFinance();
      pushToast({
        title:
          refund.status === "succeeded"
            ? "بازپرداخت موفق ثبت شد"
            : "بازپرداخت برای پردازش ارسال شد",
        variant: refund.status === "succeeded" ? "success" : "info",
      });
    },
  });

  const resolveRefundMutation = useMutation({
    mutationFn: resolveAdminRefund,
    onSuccess: async (refund) => {
      setSelectedRefund(refund);
      setResolveRefundOpen(false);
      setRefundResolution({
        outcome: "succeeded",
        providerReference: "",
        failureCode: "",
        message: "",
      });
      await invalidateFinance();
      pushToast({ title: "نتیجه authoritative ثبت شد", variant: "success" });
    },
  });

  const resolveCaseMutation = useMutation({
    mutationFn: resolveAdminReconciliationCase,
    onSuccess: async (financeCase) => {
      setSelectedCase(financeCase);
      setResolveCaseOpen(false);
      setCaseResolution({ status: "resolved", resolution: "" });
      await invalidateFinance();
      pushToast({ title: "پرونده تطبیق بسته شد", variant: "success" });
    },
  });

  const submitRefund = (event: FormEvent) => {
    event.preventDefault();
    const orderId = refundForm.orderId.trim();
    const reason = refundForm.reason.trim();
    if (!orderId || !reason) return;
    if (!idempotencyKey.current) {
      idempotencyKey.current = newIdempotencyKey();
    }
    const amount = refundForm.amount.trim()
      ? Number(refundForm.amount.replace(/[^0-9]/g, ""))
      : undefined;
    createMutation.mutate({
      orderId,
      amount: amount && amount > 0 ? amount : undefined,
      reason,
      idempotencyKey: idempotencyKey.current,
    });
  };

  const submitRefundResolution = (event: FormEvent) => {
    event.preventDefault();
    if (!selectedRefund) return;
    resolveRefundMutation.mutate({
      refundId: selectedRefund.id,
      outcome: refundResolution.outcome,
      providerReference: refundResolution.providerReference,
      failureCode: refundResolution.failureCode,
      message: refundResolution.message,
    });
  };

  const submitCaseResolution = (event: FormEvent) => {
    event.preventDefault();
    if (!selectedCase || !caseResolution.resolution.trim()) return;
    resolveCaseMutation.mutate({
      caseId: selectedCase.id,
      status: caseResolution.status,
      resolution: caseResolution.resolution,
    });
  };

  const initialError = refundsQuery.error || casesQuery.error;

  return (
    <section className="mt-8 space-y-8">
      <PageHeader
        eyebrow="FINANCIAL OPERATIONS"
        title="بازپرداخت و تطبیق مالی"
        description="تمام حرکت‌های مالی از Ledger قطعی، کنترل دو‌نفره و نتیجه Provider پیروی می‌کنند. هیچ وضعیت موفقی فقط با ادعای مرورگر یا اپراتور ساخته نمی‌شود."
        actions={
          <Button
            variant="outline"
            onClick={() => void invalidateFinance()}
            loading={refundsQuery.isFetching || casesQuery.isFetching}
          >
            <RefreshCw size={16} />
            تازه‌سازی
          </Button>
        }
      />

      <Alert variant="warning" title="مرز اجرای پول واقعی">
        این صفحه قرارداد مالی را مدیریت می‌کند، اما Provider تا پایان Acceptance سرور باید
        غیرفعال یا Manual باقی بماند. Testing Provider در Production پذیرفته نمی‌شود.
      </Alert>

      {initialError ? (
        <Alert variant="danger" title="داده‌های مالی دریافت نشد">
          {errorMessage(initialError)}
        </Alert>
      ) : null}

      <div className="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.5fr)]">
        <form
          onSubmit={submitRefund}
          className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
        >
          <div className="flex items-center gap-3">
            <span className="grid size-10 place-items-center rounded-xl bg-[color:var(--roast)] text-[color:var(--night)]">
              <WalletCards size={20} />
            </span>
            <div>
              <h2 className="font-bold">درخواست بازپرداخت</h2>
              <p className="mt-1 text-xs text-[color:var(--light)]">
                مبلغ خالی یعنی تمام مانده قابل‌بازپرداخت.
              </p>
            </div>
          </div>
          <div className="mt-5 grid gap-4">
            <TextField
              label="شناسه سفارش"
              required
              dir="ltr"
              value={refundForm.orderId}
              onChange={(event) =>
                setRefundForm((current) => ({
                  ...current,
                  orderId: event.target.value,
                }))
              }
            />
            <TextField
              label="مبلغ ریالی اختیاری"
              inputMode="numeric"
              dir="ltr"
              placeholder="تمام مانده"
              value={refundForm.amount}
              onChange={(event) =>
                setRefundForm((current) => ({
                  ...current,
                  amount: event.target.value.replace(/[^0-9]/g, ""),
                }))
              }
            />
            <TextareaField
              label="دلیل مالی"
              required
              minLength={3}
              maxLength={2_000}
              value={refundForm.reason}
              onChange={(event) =>
                setRefundForm((current) => ({
                  ...current,
                  reason: event.target.value,
                }))
              }
            />
          </div>
          {createMutation.isError ? (
            <p role="alert" className="mt-3 text-sm text-red-300">
              {errorMessage(createMutation.error)}
            </p>
          ) : null}
          <Button
            type="submit"
            className="mt-5 w-full"
            loading={createMutation.isPending}
          >
            ثبت درخواست با کنترل دو‌نفره
          </Button>
        </form>

        <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="font-bold">Refund Ledger</h2>
              <p className="mt-1 text-xs text-[color:var(--light)]">
                {toFa(refundsQuery.data?.pagination.total ?? 0)} رکورد
              </p>
            </div>
            <label className="grid gap-1 text-xs font-bold">
              وضعیت
              <select
                value={refundStatus}
                onChange={(event) =>
                  setRefundStatus(
                    event.target.value as AdminRefundStatus | "all",
                  )
                }
                className={fieldClass}
              >
                <option value="all">همه</option>
                {Object.entries(refundLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <div className="mt-5 grid gap-3">
            {refundsQuery.isLoading ? (
              Array.from({ length: 4 }).map((_, index) => (
                <Skeleton key={index} className="h-28" />
              ))
            ) : refundsQuery.data?.items.length ? (
              refundsQuery.data.items.map((refund) => (
                <button
                  type="button"
                  key={refund.id}
                  onClick={() => setSelectedRefund(refund)}
                  className="grid gap-3 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 text-start transition hover:border-[color:var(--roast)] md:grid-cols-[1fr_auto]"
                >
                  <div>
                    <p className="font-bold">
                      سفارش {refund.order_number || shortId(refund.order_id)}
                    </p>
                    <p className="mt-2 line-clamp-2 text-xs leading-6 text-[color:var(--light)]">
                      {refund.reason}
                    </p>
                    <p className="mt-2 font-mono-num text-xs text-[color:var(--roast)]">
                      {formatIrr(refund.amount)}
                    </p>
                  </div>
                  <div className="flex items-start justify-between gap-3 md:flex-col md:items-end">
                    <StatusBadge status={refund.status} />
                    <span className="text-[10px] text-[color:var(--light)]">
                      {formatDate(refund.created_at)}
                    </span>
                  </div>
                </button>
              ))
            ) : (
              <EmptyState
                title="رکورد بازپرداختی وجود ندارد"
                description="پس از ثبت درخواست مالی، رکورد تغییرناپذیر آن اینجا دیده می‌شود."
              />
            )}
          </div>
        </div>
      </div>

      {selectedRefund ? (
        <RefundDetail
          refund={selectedRefund}
          currentUserId={currentUserId}
          onApprove={() => approveMutation.mutate(selectedRefund.id)}
          onDispatch={() => dispatchMutation.mutate(selectedRefund.id)}
          onResolve={() => setResolveRefundOpen(true)}
          approving={approveMutation.isPending}
          dispatching={dispatchMutation.isPending}
          error={
            approveMutation.error || dispatchMutation.error || undefined
          }
        />
      ) : null}

      <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 font-bold">
              <ShieldCheck size={19} className="text-[color:var(--roast)]" />
              پرونده‌های تطبیق مالی
            </h2>
            <p className="mt-1 text-xs text-[color:var(--light)]">
              نتیجه نامشخص، پرداخت مشکوک و مانده بازپرداخت جزئی
            </p>
          </div>
          <label className="grid gap-1 text-xs font-bold">
            وضعیت پرونده
            <select
              value={caseStatus}
              onChange={(event) =>
                setCaseStatus(event.target.value as AdminReconciliationStatus)
              }
              className={fieldClass}
            >
              {Object.entries(caseLabels).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
        </div>

        <div className="mt-5 grid gap-4 lg:grid-cols-2">
          {casesQuery.isLoading ? (
            Array.from({ length: 4 }).map((_, index) => (
              <Skeleton key={index} className="h-36" />
            ))
          ) : casesQuery.data?.items.length ? (
            casesQuery.data.items.map((financeCase) => (
              <button
                type="button"
                key={financeCase.id}
                onClick={() => setSelectedCase(financeCase)}
                className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 text-start transition hover:border-[color:var(--roast)]"
              >
                <div className="flex items-start justify-between gap-3">
                  <span className="rounded-full border border-orange-400/40 bg-orange-950/20 px-3 py-1 text-[10px] font-bold text-orange-200">
                    {severityLabel(financeCase.severity)}
                  </span>
                  <span className="text-[10px] text-[color:var(--light)]">
                    {caseLabels[financeCase.status]}
                  </span>
                </div>
                <p className="mt-3 font-bold">{financeCase.summary}</p>
                <p className="mt-2 font-mono-num text-xs text-[color:var(--roast)]">
                  {financeCase.order_number || shortId(financeCase.order_id)}
                </p>
                <p className="mt-2 text-[10px] text-[color:var(--light)]">
                  {financeCase.kind} · {formatDate(financeCase.opened_at)}
                </p>
              </button>
            ))
          ) : (
            <div className="lg:col-span-2">
              <EmptyState
                title="پرونده‌ای در این وضعیت نیست"
                description="این نتیجه به معنی سلامت قطعی Provider نیست؛ فقط صف فعلی خالی است."
              />
            </div>
          )}
        </div>
      </div>

      {selectedCase ? (
        <CaseDetail
          financeCase={selectedCase}
          onResolve={() => setResolveCaseOpen(true)}
        />
      ) : null}

      <Dialog
        open={resolveRefundOpen}
        onOpenChange={setResolveRefundOpen}
        title="ثبت نتیجه authoritative بازپرداخت"
        description="این فرم فقط بعد از مشاهده نتیجه قطعی در پنل رسمی Provider استفاده شود."
      >
        <form onSubmit={submitRefundResolution} className="grid gap-4">
          <label className="grid gap-2 text-sm font-bold">
            نتیجه
            <select
              value={refundResolution.outcome}
              onChange={(event) =>
                setRefundResolution((current) => ({
                  ...current,
                  outcome: event.target.value as
                    | "succeeded"
                    | "failed"
                    | "cancelled",
                }))
              }
              className={fieldClass}
            >
              <option value="succeeded">موفق</option>
              <option value="failed">ناموفق</option>
              <option value="cancelled">لغوشده</option>
            </select>
          </label>
          {refundResolution.outcome === "succeeded" ? (
            <TextField
              label="شناسه مرجع Provider"
              required
              dir="ltr"
              value={refundResolution.providerReference}
              onChange={(event) =>
                setRefundResolution((current) => ({
                  ...current,
                  providerReference: event.target.value,
                }))
              }
            />
          ) : null}
          {refundResolution.outcome === "failed" ? (
            <TextField
              label="کد خطا"
              required
              dir="ltr"
              value={refundResolution.failureCode}
              onChange={(event) =>
                setRefundResolution((current) => ({
                  ...current,
                  failureCode: event.target.value,
                }))
              }
            />
          ) : null}
          <TextareaField
            label="توضیح و مدرک مشاهده‌شده"
            value={refundResolution.message}
            onChange={(event) =>
              setRefundResolution((current) => ({
                ...current,
                message: event.target.value,
              }))
            }
          />
          {resolveRefundMutation.isError ? (
            <Alert variant="danger">{errorMessage(resolveRefundMutation.error)}</Alert>
          ) : null}
          <Button type="submit" loading={resolveRefundMutation.isPending}>
            ثبت نتیجه قطعی
          </Button>
        </form>
      </Dialog>

      <Dialog
        open={resolveCaseOpen}
        onOpenChange={setResolveCaseOpen}
        title="بستن پرونده تطبیق"
        description="Resolution باید شامل منبع بررسی، نتیجه و اقدام انجام‌شده باشد."
      >
        <form onSubmit={submitCaseResolution} className="grid gap-4">
          <label className="grid gap-2 text-sm font-bold">
            تصمیم
            <select
              value={caseResolution.status}
              onChange={(event) =>
                setCaseResolution((current) => ({
                  ...current,
                  status: event.target.value as "resolved" | "dismissed",
                }))
              }
              className={fieldClass}
            >
              <option value="resolved">حل‌شده</option>
              <option value="dismissed">مختومه بدون اقدام</option>
            </select>
          </label>
          <TextareaField
            label="شرح نتیجه"
            required
            minLength={5}
            maxLength={5_000}
            value={caseResolution.resolution}
            onChange={(event) =>
              setCaseResolution((current) => ({
                ...current,
                resolution: event.target.value,
              }))
            }
          />
          {resolveCaseMutation.isError ? (
            <Alert variant="danger">{errorMessage(resolveCaseMutation.error)}</Alert>
          ) : null}
          <Button type="submit" loading={resolveCaseMutation.isPending}>
            ثبت و بستن پرونده
          </Button>
        </form>
      </Dialog>
    </section>
  );
}

function RefundDetail({
  refund,
  currentUserId,
  onApprove,
  onDispatch,
  onResolve,
  approving,
  dispatching,
  error,
}: {
  refund: AdminRefund;
  currentUserId: string;
  onApprove: () => void;
  onDispatch: () => void;
  onResolve: () => void;
  approving: boolean;
  dispatching: boolean;
  error?: unknown;
}) {
  const sameRequester = refund.requested_by === currentUserId;
  const canResolve = ["processing", "requires_review"].includes(refund.status);

  return (
    <article className="rounded-2xl border border-[color:var(--roast)]/40 bg-[color:var(--dark)] p-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-bold text-[color:var(--roast)]">
            REFUND DETAIL
          </p>
          <h2 className="mt-2 text-xl font-bold">
            سفارش {refund.order_number || shortId(refund.order_id)}
          </h2>
          <p className="mt-2 text-sm text-[color:var(--light)]">
            {refund.reason}
          </p>
        </div>
        <StatusBadge status={refund.status} />
      </div>

      <dl className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Metric label="مبلغ" value={formatIrr(refund.amount)} />
        <Metric label="Provider" value={refund.provider} dir="ltr" />
        <Metric
          label="مرجع پرداخت"
          value={refund.payment_reference_id || "ثبت نشده"}
          dir="ltr"
        />
        <Metric
          label="مرجع بازپرداخت"
          value={refund.provider_reference || "ثبت نشده"}
          dir="ltr"
        />
      </dl>

      {refund.failure_message ? (
        <div className="mt-4">
          <Alert variant="danger" title={refund.failure_code || "خطای مالی"}>
            {refund.failure_message}
          </Alert>
        </div>
      ) : null}

      {error ? (
        <p role="alert" className="mt-4 text-sm text-red-300">
          {errorMessage(error)}
        </p>
      ) : null}

      <div className="mt-5 flex flex-wrap gap-3">
        {refund.status === "requested" ? (
          <Button
            onClick={onApprove}
            loading={approving}
            disabled={sameRequester}
            title={
              sameRequester
                ? "ثبت‌کننده درخواست نمی‌تواند همان بازپرداخت را تأیید کند."
                : undefined
            }
          >
            <ShieldCheck size={16} />
            تأیید ادمین دوم
          </Button>
        ) : null}
        {refund.status === "approved" ? (
          <Button onClick={onDispatch} loading={dispatching}>
            <WalletCards size={16} />
            ارسال به Provider
          </Button>
        ) : null}
        {canResolve ? (
          <Button variant="outline" onClick={onResolve}>
            ثبت نتیجه پنل رسمی
          </Button>
        ) : null}
      </div>
      {sameRequester && refund.status === "requested" ? (
        <p className="mt-3 text-xs text-amber-200">
          برای حفظ Dual Control، ادمین دیگری باید این درخواست را تأیید کند.
        </p>
      ) : null}
    </article>
  );
}

function CaseDetail({
  financeCase,
  onResolve,
}: {
  financeCase: AdminReconciliationCase;
  onResolve: () => void;
}) {
  const terminal = ["resolved", "dismissed"].includes(financeCase.status);
  return (
    <article className="rounded-2xl border border-orange-400/40 bg-orange-950/10 p-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-bold text-orange-200">
            {severityLabel(financeCase.severity)} · {financeCase.kind}
          </p>
          <h2 className="mt-2 text-xl font-bold">{financeCase.summary}</h2>
          <p className="mt-2 font-mono-num text-xs text-[color:var(--roast)]">
            سفارش {financeCase.order_number || shortId(financeCase.order_id)}
          </p>
        </div>
        <span className="rounded-full border border-orange-400/40 px-3 py-1 text-xs font-bold text-orange-200">
          {caseLabels[financeCase.status]}
        </span>
      </div>
      {financeCase.details ? (
        <dl className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {Object.entries(financeCase.details).map(([key, value]) => (
            <Metric key={key} label={key} value={displayDetail(value)} dir="ltr" />
          ))}
        </dl>
      ) : null}
      {financeCase.resolution ? (
        <div className="mt-5">
          <Alert variant="success" title="نتیجه ثبت‌شده">
            {financeCase.resolution}
          </Alert>
        </div>
      ) : null}
      {!terminal ? (
        <Button className="mt-5" variant="outline" onClick={onResolve}>
          <CheckCircle2 size={16} />
          ثبت نتیجه تطبیق
        </Button>
      ) : null}
    </article>
  );
}

function StatusBadge({ status }: { status: AdminRefundStatus }) {
  return (
    <span
      className={`rounded-full border px-3 py-1 text-xs font-bold ${statusClasses[status]}`}
    >
      {refundLabels[status]}
    </span>
  );
}

function Metric({
  label,
  value,
  dir,
}: {
  label: string;
  value: string;
  dir?: "ltr" | "rtl";
}) {
  return (
    <div className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3">
      <dt className="text-[10px] text-[color:var(--light)]">{label}</dt>
      <dd dir={dir} className="mt-2 break-words text-sm font-bold">
        {value}
      </dd>
    </div>
  );
}

function errorMessage(error: unknown): string {
  return isApiError(error)
    ? error.message
    : error instanceof Error
      ? error.message
      : "عملیات مالی انجام نشد. وضعیت سرویس را بررسی کنید.";
}

function formatIrr(amount: number): string {
  return `${amount.toLocaleString("fa-IR")} ریال`;
}

function formatDate(value?: string | null): string {
  if (!value) return "زمان ثبت نشده";
  const timestamp = Date.parse(value);
  if (!Number.isFinite(timestamp)) return "زمان نامعتبر";
  return new Intl.DateTimeFormat("fa-IR", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: "Asia/Tehran",
  }).format(new Date(timestamp));
}

function shortId(value: string): string {
  return value.length > 12 ? `${value.slice(0, 6)}…${value.slice(-4)}` : value;
}

function severityLabel(value: AdminReconciliationCase["severity"]): string {
  return {
    low: "کم",
    medium: "متوسط",
    high: "بالا",
    critical: "بحرانی",
  }[value];
}

function displayDetail(value: unknown): string {
  if (value === null || value === undefined) return "—";
  if (typeof value === "string" || typeof value === "number") {
    return String(value);
  }
  if (typeof value === "boolean") return value ? "true" : "false";
  try {
    return JSON.stringify(value).slice(0, 300);
  } catch {
    return "[unavailable]";
  }
}

function newIdempotencyKey(): string {
  const random = globalThis.crypto?.randomUUID?.() ??
    `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  return `refund:admin:${random}`;
}
