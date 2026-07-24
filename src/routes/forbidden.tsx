import { createFileRoute, Link } from "@tanstack/react-router";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl } from "@/config/site";

export const Route = createFileRoute("/forbidden")({
  head: () => ({
    meta: [{ title: "دسترسی مجاز نیست | رستا" }, { name: "robots", content: "noindex,nofollow" }],
    links: [{ rel: "canonical", href: absoluteUrl("/forbidden") }],
  }),
  component: ForbiddenPage,
});

function ForbiddenPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto grid min-h-[65vh] max-w-xl place-items-center px-4 py-12 text-center">
        <section>
          <p className="font-mono text-6xl font-bold text-[color:var(--roast)]">۴۰۳</p>
          <h1 className="mt-4 text-2xl font-bold">اجازه دسترسی ندارید</h1>
          <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
            این حساب اجازه مشاهده این بخش را ندارد. با حساب مناسب وارد شوید یا به صفحه اصلی برگردید.
          </p>
          <div className="mt-6 flex flex-wrap justify-center gap-3">
            <Link
              to="/auth"
              search={{ mode: "login", redirect: "/profile" }}
              className="rounded-xl bg-[color:var(--roast)] px-5 py-2.5 text-sm font-bold text-[color:var(--night)]"
            >
              ورود با حساب دیگر
            </Link>
            <Link
              to="/"
              className="rounded-xl border border-[color:var(--mid)] px-5 py-2.5 text-sm font-bold"
            >
              صفحه اصلی
            </Link>
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
