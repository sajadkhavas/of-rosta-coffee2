import { createFileRoute, Link } from "@tanstack/react-router";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { SellerOperationsDashboard } from "@/components/seller/SellerOperationsDashboard";

export const Route = createFileRoute("/panel")({
  head: () => ({
    meta: [
      { title: "پنل عملیات روستری | رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: SellerPanelPage,
});

function SellerPanelPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <Breadcrumb
            items={[
              { label: "خانه", to: "/" },
              { label: "پنل روستری" },
            ]}
          />
          <Link
            to="/panel/manage"
            className="inline-flex min-h-11 items-center rounded-xl border border-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--roast)] transition hover:bg-[color:var(--roast)] hover:text-[color:var(--night)]"
          >
            ویرایش اطلاعات و کاتالوگ
          </Link>
        </div>
        <AccountGuard>
          {(user) => <SellerOperationsDashboard user={user} />}
        </AccountGuard>
      </main>
      <Footer />
    </>
  );
}
