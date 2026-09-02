import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, Skeleton, TextareaField } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import type { AuthUser } from "@/lib/api/contracts";
import { createInquiry } from "@/lib/api/inquiries";
import { cancelOrder, orderQueryOptions } from "@/lib/api/orders";
import { queryKeys } from "@/lib/api/query-keys";

export const Route = createFileRoute("/order-help/$id")({
  head: () => ({
    meta: [
      { title: "لغو یا پیگیری سفارش | رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: OrderHelpPage,
});

function OrderHelpPage() {
  return (
    <>
      <Navbar />
      <main dir="rtl" className="mx-auto max-w-3xl px-4 py-8">
        <AccountGuard>{(user) => <ResolutionContent user={user} />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function ResolutionContent({ user }: { user: AuthUser }) {
  const { id } = Route.useParams();
  const client = useQueryClient();
  const orderQuery = useQuery(orderQueryOptions(id));
  const [cancelReason, setCancelReason] = useState("");
  const [issueMessage, setIssueMessage] = useState("");
  const [referenceId, setReferenceId] = useState<string>();

  const cancellation = useMutation({
    mutationFn: () => cancelOrder(id, cancelReason),
    onSuccess: async (order) => {
      client.setQueryData(queryKeys.orders.detail(id), order);
      await client.invalidateQueries({ queryKey: ["orders"] });
    },
  });

  const issue = useMutation({
    mutationFn: async () => {
      if (!orderQuery.data) throw new Error("سفارش هنوز بارگذاری نشده است.");
      return createInquiry({
        type: "order_issue",
        name: user.name?.trim() || "مشتری رستا",
        mobile: user.mobile,
        email: user.email || undefined,
        orderNumber: orderQuery.data.orderNumber,
        message: issueMessage,
      });
    },
    onSuccess: (receipt) => {
      setReferenceId(receipt.referenceId);
      setIssueMessage("");
    },
  });

  if (orderQuery.isPending) {
    return <Skeleton className="h-[32rem]" />;
  }

  if (orderQuery.isError || !orderQuery.data) {
    return (
      <Alert variant="danger" title="سفارش دریافت نشد">
        {isApiError(orderQuery.error)
          ? orderQuery.error.message
          : "ارتباط با سرویس سفارش برقرار نشد."}
      </Alert>
    );
  }

  const order = orderQuery.data;
  const canCancel = order.status === "awaiting_payment";

  const submitCancel = (event: FormEvent) => {
    event.preventDefault();
    if (!canCancel || cancellation.isPending) return;
    cancellation.mutate();
  };

  const submitIssue = (event: FormEvent) => {
    event.preventDefault();
    if (issueMessage.trim().length < 10 || issue.isPending) return;
    issue.mutate();
  };

  return (
    <section className="space-y-6">
      <Breadcrumb
        items={[
          { label: "خانه", to: "/" },
          { label: "سفارش‌های من", to: "/orders" },
          { label: `#${order.orderNumber}` },
          { label: "لغو یا پیگیری" },
        ]}
      />

      <header className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
        <p className="text-xs font-bold tracking-[0.18em] text-[color:var(--roast)]">
          ORDER RESOLUTION
        </p>
        <h1 className="mt-2 text-3xl font-bold">لغو یا اعلام مشکل سفارش</h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          سفارش #{order.orderNumber}. لغو مستقیم فقط تا زمانی ممکن است که سفارش هنوز در انتظار
          پرداخت باشد. بعد از پرداخت، هر مشکل از مسیر پشتیبانی ثبت می‌شود تا وضعیت ارسال، بازپرداخت
          یا تسویه بدون دورزدن قرارداد مالی بررسی شود.
        </p>
      </header>

      <form
        onSubmit={submitCancel}
        className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
      >
        <h2 className="text-xl font-bold">لغو مستقیم</h2>
        {canCancel ? (
          <>
            <p className="mt-2 text-sm text-[color:var(--light)]">
              این سفارش هنوز پرداخت نشده است؛ لغو، رزرو موجودی و رزرو کوپن را در Backend آزاد
              می‌کند.
            </p>
            <div className="mt-4">
              <TextareaField
                id="cancel-reason"
                label="دلیل لغو (اختیاری)"
                value={cancelReason}
                maxLength={500}
                onChange={(event) => setCancelReason(event.target.value)}
              />
            </div>
            <Button
              className="mt-4"
              type="submit"
              variant="outline"
              loading={cancellation.isPending}
            >
              لغو سفارش
            </Button>
          </>
        ) : (
          <Alert variant="info" title="لغو مستقیم بسته است">
            سفارش وارد مرحله‌ای شده که لغو خودکار مجاز نیست. در صورت مشکل، درخواست پشتیبانی پایین را
            ثبت کنید تا تصمیم مالی/عملیاتی با شواهد همان سفارش انجام شود.
          </Alert>
        )}
        {cancellation.isSuccess ? (
          <div className="mt-4">
            <Alert variant="success">سفارش لغو شد و رزروهای مرتبط آزاد شدند.</Alert>
          </div>
        ) : null}
        {cancellation.isError ? (
          <div className="mt-4">
            <Alert variant="danger">
              {isApiError(cancellation.error) ? cancellation.error.message : "لغو سفارش انجام نشد."}
            </Alert>
          </div>
        ) : null}
      </form>

      <form
        onSubmit={submitIssue}
        className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
      >
        <h2 className="text-xl font-bold">اعلام مشکل سفارش</h2>
        <p className="mt-2 text-sm leading-7 text-[color:var(--light)]">
          شماره سفارش و حساب شما به درخواست متصل می‌شود. اطلاعات تماس و متن درخواست طبق قرارداد
          Inquiry امن Backend نگه‌داری می‌شوند.
        </p>
        <div className="mt-4">
          <TextareaField
            id="order-issue"
            label="شرح مشکل"
            value={issueMessage}
            minLength={10}
            maxLength={5000}
            required
            onChange={(event) => setIssueMessage(event.target.value)}
          />
        </div>
        <Button
          className="mt-4"
          type="submit"
          loading={issue.isPending}
          disabled={issueMessage.trim().length < 10}
        >
          ثبت درخواست پشتیبانی
        </Button>
        {referenceId ? (
          <div className="mt-4">
            <Alert variant="success" title="درخواست ثبت شد">
              کد پیگیری:{" "}
              <span dir="ltr" className="font-mono">
                {referenceId}
              </span>
            </Alert>
          </div>
        ) : null}
        {issue.isError ? (
          <div className="mt-4">
            <Alert variant="danger">
              {isApiError(issue.error) ? issue.error.message : "ثبت درخواست انجام نشد."}
            </Alert>
          </div>
        ) : null}
      </form>

      <Link
        to="/orders/$id"
        params={{ id: order.id }}
        className="inline-flex text-sm font-bold text-[color:var(--roast)] underline"
      >
        بازگشت به جزئیات سفارش
      </Link>
    </section>
  );
}
