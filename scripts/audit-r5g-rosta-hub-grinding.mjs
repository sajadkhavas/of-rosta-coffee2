import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const checks = [
  [
    "src/components/cart/CartGrindingSelector.tsx",
    [
      "grindingProfilesQueryOptions",
      "ارائه‌دهنده احتمالی: هاب رستا",
      "فقط برای مناطق فعال تهران یا کرج",
      "SKU، وزن و موجودی محصول را تغییر نمی‌دهد",
    ],
  ],
  ["src/routes/cart.tsx", ["CartGrindingSelector", "سرویس آسیاب"]],
  ["src/routes/checkout.tsx", ['service.type === "grinding"', "سرویس آسیاب"]],
  [
    "src/routes/orders.$id.tsx",
    ["roastery_to_rosta_hub", "rosta_hub_to_customer", 'service.providerType === "rosta_hub"'],
  ],
  [
    "tests/unit/r5g-rosta-hub-grinding.test.ts",
    ["grinding_profile_id", "provider_type", "whole-bean"],
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

const requestFiles = ["src/lib/api/checkout.ts", "src/lib/cart-storage.ts"];
for (const relative of requestFiles) {
  const contents = fs.readFileSync(path.join(root, relative), "utf8");
  for (const forbidden of [
    "provider_hub_id",
    "provider_type:",
    "hub_shipping_fee",
    "settlement_owner",
    "hub_capacity",
  ]) {
    if (contents.includes(forbidden)) failures.push(`${relative}: browser must not send ${forbidden}`);
  }
}

if (failures.length) {
  console.error(`R5G frontend audit failed:\n- ${failures.join("\n- ")}`);
  process.exit(1);
}

console.log("ROSTA_R5G_HUB_GRINDING_FRONTEND_COMPLETE");
