import { Link } from "@tanstack/react-router";

export function Navbar() {
  return (
    <header className="sticky top-0 z-40 border-b border-[color:var(--rosta-border)] bg-[color:var(--rosta-bg)]/90 backdrop-blur">
      <nav
        aria-label="ناوبری اصلی"
        className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4"
      >
        <Link to="/" className="flex items-center gap-2">
          <span
            aria-hidden
            className="grid h-9 w-9 place-items-center rounded-full bg-[color:var(--rosta-primary)] text-[color:var(--rosta-bg)] font-bold"
          >
            ر
          </span>
          <div className="leading-tight">
            <div className="text-lg font-bold text-[color:var(--rosta-primary)]">رستا</div>
            <div className="text-[11px] text-[color:var(--rosta-secondary-text)]">
              قهوه تازه، بدون واسطه
            </div>
          </div>
        </Link>
        <ul className="hidden items-center gap-6 text-sm font-medium text-[color:var(--rosta-primary)] md:flex">
          <li>
            <Link to="/" activeOptions={{ exact: true }} className="hover:text-[color:var(--rosta-accent)]">
              خانه
            </Link>
          </li>
          <li>
            <Link to="/roasteries" className="hover:text-[color:var(--rosta-accent)]">
              روستری‌ها
            </Link>
          </li>
          <li>
            <Link to="/products" className="hover:text-[color:var(--rosta-accent)]">
              محصولات
            </Link>
          </li>
          <li>
            <Link to="/about" className="hover:text-[color:var(--rosta-accent)]">
              درباره ما
            </Link>
          </li>
        </ul>
      </nav>
    </header>
  );
}
