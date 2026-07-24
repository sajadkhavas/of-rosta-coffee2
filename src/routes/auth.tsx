import { Outlet, createFileRoute } from "@tanstack/react-router";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl } from "@/config/site";

export const Route = createFileRoute("/auth")({
  head: () => ({
    meta: [
      { title: "ورود و ثبت‌نام | رستا" },
      { name: "description", content: "ورود امن به رستا با رمز یک‌بارمصرف شماره موبایل." },
      { name: "robots", content: "noindex,nofollow" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/auth") }],
  }),
  component: AuthLayout,
});

function AuthLayout() {
  return (
    <>
      <Navbar />
      <main className="mx-auto grid min-h-[65vh] max-w-6xl place-items-center px-4 py-10">
        <div className="w-full max-w-md">
          <Outlet />
        </div>
      </main>
      <Footer />
    </>
  );
}
