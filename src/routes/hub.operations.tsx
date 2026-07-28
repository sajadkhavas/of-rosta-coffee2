import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useState } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton } from "@/components/system";
import {
  assignHubWorkItem,
  hubOperatorsQueryOptions,
  hubWorkItemsQueryOptions,
  transitionHubWorkItem,
  type HubAction,
  type HubWorkItem,
  type HubWorkStatus,
} from "@/lib/api/hub-operations";
import { isApiError } from "@/lib/api/client";

export const Route = createFileRoute("/hub/operations")({
  head: () => ({
    meta: [{ title: "عملیات هاب رستا" }, { name: "robots", content: "noindex,nofollow" }],
  }),
  component: HubOperationsPage,
});

const labels: Record<HubWorkStatus, string> = {
  awaiting_inbound: "در انتظار ورودی",
  received: "دریافت‌شده",
  assigned: "تخصیص‌یافته",
  grinding: "در حال آسیاب",
  quality_check: "کنترل کیفیت",
  rework_required: "نیازمند اصلاح",
  packaging: "بسته‌بندی",
  ready_for_outbound: "آماده خروج",
  handed_off: "تحویل به حمل",
  cancelled: "متوقف‌شده",
};
const nextActions: Partial<Record<HubWorkStatus, Array<{ action: HubAction; label: string }>>> = {
  awaiting_inbound: [{ action: "receive", label: "ثبت دریافت در هاب" }],
  assigned: [{ action: "start_grinding", label: "شروع آسیاب" }],
  grinding: [{ action: "submit_quality_check", label: "ارسال به کنترل کیفیت" }],
  quality_check: [
    { action: "quality_pass", label: "تأیید کیفیت" },
    { action: "quality_fail", label: "رد کیفیت و اصلاح" },
  ],
  rework_required: [{ action: "restart_grinding", label: "شروع اصلاح" }],
  packaging: [{ action: "mark_ready", label: "ثبت بسته‌بندی و آمادگی" }],
  ready_for_outbound: [{ action: "handoff", label: "تحویل به حمل" }],
};

function HubOperationsPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "عملیات هاب رستا" }]} />
        <AccountGuard>
          {(user) =>
            user.roles.includes("administrator") || user.roles.includes("hub_operator") ? (
              <Dashboard isAdmin={user.roles.includes("administrator")} />
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
function Dashboard({ isAdmin }: { isAdmin: boolean }) {
  const client = useQueryClient();
  const [status, setStatus] = useState<HubWorkStatus | "all">("all");
  const query = useQuery(hubWorkItemsQueryOptions(isAdmin, status));
  const operators = useQuery({ ...hubOperatorsQueryOptions(), enabled: isAdmin });
  const [operatorByItem, setOperatorByItem] = useState<Record<string, string>>({});
  const invalidate = () => client.invalidateQueries({ queryKey: ["hub-operations"] });
  const assignMutation = useMutation({ mutationFn: assignHubWorkItem, onSuccess: invalidate });
  const transitionMutation = useMutation({
    mutationFn: transitionHubWorkItem,
    onSuccess: invalidate,
  });
  const error = assignMutation.error ?? transitionMutation.error ?? query.error;
  return (
    <section className="mt-8 space-y-6">
      <header>
        <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">
          ROSTA HUB CHAIN OF CUSTODY
        </p>
        <h1 className="mt-2 text-3xl font-bold">صف عملیات زنده هاب رستا</h1>
        <p className="mt-3 max-w-3xl text-sm leading-8 text-[color:var(--light)]">
          دریافت ورودی، تخصیص اپراتور، آسیاب، کنترل کیفیت، بسته‌بندی رایگان و تحویل خروجی همگی
          ثبت‌شده و غیرقابل‌حذف هستند.
        </p>
      </header>
      {isAdmin ? (
        <select
          className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3"
          value={status}
          onChange={(e) => setStatus(e.target.value as HubWorkStatus | "all")}
        >
          <option value="all">همه وضعیت‌ها</option>
          {Object.entries(labels).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      ) : null}
      {error ? (
        <Alert variant="danger">
          {isApiError(error) ? error.message : "عملیات هاب انجام نشد."}
        </Alert>
      ) : null}
      {query.isLoading ? (
        <div className="grid gap-4">
          <Skeleton className="h-40" />
          <Skeleton className="h-40" />
        </div>
      ) : query.data?.length ? (
        <div className="grid gap-4">
          {query.data.map((item) => (
            <WorkItemCard
              key={item.id}
              item={item}
              isAdmin={isAdmin}
              operators={operators.data ?? []}
              selectedOperator={operatorByItem[item.id] ?? ""}
              onOperator={(value) =>
                setOperatorByItem((current) => ({ ...current, [item.id]: value }))
              }
              onAssign={() =>
                assignMutation.mutate({
                  workItemId: item.id,
                  operatorId: operatorByItem[item.id] ?? "",
                })
              }
              onAction={(action) =>
                transitionMutation.mutate({ workItemId: item.id, action, isAdmin })
              }
              pending={assignMutation.isPending || transitionMutation.isPending}
            />
          ))}
        </div>
      ) : (
        <EmptyState
          title="کار عملیاتی فعالی وجود ندارد"
          description="سرویس‌های آسیاب هاب پس از برنامه‌ریزی مسیر در این صف ظاهر می‌شوند."
        />
      )}
    </section>
  );
}
function WorkItemCard({
  item,
  isAdmin,
  operators,
  selectedOperator,
  onOperator,
  onAssign,
  onAction,
  pending,
}: {
  item: HubWorkItem;
  isAdmin: boolean;
  operators: Array<{ id: string; name?: string | null }>;
  selectedOperator: string;
  onOperator: (value: string) => void;
  onAssign: () => void;
  onAction: (action: HubAction) => void;
  pending: boolean;
}) {
  return (
    <article className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
      <div className="flex flex-wrap justify-between gap-3">
        <div>
          <p className="font-bold">سفارش {item.order_number ?? item.order_id}</p>
          <p className="mt-1 text-xs text-[color:var(--light)]">
            {item.hub.name} · {item.weight_grams.toLocaleString("fa-IR")} گرم ·{" "}
            {item.quantity.toLocaleString("fa-IR")} بسته
          </p>
        </div>
        <span className="rounded-full border border-[color:var(--roast)] px-3 py-1 text-xs text-[color:var(--roast)]">
          {labels[item.status]}
        </span>
      </div>
      <p className="mt-4 text-sm">{item.public_label}</p>
      <div className="mt-4 grid gap-2 text-xs text-[color:var(--light)] sm:grid-cols-2">
        <p>ورودی: {item.inbound_leg?.status ?? "—"}</p>
        <p>خروجی: {item.outbound_leg?.status ?? "—"}</p>
        <p>اپراتور: {item.assigned_operator?.name ?? "تخصیص نیافته"}</p>
        <p>نسخه عملیات: {item.revision.toLocaleString("fa-IR")}</p>
      </div>
      {isAdmin && ["received", "assigned", "rework_required"].includes(item.status) ? (
        <div className="mt-4 flex flex-wrap gap-2">
          <select
            className="min-h-10 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-xs"
            value={selectedOperator}
            onChange={(e) => onOperator(e.target.value)}
          >
            <option value="">انتخاب اپراتور</option>
            {operators.map((op) => (
              <option key={op.id} value={op.id}>
                {op.name ?? op.id}
              </option>
            ))}
          </select>
          <Button type="button" disabled={!selectedOperator} loading={pending} onClick={onAssign}>
            تخصیص اپراتور
          </Button>
        </div>
      ) : null}
      <div className="mt-4 flex flex-wrap gap-2">
        {(nextActions[item.status] ?? []).map((next) => (
          <Button
            key={next.action}
            type="button"
            variant={next.action === "quality_fail" ? "danger" : "secondary"}
            loading={pending}
            onClick={() => onAction(next.action)}
          >
            {next.label}
          </Button>
        ))}
        {isAdmin && !["handed_off", "cancelled"].includes(item.status) ? (
          <Button
            type="button"
            variant="danger"
            loading={pending}
            onClick={() => onAction("cancel")}
          >
            توقف مدیریتی
          </Button>
        ) : null}
      </div>
    </article>
  );
}
