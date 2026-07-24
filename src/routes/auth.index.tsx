import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useMutation } from "@tanstack/react-query";
import { fallback, zodValidator } from "@tanstack/zod-adapter";
import { useState } from "react";
import { z } from "zod";
import { Alert, Button, FormSummary, TextField } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import {
  isValidIranMobile,
  modeToPurpose,
  normalizeIranMobile,
  requestOtp,
  type AuthMode,
} from "@/lib/api/identity";
import { safeRedirect, savePendingOtp } from "@/lib/auth/flow";

const searchSchema = z.object({
  mode: fallback(z.enum(["login", "register", "recover"]), "login").default("login"),
  redirect: fallback(z.string(), "/profile").default("/profile"),
});

type AuthSearch = z.infer<typeof searchSchema>;

export const Route = createFileRoute("/auth/")({
  validateSearch: zodValidator(searchSchema),
  component: AuthStartPage,
});

const modeContent: Record<AuthMode, { title: string; description: string; action: string }> = {
  login: {
    title: "ورود به رستا",
    description: "شماره موبایل خود را وارد کنید تا کد یک‌بارمصرف برایتان ارسال شود.",
    action: "دریافت کد ورود",
  },
  register: {
    title: "ساخت حساب رستا",
    description: "ثبت‌نام با شماره موبایل انجام می‌شود و نیازی به انتخاب رمز عبور نیست.",
    action: "دریافت کد ثبت‌نام",
  },
  recover: {
    title: "بازیابی دسترسی",
    description: "برای ورود دوباره، یک کد یک‌بارمصرف جدید دریافت کنید.",
    action: "ارسال کد جدید",
  },
};

function AuthStartPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/auth/" });
  const [mobile, setMobile] = useState("");
  const [mobileError, setMobileError] = useState<string>();
  const mode: AuthMode = search.mode;
  const content = modeContent[mode];

  const mutation = useMutation({
    mutationFn: requestOtp,
    onSuccess: (result) => {
      const normalizedMobile = normalizeIranMobile(mobile);
      const redirect = safeRedirect(search.redirect);
      savePendingOtp({
        requestId: result.requestId,
        mobile: normalizedMobile,
        purpose: modeToPurpose(mode),
        mode,
        expiresAt: Date.now() + result.expiresIn * 1000,
        retryAt: Date.now() + result.retryAfter * 1000,
        redirect,
      });
      navigate({ to: "/auth/verify", search: { redirect }, replace: true });
    },
  });

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setMobileError(undefined);
    if (!isValidIranMobile(mobile)) {
      setMobileError("شماره موبایل ایران را به‌صورت ۰۹xxxxxxxxx وارد کنید.");
      return;
    }
    mutation.mutate({ mobile, purpose: modeToPurpose(mode) });
  };

  const switchMode = (mode: AuthMode) =>
    navigate({
      search: (previous: AuthSearch) => ({ ...previous, mode }),
      replace: true,
    });

  return (
    <section className="rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6 shadow-2xl sm:p-8">
      <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">PASSWORDLESS</p>
      <h1 className="mt-2 text-2xl font-bold text-[color:var(--steam)]">{content.title}</h1>
      <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">{content.description}</p>

      <div className="mt-6 grid grid-cols-3 gap-2" role="group" aria-label="نوع احراز هویت">
        {(
          [
            ["login", "ورود"],
            ["register", "ثبت‌نام"],
            ["recover", "بازیابی"],
          ] as const
        ).map(([mode, label]) => (
          <button
            key={mode}
            type="button"
            onClick={() => switchMode(mode)}
            className={`min-h-10 rounded-xl border px-2 text-xs font-bold transition ${
              search.mode === mode
                ? "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]"
                : "border-[color:var(--mid)] text-[color:var(--light)] hover:border-[color:var(--roast)]"
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      <form onSubmit={submit} className="mt-6 grid gap-5" noValidate>
        <FormSummary
          errors={mobileError ? [{ fieldId: "auth-mobile", message: mobileError }] : []}
        />
        {mutation.isError ? (
          <Alert variant="danger" title="ارسال کد انجام نشد">
            {isApiError(mutation.error)
              ? mutation.error.message
              : "ارتباط با سرویس احراز هویت برقرار نشد."}
          </Alert>
        ) : null}
        <TextField
          id="auth-mobile"
          label="شماره موبایل"
          inputMode="tel"
          autoComplete="tel"
          dir="ltr"
          placeholder="09123456789"
          value={mobile}
          error={mobileError}
          onChange={(event) => {
            setMobile(event.target.value);
            if (mobileError) setMobileError(undefined);
          }}
          required
        />
        <Button type="submit" loading={mutation.isPending} loadingLabel="در حال ارسال کد">
          {content.action}
        </Button>
      </form>

      <p className="mt-5 text-center text-xs leading-6 text-[color:var(--light)]">
        با ادامه،{" "}
        <Link to="/terms" className="text-[color:var(--roast)] underline">
          قوانین رستا
        </Link>{" "}
        و{" "}
        <Link to="/privacy" className="text-[color:var(--roast)] underline">
          حریم خصوصی
        </Link>{" "}
        را می‌پذیرید.
      </p>
    </section>
  );
}
