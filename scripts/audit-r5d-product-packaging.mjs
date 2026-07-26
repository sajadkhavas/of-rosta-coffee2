import { readFile, writeFile } from "node:fs/promises";

const paths = {
  contracts: "src/lib/api/contracts.ts",
  schemas: "src/lib/api/schemas.ts",
  financial: "src/lib/api/financial-contracts.ts",
  checkout: "src/lib/api/checkout.ts",
  orders: "src/lib/api/orders.ts",
  cartStorage: "src/lib/cart-storage.ts",
  cartContext: "src/lib/cart-context.tsx",
  product: "src/routes/products.$slug.tsx",
  cart: "src/routes/cart.tsx",
  checkoutRoute: "src/routes/checkout.tsx",
  sellerApi: "src/lib/api/seller-operations.ts",
  sellerUi: "src/components/seller/SellerOperationsDashboard.tsx",
  test: "tests/unit/r5d-packaging.test.ts",
  package: "package.json",
};
const files = Object.fromEntries(
  await Promise.all(
    Object.entries(paths).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);
const hasAll = (source, fragments) => fragments.every((fragment) => source.includes(fragment));
const gates = [];
const gate = (name, passed, evidence) => gates.push({ name, passed: Boolean(passed), evidence });
const scripts = JSON.parse(files.package).scripts ?? {};

gate(
  "permanent_gate",
  scripts["audit:r5d"] === "node scripts/audit-r5d-product-packaging.mjs" &&
    scripts.check.includes("audit:r5d"),
  "Frontend check must permanently execute R5D audit.",
);
gate(
  "marketplace_contract",
  hasAll(files.schemas, [
    "groups: z.array(quoteGroupWireSchema).min(1).max(50)",
    "packaging_total",
    "shipment_legs",
  ]) &&
    !files.schemas.includes("groups: z.array(quoteGroupWireSchema).min(1).max(1)") &&
    !files.schemas.includes("sub_orders: z.array(subOrderWireSchema).min(1).max(1)"),
  "Browser contracts must accept several roastery groups and child shipments.",
);
gate(
  "explicit_packaging",
  hasAll(files.contracts, ["interface PackagingPolicy", "packagingTotal", "packagingFee"]) &&
    hasAll(files.checkout, [
      "packagingTotal: value.packaging_total",
      "services: line.services.map",
    ]) &&
    hasAll(files.orders, [
      "packagingTotal: value.packaging_total",
      "packagingFee: service.packaging_fee",
    ]),
  "Product, quote and order mappings must keep explicit packaging lines.",
);
gate(
  "multi_roastery_cart",
  files.cartContext.includes('status: "added"') &&
    !files.cartContext.includes('status: "requires_reset"') &&
    !files.cartStorage.includes("سبد شامل چند روستری است"),
  "Adding another roastery must not clear or reject the cart.",
);
gate(
  "customer_surfaces",
  hasAll(files.product, ["product.packaging.label", "product.packaging.feeAmount"]) &&
    hasAll(files.cart, ["quote.packagingTotal", "بسته‌بندی رایگان"]) &&
    hasAll(files.checkoutRoute, ["quote.packagingTotal", "بسته‌بندی روستری"]),
  "Product, cart and checkout must show free or paid packaging.",
);
gate(
  "seller_control",
  hasAll(files.sellerApi, [
    "packagingFeeMode",
    "packaging_fee_amount",
    'packaging_fee_mode: "packagingFeeMode"',
    'packaging_fee_amount: "packagingFeeAmount"',
  ]) &&
    hasAll(files.sellerUi, ["هزینه بسته‌بندی", "packagingMutation", "ذخیره بسته‌بندی"]),
  "Owner and manager UI must create and update product packaging through the PATCH allowlist.",
);
gate(
  "unit_contract",
  hasAll(files.test, [
    "calculates paid packaging per package",
    "keeps free packaging explicit",
    "supports multiple roasteries",
  ]),
  "Unit tests must preserve packaging math and marketplace cart behaviour.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generated_at: new Date().toISOString(),
  passed: failed.length === 0,
  gates,
  failures: failed.map((item) => item.name),
  marker: failed.length === 0 ? "ROSTA_R5D_PRODUCT_PACKAGING_FRONTEND_COMPLETE" : null,
};
await writeFile(
  "r5d-product-packaging-frontend-audit.json",
  `${JSON.stringify(report, null, 2)}\n`,
);
if (failed.length) {
  for (const item of failed) console.error(`- ${item.name}: ${item.evidence}`);
  process.exit(1);
}
console.log("ROSTA_R5D_PRODUCT_PACKAGING_FRONTEND_COMPLETE");
