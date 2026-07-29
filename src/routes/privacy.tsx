import { createFileRoute } from "@tanstack/react-router";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { absoluteUrl } from "@/config/site";

const CRUMBS = [{ label: "خانه", to: "/" }, { label: "حریم خصوصی" }];

export const Route = createFileRoute("/privacy")({
  head: () => ({
    meta: [
      { title: "حریم خصوصی | رستا" },
      {
        name: "description",
        content: "نحوه جمع‌آوری، استفاده و حفاظت از اطلاعات شخصی کاربران در رستا.",
      },
      { property: "og:title", content: "حریم خصوصی | رستا" },
      {
        property: "og:description",
        content: "نحوه جمع‌آوری، استفاده و حفاظت از اطلاعات شخصی کاربران در رستا.",
      },
      { property: "og:url", content: absoluteUrl("/privacy") },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/privacy") }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify(breadcrumbJsonLd(CRUMBS)),
      },
    ],
  }),
  component: PrivacyPage,
});

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="mt-8">
      <h2 className="text-lg font-bold text-[color:var(--steam)]">{title}</h2>
      <div className="mt-3 space-y-3 text-sm leading-7 text-[color:var(--light)]">{children}</div>
    </section>
  );
}

function PrivacyPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-3xl px-4 py-8">
        <Breadcrumb items={CRUMBS} />
        <h1 className="text-3xl font-bold text-[color:var(--steam)]">حریم خصوصی</h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          حفظ حریم خصوصی کاربران برای رستا بسیار مهم است. در این صفحه توضیح می‌دهیم چه اطلاعاتی
          جمع‌آوری می‌کنیم و چگونه از آن نگهداری می‌کنیم.
        </p>

        <Section title="اطلاعاتی که جمع‌آوری می‌کنیم">
          <p>
            برای ارائه خدمات، رستا ممکن است اطلاعات زیر را دریافت کند: نام و نام‌خانوادگی، شماره
            تماس، آدرس تحویل، سابقه سفارش، و سلیقه قهوه (در صورت تکمیل کوییز پیشنهاد قهوه).
          </p>
        </Section>

        <Section title="نحوه استفاده از اطلاعات">
          <p>
            اطلاعات جمع‌آوری‌شده صرفاً برای پردازش سفارش، بهبود پیشنهادها، و اطلاع‌رسانی وضعیت سفارش
            از طریق پیامک استفاده می‌شود.
          </p>
        </Section>

        <Section title="اشتراک‌گذاری اطلاعات">
          <p>
            اطلاعات تحویل (نام، آدرس و شماره تماس) فقط با روستری مربوط به سفارش شما به اشتراک گذاشته
            می‌شود تا امکان ارسال محصول فراهم گردد. رستا اطلاعات شخصی کاربران را به هیچ شخص ثالث
            دیگری نمی‌فروشد و در اختیار مقاصد تبلیغاتی قرار نمی‌دهد.
          </p>
        </Section>

        <Section title="امنیت اطلاعات">
          <p>
            برای انتقال اطلاعات پرداخت و ارتباط با درگاه‌ها از رمزنگاری استاندارد (HTTPS/TLS)
            استفاده می‌شود. رستا هیچ‌گاه اطلاعات کارت بانکی شما را ذخیره نمی‌کند.
          </p>
        </Section>

        <Section title="کوکی‌ها و ذخیره‌سازی محلی">
          <p>
            برای حفظ سبد خرید، سلیقه قهوه و بهبود تجربه کاربری، از حافظه محلی مرورگر (localStorage)
            استفاده می‌کنیم. این اطلاعات فقط روی دستگاه شما ذخیره می‌شوند و به سرور رستا ارسال
            نمی‌گردند.
          </p>
        </Section>

        <Section title="حقوق شما">
          <p>
            شما می‌توانید در هر زمان درخواست حذف اطلاعات شخصی خود از سامانه رستا را از طریق تماس با
            پشتیبانی ثبت کنید. درخواست‌ها حداکثر تا ۷ روز کاری بررسی و اعمال می‌شوند.
          </p>
        </Section>
      </main>
      <Footer />
    </>
  );
}
