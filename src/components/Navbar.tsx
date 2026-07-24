import { Link, useRouterState } from "@tanstack/react-router";
import { Search, ShieldCheck, ShoppingBag, Store, User, WalletCards } from "lucide-react";
import { useCart } from "@/lib/cart-context";
import { toFa } from "@/lib/persian";

const adminNavBase = "whitespace-nowrap rounded-lg border px-3 py-2 transition";
const adminNavIdle =
  "border-[color:var(--mid)] text-[color:var(--light)] hover:border-[color:var(--roast)] hover:text-[color:var(--roast)]";
const adminNavActive =
  "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]";

export function Navbar() {
  const { itemCount } = useCart();
  const pathname = useRouterState({
    select: (state) => state.location.pathname,
  });
  const isAdminRoute = pathname.startsWith("/admin");
  const isEditorialActive =
    pathname === "/admin/content" || pathname.startsWith("/admin/content-edit/");
  const isLinkReportActive = pathname === "/admin/content-links";
  const isFinanceActive = pathname === "/admin/finance";
  const isOperationsActive = pathname === "/admin/operations";

  return (
    <header className="sticky top-0 z-40 border-b border-[color:var(--mid)]/60 bg-[color:var(--night)]/85 backdrop-blur-xl">
      <nav
        aria-label="ناوبری اصلی"
        className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4"
      >
        <Link to="/" className="flex items-center gap-3">
          <span
            aria-hidden
            className="grid h-10 w-10 place-items-center rounded-full border border-[color:var(--roast)]/40 bg-gradient-to-br from-[color:var(--roast)] to-[color:var(--muted-gold)] font-bold text-[color:var(--night)] shadow-[0_0_20px_-4px_rgba(200,150,90,0.6)]"
          >
            ر
          </span>
          <div className="leading-tight">
            <div className="font-display text-xl font-bold tracking-wide text-[color:var(--steam)]">
              ROSTA
            </div>
            <div className="text-[10px] tracking-[0.3em] text-[color:var(--roast)]">
              رستا · قهوه تازه
            </div>
          </div>
        </Link>

        <ul className="hidden items-center gap-8 text-sm font-medium text-[color:var(--light)] md:flex">
          <li>
            <Link
              to="/"
              activeOptions={{ exact: true }}
              className="transition hover:text-[color:var(--roast)]"
            >
              خانه
            </Link>
          </li>
          <li>
            <Link to="/roasteries" className="transition hover:text-[color:var(--roast)]">
              روستری‌ها
            </Link>
          </li>
          <li>
            <Link to="/products" className="transition hover:text-[color:var(--roast)]">
              محصولات
            </Link>
          </li>
          <li>
            <Link to="/blog" className="transition hover:text-[color:var(--roast)]">
              مجله
            </Link>
          </li>
          <li>
            <Link to="/quiz" className="transition hover:text-[color:var(--roast)]">
              کوییز
            </Link>
          </li>
          <li>
            <Link to="/about" className="transition hover:text-[color:var(--roast)]">
              درباره ما
            </Link>
          </li>
        </ul>

        <div className="hidden items-center gap-2 md:flex">
          <Link
            to="/search"
            aria-label="جستجو"
            className="grid h-9 w-9 place-items-center rounded-full text-[color:var(--light)] transition hover:bg-[color:var(--dark)] hover:text-[color:var(--roast)]"
          >
            <Search size={18} />
          </Link>
          <Link
            to="/panel"
            aria-label="پنل روستری"
            className="grid h-9 w-9 place-items-center rounded-full text-[color:var(--light)] transition hover:bg-[color:var(--dark)] hover:text-[color:var(--roast)]"
          >
            <Store size={18} />
          </Link>
          <Link
            to="/cart"
            aria-label="سبد خرید"
            className="relative grid h-9 w-9 place-items-center rounded-full text-[color:var(--light)] transition hover:bg-[color:var(--dark)] hover:text-[color:var(--roast)]"
          >
            <ShoppingBag size={18} />
            {itemCount > 0 ? (
              <span className="absolute -right-1 -top-1 grid min-w-[18px] place-items-center rounded-full bg-[color:var(--roast)] px-1 font-mono-num text-[10px] font-bold text-[color:var(--night)]">
                {toFa(itemCount)}
              </span>
            ) : null}
          </Link>
          <Link
            to="/profile"
            aria-label="حساب من"
            className="grid h-9 w-9 place-items-center rounded-full text-[color:var(--light)] transition hover:bg-[color:var(--dark)] hover:text-[color:var(--roast)]"
          >
            <User size={18} />
          </Link>
        </div>
      </nav>

      {isAdminRoute ? (
        <nav
          aria-label="ناوبری عملیات ادمین"
          className="border-t border-[color:var(--mid)]/50 bg-[color:var(--dark)]/70"
        >
          <div className="mx-auto flex max-w-6xl gap-2 overflow-x-auto px-4 py-2 text-xs font-bold">
            <Link
              to="/admin/content"
              aria-current={isEditorialActive ? "page" : undefined}
              className={`${adminNavBase} ${isEditorialActive ? adminNavActive : adminNavIdle}`}
            >
              تحریریه
            </Link>
            <Link
              to="/admin/content-links"
              aria-current={isLinkReportActive ? "page" : undefined}
              className={`${adminNavBase} ${isLinkReportActive ? adminNavActive : adminNavIdle}`}
            >
              سلامت لینک‌ها
            </Link>
            <Link
              to="/admin/finance"
              aria-current={isFinanceActive ? "page" : undefined}
              className={`${adminNavBase} ${isFinanceActive ? adminNavActive : adminNavIdle}`}
            >
              <span className="inline-flex items-center gap-1.5">
                <WalletCards size={14} />
                عملیات مالی
              </span>
            </Link>
            <Link
              to="/admin/operations"
              aria-current={isOperationsActive ? "page" : undefined}
              className={`${adminNavBase} ${isOperationsActive ? adminNavActive : adminNavIdle}`}
            >
              <span className="inline-flex items-center gap-1.5">
                <ShieldCheck size={14} />
                نظارت عملیات
              </span>
            </Link>
          </div>
        </nav>
      ) : null}
    </header>
  );
}
