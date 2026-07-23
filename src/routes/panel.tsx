import { createFileRoute, Navigate } from "@tanstack/react-router";
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

const sellerRoles = new Set([
  "roastery_owner",
  "roastery_manager",
  "roastery_staff",
  "administrator",
]);

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
          {(user) =>
            user.roles.some((role) => sellerRoles.has(role)) ? (
              <SellerOperationsDashboard user={user} />
            ) : (
              <Navigate to="/forbidden" replace />
            )
          }
        </AccountGuard>
      </main>
      <Footer />
    </>
  );
}
