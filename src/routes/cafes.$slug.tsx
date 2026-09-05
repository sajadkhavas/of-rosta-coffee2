import { useQuery } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { getCafe } from "@/lib/api/cafes";
export const Route = createFileRoute("/cafes/$slug")({ component: CafePage });
function CafePage() {
  const { slug } = Route.useParams();
  const query = useQuery({ queryKey: ["cafe", slug], queryFn: () => getCafe(slug) });
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-4 py-10" dir="rtl">
        {query.data ? (
          <article className="rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6 md:p-8">
            <Link to="/cafes" className="text-sm text-[color:var(--roast)]">
              ← همه کافه‌ها
            </Link>
            <h1 className="mt-5 text-3xl font-bold">{query.data.name}</h1>
            <p className="mt-2 text-sm text-[color:var(--light)]">
              {query.data.city} · کافه تأییدشده رستا
            </p>
            {query.data.description ? (
              <p className="mt-6 leading-8">{query.data.description}</p>
            ) : null}
            <dl className="mt-8 grid gap-4 sm:grid-cols-2">
              <div>
                <dt className="text-xs text-[color:var(--light)]">آدرس</dt>
                <dd className="mt-1">{query.data.address}</dd>
              </div>
              {query.data.phone ? (
                <div>
                  <dt className="text-xs text-[color:var(--light)]">تماس</dt>
                  <dd className="mt-1">{query.data.phone}</dd>
                </div>
              ) : null}
              {query.data.instagram_handle ? (
                <div>
                  <dt className="text-xs text-[color:var(--light)]">اینستاگرام</dt>
                  <dd className="mt-1">@{query.data.instagram_handle.replace(/^@/, "")}</dd>
                </div>
              ) : null}
              {query.data.website_url ? (
                <div>
                  <dt className="text-xs text-[color:var(--light)]">وب‌سایت</dt>
                  <dd className="mt-1">
                    <a
                      href={query.data.website_url}
                      rel="noreferrer"
                      target="_blank"
                      className="text-[color:var(--roast)]"
                    >
                      مشاهده وب‌سایت
                    </a>
                  </dd>
                </div>
              ) : null}
            </dl>
            {query.data.amenities.length ? (
              <div className="mt-8 flex flex-wrap gap-2">
                {query.data.amenities.map((item) => (
                  <span
                    key={item}
                    className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-sm"
                  >
                    {item}
                  </span>
                ))}
              </div>
            ) : null}
          </article>
        ) : (
          <p>{query.isError ? "کافه پیدا نشد." : "در حال دریافت…"}</p>
        )}
      </main>
      <Footer />
    </>
  );
}
