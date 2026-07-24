import { Alert, Button } from "@/components/system";
import type { AdminContentLinkReport } from "@/lib/api/admin-content";
import { isApiError } from "@/lib/api/client";

interface ContentLinkReportPanelProps {
  report?: AdminContentLinkReport;
  isPending: boolean;
  isFetching: boolean;
  error: unknown;
  onRefresh: () => void;
  onEditEntry: (entryId: string) => void;
}

const REASON_LABELS: Record<AdminContentLinkReport["broken_relations"][number]["reason"], string> =
  {
    missing_target: "مقصد وجود ندارد",
    unpublished_target: "مقصد عمومی یا منتشرشده نیست",
    wrong_content_type: "نوع مقصد با رابطه سازگار نیست",
  };

const TARGET_LABELS: Record<
  AdminContentLinkReport["broken_relations"][number]["target_type"],
  string
> = {
  content: "محتوا",
  product: "محصول",
  roastery: "روستری",
  origin: "خاستگاه",
  brew_method: "روش دم‌آوری",
  taste: "طعم",
};

function errorMessage(error: unknown): string {
  return isApiError(error)
    ? error.message
    : "گزارش لینک‌سازی داخلی دریافت نشد. اتصال API را بررسی کنید.";
}

function EmptyMessage({ children }: { children: string }) {
  return (
    <p className="rounded-xl border border-dashed border-[color:var(--mid)] p-5 text-center text-sm leading-7 text-[color:var(--light)]">
      {children}
    </p>
  );
}

export function ContentLinkReportPanel({
  report,
  isPending,
  isFetching,
  error,
  onRefresh,
  onEditEntry,
}: ContentLinkReportPanelProps) {
  return (
    <section
      aria-labelledby="content-link-report-title"
      className="rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 sm:p-6"
    >
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-bold tracking-[0.16em] text-[color:var(--roast)]">
            INTERNAL LINK HEALTH
          </p>
          <h2
            id="content-link-report-title"
            className="mt-2 text-2xl font-bold text-[color:var(--steam)]"
          >
            سلامت لینک‌سازی داخلی
          </h2>
          <p className="mt-2 max-w-3xl text-sm leading-8 text-[color:var(--light)]">
            روابط شکسته، صفحات منتشرشده بدون لینک ورودی و صفحاتی که کمتر از دو رابطه خروجی دارند در
            این بخش نمایش داده می‌شوند.
          </p>
        </div>
        <Button
          type="button"
          variant="outline"
          loading={isFetching && !isPending}
          onClick={onRefresh}
        >
          بازبینی دوباره
        </Button>
      </div>

      {error ? (
        <div className="mt-5">
          <Alert variant="danger" title="گزارش لینک داخلی دریافت نشد">
            {errorMessage(error)}
          </Alert>
        </div>
      ) : null}

      {isPending ? (
        <div
          aria-busy="true"
          aria-label="در حال دریافت گزارش لینک داخلی"
          className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
        >
          {Array.from({ length: 5 }, (_, index) => (
            <div
              key={index}
              className="h-24 animate-pulse rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)]"
            />
          ))}
        </div>
      ) : report ? (
        <>
          <div className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            {[
              ["منتشرشده", report.summary.entries_by_status.published],
              ["در انتظار بررسی", report.summary.entries_by_status.review],
              ["روابط شکسته", report.summary.broken_relations],
              ["صفحات یتیم", report.summary.orphaned_entries],
              ["لینک خروجی ضعیف", report.summary.weak_outbound_entries],
            ].map(([label, value]) => (
              <div
                key={String(label)}
                className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
              >
                <p className="text-xs leading-6 text-[color:var(--light)]">{label}</p>
                <p className="mt-2 text-2xl font-bold text-[color:var(--roast)]">
                  {Number(value).toLocaleString("fa-IR")}
                </p>
              </div>
            ))}
          </div>

          {report.summary.relations_truncated ? (
            <div className="mt-4">
              <Alert variant="warning" title="گزارش محدود شده است">
                تعداد روابط از سقف بررسی یک‌باره بیشتر است. گزارش برای جلوگیری از فشار روی پنل به
                اولین ۵٬۰۰۰ رابطه محدود شده است.
              </Alert>
            </div>
          ) : null}

          <div className="mt-6 grid gap-5 xl:grid-cols-3">
            <section aria-labelledby="broken-links-title">
              <div className="mb-3 flex items-center justify-between gap-3">
                <h3 id="broken-links-title" className="font-bold text-[color:var(--steam)]">
                  روابط شکسته
                </h3>
                <span className="text-xs text-[color:var(--light)]">
                  {report.broken_relations.length.toLocaleString("fa-IR")}
                </span>
              </div>
              <div className="max-h-[34rem] space-y-3 overflow-auto pe-1">
                {report.broken_relations.length ? (
                  report.broken_relations.map((relation) => (
                    <article
                      key={relation.relation_id}
                      className="rounded-2xl border border-red-400/30 bg-red-950/10 p-4"
                    >
                      <p className="font-bold leading-7 text-[color:var(--steam)]">
                        {relation.source.title ?? "منبع حذف‌شده"}
                      </p>
                      <p
                        className="mt-2 break-all text-xs leading-6 text-[color:var(--light)]"
                        dir="ltr"
                      >
                        {relation.source.canonical_path ?? "—"}
                      </p>
                      <dl className="mt-3 grid gap-2 text-xs text-[color:var(--light)]">
                        <div className="flex justify-between gap-3">
                          <dt>مقصد</dt>
                          <dd className="break-all text-left" dir="ltr">
                            {TARGET_LABELS[relation.target_type]}: {relation.target_key}
                          </dd>
                        </div>
                        <div className="flex justify-between gap-3">
                          <dt>مشکل</dt>
                          <dd className="text-left text-red-200">
                            {REASON_LABELS[relation.reason]}
                          </dd>
                        </div>
                      </dl>
                      {relation.source.id ? (
                        <Button
                          type="button"
                          variant="outline"
                          className="mt-4 w-full"
                          onClick={() => onEditEntry(relation.source.id as string)}
                        >
                          اصلاح رابطه
                        </Button>
                      ) : null}
                    </article>
                  ))
                ) : (
                  <EmptyMessage>هیچ رابطه شکسته‌ای پیدا نشد.</EmptyMessage>
                )}
              </div>
            </section>

            <section aria-labelledby="orphaned-pages-title">
              <div className="mb-3 flex items-center justify-between gap-3">
                <h3 id="orphaned-pages-title" className="font-bold text-[color:var(--steam)]">
                  صفحات یتیم
                </h3>
                <span className="text-xs text-[color:var(--light)]">
                  {report.orphaned_entries.length.toLocaleString("fa-IR")}
                </span>
              </div>
              <div className="max-h-[34rem] space-y-3 overflow-auto pe-1">
                {report.orphaned_entries.length ? (
                  report.orphaned_entries.map((entry) => (
                    <article
                      key={entry.id}
                      className="rounded-2xl border border-amber-300/30 bg-amber-950/10 p-4"
                    >
                      <p className="font-bold leading-7 text-[color:var(--steam)]">{entry.title}</p>
                      <p
                        className="mt-2 break-all text-xs leading-6 text-[color:var(--light)]"
                        dir="ltr"
                      >
                        {entry.canonical_path}
                      </p>
                      <p className="mt-3 text-xs leading-6 text-amber-100">
                        هیچ محتوایی با رابطه داخلی به این صفحه اشاره نمی‌کند.
                      </p>
                      <Button
                        type="button"
                        variant="outline"
                        className="mt-4 w-full"
                        onClick={() => onEditEntry(entry.id)}
                      >
                        بازکردن محتوا
                      </Button>
                    </article>
                  ))
                ) : (
                  <EmptyMessage>هیچ صفحه منتشرشده یتیمی پیدا نشد.</EmptyMessage>
                )}
              </div>
            </section>

            <section aria-labelledby="weak-outbound-title">
              <div className="mb-3 flex items-center justify-between gap-3">
                <h3 id="weak-outbound-title" className="font-bold text-[color:var(--steam)]">
                  لینک خروجی ضعیف
                </h3>
                <span className="text-xs text-[color:var(--light)]">
                  {report.weak_outbound_entries.length.toLocaleString("fa-IR")}
                </span>
              </div>
              <div className="max-h-[34rem] space-y-3 overflow-auto pe-1">
                {report.weak_outbound_entries.length ? (
                  report.weak_outbound_entries.map((entry) => (
                    <article
                      key={entry.id}
                      className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
                    >
                      <p className="font-bold leading-7 text-[color:var(--steam)]">{entry.title}</p>
                      <p
                        className="mt-2 break-all text-xs leading-6 text-[color:var(--light)]"
                        dir="ltr"
                      >
                        {entry.canonical_path}
                      </p>
                      <p className="mt-3 text-xs leading-6 text-[color:var(--light)]">
                        روابط خروجی فعلی: {entry.relations_count.toLocaleString("fa-IR")}
                      </p>
                      <Button
                        type="button"
                        variant="outline"
                        className="mt-4 w-full"
                        onClick={() => onEditEntry(entry.id)}
                      >
                        افزودن رابطه
                      </Button>
                    </article>
                  ))
                ) : (
                  <EmptyMessage>همه صفحات منتشرشده حداقل دو رابطه خروجی دارند.</EmptyMessage>
                )}
              </div>
            </section>
          </div>

          <p className="mt-5 text-xs leading-6 text-[color:var(--light)]">
            آخرین تولید گزارش: {new Date(report.generated_at).toLocaleString("fa-IR")}
          </p>
        </>
      ) : null}
    </section>
  );
}
