import { createFileRoute } from "@tanstack/react-router";
import { Clock, Mail, MessageCircle } from "lucide-react";
import { type FormEvent, useState } from "react";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl } from "@/config/site";
import { createInquiry, type InquiryReceipt, type InquiryType } from "@/lib/api/inquiries";

const CRUMBS = [{ label: "خانه", to: "/" }, { label: "تماس با ما" }];

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

const inquiryLabels: Record<InquiryType, string> = {
  support: "پشتیبانی عمومی",
  order_issue: "پیگیری یا مشکل سفارش",
  roastery_onboarding: "همکاری روستری",
  corporate_purchase: "خرید سازمانی",
  content_correction: "اصلاح اطلاعات سایت",
  privacy_request: "درخواست حریم خصوصی",
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
  const [type, setType] = useState<InquiryType>("support");
  const [name, setName] = useState("");
  const [mobile, setMobile] = useState("");
  const [email, setEmail] = useState("");
  const [orderNumber, setOrderNumber] = useState("");
  const [message, setMessage] = useState("");
  const [website, setWebsite] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [receipt, setReceipt] = useState<InquiryReceipt | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;

    setSubmitting(true);
    setError(null);
    setReceipt(null);
    try {
      const result = await createInquiry({
        type,
        name,
        mobile,
        email,
        orderNumber,
        message,
        website,
      });
      setReceipt(result);
      setMessage("");
      setOrderNumber("");
    } catch (caught) {
      setError(
        caught instanceof Error ? caught.message : "ثبت درخواست انجام نشد. دوباره تلاش کنید.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-2xl px-4 py-8">
        <Breadcrumb items={CRUMBS} />
        <h1 className="text-3xl font-bold text-[color:var(--steam)]">تماس با ما</h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          برای پرسش درباره سفارش، همکاری روستری یا گزارش مشکل، درخواست خود را ثبت کنید. پس از ذخیره
          موفق، یک شناسه پیگیری دریافت می‌کنید.
        </p>

        <div className="mt-6 grid gap-3 sm:grid-cols-3">
          <InfoCard
            icon={<Mail size={18} />}
            label="ایمیل پشتیبانی"
            value="support@rosta.shop"
            href="mailto:support@rosta.shop"
          />
          <InfoCard
            icon={<MessageCircle size={18} />}
            label="شبکه‌های اجتماعی"
            value="@rostacoffee"
          />
          <InfoCard
            icon={<Clock size={18} />}
            label="ساعات پاسخگویی"
            value="شنبه تا پنج‌شنبه، ۹ تا ۱۸"
          />
        </div>

        <section className="mt-8 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 sm:p-7">
          <h2 className="text-lg font-bold text-[color:var(--steam)]">ثبت درخواست پشتیبانی</h2>
          <form className="mt-6 space-y-5" onSubmit={submit}>
            <label className="block text-sm text-[color:var(--light)]">
              موضوع درخواست
              <select
                value={type}
                onChange={(event) => setType(event.target.value as InquiryType)}
                className="mt-2 min-h-11 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-[color:var(--steam)]"
              >
                {Object.entries(inquiryLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="block text-sm text-[color:var(--light)]">
                نام و نام خانوادگی
                <input
                  required
                  minLength={2}
                  maxLength={160}
                  autoComplete="name"
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                  className="mt-2 min-h-11 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-[color:var(--steam)]"
                />
              </label>
              <label className="block text-sm text-[color:var(--light)]">
                شماره موبایل
                <input
                  inputMode="tel"
                  autoComplete="tel"
                  dir="ltr"
                  value={mobile}
                  onChange={(event) => setMobile(event.target.value)}
                  placeholder="09123456789"
                  className="mt-2 min-h-11 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-left text-[color:var(--steam)]"
                />
              </label>
              <label className="block text-sm text-[color:var(--light)]">
                ایمیل
                <input
                  type="email"
                  autoComplete="email"
                  dir="ltr"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  placeholder="name@example.com"
                  className="mt-2 min-h-11 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-left text-[color:var(--steam)]"
                />
              </label>
              {type === "order_issue" ? (
                <label className="block text-sm text-[color:var(--light)]">
                  شماره سفارش
                  <input
                    required
                    dir="ltr"
                    value={orderNumber}
                    onChange={(event) => setOrderNumber(event.target.value)}
                    className="mt-2 min-h-11 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-left text-[color:var(--steam)]"
                  />
                </label>
              ) : null}
            </div>

            <label className="block text-sm text-[color:var(--light)]">
              شرح درخواست
              <textarea
                required
                minLength={10}
                maxLength={5000}
                rows={6}
                value={message}
                onChange={(event) => setMessage(event.target.value)}
                className="mt-2 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--night)] px-3 py-3 text-[color:var(--steam)]"
              />
            </label>

            <div
              className="absolute -left-[10000px] top-auto h-px w-px overflow-hidden"
              aria-hidden="true"
            >
              <label>
                وب‌سایت
                <input
                  tabIndex={-1}
                  autoComplete="off"
                  value={website}
                  onChange={(event) => setWebsite(event.target.value)}
                />
              </label>
            </div>

            {error ? (
              <p
                role="alert"
                className="rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-200"
              >
                {error}
              </p>
            ) : null}
            {receipt ? (
              <p
                role="status"
                className="rounded-lg border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-100"
              >
                درخواست ثبت شد. شناسه پیگیری: <b dir="ltr">{receipt.referenceId}</b>
              </p>
            ) : null}

            <button
              type="submit"
              disabled={submitting}
              className="inline-flex min-h-11 items-center rounded-lg bg-[color:var(--roast)] px-6 text-sm font-bold text-[color:var(--night)] transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {submitting ? "در حال ثبت…" : "ثبت درخواست"}
            </button>
          </form>
        </section>
      </main>
      <Footer />
    </>
  );
}
