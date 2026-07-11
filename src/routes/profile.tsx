import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { loadProfile, type TasteProfile } from "@/lib/quiz-logic";

// TODO: replace with real API call when backend ready.
const mockUser = {
  name: "مهدی رضایی",
  phone: "0912***4567",
};

export const Route = createFileRoute("/profile")({
  head: () => ({
    meta: [
      { title: "حساب کاربری من | رستا" },
      { name: "description", content: "پروفایل، سلیقه و سفارش‌های شما در رستا." },
      { name: "robots", content: "noindex,follow" },
    ],
    links: [{ rel: "canonical", href: "/profile" }],
  }),
  component: ProfilePage,
});

function ProfilePage() {
  const [profile, setProfile] = useState<TasteProfile | null>(null);
  useEffect(() => setProfile(loadProfile()), []);

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "حساب من" }]} />
        <h1 className="text-2xl font-bold text-[color:var(--steam)]">حساب کاربری</h1>

        <div className="mt-6 grid gap-4 md:grid-cols-2">
          <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
            <div className="flex items-center gap-3">
              <div className="grid h-14 w-14 place-items-center rounded-full bg-[color:var(--roast)] font-display text-lg font-bold text-[color:var(--night)]">
                {mockUser.name[0]}
              </div>
              <div>
                <div className="font-bold text-[color:var(--steam)]">{mockUser.name}</div>
                <div className="font-mono-num text-xs text-[color:var(--light)]">
                  {mockUser.phone}
                </div>
              </div>
            </div>
            <button
              type="button"
              className="mt-4 rounded-lg border border-[color:var(--mid)] px-3 py-1.5 text-xs text-[color:var(--light)]"
              title="به‌زودی"
            >
              ویرایش (به‌زودی)
            </button>
          </section>

          <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
            <h2 className="text-sm font-bold text-[color:var(--steam)]">سلیقه قهوه</h2>
            {profile ? (
              <>
                <ul className="mt-3 space-y-1 text-sm text-[color:var(--light)]">
                  {profile.roastLevel && <li>سطح رست: {profile.roastLevel}</li>}
                  {profile.method && <li>روش دم‌آوری: {profile.method}</li>}
                  {profile.flavor && <li>طعم دلخواه: {profile.flavor}</li>}
                </ul>
                <Link
                  to="/quiz"
                  className="mt-3 inline-block text-xs text-[color:var(--roast)] underline"
                >
                  ویرایش سلیقه
                </Link>
              </>
            ) : (
              <>
                <p className="mt-2 text-sm text-[color:var(--light)]">
                  هنوز سلیقه‌ات را ثبت نکرده‌ای.
                </p>
                <Link
                  to="/quiz"
                  className="mt-3 inline-block rounded-lg bg-[color:var(--roast)] px-4 py-2 text-xs font-bold text-[color:var(--night)]"
                >
                  شروع کوییز
                </Link>
              </>
            )}
          </section>

          <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 md:col-span-2">
            <h2 className="text-sm font-bold text-[color:var(--steam)]">آدرس‌های من</h2>
            <p className="mt-2 text-sm text-[color:var(--light)]">
              هنوز آدرسی ثبت نکرده‌اید.
            </p>
            <button
              type="button"
              className="mt-3 rounded-lg border border-[color:var(--roast)] px-3 py-1.5 text-xs text-[color:var(--roast)]"
              title="به‌زودی"
            >
              افزودن آدرس (به‌زودی)
            </button>
          </section>

          <nav className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 md:col-span-2">
            <ul className="divide-y divide-[color:var(--mid)] text-sm">
              <li>
                <Link
                  to="/orders"
                  className="flex items-center justify-between py-3 text-[color:var(--steam)] hover:text-[color:var(--roast)]"
                >
                  سفارش‌های من
                  <span aria-hidden>‹</span>
                </Link>
              </li>
              <li className="flex items-center justify-between py-3 text-[color:var(--light)]">
                علاقه‌مندی‌ها
                <span className="text-[11px]">به‌زودی</span>
              </li>
              <li>
                <button
                  type="button"
                  className="w-full py-3 text-right text-[color:var(--light)] hover:text-[color:var(--roast)]"
                >
                  خروج
                </button>
              </li>
            </ul>
          </nav>
        </div>
      </main>
      <Footer />
    </>
  );
}
