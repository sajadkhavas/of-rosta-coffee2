import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { Mail, MessageCircle, Clock } from "lucide-react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";

const CRUMBS = [
  { label: "خانه", to: "/" },
  { label: "تماس با ما" },
];

const contactJsonLd = {
  "@context": "https://schema.org",
  "@type": "ContactPage",
  name: "تماس با رستا",
  url: "https://rosta.coffee/contact",
  publisher: {
    "@type": "Organization",
    name: "رستا",
    url: "https://rosta.coffee",
    contactPoint: [
      {
        "@type": "ContactPoint",
        contactType: "customer support",
        email: "support@rosta.shop",
        availableLanguage: ["Persian", "Farsi"],
        areaServed: "IR",
      },
    ],
  },
};

export const Route = createFileRoute("/contact")({
  head: () => ({
    meta: [
      { title: "تماس با ما | رستا" },
      { name: "description", content: "راه‌های ارتباط با تیم پشتیبانی رستا." },
      { property: "og:title", content: "تماس با ما | رستا" },
      { property: "og:description", content: "راه‌های ارتباط با تیم پشتیبانی رستا." },
      { property: "og:url", content: "/contact" },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: "/contact" }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify([breadcrumbJsonLd(CRUMBS), contactJsonLd]),
      },
    ],
  }),
  component: ContactPage,
});

function InfoCard({
  icon,
  label,
  value,
  href,
}: {
  icon: React.ReactNode;
  label: string;
  value: string;
  href?: string;
}) {
  const inner = (
    <div className="flex items-start gap-3 rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4">
      <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[color:var(--night)] text-[color:var(--roast)]">
        {icon}
      </div>
      <div>
        <div className="text-xs text-[color:var(--light)]">{label}</div>
        <div className="mt-1 text-sm font-medium text-[color:var(--steam)]" dir="ltr">
          {value}
        </div>
      </div>
    </div>
  );
  return href ? (
    <a href={href} className="block transition hover:opacity-90">
      {inner}
    </a>
  ) : (
    inner
  );
}

function ContactPage() {
  const [sent, setSent] = useState(false);

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-lg px-4 py-8">
        <Breadcrumb items={CRUMBS} />
        <h1 className="text-3xl font-bold text-[color:var(--steam)]">تماس با ما</h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          برای هرگونه سوال درباره سفارش، همکاری با رستا به‌عنوان روستری، یا پیشنهادات خود،
          از راه‌های زیر با ما در ارتباط باشید.
        </p>

        <div className="mt-6 space-y-3">
          <InfoCard
            icon={<Mail size={18} />}
            label="ایمیل"
            value="support@rosta.shop"
            href="mailto:support@rosta.shop"
          />
          <InfoCard
            icon={<MessageCircle size={18} />}
            label="تلگرام و اینستاگرام"
            value="@rostacoffee"
          />
          <InfoCard
            icon={<Clock size={18} />}
            label="ساعات پاسخگویی"
            value="شنبه تا پنج‌شنبه، ۹ تا ۱۸"
          />
        </div>

        <form
          className="mt-8 space-y-3 rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4"
          onSubmit={(e) => {
            e.preventDefault();
            // TODO: wire to real endpoint when backend ready
            setSent(true);
          }}
        >
          <h2 className="text-lg font-bold text-[color:var(--steam)]">ارسال پیام</h2>
          <div>
            <label htmlFor="c-name" className="block text-xs text-[color:var(--light)]">نام</label>
            <input
              id="c-name"
              type="text"
              required
              className="mt-1 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 py-2 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]"
            />
          </div>
          <div>
            <label htmlFor="c-phone" className="block text-xs text-[color:var(--light)]">شماره تماس</label>
            <input
              id="c-phone"
              type="tel"
              required
              inputMode="tel"
              className="mt-1 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 py-2 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]"
            />
          </div>
          <div>
            <label htmlFor="c-msg" className="block text-xs text-[color:var(--light)]">پیام</label>
            <textarea
              id="c-msg"
              required
              rows={4}
              className="mt-1 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 py-2 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]"
            />
          </div>
          <button
            type="submit"
            className="w-full rounded-lg bg-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--night)] transition hover:opacity-90"
          >
            ارسال پیام
          </button>
          {sent && (
            <p role="status" className="text-center text-sm text-[color:var(--roast)]">
              پیام شما ارسال شد، به‌زودی پاسخ می‌دهیم.
            </p>
          )}
        </form>
      </main>
      <Footer />
    </>
  );
}
