import { Link, useRouterState } from "@tanstack/react-router";
import { Home, Search, Sparkles, ShoppingBag, User } from "lucide-react";

export function MobileBottomNav() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const isActive = (p: string, exact = false) =>
    exact ? pathname === p : pathname === p || pathname.startsWith(p + "/");

  const Item = ({
    to,
    label,
    active,
    children,
  }: {
    to: string;
    label: string;
    active: boolean;
    children: React.ReactNode;
  }) => (
    <Link
      to={to}
      className="flex flex-1 flex-col items-center justify-center gap-1 transition-transform active:scale-90"
      style={{ color: active ? "var(--roast)" : "rgba(232,213,183,0.6)" }}
    >
      {children}
      <span className="text-[10px] font-medium">{label}</span>
    </Link>
  );

  return (
    <nav
      aria-label="ناوبری موبایل"
      className="fixed bottom-0 left-0 right-0 z-50 flex h-16 items-center justify-around border-t border-[color:var(--mid)] bg-[color:var(--dark)]/95 backdrop-blur-lg md:hidden"
      style={{ paddingBottom: "env(safe-area-inset-bottom)" }}
    >
      <Item to="/" active={isActive("/", true)} label="خانه">
        <Home size={22} strokeWidth={1.5} />
      </Item>
      <Item to="/search" active={isActive("/search")} label="جستجو">
        <Search size={22} strokeWidth={1.5} />
      </Item>
      {/* Quiz — raised action */}
      <Link
        to="/quiz"
        aria-label="کوییز سلیقه"
        className="flex flex-1 flex-col items-center justify-center gap-1"
      >
        <span
          className="grid h-12 w-12 -translate-y-2 place-items-center rounded-full text-[color:var(--night)] shadow-[0_6px_20px_-4px_rgba(200,150,90,0.7)] transition-transform active:scale-90"
          style={{ background: "var(--roast)" }}
        >
          <Sparkles size={22} strokeWidth={2} />
        </span>
        <span
          className="-mt-1 text-[10px] font-bold"
          style={{ color: pathname === "/quiz" ? "var(--roast)" : "rgba(232,213,183,0.7)" }}
        >
          کوییز
        </span>
      </Link>
      <Item to="/cart" active={isActive("/cart")} label="سبد خرید">
        <span className="relative">
          <ShoppingBag size={22} strokeWidth={1.5} />
        </span>
      </Item>
      <Item to="/profile" active={isActive("/profile")} label="حساب من">
        <User size={22} strokeWidth={1.5} />
      </Item>
    </nav>
  );
}
