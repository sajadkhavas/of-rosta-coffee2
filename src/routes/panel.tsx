import { createFileRoute } from "@tanstack/react-router";
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
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "پنل روستری" },
          ]}
        />
        <AccountGuard>
          {(user) => <SellerOperationsDashboard user={user} />}
        </AccountGuard>
      </main>
      <Footer />
    </>
  );
}
