import { useQuery } from "@tanstack/react-query";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { fallback, zodValidator } from "@tanstack/zod-adapter";
import { z } from "zod";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { CatalogRoasteryCard } from "@/components/catalog/CatalogRoasteryCard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl } from "@/config/site";
import { isApiError } from "@/lib/api/client";
import { roasteriesQueryOptions } from "@/lib/api/catalog";

const searchSchema = z.object({
  page: fallback(z.coerce.number().int().min(1), 1).default(1),
});
type RoasteriesSearch = z.infer<typeof searchSchema>;

export const Route = createFileRoute("/roasteries/")({
  validateSearch: zodValidator(searchSchema),
  loaderDeps: ({ search }) => searchSchema.parse(search ?? {}),
  loader: ({ context, deps }) => context.queryClient.ensureQueryData(roasteriesQueryOptions({ page: deps.page, perPage: 18 })),
  head: ({ loaderData }) => ({
    meta: [
      { title: "روستری‌های ایران | خرید مستقیم دانه قهوه | رستا" },
      { name: "description", content: "کشف روستری‌های تأییدشده ایران و مشاهده دانه‌های تازه‌رست، موجودی و زمان آماده‌سازی زنده." },
      { property: "og:title", content: "روستری‌های ایران | رستا" },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/roasteries") }],
    scripts: [
      { type: "application/ld+json", children: JSON.stringify(breadcrumbJsonLd([{ label: "خانه", to: "/" }, { label: "روستری‌ها", to: "/roasteries" }])) },
      ...(loaderData?.items.length ? [{
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "ItemList",
          itemListElement: loaderData.items.map((roastery, index) => ({
            "@type": "ListItem",
            position: index + 1,
            url: absoluteUrl(`/roasteries/${roastery.slug}`),
            name: roastery.name,
          })),
        }),
      }] : []),
    ],
  }),
  component: RoasteriesIndex,
});

function RoasteriesIndex() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/roasteries/" });
  const query = useQuery(roasteriesQueryOptions({ page: search.page, perPage: 18 }));
  const currentPage = query.data?.meta?.current_page ?? search.page;
  const lastPage = query.data?.meta?.last_page ?? currentPage;

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "روستری‌ها" }]} />
        <header className="mt-4"><p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">ROASTERIES</p><h1 className="mt-2 text-3xl font-bold sm:text-4xl">روستری‌های ایران</h1><p className="mt-3 max-w-2xl text-sm leading-7 text-[color:var(--light)]">پروفایل، شهر، امتیاز و زمان آماده‌سازی فقط از API رسمی روستری‌های تأییدشده نمایش داده می‌شود.</p></header>
        {query.isPending ? (
          <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-label="در حال بارگذاری">{Array.from({ length: 6 }, (_, index) => <div key={index} className="h-72 animate-pulse rounded-2xl bg-[color:var(--dark)]" />)}</div>
        ) : query.isError ? (
          <section className="mt-10 rounded-2xl border border-red-400/40 bg-red-950/20 p-6 text-center"><h2 className="font-bold">فهرست روستری‌ها بارگذاری نشد</h2><p className="mt-2 text-sm text-[color:var(--light)]">{isApiError(query.error) ? query.error.message : "ارتباط با API برقرار نشد."}</p><button type="button" onClick={() => query.refetch()} className="mt-5 rounded-xl bg-[color:var(--roast)] px-5 py-2.5 text-sm font-bold text-[color:var(--night)]">تلاش مجدد</button></section>
        ) : !query.data.items.length ? (
          <section className="mt-10 rounded-2xl border border-dashed border-[color:var(--mid)] p-10 text-center"><h2 className="font-bold">هنوز روستری فعالی ثبت نشده است</h2><p className="mt-2 text-sm text-[color:var(--light)]">پس از تأیید ادمین، روستری‌های فعال اینجا نمایش داده می‌شوند.</p></section>
        ) : (
          <><div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{query.data.items.map((roastery) => <CatalogRoasteryCard key={roastery.id} roastery={roastery} />)}</div>{lastPage > 1 ? <nav className="mt-10 flex items-center justify-center gap-3" aria-label="صفحه‌بندی روستری‌ها"><button type="button" disabled={currentPage <= 1} onClick={() => navigate({ search: { page: currentPage - 1 }, replace: true })} className="rounded-xl border border-[color:var(--mid)] px-4 py-2 text-sm disabled:opacity-40">قبلی</button><span className="text-sm text-[color:var(--light)]">صفحه {currentPage.toLocaleString("fa-IR")} از {lastPage.toLocaleString("fa-IR")}</span><button type="button" disabled={currentPage >= lastPage} onClick={() => navigate({ search: { page: currentPage + 1 }, replace: true })} className="rounded-xl border border-[color:var(--mid)] px-4 py-2 text-sm disabled:opacity-40">بعدی</button></nav> : null}</>
        )}
      </main>
      <Footer />
    </>
  );
}
