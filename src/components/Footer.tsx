import { Link } from "@tanstack/react-router";

export function Footer() {
  return (
    <footer className="mt-24 border-t border-[color:var(--mid)] bg-[color:var(--dark)]">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 py-14 md:grid-cols-5">
        <div className="md:col-span-2">
          <div className="flex items-center gap-3">
            <span
              aria-hidden
              className="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-[color:var(--roast)] to-[color:var(--muted-gold)] text-[color:var(--night)] font-bold"
            >
              ر
            </span>
            <div>
              <div className="font-display text-xl font-bold text-[color:var(--steam)]">ROSTA</div>
              <div className="text-[10px] tracking-[0.3em] text-[color:var(--roast)]">رستا</div>
            </div>
          </div>
          <p className="mt-4 max-w-sm text-sm leading-7 text-[color:var(--light)]">
            قهوه تازه، بدون واسطه. دانه کامل با تاریخ رست دقیق، مستقیم از روستری.
          </p>
        </div>

        <nav aria-label="لینک‌های فوتر">
          <h2 className="eyebrow">مسیرها</h2>
          <ul className="mt-4 space-y-3 text-sm text-[color:var(--light)]">
            <li><Link to="/" className="transition hover:text-[color:var(--roast)]">خانه</Link></li>
            <li><Link to="/roasteries" className="transition hover:text-[color:var(--roast)]">روستری‌ها</Link></li>
            <li><Link to="/products" className="transition hover:text-[color:var(--roast)]">محصولات</Link></li>
            <li><Link to="/about" className="transition hover:text-[color:var(--roast)]">درباره ما</Link></li>
          </ul>
        </nav>

        <nav aria-label="راهنما">
          <h2 className="eyebrow">راهنما</h2>
          <ul className="mt-4 space-y-3 text-sm text-[color:var(--light)]">
            <li><Link to="/contact" className="transition hover:text-[color:var(--roast)]">تماس با ما</Link></li>
            <li><Link to="/terms" className="transition hover:text-[color:var(--roast)]">قوانین و مقررات</Link></li>
            <li><Link to="/privacy" className="transition hover:text-[color:var(--roast)]">حریم خصوصی</Link></li>
          </ul>
        </nav>

        <div>
          <h2 className="eyebrow">اعتماد</h2>
          <ul className="mt-4 space-y-3 text-sm text-[color:var(--light)]">
            <li>تازه‌رست از روستری</li>
            <li>بدون واسطه</li>
            <li>انتخاب آسیاب</li>
            <li>ارسال سریع</li>
          </ul>
        </div>
      </div>

      <div className="border-t border-[color:var(--mid)] py-5 text-center text-xs tracking-widest text-[color:var(--muted-gold)]">
        © ۱۴۰۴ رستا — همه حقوق محفوظ است
      </div>
    </footer>
  );
}
