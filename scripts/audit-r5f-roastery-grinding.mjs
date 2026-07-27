import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const checks = [
  [
    "src/lib/cart-storage.ts",
    ["rosta_cart_v5", "grindingProfileId", "CART_STORAGE_VERSION = 5"],
  ],
  [
    "src/lib/cart-context.tsx",
    ["setGrindingProfile", "grindingProfileId: item.grindingProfileId"],
  ],
  [
    "src/lib/api/checkout.ts",
    ["grinding_profile_id", "serviceFee", "grindingProfile"],
  ],
  [
    "src/components/cart/CartGrindingSelector.tsx",
    [
      "بدون آسیاب — تحویل دانه کامل",
      "انتخاب آسیاب فقط یک خدمت سفارش است",
      "publicGrindingCapabilityQueryOptions",
    ],
  ],
  [
    "src/routes/cart.tsx",
    ["CartGrindingSelector", "setGrindingProfile", "quote.grindingTotal"],
  ],
  [
    "src/routes/checkout.tsx",
    ["quote.grindingTotal", 'service.type === "grinding"'],
  ],
  [
    "src/routes/orders.$id.tsx",
    ["item.services", 'service.type === "grinding"', "subOrder.grindingTotal"],
  ],
  [
    "tests/unit/r5f-roastery-grinding.test.ts",
    ["grinding_profile_id", "whole-bean identity"],
  ],
];

const failures = [];
for (const [relative, needles] of checks) {
  const file = path.join(root, relative);
  if (!fs.existsSync(file)) {
    failures.push(`missing ${relative}`);
    continue;
  }
  const contents = fs.readFileSync(file, "utf8");
  for (const needle of needles) {
    if (!contents.includes(needle)) failures.push(`${relative}: missing ${needle}`);
  }
}

const productIdentityFiles = [
  "src/lib/api/contracts.ts",
  "src/lib/cart-storage.ts",
  "src/lib/api/checkout.ts",
];
for (const relative of productIdentityFiles) {
  const contents = fs.readFileSync(path.join(root, relative), "utf8");
  for (const forbidden of ["grindingVariant", "grindSku", "grindingStock"]) {
    if (contents.includes(forbidden)) failures.push(`${relative}: forbidden ${forbidden}`);
  }
}

if (failures.length) {
  console.error(`R5F frontend audit failed:\n- ${failures.join("\n- ")}`);
  process.exit(1);
}

console.log("ROSTA_R5F_ROASTERY_GRINDING_FRONTEND_COMPLETE");
