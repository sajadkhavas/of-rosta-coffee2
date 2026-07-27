import fs from "node:fs";

const files = {
  seller: fs.readFileSync("src/components/seller/SellerOperationsDashboard.tsx", "utf8"),
  sellerApi: fs.readFileSync("src/lib/api/seller-operations.ts", "utf8"),
  admin: fs.readFileSync("src/routes/admin.operations.tsx", "utf8"),
  adminApi: fs.readFileSync("src/lib/api/admin-operations.ts", "utf8"),
  customer: fs.readFileSync("src/routes/orders.$id.tsx", "utf8"),
  schema: fs.readFileSync("src/lib/api/schemas.ts", "utf8"),
  browser: fs.readFileSync("tests/browser/r3c2-commerce-roles.spec.ts", "utf8"),
};

const failures = [];
const requireAll = (name, source, needles) => {
  for (const needle of needles) {
    if (!source.includes(needle)) failures.push(`${name}: missing ${needle}`);
  }
};

requireAll("seller commitment", files.seller, [
  "پذیرش قراردادی خودکار",
  "ثبت Incident برای پشتیبانی رستا",
  "مهلت تحویل به حمل",
  'status: "preparing"',
]);
requireAll("seller API", files.sellerApi, [
  '"preparing"',
  '"ready_to_ship"',
  '"shipped"',
  "reportSellerFulfillmentIncident",
]);
if (/status:\s*"rejected"|رد سفارش و ارسال به Refund Pending/.test(files.seller)) {
  failures.push("seller UI must not expose rejection");
}
requireAll("admin incident control", files.admin, [
  "Incidentهای تعهد ارسال",
  "رفع مشکل و ادامه ارسال",
  "لغو همین زیرسفارش و ثبت بازپرداخت",
]);
requireAll("admin incident API", files.adminApi, [
  "listAdminFulfillmentIncidents",
  "resolveAdminFulfillmentIncident",
  "/admin/fulfillment-incidents",
]);
requireAll("customer safety", files.customer, [
  "تعهد ارسال روستری فعال است",
  "بررسی عملیاتی در جریان است",
]);
requireAll("strict contract", files.schema, [
  "fulfillmentCommitmentWireSchema",
  "fulfillmentIncidentWireSchema",
]);
requireAll("browser lifecycle", files.browser, [
  "manualAcceptance.status).toBe(422)",
  "sellerDelivery.status).toBe(409)",
  "adminDelivery.status).toBe(200)",
]);

if (failures.length) {
  console.error(`R5H frontend audit failed:\n- ${failures.join("\n- ")}`);
  process.exit(1);
}
console.log("ROSTA_R5H_FULFILLMENT_COMMITMENT_FRONTEND_COMPLETE");
