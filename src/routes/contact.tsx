import { createFileRoute } from "@tanstack/react-router";
import { Clock, Mail, MessageCircle } from "lucide-react";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl } from "@/config/site";

const CRUMBS = [
  { label: "خانه", to: "/" },
  { label: "تماس با ما" },
];

const contactJsonLd = {
  "@context": "https://schema.org",
  "@type": "ContactPage",
  name: "تماس با رستا",
  url: absoluteUrl("/contact"),
  publisher: {
    "@type": "Organization",
    name: "رستا",
    url: absoluteUrl("/"),
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
      {
        property: "og:description",
        content: "راه‌های ارتباط با تیم پشتیبانی رستا.",
      },
      { property: "og:url", content: absoluteUrl("/contact") },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/contact") }],
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
        <div
          className="mt-1 text-sm font-medium text-[color:var(--steam)]"
          dir="ltr"
        >
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
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-lg px-4 py-8">
        <Breadcrumb items={CRUMBS} />
        <h1 className="text-3xl font-bold text-[color:var(--steam)]">
          تماس با ما
        </h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          برای پرسش درباره سفارش، همکاری روستری یا گزارش مشکل، از مسیرهای
          تأییدشده زیر استفاده کنید.
        </p>

        <div className="mt-6 space-y-3">
          <InfoCard
            icon={<Mail size={18} />}
            label="ایمیل پشتیبانی"
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

        <section className="mt-8 rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
          <h2 className="text-lg font-bold text-[color:var(--steam)]">
            ارسال درخواست پشتیبانی
          </h2>
          <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
            فرم تیکت آنلاین پس از اتصال سرویس پشتیبانی فعال می‌شود. تا آن زمان،
            ایمیل مسیر رسمی ثبت و پیگیری درخواست است.
          </p>
          <a
            href="mailto:support@rosta.shop?subject=درخواست%20پشتیبانی%20رستا"
            className="mt-5 inline-flex min-h-11 items-center rounded-lg bg-[color:var(--roast)] px-5 text-sm font-bold text-[color:var(--night)] transition hover:opacity-90"
          >
            ارسال ایمیل به پشتیبانی
          </a>
        </section>
      </main>
      <Footer />
    </>
  );
}
