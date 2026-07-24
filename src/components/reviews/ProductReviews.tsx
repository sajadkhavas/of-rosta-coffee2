import { useQuery } from "@tanstack/react-query";
import { Alert, EmptyState, Skeleton } from "@/components/system";
import { productReviewsQueryOptions } from "@/lib/api/reviews";
import { isApiError } from "@/lib/api/client";
import { toFa } from "@/lib/persian";

export function ProductReviews({ productSlug }: { productSlug: string }) {
  const query = useQuery(productReviewsQueryOptions(productSlug));
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
              <div className="mt-4 flex items-center justify-between text-[10px] text-[color:var(--muted-gold)]">
                <span>✓ خرید تأییدشده</span>
                {review.created_at ? (
                  <time dateTime={review.created_at}>
                    {new Date(review.created_at).toLocaleDateString("fa-IR")}
                  </time>
                ) : null}
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
