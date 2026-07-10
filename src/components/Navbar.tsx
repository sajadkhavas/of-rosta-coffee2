import { Link } from "@tanstack/react-router";
import { Search, ShoppingBag, User } from "lucide-react";
import { useCart } from "@/lib/cart-context";
import { toFa } from "@/lib/persian";



export function Navbar() {
  const { itemCount } = useCart();
  return (
    <header className="sticky top-0 z-40 border-b border-[color:var(--mid)]/60 bg-[color:var(--night)]/70 backdrop-blur-xl">
      <nav
        aria-label="ناوبری اصلی"
        className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4"
      >
        <Link to="/" className="flex items-center gap-3">
          <span
            aria-hidden
            className="grid h-10 w-10 place-items-center rounded-full border border-[color:var(--roast)]/40 bg-gradient-to-br from-[color:var(--roast)] to-[color:var(--muted-gold)] text-[color:var(--night)] font-bold shadow-[0_0_20px_-4px_rgba(200,150,90,0.6)]"
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
            <Link to="/" activeOptions={{ exact: true }} className="transition hover:text-[color:var(--roast)]">
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
            to="/cart"
            aria-label="سبد خرید"
            className="grid h-9 w-9 place-items-center rounded-full text-[color:var(--light)] transition hover:bg-[color:var(--dark)] hover:text-[color:var(--roast)]"
          >
            <ShoppingBag size={18} />
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
    </header>
  );
}
