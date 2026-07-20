import "@fontsource-variable/vazirmatn";
import "@fontsource-variable/playfair-display";
import "@fontsource/dm-mono/400.css";
import "@fontsource/dm-mono/500.css";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
  HeadContent,
  Link,
  Outlet,
  Scripts,
  createRootRouteWithContext,
  useRouter,
  useRouterState,
} from "@tanstack/react-router";
import { useEffect, type ReactNode } from "react";
import appCss from "../styles.css?url";
import { reportLovableError } from "../lib/lovable-error-reporting";
import { MobileBottomNav } from "../components/MobileBottomNav";
import { ServiceWorkerRegistration } from "../components/ServiceWorkerRegistration";
import { CartProvider } from "../lib/cart-context";
import { ToastProvider } from "../components/system";
import { absoluteUrl, siteConfig } from "../config/site";

function NotFoundComponent() {
  return (
    <main className="grid min-h-screen place-items-center px-4">
      <section className="max-w-md text-center">
        <p className="font-mono text-7xl font-bold text-[color:var(--roast)]">۴۰۴</p>
        <h1 className="mt-4 text-2xl font-bold">صفحه پیدا نشد</h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          صفحه‌ای که به دنبال آن هستید وجود ندارد یا جابه‌جا شده است.
        </p>
        <div className="mt-6 flex flex-wrap justify-center gap-3">
          <Link to="/" className="rounded-xl bg-[color:var(--roast)] px-5 py-2.5 text-sm font-bold text-[color:var(--night)]">
            صفحه اصلی
          </Link>
          <Link to="/products" className="rounded-xl border border-[color:var(--mid)] px-5 py-2.5 text-sm font-bold">
            مشاهده محصولات
          </Link>
        </div>
      </section>
    </main>
  );
}

function ErrorComponent({ error, reset }: { error: Error; reset: () => void }) {
  const router = useRouter();
  useEffect(() => {
    reportLovableError(error, { boundary: "tanstack_root_error_component" });
  }, [error]);

  return (
    <main className="grid min-h-screen place-items-center px-4">
      <section className="max-w-md text-center">
        <h1 className="text-2xl font-bold">این صفحه بارگذاری نشد</h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          یک خطای پیش‌بینی‌نشده رخ داد. دوباره تلاش کنید یا به صفحه اصلی برگردید.
        </p>
        <div className="mt-6 flex flex-wrap justify-center gap-3">
          <button
            type="button"
            onClick={() => {
              router.invalidate();
              reset();
            }}
            className="rounded-xl bg-[color:var(--roast)] px-5 py-2.5 text-sm font-bold text-[color:var(--night)]"
          >
            تلاش مجدد
          </button>
          <a href="/" className="rounded-xl border border-[color:var(--mid)] px-5 py-2.5 text-sm font-bold">
            صفحه اصلی
          </a>
        </div>
      </section>
    </main>
  );
}

export const Route = createRootRouteWithContext<{ queryClient: QueryClient }>()({
  head: () => ({
    meta: [
      { charSet: "utf-8" },
      { name: "viewport", content: "width=device-width, initial-scale=1, viewport-fit=cover" },
      { name: "theme-color", content: siteConfig.themeColor },
      { title: "رستا | کشف و مقایسه دانه کامل قهوه" },
      { name: "description", content: siteConfig.description },
      { name: "author", content: siteConfig.name },
      { property: "og:site_name", content: siteConfig.name },
      { property: "og:type", content: "website" },
      { property: "og:locale", content: siteConfig.locale },
      { property: "og:url", content: absoluteUrl("/") },
      { property: "og:image", content: absoluteUrl(siteConfig.socialImagePath) },
      { name: "twitter:card", content: "summary_large_image" },
      { name: "twitter:image", content: absoluteUrl(siteConfig.socialImagePath) },
      { name: "apple-mobile-web-app-capable", content: "yes" },
      { name: "apple-mobile-web-app-status-bar-style", content: "black-translucent" },
      { name: "apple-mobile-web-app-title", content: siteConfig.name },
      {
        name: "robots",
        content: siteConfig.allowIndexing ? "index,follow" : "noindex,nofollow",
      },
    ],
    links: [
      { rel: "stylesheet", href: appCss },
      { rel: "icon", href: "/favicon.ico", type: "image/x-icon" },
      { rel: "icon", href: "/icon-192.png", type: "image/png", sizes: "192x192" },
      { rel: "apple-touch-icon", href: "/icon-192.png" },
      { rel: "manifest", href: "/manifest.json" },
      { rel: "canonical", href: absoluteUrl("/") },
    ],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "Organization",
          name: siteConfig.name,
          url: siteConfig.siteUrl,
          logo: absoluteUrl("/icon-512.png"),
        }),
      },
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "WebSite",
          name: siteConfig.name,
          url: siteConfig.siteUrl,
          inLanguage: siteConfig.language,
          potentialAction: {
            "@type": "SearchAction",
            target: `${siteConfig.siteUrl}/search?q={search_term_string}`,
            "query-input": "required name=search_term_string",
          },
        }),
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
    <html lang={siteConfig.language} dir={siteConfig.direction}>
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
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const hideMobileNav = pathname === "/quiz" || pathname.startsWith("/auth");

  useEffect(() => {
    let cancelled = false;
    let cleanup: (() => void) | undefined;
    void import("../lib/animations").then((animations) => {
      if (cancelled) return;
      animations.initLenis();
      animations.initCursor();
      const timer = window.setTimeout(() => {
        animations.splitTextReveal("[data-split-text]");
        animations.fadeUpStagger("[data-fade-up]", 0.08);
        animations.fadeUpStagger(".r-card", 0.1);
        document.querySelectorAll<HTMLElement>("[data-counter]").forEach((element) => {
          const target = Number.parseInt(element.getAttribute("data-counter") || "0", 10);
          animations.animateCounter(element, target, element.getAttribute("data-suffix") || "");
        });
        animations.magneticEffect("[data-magnetic]");
      }, 60);
      cleanup = () => window.clearTimeout(timer);
    });
    return () => {
      cancelled = true;
      cleanup?.();
    };
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <CartProvider>
          <ServiceWorkerRegistration />
          <div className={hideMobileNav ? "" : "pb-16 md:pb-0"}>
            <Outlet />
          </div>
          {!hideMobileNav ? <MobileBottomNav /> : null}
        </CartProvider>
      </ToastProvider>
    </QueryClientProvider>
  );
}
