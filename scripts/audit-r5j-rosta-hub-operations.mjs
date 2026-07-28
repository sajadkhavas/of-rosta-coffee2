import fs from "node:fs";

const files = {
  api: fs.readFileSync("src/lib/api/hub-operations.ts", "utf8"),
  page: fs.readFileSync("src/routes/hub.operations.tsx", "utf8"),
  order: fs.readFileSync("src/routes/orders.$id.tsx", "utf8"),
  seller: fs.readFileSync("src/components/seller/SellerOperationsDashboard.tsx", "utf8"),
  schemas: fs.readFileSync("src/lib/api/schemas.ts", "utf8"),
};
const failures = [];
const requireAll = (name, source, needles) => {
  for (const needle of needles) {
    if (!source.includes(needle)) failures.push(`${name}: missing ${needle}`);
  }
};
requireAll("api", files.api, [
  "/admin/hub-operations",
  "/hub-operations",
  "assignHubWorkItem",
  "transitionHubWorkItem",
]);
requireAll("page", files.page, ["عملیات هاب رستا", "کنترل کیفیت", "تخصیص اپراتور", "تحویل به حمل"]);
requireAll("customer", files.order, ["عملیات هاب:", "hubOperation.label"]);
requireAll("seller", files.seller, [
  'data-testid="seller-hub-handoff-status"',
  "roastery_to_rosta_hub",
  "hubOperation.receivedAt",
]);
requireAll("schema", files.schemas, ["hub_operation", "handed_off_at"]);
if (files.api.includes("private_evidence")) {
  failures.push("frontend API accepts private evidence field");
}
if (
  files.seller.includes("hubOperation.readyAt") ||
  files.seller.includes("hubOperation.handedOffAt")
) {
  failures.push("seller surface exposes internal Hub completion timestamps");
}
if (failures.length) {
  console.error(`R5J frontend audit failed:\n- ${failures.join("\n- ")}`);
  process.exit(1);
}
console.log("ROSTA_R5J_HUB_OPERATIONS_FRONTEND_COMPLETE");
