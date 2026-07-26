import { readFile, writeFile } from "node:fs/promises";

const paths = {
  api: "src/lib/api/grinding-capability.ts",
  publicPanel: "src/components/catalog/RoasteryGrindingCapability.tsx",
  publicPage: "src/routes/roasteries.$slug.tsx",
  sellerPage: "src/routes/panel.grinding.tsx",
  panelIndex: "src/routes/panel.index.tsx",
  unitTest: "tests/unit/r5e-grinding-capability.test.ts",
  package: "package.json",
  backendPolicy: "backend/app/Services/Catalog/RoasteryGrindingPolicy.php",
  backendController: "backend/app/Http/Controllers/Seller/SellerGrindingCapabilityController.php",
  backendRoutes: "backend/routes/grinding-capability.php",
  contract: "docs/r5/R5E_ROASTERY_GRINDING_CAPABILITY.md",
  cart: "src/lib/cart-storage.ts",
  product: "src/lib/api/contracts.ts",
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
  "permanent_frontend_gate",
  scripts["audit:r5e"] === "node scripts/audit-r5e-grinding-capability.mjs" &&
    scripts.check.includes("audit:r5e"),
  "Frontend check must permanently execute the R5E audit.",
);
gate(
  "strict_contract",
  hasAll(files.api, [
    'availability: z.enum(["available", "unavailable"])',
    "is_available: z.boolean()",
    'fee_mode: z.enum(["free", "fixed"])',
    "supported_weights: z.array(weight).max(5)",
    "profiles: z.array(grindingProfileWireSchema).max(20)",
    'value.is_free !== (value.fee_mode === "free" || value.fee_amount === 0)',
    "/grinding-capability",
  ]) &&
    hasAll(files.unitTest, [
      "rejects inconsistent free money",
      "rejects an available capability without profiles",
      "keeps grinding outside product and inventory identity",
    ]),
  "Browser contracts and unit tests must reject inconsistent capability data.",
);
gate(
  "public_capability_surface",
  hasAll(files.publicPanel, [
    "سرویس آسیاب روستری",
    "پروفایل‌های قابل ارائه",
    "محصولات همچنان به‌صورت دانه کامل",
    "capability.supportedWeights",
    "capability.profiles.map",
  ]) &&
    hasAll(files.publicPage, ["RoasteryGrindingCapability", "roasterySlug={roastery.slug}"]),
  "The public roastery page must show availability, fee, weights and profiles.",
);
gate(
  "seller_capability_surface",
  hasAll(files.sellerPage, [
    'createFileRoute("/panel/grinding")',
    "updateSellerGrindingCapability",
    "grindingProfilesQueryOptions",
    "وزن‌های دانه کامل قابل آسیاب",
    "پروفایل‌های تأییدشده رستا",
    "محصول، SKU، بچ رست و موجودی همچنان",
  ]) && hasAll(files.panelIndex, ['to="/panel/grinding"', "تنظیم سرویس آسیاب"]),
  "Owner and manager must have a linked workspace for capability configuration.",
);
gate(
  "seller_api_contract",
  hasAll(files.api, [
    "getSellerGrindingCapability",
    "updateSellerGrindingCapability",
    "grinding_profile_ids",
    "supported_weights",
  ]) &&
    hasAll(files.backendRoutes, [
      "/grinding-capability",
      "roastery_owner,roastery_manager,roastery_staff,administrator",
    ]) &&
    hasAll(files.backendPolicy, ["profiles()->sync", "ValidationException::withMessages"]) &&
    hasAll(files.backendController, [
      "Role::RoasteryOwner",
      "Role::RoasteryManager",
      "assertRoasteryAccess",
    ]),
  "Frontend writes must target the scoped Laravel capability endpoint with controller-enforced roles.",
);
gate(
  "whole_bean_boundary",
  !files.cart.includes("grindingProfile") &&
    !files.product.includes("grindVariant") &&
    hasAll(files.contract, [
      "R5E does not attach grinding to cart, quote or order items",
      "no grinding variant, SKU or stock dimension is introduced",
      "ROSTA_R5E_GRINDING_CAPABILITY_COMPLETE",
    ]),
  "R5E may publish capability but must not add grind state to product inventory or cart persistence.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generated_at: new Date().toISOString(),
  passed: failed.length === 0,
  gates,
  failures: failed.map((item) => item.name),
  marker: failed.length === 0 ? "ROSTA_R5E_GRINDING_CAPABILITY_FRONTEND_COMPLETE" : null,
};
await writeFile(
  "r5e-grinding-capability-frontend-audit.json",
  `${JSON.stringify(report, null, 2)}\n`,
);
if (failed.length) {
  for (const item of failed) console.error(`- ${item.name}: ${item.evidence}`);
  process.exit(1);
}
console.log("ROSTA_R5E_GRINDING_CAPABILITY_FRONTEND_COMPLETE");
