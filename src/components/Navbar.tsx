import { Link } from "@tanstack/react-router";

export function Navbar() {
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
            <Link to="/about" className="transition hover:text-[color:var(--roast)]">
              درباره ما
            </Link>
          </li>
        </ul>
      </nav>
    </header>
  );
}
