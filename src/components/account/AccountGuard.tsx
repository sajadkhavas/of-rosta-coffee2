import { useQuery } from "@tanstack/react-query";
import { useLocation, useNavigate } from "@tanstack/react-router";
import { useEffect, type ReactNode } from "react";
import { Alert, Button } from "@/components/system";
import { currentUserQueryOptions } from "@/lib/api/identity";
import { isForbiddenError, isUnauthenticatedError, isApiError } from "@/lib/api/client";
import type { AuthUser } from "@/lib/api/contracts";

export function AccountGuard({ children }: { children: (user: AuthUser) => ReactNode }) {
  const location = useLocation();
  const navigate = useNavigate();
  const query = useQuery(currentUserQueryOptions());

  useEffect(() => {
    if (!query.isError) return;
    if (isUnauthenticatedError(query.error)) {
      navigate({
        to: "/auth",
        search: { mode: "login", redirect: location.pathname },
        replace: true,
      });
      return;
    }
    if (isForbiddenError(query.error)) {
      navigate({ to: "/forbidden", replace: true });
    }
  }, [location.pathname, navigate, query.error, query.isError]);

  if (
    query.isPending ||
    (query.isError && (isUnauthenticatedError(query.error) || isForbiddenError(query.error)))
  ) {
    return (
      <div className="grid min-h-[45vh] place-items-center" role="status">
        <div className="text-center">
          <div className="mx-auto size-9 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
          <p className="mt-4 text-sm text-[color:var(--light)]">در حال بررسی نشست…</p>
        </div>
      </div>
    );
  }

  if (query.isError || !query.data) {
    return (
      <div className="mx-auto max-w-xl py-10">
        <Alert variant="danger" title="حساب کاربری بارگذاری نشد">
          {isApiError(query.error)
            ? query.error.message
            : "ارتباط با سرویس حساب کاربری برقرار نشد."}
        </Alert>
        <Button className="mt-5" onClick={() => query.refetch()}>
          تلاش مجدد
        </Button>
      </div>
    );
  }

  return <>{children(query.data)}</>;
}
