import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
  Outlet,
  Link,
  createRootRouteWithContext,
  useRouter,
  HeadContent,
  Scripts,
} from "@tanstack/react-router";
import { useEffect, type ReactNode } from "react";

import appCss from "../styles.css?url";
import { reportLovableError } from "../lib/lovable-error-reporting";
import { Navbar } from "../components/Navbar";
import { Footer } from "../components/Footer";
import { MobileBottomNav } from "../components/MobileBottomNav";
import { useRouterState } from "@tanstack/react-router";
import { CartProvider } from "../lib/cart-context";

function NotFoundComponent() {
  return (
    <>
      <Navbar />
      <main className="flex min-h-[60vh] items-center justify-center px-4">
        <div className="max-w-md text-center">
          <h1 className="text-7xl font-bold text-[color:var(--rosta-primary)]">۴۰۴</h1>
          <h2 className="mt-4 text-xl font-semibold">صفحه پیدا نشد</h2>
          <p className="mt-2 text-sm text-[color:var(--rosta-secondary-text)]">
            صفحه‌ای که به دنبال آن هستید وجود ندارد یا جابه‌جا شده است.
          </p>
          <div className="mt-6 flex flex-wrap justify-center gap-2">
            <Link
              to="/"
              className="rounded-lg bg-[color:var(--rosta-primary)] px-4 py-2 text-sm font-medium text-[color:var(--rosta-bg)] hover:bg-[color:var(--rosta-accent)]"
            >
              بازگشت به خانه
            </Link>
            <Link
              to="/products"
              className="rounded-lg border border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] px-4 py-2 text-sm font-medium hover:border-[color:var(--rosta-accent)]"
            >
              مشاهده محصولات
            </Link>
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}

function ErrorComponent({ error, reset }: { error: Error; reset: () => void }) {
  console.error(error);
  const router = useRouter();
  useEffect(() => {
    reportLovableError(error, { boundary: "tanstack_root_error_component" });
  }, [error]);

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <div className="max-w-md text-center">
        <h1 className="text-xl font-semibold">این صفحه بارگذاری نشد</h1>
        <p className="mt-2 text-sm text-[color:var(--rosta-secondary-text)]">
          مشکلی پیش آمد. می‌توانید دوباره تلاش کنید یا به خانه برگردید.
        </p>
        <div className="mt-6 flex flex-wrap justify-center gap-2">
          <button
            onClick={() => { router.invalidate(); reset(); }}
            className="rounded-lg bg-[color:var(--rosta-primary)] px-4 py-2 text-sm font-medium text-[color:var(--rosta-bg)]"
          >
            تلاش مجدد
          </button>
          <a href="/" className="rounded-lg border border-[color:var(--rosta-border)] px-4 py-2 text-sm font-medium">
            بازگشت به خانه
          </a>
        </div>
      </div>
    </div>
  );
}

export const Route = createRootRouteWithContext<{ queryClient: QueryClient }>()({
  head: () => ({
    meta: [
      { charSet: "utf-8" },
      { name: "viewport", content: "width=device-width, initial-scale=1" },
      { name: "theme-color", content: "#0A0400" },
      { title: "رستا | خرید قهوه تازه مستقیم از روستری" },
      {
        name: "description",
        content:
          "رستا مارکت‌پلیس قهوه ایران — قهوه تازه‌رست از بهترین روستری‌های ایران را مقایسه و سفارش دهید. بدون واسطه، با انتخاب آسیاب.",
      },
      { name: "author", content: "رستا" },
      { property: "og:site_name", content: "رستا" },
      { property: "og:type", content: "website" },
      { property: "og:locale", content: "fa_IR" },
      { name: "twitter:card", content: "summary_large_image" },
      { name: "apple-mobile-web-app-capable", content: "yes" },
      { name: "apple-mobile-web-app-status-bar-style", content: "black-translucent" },
      { name: "apple-mobile-web-app-title", content: "رستا" },

    links: [
      { rel: "stylesheet", href: appCss },
      { rel: "icon", href: "/favicon.ico", type: "image/x-icon" },
      { rel: "icon", href: "/icon-192.png", type: "image/png", sizes: "192x192" },
      { rel: "apple-touch-icon", href: "/icon-192.png" },
      { rel: "manifest", href: "/manifest.json" },
      { rel: "preconnect", href: "https://fonts.googleapis.com" },
      { rel: "preconnect", href: "https://fonts.gstatic.com", crossOrigin: "anonymous" },
      {
        rel: "stylesheet",
        href: "https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Mono:wght@400;500&display=swap",
      },
    ],

  }),
  shellComponent: RootShell,
  component: RootComponent,
  notFoundComponent: NotFoundComponent,
  errorComponent: ErrorComponent,
});

function RootShell({ children }: { children: ReactNode }) {
  return (
    <html lang="fa" dir="rtl">
      <head>
        <HeadContent />
      </head>
      <body>
        {children}
        <Scripts />
      </body>
    </html>
  );
}

function RootComponent() {
  const { queryClient } = Route.useRouteContext();
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const hideMobileNav = pathname === "/quiz";
  useEffect(() => {
    let cleanup: (() => void) | undefined;
    (async () => {
      const a = await import("../lib/animations");
      a.initLenis();
      a.initCursor();
      const run = () => {
        a.splitTextReveal("[data-split-text]");
        a.fadeUpStagger("[data-fade-up]", 0.08);
        a.fadeUpStagger(".r-card", 0.1);
        document.querySelectorAll<HTMLElement>("[data-counter]").forEach((el) => {
          const target = parseInt(el.getAttribute("data-counter") || "0", 10);
          const suffix = el.getAttribute("data-suffix") || "";
          a.animateCounter(el, target, suffix);
        });
        a.magneticEffect("[data-magnetic]");
      };
      const t = window.setTimeout(run, 60);
      cleanup = () => window.clearTimeout(t);
    })();
    return () => cleanup?.();
  }, []);
  return (
    <QueryClientProvider client={queryClient}>
      <CartProvider>
        <div className={hideMobileNav ? "" : "pb-16 md:pb-0"}>
          <Outlet />
        </div>
        {!hideMobileNav && <MobileBottomNav />}
      </CartProvider>
    </QueryClientProvider>
  );
}
