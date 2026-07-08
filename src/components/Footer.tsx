import { Link } from "@tanstack/react-router";

export function Footer() {
  return (
    <footer className="mt-16 border-t border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)]">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 md:grid-cols-3">
        <div>
          <div className="flex items-center gap-2">
            <span
              aria-hidden
              className="grid h-9 w-9 place-items-center rounded-full bg-[color:var(--rosta-primary)] text-[color:var(--rosta-bg)] font-bold"
            >
              ر
            </span>
            <span className="text-lg font-bold text-[color:var(--rosta-primary)]">رستا</span>
          </div>
          <p className="mt-3 text-sm text-[color:var(--rosta-secondary-text)]">
            قهوه تازه، مستقیم از روستری
          </p>
        </div>

        <nav aria-label="لینک‌های فوتر">
          <h2 className="text-sm font-bold text-[color:var(--rosta-primary)]">لینک‌ها</h2>
          <ul className="mt-3 space-y-2 text-sm text-[color:var(--rosta-secondary-text)]">
            <li><Link to="/" className="hover:text-[color:var(--rosta-accent)]">خانه</Link></li>
            <li><Link to="/roasteries" className="hover:text-[color:var(--rosta-accent)]">روستری‌ها</Link></li>
            <li><Link to="/products" className="hover:text-[color:var(--rosta-accent)]">محصولات</Link></li>
            <li><Link to="/about" className="hover:text-[color:var(--rosta-accent)]">درباره ما</Link></li>
          </ul>
        </nav>

        <div>
          <h2 className="text-sm font-bold text-[color:var(--rosta-primary)]">اعتماد</h2>
          <p className="mt-3 text-sm text-[color:var(--rosta-secondary-text)]">
            قهوه تازه، بدون واسطه. هر محصول با تاریخ رست دقیق و انتخاب آسیاب.
          </p>
        </div>
      </div>
      <div className="border-t border-[color:var(--rosta-border)] py-4 text-center text-xs text-[color:var(--rosta-secondary-text)]">
        © ۱۴۰۴ رستا — همه حقوق محفوظ است
      </div>
    </footer>
  );
}
