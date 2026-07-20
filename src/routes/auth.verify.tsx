import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { fallback, zodValidator } from "@tanstack/zod-adapter";
import { useEffect, useState, type FormEvent } from "react";
import { z } from "zod";
import { Alert, Button, FormSummary, TextField } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import { queryKeys } from "@/lib/api/query-keys";
import { requestOtp, toEnglishDigits, verifyOtp } from "@/lib/api/identity";
import {
  clearPendingOtp,
  loadPendingOtp,
  safeRedirect,
  savePendingOtp,
  type PendingOtpFlow,
} from "@/lib/auth/flow";

const searchSchema = z.object({
  redirect: fallback(z.string(), "/profile").default("/profile"),
});

export const Route = createFileRoute("/auth/verify")({
  validateSearch: zodValidator(searchSchema),
  component: VerifyOtpPage,
});

function secondsUntil(timestamp: number, now: number) {
  return Math.max(0, Math.ceil((timestamp - now) / 1000));
}

function VerifyOtpPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/auth/verify" });
  const queryClient = useQueryClient();
  const [flow, setFlow] = useState<PendingOtpFlow | null>(null);
  const [hydrated, setHydrated] = useState(false);
  const [code, setCode] = useState("");
  const [codeError, setCodeError] = useState<string>();
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    const stored = loadPendingOtp();
    setFlow(stored);
    setHydrated(true);
  }, []);

  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(timer);
  }, []);

  const verifyMutation = useMutation({
    mutationFn: verifyOtp,
    onSuccess: async () => {
      clearPendingOtp();
      await queryClient.invalidateQueries({ queryKey: queryKeys.auth.all });
      const redirect = safeRedirect(flow?.redirect || search.redirect);
      navigate({ to: redirect, replace: true });
    },
  });

  const resendMutation = useMutation({
    mutationFn: requestOtp,
    onSuccess: (result) => {
      if (!flow) return;
      const nextFlow: PendingOtpFlow = {
        ...flow,
        requestId: result.requestId,
        expiresAt: Date.now() + result.expiresIn * 1000,
        retryAt: Date.now() + result.retryAfter * 1000,
      };
      setFlow(nextFlow);
      savePendingOtp(nextFlow);
      setCode("");
      setCodeError(undefined);
    },
  });

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!flow) return;
    const normalizedCode = toEnglishDigits(code).replace(/\D/g, "");
    if (!/^\d{6}$/.test(normalizedCode)) {
      setCodeError("کد شش‌رقمی پیامک‌شده را وارد کنید.");
      return;
    }
    if (flow.expiresAt <= Date.now()) {
      setCodeError("اعتبار این کد تمام شده است. کد جدید دریافت کنید.");
      return;
    }
    setCodeError(undefined);
    verifyMutation.mutate({ requestId: flow.requestId, code: normalizedCode });
  };

  if (!hydrated) {
    return (
      <div className="rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-8 text-center" role="status">
        <div className="mx-auto size-8 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
        <p className="mt-4 text-sm text-[color:var(--light)]">در حال آماده‌سازی تأیید…</p>
      </div>
    );
  }

  if (!flow) {
    return (
      <section className="rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-8 text-center">
        <h1 className="text-2xl font-bold">درخواست کد پیدا نشد</h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          برای امنیت بیشتر، اطلاعات کد فقط در همین نشست مرورگر نگه‌داری می‌شود.
        </p>
        <Link
          to="/auth"
          search={{ mode: "login", redirect: safeRedirect(search.redirect) }}
          className="mt-6 inline-flex min-h-11 items-center rounded-xl bg-[color:var(--roast)] px-5 text-sm font-bold text-[color:var(--night)]"
        >
          دریافت کد جدید
        </Link>
      </section>
    );
  }

  const expiresIn = secondsUntil(flow.expiresAt, now);
  const resendIn = secondsUntil(flow.retryAt, now);

  return (
    <section className="rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6 shadow-2xl sm:p-8">
      <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">OTP VERIFY</p>
      <h1 className="mt-2 text-2xl font-bold">تأیید شماره موبایل</h1>
      <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
        کد ارسال‌شده به <span dir="ltr" className="font-mono text-[color:var(--steam)]">{flow.mobile}</span> را وارد کنید.
      </p>

      <form onSubmit={submit} className="mt-6 grid gap-5" noValidate>
        <FormSummary
          errors={codeError ? [{ fieldId: "otp-code", message: codeError }] : []}
        />
        {verifyMutation.isError ? (
          <Alert variant="danger" title="تأیید کد انجام نشد">
            {isApiError(verifyMutation.error)
              ? verifyMutation.error.message
              : "ارتباط با سرویس احراز هویت برقرار نشد."}
          </Alert>
        ) : null}
        {resendMutation.isError ? (
          <Alert variant="warning" title="ارسال مجدد انجام نشد">
            {isApiError(resendMutation.error)
              ? resendMutation.error.message
              : "لطفاً کمی بعد دوباره تلاش کنید."}
          </Alert>
        ) : null}
        <TextField
          id="otp-code"
          label="کد شش‌رقمی"
          inputMode="numeric"
          autoComplete="one-time-code"
          dir="ltr"
          maxLength={6}
          value={code}
          error={codeError}
          onChange={(event) => {
            setCode(toEnglishDigits(event.target.value).replace(/\D/g, "").slice(0, 6));
            if (codeError) setCodeError(undefined);
          }}
          required
        />
        <div className="flex items-center justify-between gap-4 text-xs text-[color:var(--light)]">
          <span>
            {expiresIn > 0
              ? `اعتبار کد: ${expiresIn.toLocaleString("fa-IR")} ثانیه`
              : "اعتبار کد پایان یافته است"}
          </span>
          <button
            type="button"
            disabled={resendIn > 0 || resendMutation.isPending}
            onClick={() =>
              resendMutation.mutate({ mobile: flow.mobile, purpose: flow.purpose })
            }
            className="font-bold text-[color:var(--roast)] disabled:cursor-not-allowed disabled:opacity-50"
          >
            {resendMutation.isPending
              ? "در حال ارسال…"
              : resendIn > 0
                ? `ارسال مجدد تا ${resendIn.toLocaleString("fa-IR")}`
                : "ارسال مجدد کد"}
          </button>
        </div>
        <Button type="submit" loading={verifyMutation.isPending} loadingLabel="در حال تأیید">
          تأیید و ادامه
        </Button>
      </form>

      <div className="mt-5 text-center">
        <Link
          to="/auth"
          search={{ mode: flow.mode, redirect: safeRedirect(flow.redirect) }}
          className="text-xs text-[color:var(--roast)] underline underline-offset-4"
        >
          تغییر شماره موبایل
        </Link>
      </div>
    </section>
  );
}
