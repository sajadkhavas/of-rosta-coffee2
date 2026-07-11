import { createFileRoute } from "@tanstack/react-router";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";

const CRUMBS = [
  { label: "خانه", to: "/" },
  { label: "قوانین و مقررات" },
];

export const Route = createFileRoute("/terms")({
  head: () => ({
    meta: [
      { title: "قوانین و مقررات | رستا" },
      {
        name: "description",
        content: "قوانین استفاده از پلتفرم رستا، شرایط خرید، ارسال و بازگشت کالا.",
      },
      { property: "og:title", content: "قوانین و مقررات | رستا" },
      { property: "og:description", content: "قوانین استفاده از پلتفرم رستا، شرایط خرید، ارسال و بازگشت کالا." },
      { property: "og:url", content: "/terms" },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: "/terms" }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify(breadcrumbJsonLd(CRUMBS)),
      },
    ],
  }),
  component: TermsPage,
});

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="mt-8">
      <h2 className="text-lg font-bold text-[color:var(--steam)]">{title}</h2>
      <div className="mt-3 space-y-3 text-sm leading-7 text-[color:var(--light)]">{children}</div>
    </section>
  );
}

function TermsPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-3xl px-4 py-8">
        <Breadcrumb items={CRUMBS} />
        <h1 className="text-3xl font-bold text-[color:var(--steam)]">قوانین و مقررات رستا</h1>
        <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
          استفاده از پلتفرم رستا به منزله پذیرش کامل قوانین زیر است. لطفاً پیش از ثبت سفارش این متن را مطالعه کنید.
        </p>

        <Section title="درباره رستا">
          <p>
            رستا یک مارکت‌پلیس آنلاین است که نقش واسط میان مشتری و روستری‌های مستقل قهوه در ایران را ایفا می‌کند.
            رستا خود تولیدکننده قهوه نیست و صرفاً بستری برای عرضه، سفارش و پیگیری تحویل محصولات روستری‌های عضو فراهم می‌کند.
          </p>
        </Section>

        <Section title="ثبت سفارش">
          <p>
            سفارش پس از پرداخت و تایید روستری مربوطه نهایی می‌شود. زمان آماده‌سازی و ارسال بسته به روستری متفاوت است و در صفحه هر محصول اعلام می‌گردد.
          </p>
          <p>
            در صورت عدم موجودی یا عدم امکان تامین، رستا مبلغ پرداختی را طی حداکثر ۷۲ ساعت کاری به حساب مشتری بازمی‌گرداند.
          </p>
        </Section>

        <Section title="قیمت‌گذاری">
          <p>
            قیمت هر محصول توسط روستری فروشنده تعیین می‌شود و ممکن است بدون اطلاع قبلی تغییر کند.
            قیمت نهایی همان مبلغی است که در زمان ثبت سفارش پرداخت می‌شود.
          </p>
        </Section>

        <Section title="ارسال">
          <p>
            زمان‌های اعلام‌شده برای تحویل، تخمینی و بر اساس تجربه روستری با پست پیشتاز است و تضمین قطعی محسوب نمی‌شود.
            تاخیرهای پستی خارج از کنترل رستا هستند، اما تیم پشتیبانی ما تا زمان تحویل نهایی پیگیر خواهد بود.
          </p>
        </Section>

        <Section title="بازگشت کالا و انصراف">
          <p>
            به دلیل ماهیت مواد غذایی فسادپذیر، امکان بازگشت قهوه‌ای که بسته‌بندی آن باز شده وجود ندارد.
          </p>
          <p>
            در صورت وجود نقص، خرابی بسته‌بندی، یا اشتباه در ارسال، مشتری می‌تواند تا حداکثر ۴۸ ساعت پس از تحویل با پشتیبانی رستا تماس بگیرد و درخواست تعویض یا بازگشت وجه بدهد.
          </p>
        </Section>

        <Section title="مسئولیت کیفیت">
          <p>
            مسئولیت کیفیت، سلامت و صحت اطلاعات محصول (تاریخ رست، خاستگاه، درصد ترکیب) بر عهده روستری فروشنده است.
            رستا صرفاً به‌عنوان واسط، اطلاعات ارائه‌شده توسط روستری را نمایش می‌دهد.
          </p>
        </Section>

        <Section title="تغییر قوانین">
          <p>
            رستا حق تغییر، اصلاح یا به‌روزرسانی این قوانین را در هر زمان محفوظ می‌دارد.
            نسخه معتبر همواره آخرین نسخه منتشرشده در همین صفحه است.
          </p>
        </Section>
      </main>
      <Footer />
    </>
  );
}
