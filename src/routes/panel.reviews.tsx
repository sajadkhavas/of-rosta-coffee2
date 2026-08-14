import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import {
  listSellerReviews,
  saveSellerReply,
  type SellerReviewSafety,
} from "@/lib/api/review-safety";
import { listSellerRoasteries } from "@/lib/api/seller-operations";

export const Route = createFileRoute("/panel/reviews")({
  head: () => ({
    meta: [
      { title: "پاسخ به نظرها | پنل فروشنده رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: Page,
});
const sellerRoles = new Set([
  "roastery_owner",
  "roastery_manager",
  "roastery_staff",
  "administrator",
]);
function Page() {
  return (
    <>
      <Navbar />
      <main dir="rtl" className="mx-auto max-w-5xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "پنل فروشنده", to: "/panel" },
            { label: "نظرها" },
          ]}
        />
        <AccountGuard>
          {(user) =>
            user.roles.some((role) => sellerRoles.has(role)) ? (
              <SellerReviews />
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
function SellerReviews() {
  const roasteries = useQuery({
    queryKey: ["seller", "roasteries", "review-safety"],
    queryFn: listSellerRoasteries,
    staleTime: 30_000,
  });
  const [roasteryId, setRoasteryId] = useState("");
  useEffect(() => {
    if (!roasteryId && roasteries.data?.[0]) setRoasteryId(roasteries.data[0].id);
  }, [roasteryId, roasteries.data]);
  const reviews = useQuery({
    queryKey: ["seller", "reviews", roasteryId],
    queryFn: () => listSellerReviews(roasteryId),
    enabled: Boolean(roasteryId),
    staleTime: 15_000,
  });
  return (
    <section className="mt-8">
      <header>
        <p className="eyebrow">REVIEW SAFETY</p>
        <h1 className="mt-2 text-3xl font-bold">نظرها و پاسخ فروشنده</h1>
        <p className="mt-2 text-sm leading-7 text-[color:var(--light)]">
          هر Review فقط یک پاسخ canonical دارد؛ ویرایش‌ها در Backend تاریخچه و Audit دارند.
        </p>
      </header>
      {roasteries.isLoading ? (
        <Skeleton className="mt-6 h-20" />
      ) : roasteries.isError ? (
        <div className="mt-6">
          <Alert variant="danger">{errorText(roasteries.error)}</Alert>
        </div>
      ) : !roasteries.data?.length ? (
        <div className="mt-6">
          <EmptyState title="روستری قابل مدیریت پیدا نشد" />
        </div>
      ) : (
        <div className="mt-6">
          <label htmlFor="review-roastery" className="text-sm font-bold">
            روستری
          </label>
          <select
            id="review-roastery"
            value={roasteryId}
            onChange={(event) => setRoasteryId(event.target.value)}
            className="mt-2 min-h-11 w-full max-w-md rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] px-3"
          >
            {roasteries.data.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </div>
      )}
      {roasteryId ? (
        <div className="mt-6">
          {reviews.isLoading ? (
            <div className="grid gap-3">
              <Skeleton className="h-40" />
              <Skeleton className="h-40" />
            </div>
          ) : reviews.isError ? (
            <>
              <Alert variant="danger">{errorText(reviews.error)}</Alert>
              <Button variant="outline" className="mt-3" onClick={() => reviews.refetch()}>
                تلاش مجدد
              </Button>
            </>
          ) : !reviews.data?.length ? (
            <EmptyState title="نظری برای این روستری نیست" />
          ) : (
            <div className="grid gap-4">
              {reviews.data.map((review) => (
                <ReviewCard key={review.id} roasteryId={roasteryId} review={review} />
              ))}
            </div>
          )}
        </div>
      ) : null}
    </section>
  );
}
function ReviewCard({ roasteryId, review }: { roasteryId: string; review: SellerReviewSafety }) {
  const client = useQueryClient();
  const [body, setBody] = useState(review.reply?.body ?? "");
  useEffect(() => setBody(review.reply?.body ?? ""), [review.reply?.body]);
  const mutation = useMutation({
    mutationFn: () => saveSellerReply(roasteryId, review.id, body),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["seller", "reviews", roasteryId] }),
  });
  return (
    <article className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <strong>
          {"★".repeat(review.rating)} ·{" "}
          {review.is_verified_purchase ? "خرید تأییدشده" : "تأییدنشده"}
        </strong>
        <span className="text-xs text-[color:var(--muted-gold)]">{review.status}</span>
      </div>
      {review.title ? <h2 className="mt-3 font-bold">{review.title}</h2> : null}
      <p className="mt-2 whitespace-pre-wrap text-sm leading-7 text-[color:var(--light)]">
        {review.body}
      </p>
      <label htmlFor={`reply-${review.id}`} className="mt-5 block text-sm font-bold">
        {review.reply ? "ویرایش پاسخ" : "پاسخ فروشنده"}
      </label>
      <textarea
        id={`reply-${review.id}`}
        value={body}
        maxLength={5000}
        onChange={(event) => setBody(event.target.value)}
        className="mt-2 min-h-28 w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"
      />
      {review.reply && review.reply.status !== "visible" ? (
        <p className="mt-2 text-xs text-amber-200">
          این پاسخ توسط Moderation عمومی نیست؛ ویرایش فروشنده آن را خودکار public نمی‌کند.
        </p>
      ) : null}
      {mutation.isError ? (
        <div className="mt-3">
          <Alert variant="danger">{errorText(mutation.error)}</Alert>
        </div>
      ) : null}
      <Button
        className="mt-3"
        loading={mutation.isPending}
        disabled={!body.trim()}
        onClick={() => mutation.mutate()}
      >
        ذخیره پاسخ
      </Button>
    </article>
  );
}
function errorText(error: unknown) {
  return isApiError(error) ? error.message : "اطلاعات دریافت یا ذخیره نشد.";
}
