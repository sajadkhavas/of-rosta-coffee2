import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import {
  Alert,
  Button,
  CheckboxField,
  Container,
  Dialog,
  Drawer,
  EmptyState,
  PageHeader,
  Skeleton,
  TextareaField,
  TextField,
  useToast,
} from "@/components/system";
import { absoluteUrl } from "@/config/site";

export const Route = createFileRoute("/design-system")({
  head: () => ({
    meta: [{ title: "Design System رستا" }, { name: "robots", content: "noindex,nofollow" }],
    links: [{ rel: "canonical", href: absoluteUrl("/design-system") }],
  }),
  component: DesignSystemPage,
});

function DemoSection({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 sm:p-6">
      <h2 className="text-lg font-bold">{title}</h2>
      <div className="mt-5">{children}</div>
    </section>
  );
}

function DesignSystemPage() {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const { pushToast } = useToast();

  return (
    <>
      <Navbar />
      <main className="py-10">
        <Container>
          <PageHeader
            eyebrow="ROSTA SYSTEM"
            title="سیستم طراحی رستا"
            description="مرجع زنده Primitiveهای RTL، حالت‌های تعاملی، فرم، بازخورد و Overlay. این صفحه فقط برای توسعه است و ایندکس نمی‌شود."
          />

          <div className="mt-8 grid gap-5">
            <DemoSection title="رنگ و تایپوگرافی">
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                  ["Night", "var(--night)", "#0A0400"],
                  ["Dark", "var(--dark)", "#1A0A00"],
                  ["Roast", "var(--roast)", "#C8965A"],
                  ["Steam", "var(--steam)", "#F7F0E6"],
                ].map(([name, value, hex]) => (
                  <div
                    key={name}
                    className="overflow-hidden rounded-2xl border border-[color:var(--mid)]"
                  >
                    <div className="h-24" style={{ background: value }} />
                    <div className="bg-[color:var(--night)] p-3">
                      <p className="font-bold">{name}</p>
                      <p className="mt-1 font-mono text-xs text-[color:var(--light)]">{hex}</p>
                    </div>
                  </div>
                ))}
              </div>
              <div className="mt-6 grid gap-4 md:grid-cols-3">
                <p className="font-display text-3xl">Playfair Display</p>
                <p className="text-2xl">وزیرمتن برای رابط فارسی</p>
                <p className="font-mono text-xl">IRR 250,000</p>
              </div>
            </DemoSection>

            <DemoSection title="دکمه‌ها و Actionها">
              <div className="flex flex-wrap gap-3">
                <Button>عملیات اصلی</Button>
                <Button variant="secondary">ثانویه</Button>
                <Button variant="outline">خطی</Button>
                <Button variant="ghost">Ghost</Button>
                <Button variant="danger">حذف</Button>
                <Button loading>در حال پردازش</Button>
                <Button disabled>غیرفعال</Button>
              </div>
            </DemoSection>

            <DemoSection title="Fieldها و فرم">
              <div className="grid gap-4 md:grid-cols-2">
                <TextField label="نام کامل" placeholder="نام و نام خانوادگی" />
                <TextField label="شماره موبایل" dir="ltr" placeholder="09123456789" />
                <TextField
                  label="Field خطادار"
                  error="این مقدار معتبر نیست."
                  defaultValue="مقدار اشتباه"
                />
                <CheckboxField
                  label="آدرس پیش‌فرض"
                  description="در Checkout به‌صورت خودکار انتخاب شود."
                />
                <div className="md:col-span-2">
                  <TextareaField label="توضیحات" placeholder="متن توضیحات…" />
                </div>
              </div>
            </DemoSection>

            <DemoSection title="بازخورد و Stateها">
              <div className="grid gap-4">
                <Alert title="اطلاع" variant="info">
                  اطلاعات تکمیلی برای کاربر.
                </Alert>
                <Alert title="موفق" variant="success">
                  عملیات با موفقیت انجام شد.
                </Alert>
                <Alert title="هشدار" variant="warning">
                  این بخش نیاز به بررسی دارد.
                </Alert>
                <Alert title="خطا" variant="danger">
                  ارتباط با سرویس برقرار نشد.
                </Alert>
                <EmptyState
                  title="داده‌ای وجود ندارد"
                  description="پس از ایجاد اولین مورد، این بخش تکمیل می‌شود."
                  action={<Button variant="outline">ایجاد مورد</Button>}
                />
                <div className="grid grid-cols-3 gap-3">
                  <Skeleton className="h-20" />
                  <Skeleton className="h-20" />
                  <Skeleton className="h-20" />
                </div>
              </div>
            </DemoSection>

            <DemoSection title="Overlay و Toast">
              <div className="flex flex-wrap gap-3">
                <Button onClick={() => setDialogOpen(true)}>بازکردن Dialog</Button>
                <Button variant="secondary" onClick={() => setDrawerOpen(true)}>
                  بازکردن Drawer
                </Button>
                <Button
                  variant="outline"
                  onClick={() =>
                    pushToast({
                      title: "تغییرات ذخیره شد",
                      description: "Toast از Live Region استفاده می‌کند.",
                      variant: "success",
                    })
                  }
                >
                  نمایش Toast
                </Button>
              </div>
            </DemoSection>
          </div>
        </Container>
      </main>
      <Footer />

      <Dialog
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        title="Dialog نمونه"
        description="Escape، Backdrop و بازگشت Focus پشتیبانی می‌شوند."
      >
        <p className="text-sm leading-7 text-[color:var(--light)]">
          این محتوای Dialog نمونه سیستم طراحی رستا است.
        </p>
        <Button className="mt-5" onClick={() => setDialogOpen(false)}>
          بستن
        </Button>
      </Dialog>

      <Drawer
        open={drawerOpen}
        onOpenChange={setDrawerOpen}
        title="Drawer نمونه"
        description="برای فرم‌های موبایل و جزئیات جانبی."
      >
        <p className="text-sm leading-7 text-[color:var(--light)]">
          Drawer از سمت انتهای رابط RTL نمایش داده می‌شود.
        </p>
        <Button className="mt-5" onClick={() => setDrawerOpen(false)}>
          بستن
        </Button>
      </Drawer>
    </>
  );
}
