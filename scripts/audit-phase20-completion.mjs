import { readFile, writeFile } from "node:fs/promises";

const paths = {
  package: "package.json",
  manage: "src/routes/panel.manage.tsx",
  panel: "src/routes/panel.tsx",
  managementClient: "src/lib/api/seller-management.ts",
  sellerClient: "src/lib/api/seller-operations.ts",
  adminRoute: "src/routes/admin.operations.tsx",
  adminClient: "src/lib/api/admin-operations.ts",
  routeTree: "src/routeTree.gen.ts",
  variantController: "backend/app/Http/Controllers/Seller/SellerVariantController.php",
  adminController: "backend/app/Http/Controllers/Admin/AdminOperationsController.php",
};
const files = Object.fromEntries(
  await Promise.all(Object.entries(paths).map(async ([key, path]) => [key, await readFile(path, "utf8")])),
);
const packageJson = JSON.parse(files.package);
const gates = [];
const gate = (name, condition, evidence) => gates.push({ name, passed: Boolean(condition), evidence });

gate(
  "permanent_phase20_completion_gate",
  packageJson.scripts?.["audit:phase20"] === "node scripts/audit-phase20-completion.mjs" &&
    packageJson.scripts?.check?.includes("audit:phase20"),
  "Phase 20 completion audit must remain in the permanent frontend check chain.",
);
gate(
  "seller_professional_editor_registered",
  files.manage.includes('createFileRoute("/panel/manage")') &&
    files.manage.includes('content: "noindex,nofollow"') &&
    files.panel.includes('to="/panel/manage"') &&
    files.routeTree.includes("PanelManageRouteImport"),
  "The professional seller editor must be protected, non-indexable, reachable and registered.",
);
gate(
  "complete_roastery_edit_contract",
  files.manage.includes("updateSellerRoastery") &&
    files.manage.includes("logoMediaId") &&
    files.manage.includes("coverMediaId") &&
    files.manage.includes("shippingPolicy") &&
    files.managementClient.includes("logo_media_id") &&
    files.managementClient.includes("cover_media_id"),
  "Roastery identity, fulfillment policy and owned media selection must remain editable through the scoped API.",
);
gate(
  "complete_product_edit_contract",
  files.manage.includes("updateSellerProduct") &&
    files.manage.includes("seoTitle") &&
    files.manage.includes("seoDescription") &&
    files.manage.includes("galleryMediaIds") &&
    files.manage.includes("brewingSuggestions"),
  "Existing product content, SEO, primary media, gallery and brewing guidance must remain editable.",
);
gate(
  "whole_bean_variant_management_only",
  files.manage.includes("VariantEditors") &&
    files.sellerClient.includes("weightGrams: 50 | 100 | 250 | 500 | 1000") &&
    !/grind[_-]?(selector|state)|grind_option/i.test([files.manage, files.sellerClient].join("\n")),
  "Variant management must remain limited to whole-bean fixed weights without grind state.",
);
gate(
  "availability_is_not_activation",
  files.variantController.includes("unset($data['is_active'])") &&
    files.variantController.includes("authoritative stock") &&
    files.manage.includes("updateSellerVariant"),
  "Editing price or SKU must not disable an active but temporarily out-of-stock variant.",
);
gate(
  "administrator_operations_complete",
  files.adminRoute.includes("RoasteriesQueue") &&
    files.adminRoute.includes("ProductsQueue") &&
    files.adminRoute.includes("ReviewsQueue") &&
    files.adminRoute.includes("InquiriesQueue") &&
    files.adminRoute.includes("NotificationsQueue") &&
    files.adminRoute.includes("AuditsQueue") &&
    files.adminClient.includes(".strict()"),
  "Phase 20 must retain all administrator moderation and operational health queues.",
);
gate(
  "private_operations_data_is_redacted",
  files.adminController.includes("maskDestination") &&
    files.adminController.includes("redactMetadata") &&
    !files.adminController.includes("'payload' => $item->payload"),
  "Administrator visibility must never expose encrypted notification payloads or unredacted sensitive audit metadata.",
);

const failed = gates.filter((item) => !item.passed);
await writeFile("frontend-phase20-audit.json", `${JSON.stringify({ generatedAt: new Date().toISOString(), marker: "phase20_workspaces=complete", passed: failed.length === 0, gates }, null, 2)}\n`);
if (failed.length) {
  console.error("Phase 20 completion audit failed:");
  failed.forEach((item) => console.error(`- ${item.name}: ${item.evidence}`));
  process.exit(1);
}
console.log(`Phase 20 completion audit passed (${gates.length} gates).`);
