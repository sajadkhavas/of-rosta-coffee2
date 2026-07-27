import fs from "node:fs";

const files = {
  customer: fs.readFileSync("src/routes/orders.$id.tsx", "utf8"),
  ordersApi: fs.readFileSync("src/lib/api/orders.ts", "utf8"),
  contracts: fs.readFileSync("src/lib/api/contracts.ts", "utf8"),
  schema: fs.readFileSync("src/lib/api/schemas.ts", "utf8"),
  seller: fs.readFileSync("src/components/seller/SellerOperationsDashboard.tsx", "utf8"),
  sellerApi: fs.readFileSync("src/lib/api/seller-operations.ts", "utf8"),
  admin: fs.readFileSync("src/routes/admin.operations.tsx", "utf8"),
  adminApi: fs.readFileSync("src/lib/api/admin-finance.ts", "utf8"),
  terms: fs.readFileSync("src/routes/terms.tsx", "utf8"),
};
const failures = [];
const requireAll = (name, source, needles) => {
  for (const needle of needles) if (!source.includes(needle)) failures.push(`${name}: missing ${needle}`);
};
requireAll("customer delivery", files.customer, [
  "تأیید دریافت سفارش",
  "مهلت اعلام مشکل",
  "confirmOrderDelivery",
  "deliveryConfirmation",
]);
requireAll("strict delivery API", files.ordersApi, [
  "/delivery-confirmations",
  'proof_type: "customer_acknowledgement"',
  "authoritativeOrderDetailWireSchema",
]);
requireAll("wire contract", files.schema + files.contracts, [
  "deliveryConfirmation",
  "disputeWindowEndsAt",
  "settlementState",
  "customer_can_confirm",
]);
requireAll("seller settlement", files.seller + files.sellerApi, [
  "صورت‌حساب‌ها و پرداخت‌های روستری",
  "sellerSettlementsQueryOptions",
  "در برنامه پرداخت",
]);
requireAll("admin payout", files.admin + files.adminApi, [
  "Batchهای تسویه روستری",
  "createAdminSettlementBatch",
  "resolveAdminSettlementBatch",
  "ثبت پرداخت موفق",
  "ثبت پرداخت ناموفق",
]);
if (files.terms.includes("پرداخت و تایید روستری")) failures.push("terms still require manual seller acceptance");
if (failures.length) {
  console.error(`R5I frontend audit failed:\n- ${failures.join("\n- ")}`);
  process.exit(1);
}
console.log("ROSTA_R5I_DELIVERY_SETTLEMENT_FRONTEND_COMPLETE");
