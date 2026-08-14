import { useMutation, useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Alert, EmptyState, Skeleton } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import {
  productReviewsQueryOptions,
  reportReview,
  type ReviewReportReason,
} from "@/lib/api/reviews";
import { toFa } from "@/lib/persian";

const reasons: Array<{ value: ReviewReportReason; label: string }> = [
  { value: "spam", label: "هرزنامه" },
  { value: "harassment", label: "آزار یا توهین" },
  { value: "hate", label: "محتوای نفرت‌پراکن" },
  { value: "personal_data", label: "اطلاعات شخصی" },
  { value: "fraud", label: "فریب یا ادعای مشکوک" },
  { value: "off_topic", label: "نامرتبط" },
  { value: "other", label: "سایر" },
];

export function ProductReviews({ productSlug }: { productSlug: string }) {
  const query = useQuery(productReviewsQueryOptions(productSlug));
  const [reportingId, setReportingId] = useState<string | null>(null);
  const [reason, setReason] = useState<ReviewReportReason>("spam");
  const [evidence, setEvidence] = useState("");
  const report = useMutation({
    mutationFn: ({ reviewId }: { reviewId: string }) =>
      reportReview(reviewId, { reason, evidence }),
    onSuccess: () => {
      setEvidence("");
      setReportingId(null);
    },
  });
  if (query.isLoading)
    return (
      <section className="mt-14">
        <Skeleton className="h-48" />
      </section>
    );
  if (query.isError)
    return (
      <section className="mt-14">
        <Alert variant="warning" title="نظرها دریافت نشد">
          {isApiError(query.error) ? query.error.message : "ارتباط با سرویس نظرها برقرار نشد."}
        </Alert>
      </section>
    );
  const data = query.data;
  return (
    <section
      className="mt-14 border-t border-[color:var(--mid)] pt-10"
      aria-labelledby="product-reviews-title"
    >
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="eyebrow">خرید تأییدشده</p>
          <h2 id="product-reviews-title" className="mt-2 text-2xl font-bold">
            نظر خریداران
          </h2>
        </div>
        <div className="text-sm text-[color:var(--light)]">
          {data?.summary.average ? (
            <>
              <strong className="text-[color:var(--roast)]">
                ★ {toFa(data.summary.average.toFixed(1))}
              </strong>{" "}
              از {toFa(data.summary.count)} نظر
            </>
          ) : (
            "هنوز امتیازی ثبت نشده"
          )}
        </div>
      </div>
      {!data?.items.length ? (
        <div className="mt-6">
          <EmptyState
            title="نظر تأییدشده‌ای وجود ندارد"
            description="نظر فقط پس از تحویل سفارش ثبت و بعد از Moderation منتشر می‌شود."
          />
        </div>
      ) : (
        <div className="mt-6 grid gap-4 md:grid-cols-2">
          {data.items.map((review) => (
            <article
              key={review.id}
              className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
            >
              <div className="flex items-center justify-between gap-3">
                <p className="font-bold">{review.author}</p>
                <span className="text-[color:var(--roast)]">
                  {"★".repeat(review.rating)}
                  <span className="text-[color:var(--mid)]">{"★".repeat(5 - review.rating)}</span>
                </span>
              </div>
              {review.title ? <h3 className="mt-3 font-bold">{review.title}</h3> : null}
              <p className="mt-3 whitespace-pre-wrap text-sm leading-7 text-[color:var(--light)]">
                {review.body}
              </p>
              {review.seller_reply ? (
                <div className="mt-4 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3 text-sm leading-7">
                  <strong className="text-[color:var(--roast)]">پاسخ فروشنده</strong>
                  <p className="mt-1 whitespace-pre-wrap text-[color:var(--light)]">
                    {review.seller_reply.body}
                  </p>
                </div>
              ) : null}
              <div className="mt-4 flex items-center justify-between gap-3 text-[10px] text-[color:var(--muted-gold)]">
                <span>✓ خرید تأییدشده</span>
                <button
                  type="button"
                  onClick={() => {
                    setReportingId(reportingId === review.id ? null : review.id);
                    report.reset();
                  }}
                  className="rounded px-2 py-1 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--roast)]"
                >
                  گزارش
                </button>
                {review.created_at ? (
                  <time dateTime={review.created_at}>
                    {new Date(review.created_at).toLocaleDateString("fa-IR")}
                  </time>
                ) : null}
              </div>
              {reportingId === review.id ? (
                <div className="mt-3 rounded-xl border border-[color:var(--mid)] p-3">
                  <label className="text-xs font-bold" htmlFor={`report-reason-${review.id}`}>
                    دلیل گزارش
                  </label>
                  <select
                    id={`report-reason-${review.id}`}
                    value={reason}
                    onChange={(event) => setReason(event.target.value as ReviewReportReason)}
                    className="mt-2 min-h-11 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3"
                  >
                    {reasons.map((item) => (
                      <option key={item.value} value={item.value}>
                        {item.label}
                      </option>
                    ))}
                  </select>
                  <label className="mt-3 block text-xs" htmlFor={`report-evidence-${review.id}`}>
                    توضیح اختیاری، حداکثر ۵۰۰ نویسه
                  </label>
                  <textarea
                    id={`report-evidence-${review.id}`}
                    value={evidence}
                    maxLength={500}
                    onChange={(event) => setEvidence(event.target.value)}
                    className="mt-2 min-h-20 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] p-3"
                  />
                  {report.isError ? (
                    <div className="mt-2">
                      <Alert variant="warning">
                        {isApiError(report.error) && report.error.status === 401
                          ? "برای ثبت گزارش وارد حساب شو."
                          : isApiError(report.error)
                            ? report.error.message
                            : "گزارش ثبت نشد."}
                      </Alert>
                    </div>
                  ) : null}
                  <button
                    type="button"
                    disabled={report.isPending}
                    onClick={() => report.mutate({ reviewId: review.id })}
                    className="mt-3 min-h-11 rounded-lg bg-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--night)] disabled:opacity-50"
                  >
                    {report.isPending ? "در حال ثبت…" : "ثبت گزارش"}
                  </button>
                </div>
              ) : null}
            </article>
          ))}
        </div>
      )}
      {report.isSuccess ? (
        <div className="mt-4">
          <Alert variant="success">
            گزارش ثبت شد و گزارش تکراری برای همین حساب دوباره ساخته نمی‌شود.
          </Alert>
        </div>
      ) : null}
    </section>
  );
}
